<?php

use CLIFramework\Testing\CommandTestCase;

/**
 * @group command
 */
class IndexCommandsTest extends CommandTestCase
{
    public function setupApplication()
    {
        return new Maghead\Console;
    }

    public function setUp()
    {
        parent::setUp();
        $db = getenv('DB') ?: 'sqlite';
        if ($db !== "mysql") {
            return $this->markTestSkipped('sqlite migration is not supported.');
        }

        if (getenv('TRAVIS')) {
            // FIXME: FAILS ON TRAVIS-CI, innodb 
            return $this->markTestSkipped('innodb is not supported on Travis-CI');
        }


        copy("tests/config/$db.yml", "tests/config/tmp.yml");
        $this->app->run(['maghead','use','tests/config/tmp.yml']);
    }

    public function testIndex()
    {
        $this->expectOutputRegex('/TABLE_NAME/');
        $ret = $this->app->run(['maghead','index','master']);
    }
}
