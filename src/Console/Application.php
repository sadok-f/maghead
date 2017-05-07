<?php

namespace Maghead\Console;

class Application extends \CLIFramework\Application
{
    const NAME = 'Maghead';
    const VERSION = '4.0.x';

    public function brief()
    {
        return 'Maghead ORM';
    }

    public function init()
    {
        parent::init();

        /*
         * Command for initialize related file structure
         */
        $this->command('init');

        /*
         * Command for building config file.
         */
        $this->command('use');
        // $this->command('init-conf', 'Maghead\\Command\\InitConfCommand');

        $this->command('schema'); // the schema command builds all schema files and shows a diff after building new schema
        $this->command('basedata');
        $this->command('sql');
        $this->command('diff');
        $this->command('migrate');
        $this->command('meta');
        $this->command('version');
        $this->command('db');
        $this->command('shard');
        $this->command('table');
        $this->command('index');
        $this->command('shard');
    }
}