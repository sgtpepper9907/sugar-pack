<?php

namespace SugarPack\Commands;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends BaseCommand
{
    public const MESSAGE_FORMAT = 'message';
    public const UPLOAD_FORMAT = 'upload';

    public function __construct(string $name = null)
    {
        parent::__construct($name);
        ProgressBar::setFormatDefinition(self::MESSAGE_FORMAT, '%current%/%max% -- %message%');
        ProgressBar::setFormatDefinition(self::UPLOAD_FORMAT, '%message% %bar% %percent%%  %current%/%max% bytes');
    }

    protected function showTaskProgress(
        OutputInterface $output, 
        string $message, 
        callable $task, 
        int $steps = 1,
        string $format = self::MESSAGE_FORMAT): void
    {
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat($format);
        $progressBar->setMessage($message);
        $progressBar->start($steps);

        $task($progressBar);

        $progressBar->finish();
        $progressBar->clear();
    }
}