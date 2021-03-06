<?php

namespace Maghead\Console\Command\ShardCommand;

use Maghead\Console\Command\BaseCommand;
use Maghead\Manager\DatabaseManager;
use Maghead\Manager\DataSourceManager;
use Maghead\Manager\ConnectionManager;
use Maghead\Sharding\Manager\ConfigManager;
use Maghead\Sharding\Manager\ShardManager;
use Maghead\DSN\DSN;
use Maghead\Runtime\Config\SymbolicLinkConfigWriter;
use Maghead\Sharding\Operations\AllocateShard;


use PDO;
use Exception;

class AllocateCommand extends BaseCommand
{
    public function brief()
    {
        return 'allocate a shard';
    }

    public function options($opts)
    {
        parent::options($opts);
        $opts->add('mapping:', 'the shard mapping where the new shard will be added to.');
        $opts->add('instance:', 'the instance id')
            ->defaultValue('local');
    }

    public function arguments($args)
    {
        $args->add('node-id');
    }

    public function execute($nodeId)
    {
        $config = $this->getConfig(true);

        $op = new AllocateShard($config);
        $op->allocate($this->options->mapping, $this->options->instance, $nodeId);

        SymbolicLinkConfigWriter::write($config);
    }
}
