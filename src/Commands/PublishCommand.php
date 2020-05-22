<?php

namespace SugarPack\Commands;

use SugarPack\Http\SugarCient;
use SugarPack\Utils\Manifest;
use SugarPack\Utils\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PublishCommand extends Command
{
    protected static $defaultName = 'publish';

    private const PUBLISH_CONFIG_FILE_NAME = 'sugar_pack.publish.json';

    /**
     * The path where the package to be published is
     *
     * @var string
     */
    private $path;

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
            $skipUpgrade = $input->getOption('skip-upgrade');
            $upgradeType = $input->getOption('upgrade-type');

            $this->path = getcwd() . DIRECTORY_SEPARATOR . rtrim($input->getArgument('package'), DIRECTORY_SEPARATOR);
            $package = new Package($this->path);

            $sugarClient = $this->buildSugarClient($input, $output);

            if (is_null($sugarClient)) {
                return 1;
            }

            ProgressBar::setFormatDefinition('message', '%current%/%max% -- %message%');
            ProgressBar::setFormatDefinition('upload', '%message% %bar% %percent%%  %current%/%max% bytes');

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
            
            
            $progressBar = new ProgressBar($output);
            $progressBar->setFormat('message');


            $progressBar->setMessage("Verifying if the package is already installed...");
            $progressBar->start();
            $oldInstalledPackage = $sugarClient->getInstalledPackage($packageName);
            $progressBar->finish();
            $progressBar->clear();


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

                
                $progressBar->setMessage("Uninstalling package...");
                $progressBar->start(1);
                
                $sugarClient->uninstallPackageById($oldInstalledPackage->id);
                $sugarClient->deleteStagedPackage($packageName);
                
                $progressBar->finish();
                $progressBar->clear();
            }

            $oldPackageStaged = $sugarClient->getStagedPackage($packageName);
            if ($oldPackageStaged) {
                $output->writeln("<comment>Found staged package with version: <options=bold>{$oldPackageStaged->version}</></>");

                $progressBar->setMessage("Deleting staged package...");
                $progressBar->start(1);

                $sugarClient->deleteStagedPackage($packageName);

                $progressBar->finish();
                $progressBar->clear();
            }

            $progressBar->setMessage("Compressing package...");
            $progressBar->start(1);

            $zipFilePath = tempnam(sys_get_temp_dir(), 'sugar-pack');
            $package->compress($zipFilePath . '.zip');
            $fileHandle = fopen($zipFilePath. '.zip', 'r');

            $progressBar->finish();
            $progressBar->clear();

            $progressBar->setFormat('upload');
            $progressBar->setMessage('Uploading file...');

            $progressBar->start(filesize($zipFilePath . '.zip'));

            $installId = $sugarClient->uploadPackage($fileHandle, function ($dt, $db, $ut, $ub) use ($progressBar) {
                $progressBar->setProgress($ub);
            });
            
            @fclose($fileHandle);
            @unlink($zipFilePath);
            @unlink($zipFilePath . '.zip');

            $progressBar->finish();
            $progressBar->clear();

            $progressBar->setFormat('message');
            $progressBar->setMessage("Installing package...");
            $progressBar->start(1);

            $sugarClient->installPackage($installId);

            $progressBar->finish();
            $progressBar->clear();


            $output->writeln("<info>Package <options=bold>{$packageName} v{$packageVersion}</> installed successfully</>");
            return 0;
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</>");
            return 1;
        }
    }

    private function buildSugarClient(InputInterface $input, OutputInterface $output) : ?SugarCient
    {
        $profile = $input->getOption('profile');
        [$instance, $username, $password, $platform] = $this->tryReadConfigurationFile($profile, $output);

        $this->tryToReadFromOptions($input, $output, $instance, 'instance');
        $this->tryToReadFromOptions($input, $output, $username, 'username');
        $this->tryToReadFromOptions($input, $output, $password, 'password');

        if (is_null($instance) || is_null($username) || is_null($password)) {
            return null;
        }

        return new SugarCient($instance, $username, $password, $platform);
    }

    private function tryReadConfigurationFile(?string $profileName, OutputInterface $output): array
    {
        $possibleConfigFilePaths = [
            $this->path . '/' . self::PUBLISH_CONFIG_FILE_NAME,
            $this->path . '/../' . self::PUBLISH_CONFIG_FILE_NAME
        ];

        $configFilePath = null;

        foreach ($possibleConfigFilePaths as $possibleConfigFilePath) {
            if (file_exists($possibleConfigFilePath)) {
                $configFilePath = $possibleConfigFilePath;
                break;
            }
        }
        
        if (is_null($configFilePath)) {
            $output->writeln('<comment>Failed to locate publish configuration.</>');
            return [null, null, null, null];
        }

        $config = @json_decode(file_get_contents($configFilePath));
        $profiles = $config->profiles;

        if (!$profiles || !is_array($profiles) ||  empty($profiles)) {
            $output->writeln('<comment>Couldn\'t read profiles from config file.</>');
        }

        $publishProfile = null;

        if (!is_null($profileName)) {
            $publishProfile = $this->findProfile($profiles, $profileName);

            if (is_null($publishProfile)) {
                $output->writeln("<comment>Couldn't find profile <options=bold>{$profileName}</>, defaulting to first item.</>");
            }
        }

        if (is_null($publishProfile)) {
            $publishProfile = $profiles[0];
        }

        return [
            isset($publishProfile->instance) ? $publishProfile->instance : null,
            isset($publishProfile->username) ? $publishProfile->username : null,
            isset($publishProfile->password) ? $publishProfile->password : null,
            isset($publishProfile->platform) ? $publishProfile->platform : null
        ];
    }

    private function tryToReadFromOptions(InputInterface $input, OutputInterface $output, ?string &$value, string $optionName)
    {
        $value = $input->getOption($optionName) ?? $value;

        if (is_null($value)) {
            $output->writeln("<error>No {$optionName} was specified, either add it to the config publish file or pass it as an argument.</>");
        }
    }

    private function findProfile(array $profiles, string $profileName): ?\stdClass
    {
        foreach ($profiles as $profile) {
            if (isset($profile->name) && $profile->name == $profileName) {
                return $profile;
            }
        }

        return null;
    }
}
