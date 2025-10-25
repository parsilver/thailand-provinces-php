<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Console;

use Farzai\ThailandAddress\Console\Commands\DownloadCommand;
use Farzai\ThailandAddress\Console\Commands\ListCommand;
use Farzai\ThailandAddress\Console\Commands\ValidateCommand;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    private const string VERSION = '2.0.0';

    public function __construct()
    {
        parent::__construct('Thai Address CLI', self::VERSION);

        $this->addCommands([
            new ListCommand,
            new DownloadCommand,
            new ValidateCommand,
        ]);
    }
}
