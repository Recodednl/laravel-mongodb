<?php

namespace Recoded\MongoDB\Database\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar as IlluminateGrammar;
use Illuminate\Support\Fluent;
use Recoded\MongoDB\Database\MongodbConnection;

class Grammar extends IlluminateGrammar
{
    use \Recoded\MongoDB\Database\Grammar;

    public function compileCreate(Blueprint $blueprint, Fluent $command, MongodbConnection $connection): ?callable
    {
        if ($connection->getSchemaBuilder()->hasTable($table = $blueprint->getTable())) {
            return null;
        }

        return fn () => $connection->getMongo()->createCollection($table);
    }

    public function compileDrop(Blueprint $blueprint, Fluent $command, MongodbConnection $connection): callable
    {
        return fn () => $connection->getMongo()->dropCollection($blueprint->getTable());
    }

    public function compileDropIfExists(Blueprint $blueprint, Fluent $command, MongodbConnection $connection): callable
    {
        return $this->compileDrop($blueprint, $command, $connection);
    }

    public function compileDropIndex(Blueprint $blueprint, Fluent $command, MongodbConnection $connection): callable
    {
        return fn () => $connection->getMongo()
            ->selectCollection($this->wrapTable($blueprint))
            ->dropIndex($this->wrap($command->index));
    }

    public function compileIndex(Blueprint $blueprint, Fluent $command, MongodbConnection $connection): callable
    {
        $columns = $this->columnize($command->columns);
        $columns = array_fill_keys($columns, 1); // TODO support custom direction specification

        return fn () => $connection->getMongo()
            ->selectCollection($this->wrapTable($blueprint))
            ->createIndex($columns, $command->index);
    }

    protected function getColumns(Blueprint $blueprint)
    {
        // Can't add columns to MongoDB because it's schemaless
        return [];
    }
}