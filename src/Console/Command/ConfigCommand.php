<?php

namespace Maghead\Console\Command;

use CLIFramework\Command;

class ConfigCommand extends BaseCommand
{
    public function brief()
    {
        return 'config related commands.';
    }

    public function init()
    {
        $this->command('upload');
        $this->command('init');
        $this->command('use');
    }

    public function execute()
    {
        $cmd = $this->createCommand('CLIFramework\\Command\\HelpCommand');
        $cmd->execute($this->getName());
    }
}