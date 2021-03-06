<?php

namespace Maghead\Testing;

use Maghead\Runtime\Config\FileConfigLoader;
use Maghead\Runtime\Connection;
use Maghead\Runtime\Bootstrap;
use Maghead\Manager\DataSourceManager;
use Maghead\Runtime\SeedBuilder;
use Maghead\TableBuilder\TableBuilder;
use Maghead\TableParser\TableParser;
use Maghead\Generator\Schema\SchemaGenerator;
use Maghead\Schema\SchemaCollection;
use Maghead\Schema\SchemaUtils;
use Maghead\Manager\TableManager;
use Magsql\Driver\BaseDriver;

/**
 * @codeCoverageIgnore
 */
abstract class ModelTestCase extends DbTestCase
{
    /**
     * Define this to support multiple connection
     */
    protected $requiredDataSources;

    protected $schemaHasBeenBuilt = false;

    protected $schemaClasses = [];

    protected $tableManager;

    public function setUp()
    {
        parent::setUp();

        // Ensure that we use the correct master data source ID
        $this->assertEquals($this->getMasterDataSourceId(), $this->config->getMasterDataSourceId());

        // Rebuild means rebuild the database for new tests
        $annnotations = $this->getAnnotations();
        $rebuild = true;
        $basedata = true;
        if (isset($annnotations['method']['rebuild'][0]) && $annnotations['method']['rebuild'][0] == 'false') {
            $rebuild = false;
        }
        if (isset($annnotations['method']['basedata'][0]) && $annnotations['method']['basedata'][0] == 'false') {
            $basedata = false;
        }

        $schemas = SchemaCollection::create($this->models())->evaluate();
        $this->prepareSchemaFiles($schemas);

        if ($this->requiredDataSources) {
            foreach ($this->requiredDataSources as $nodeId) {
                $conn = $this->dataSourceManager->getConnection($nodeId);
                $this->prepareDatabase($conn, $conn->getQueryDriver(), $schemas, $rebuild, $basedata);
            }
        } else {
            $this->prepareDatabase($this->conn, $this->conn->getQueryDriver(), $schemas, $rebuild, $basedata);
        }
    }

    protected function prepareDatabase(Connection $conn, BaseDriver $queryDriver, SchemaCollection $schemas, bool $rebuild, bool $basedata)
    {
        $this->prepareTables($conn, $queryDriver, $schemas, $rebuild);
        if ($rebuild && $basedata) {
            $this->prepareBaseData($schemas);
        }
    }

    protected function prepareBaseData(SchemaCollection $schemas)
    {
        $seeder = new SeedBuilder($this->logger);
        $seeder->build($schemas);
        if ($seeds = $this->config->loadSeedScripts()) {
            $seedBuilder->buildSeeds($seeds);
        }
    }

    protected function prepareTables(Connection $conn, BaseDriver $queryDriver, SchemaCollection $schemas, bool $rebuild)
    {
        if ($rebuild === false) {
            // Find missing tables...
            $tableParser = TableParser::create($conn, $queryDriver, $this->config);
            $tables = $tableParser->getTables();
            $schemas = $schemas->filter(function ($s) use ($tables) {
                return !in_array($s->getTable(), $tables);
            });
        }

        $this->tableManager = new TableManager($conn, ['rebuild' => $rebuild], $this->logger);
        $this->tableManager->build($schemas);
    }

    protected function prepareSchemaFiles(SchemaCollection $schemas)
    {
        if ($this->schemaHasBeenBuilt) {
            return;
        }
        $g = new SchemaGenerator($this->config);
        $g->setForceUpdate(true);
        $g->generate($schemas);
        $this->schemaHasBeenBuilt = true;
    }

    protected function dropSchemaTables($schemas)
    {
        $this->tableManager->remove($schemas);
    }

    protected function buildSchemaTables($schemas)
    {
        $this->tableManager->build($schemas);
    }

    public function testClasses()
    {
        foreach ($this->models() as $class) {
            $this->assertTrue(class_exists($class, true));
        }
    }
}
