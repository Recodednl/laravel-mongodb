<?php

namespace Recoded\MongoDB\Database\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint as IlluminateBlueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Recoded\MongoDB\Exceptions\UnsupportedByMongoDBException;

class Blueprint extends IlluminateBlueprint
{
    protected function addFluentIndexes(): void
    {
        foreach ($this->columns as $column) {
            foreach (['unique', 'index'] as $index) {
                if ($column->{$index} === true) {
                    $this->{$index}($column->name);
                    $column->{$index} = false;

                    continue 2;
                } elseif (isset($column->{$index})) {
                    $this->{$index}($column->name, $column->{$index});
                    $column->{$index} = false;

                    continue 2;
                }
            }
        }
    }

    public function build(Connection $connection, Grammar $grammar): void
    {
        array_map('call_user_func', $this->toSql($connection, $grammar));
    }

    protected function dropIndexCommand($command, $type, $index)
    {
        if (is_array($index)) {
            $index = $this->createIndexName('index', $columns = $index);
        }

        return $this->addCommand('dropIndex', compact('index'));
    }

    /**
     * @param string $type
     * @param array|string $columns
     * @param array $index
     * @param null $algorithm
     * @return \Illuminate\Support\Fluent
     * @throws \Recoded\MongoDB\Exceptions\UnsupportedByMongoDBException
     */
    protected function indexCommand($type, $columns, $index, $algorithm = null): Fluent
    {
        if ($type !== 'index') {
            throw new UnsupportedByMongoDBException('IndexTypes', true);
        }

        $columns = (array) $columns;

        $index = is_string($index) ? ['name' => $index] : $index;
        $index['name'] ??= $this->createIndexName('index', $columns);

        return $this->addCommand($type, compact('index', 'columns'));
    }

    public function primary($columns, $name = null, $algorithm = null)
    {
        return $this->unique($columns, $name, $algorithm);
    }

    public function unique($columns, $name = null, $algorithm = null)
    {
        $index = is_string($name) ? compact('name') : [];
        $index['unique'] = true;

        return $this->indexCommand('index', $columns, $index, $algorithm);
    }
}