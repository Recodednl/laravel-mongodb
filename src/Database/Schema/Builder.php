<?php

namespace Recoded\MongoDB\Database\Schema;

use Illuminate\Database\Schema\Builder as IlluminateBuilder;
use Recoded\MongoDB\Exceptions\UnsupportedByMongoDBException;

class Builder extends IlluminateBuilder
{
    /**
     * @var \Recoded\MongoDB\Database\MongodbConnection
     */
    protected $connection;

    /**
     * Create a new command set with a Closure.
     *
     * @param  string  $table
     * @param  \Closure|null  $callback
     * @return \Illuminate\Database\Schema\Blueprint
     */
    protected function createBlueprint($table, \Closure $callback = null)
    {
        $prefix = $this->connection->getConfig('prefix_indexes')
            ? $this->connection->getConfig('prefix')
            : '';

        if (isset($this->resolver)) {
            return call_user_func($this->resolver, $table, $callback, $prefix);
        }

        return new Blueprint($table, $callback, $prefix);
    }

    public function dropAllTables(): void
    {
        array_map([$this, 'drop'], $this->getAllTables());
    }

    public function getAllTables(): array
    {
        return iterator_to_array($this->connection->getMongo()->listCollectionNames());
    }

    public function getColumnListing($table): array
    {
        $collections = $this->connection->getMongo()
            ->selectCollection($table)
            ->aggregate([
                ['$project' => ['fields' => ['$objectToArray' => '$$ROOT']]],
                ['$unwind' => '$fields'],
                ['$group' => ['_id' => null, 'aggregate' => ['$addToSet' => '$fields.k']]],
            ]);

        return (array)iterator_to_array($collections)[0]['aggregate'];
    }

    public function getColumnType($table, $column)
    {
        throw new UnsupportedByMongoDBException('ColumnTypes', true);
    }

    public function hasTable($table): bool
    {
        return in_array($table, $this->getAllTables(), true);
    }
}
