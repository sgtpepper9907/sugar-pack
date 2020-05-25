<?php

namespace SugarPack\Commands;

use SugarPack\Configuration\PublishConfigurationManager;
use SugarPack\Configuration\PublishProfile;
use SugarPack\Http\SugarClient;
use SugarPack\Http\SugarClientFactory;
use SugarPack\Utils\Manifest;
use SugarPack\Utils\Package;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PublishCommand extends Command
{
    protected static $defaultName = 'publish';

    protected function configure()
    {
        $this->setDescription('Publishes a package to the SugarCRM© instance.');

        $this->addArgument(
            'package',
            InputArgument::REQUIRED,
            'The pacakage to publish.'
        );

        $this->addOption(
            'profile',
            null,
            InputOption::VALUE_REQUIRED,
            'Specify the publish profile defined in the profiles file.'
        );

        $this->addOption(
            'username',
            'u',
            InputOption::VALUE_REQUIRED,
            'Specify the username used for authentication in the instance.'
        );

        $this->addOption(
            'password',
            'p',
            InputOption::VALUE_REQUIRED,
            'Specify the password used for authentication in the instance.'
        );

        $this->addOption(
            'instance',
            'i',
            InputOption::VALUE_REQUIRED,
            "Specify the instance's url."
        );

        $this->addOption(
            'skip-upgrade',
            's',
            InputOption::VALUE_NONE,
            'Skips the manifest upgrade.'
        );

        $this->addOption(
            'upgrade-type',
            null,
            InputOption::VALUE_REQUIRED,
            'Type of upgrade to apply to manifest: PATCH|MINOR|MAJOR',
            'PATCH'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $path = getcwd() . DIRECTORY_SEPARATOR . rtrim($input->getArgument('package'), DIRECTORY_SEPARATOR);
            $package = new Package($path);

            $sugarClient = $this->buildSugarClient($path, $input, $output);
            if (is_null($sugarClient)) {
                return 1;
            }

            $skipUpgrade = $input->getOption('skip-upgrade');
            $upgradeType = $input->getOption('upgrade-type');

            if (!$skipUpgrade) {
                if (!Manifest::isValidUpgradeType($upgradeType)) {
                    $output->writeln('<comment>Invalid upgrade type, Defaulting to PATCH.</>');
                }

                $package->manifest->upgrade($upgradeType);
            } else {
                $output->writeln('Skipping manifest upgrade');
            }

            $packageName = $package->manifest->getName();
            $packageVersion = $package->manifest->getVersion();
            
            $oldInstalledPackage = null;
            $this->showTaskProgress(
                $output,
                'Verifying if the package is already installed...',
                function () use ($sugarClient, $packageName, &$oldInstalledPackage) {
                    $oldInstalledPackage = $sugarClient->getInstalledPackage($packageName);
                }
            );

            if ($oldInstalledPackage) {
                $output->writeln("<comment>Found package installed with version: <options=bold>{$oldInstalledPackage->version}</></>");
                
                $versionComparison = version_compare($packageVersion, $oldInstalledPackage->version);
                if ($versionComparison != 1) {
                    $versionDiff = $versionComparison == 0 ? 'the same' : 'a greater';

                    $helper = $this->getHelper('question');
                    $question = new ConfirmationQuestion("<question>The package currently installed has $versionDiff version. Proceed anyways? [y/n] </>", true);

                    if (!$helper->ask($input, $output, $question)) {
                        $output->writeln('Aborting publish.');
                        return 0;
                    }
                }

                $this->showTaskProgress(
                    $output,
                    'Uninstalling package...',
                    function () use ($sugarClient, $packageName, $oldInstalledPackage) {
                        $sugarClient->uninstallPackageById($oldInstalledPackage->id);
                        $sugarClient->deleteStagedPackage($packageName);
                    }
                );
            }

            $oldPackageStaged = $sugarClient->getStagedPackage($packageName);
            if ($oldPackageStaged) {
                $output->writeln("<comment>Found staged package with version: <options=bold>{$oldPackageStaged->version}</></>");

                $this->showTaskProgress(
                    $output,
                    'Deleting staged package...',
                    function () use ($sugarClient, $packageName) {
                        $sugarClient->deleteStagedPackage($packageName);
                    }
                );
            }

            $zipFilePath = null;
            $fileHandle = null;

            $this->showTaskProgress(
                $output,
                'Compressing package...',
                function () use ($package, &$zipFilePath, &$fileHandle) {
                    $zipFilePath = tempnam(sys_get_temp_dir(), 'sugar-pack');
                    $package->compress($zipFilePath . '.zip');
                    $fileHandle = fopen($zipFilePath . '.zip', 'r');
                }
            );

            $installId = '';
            $this->showTaskProgress(
                $output,
                'Uploading file...',
                function (ProgressBar $progressBar) use ($sugarClient, $fileHandle, &$installId) {
                    $installId = $sugarClient->uploadPackage(
                        $fileHandle,
                        function ($dt, $db, $ut, $ub) use ($progressBar) {
                            $progressBar->setProgress($ub);
                        }
                    );
                },
                filesize($zipFilePath . '.zip'),
                self::UPLOAD_FORMAT
            );
            
            @fclose($fileHandle);
            @unlink($zipFilePath);
            @unlink($zipFilePath . '.zip');

            $this->showTaskProgress(
                $output,
                'Installing package...',
                function () use ($sugarClient, $installId) {
                    $sugarClient->installPackage($installId);
                }
            );

            $output->writeln("<info>Package <options=bold>{$packageName} v{$packageVersion}</> installed successfully</>");
            return 0;
        } catch (\Exception $e) {
            $output->writeln("\r<error>{$e->getMessage()}</>");
            return 1;
        }
    }

    private function buildSugarClient(string $packagePath, InputInterface $input, OutputInterface $output) : ?SugarClient
    {
        $profile = $this->getPublishProfile($packagePath, $input, $output);

        $this->warnIfNullRequiredParameter('instance', $profile->instance, $output);
        $this->warnIfNullRequiredParameter('username', $profile->username, $output);
        $this->warnIfNullRequiredParameter('password', $profile->password, $output);

        if (is_null($profile->instance) || is_null($profile->username) || is_null($profile->password)) {
            return null;
        }

        return SugarClientFactory::create($profile);
    }

    private function getPublishProfile(string $path, InputInterface $input, OutputInterface $output): PublishProfile
    {
        $profile = null;
        $profileName = $input->getOption('profile');
        $configManager = new PublishConfigurationManager;

        if (!$configManager->loadFromFile($path)) {
            $output->writeln('<comment>Failed to locate a valid publish configuration file.</>');

            return $this->getPublishProfileFromOptions($input);
        }

        if ($profileName) {
            $profile = $configManager->getProfileByName($profileName);
        }

        if (is_null($profile)) {
            if ($profileName) {
                $output->writeln("<comment>Couldn't find profile <options=bold>{$profileName}</>, defaulting to first item.</>");
            }

            $profile = $configManager->getDefaultProfile();
        }

        return PublishConfigurationManager::mergeProfiles(
            $profile,
            $this->getPublishProfileFromOptions($input)
        );
    }

    private function warnIfNullRequiredParameter(string $name, ?string $value, OutputInterface $output): void
    {
        if (!is_null($value) && !empty($value)) {
            return;
        }

        $output->writeln("<error>No {$name} was specified, either add it to the config publish file or pass it as an argument.</>");
    }

    private function getPublishProfileFromOptions(InputInterface $input): PublishProfile
    {
        $profile = new PublishProfile;
        $profile->instance = $input->getOption('instance');
        $profile->username = $input->getOption('username');
        $profile->password = $input->getOption('password');

        return $profile;
    }
}
