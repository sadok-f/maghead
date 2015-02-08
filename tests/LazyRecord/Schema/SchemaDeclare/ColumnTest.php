<?php

class ColumnTest extends PHPUnit_Framework_TestCase
{
    function test()
    {
        $column = new LazyRecord\Schema\ColumnDeclare('foo');
        ok($column);

        $column->primary()
            ->integer()
            ->autoIncrement()
            ->notNull();

        is('foo',$column->name);
        ok($column->primary);
        is('integer',$column->type);
        $this->assertFalse($column->null);
    }
}

