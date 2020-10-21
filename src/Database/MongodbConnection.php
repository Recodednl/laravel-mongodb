<?php

namespace Recoded\MongoDB\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use InvalidArgumentException;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\InsertOneResult;
use Recoded\MongoDB\Database\Query\Builder;
use Recoded\MongoDB\Database\Query\Grammars\MongodbGrammar;
use Recoded\MongoDB\Database\Query\Processors\Processor;

class MongodbConnection extends Connection
{
    public const DSN_PATTERN = '/^mongodb(?:[+]srv)?:\\/\\/.+\\/([^?&]+)/s';

    protected Client $connection;

    protected Database $db;

    protected ?InsertOneResult $lastInserted = null;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(array $config = [])
    {
        $this->assertDsn($dsn = $config['dsn'] ?? '');

        $this->connection = $this->createConnection($dsn, $config, $config['options'] ?? []);

        $this->db = $this->connection->selectDatabase(
            $this->database = $this->getDefaultDatabaseName($config['dsn']),
        );

        $this->tablePrefix = $config['table_prefix'] ?? '';

        $this->config = $config;

        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();
    }

    public function affectingCallback($query, callable $callback, $bindings = [], $default = 0)
    {
        return $this->run(json_encode($query), $bindings, function () use ($bindings, $callback, $default, $query) {
            if ($this->pretending()) {
                return $default;
            }

            $result = $callback($query, $bindings);

            $this->recordsHaveBeenModified($result);

            return $result;
        });
    }

    public function assertDsn(string $dsn): void
    {
        if (!preg_match(static::DSN_PATTERN, $dsn)) {
            throw new InvalidArgumentException('Database is not properly configured.');
        }
    }

    protected function createConnection($dsn, array $config, array $options): Client
    {
        // By default driver options is an empty array.
        $driverOptions = [];

        if (isset($config['driver_options']) && is_array($config['driver_options'])) {
            $driverOptions = $config['driver_options'];
        }

        // Check if the credentials are not already set in the options
        if (!isset($options['username']) && !empty($config['username'])) {
            $options['username'] = $config['username'];
        }
        if (!isset($options['password']) && !empty($config['password'])) {
            $options['password'] = $config['password'];
        }

        return new Client($dsn, $options, $driverOptions);
    }

    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        return parent::cursor($query, $bindings, $useReadPdo); // TODO: Change the autogenerated stub
    }

    public function delete($query, $bindings = [])
    {
        return $this->affectingCallback($query, function ($query) {
            return $this
                ->getCollection($query['collection'])
                ->deleteMany($query['filter'])
                ->getDeletedCount();
        }, $bindings);
    }

    public function getCollection(string $collection): Collection
    {
        return $this->db->selectCollection($collection);
    }

    protected function getDefaultDatabaseName($dsn): string
    {
        if (!preg_match(static::DSN_PATTERN, $dsn, $matches)) {
            throw new InvalidArgumentException('Database is not properly configured.');
        }

        return $matches[1];
    }

    protected function getDefaultPostProcessor(): Processor
    {
        return new Processor();
    }

    protected function getDefaultQueryGrammar(): Grammar
    {
        return new MongodbGrammar();
    }

    public function getLastInserted(): ?InsertOneResult
    {
        return $this->lastInserted;
    }

    public function query(): Builder
    {
        return new Builder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor(),
        );
    }

    public function insert($query, $bindings = [])
    {
        return $this->affectingCallback($query, function ($query) {
            $this->lastInserted = $this
                ->getCollection($query['collection'])
                ->insertOne($query['values']);

            return $this->lastInserted->isAcknowledged();
        }, $bindings, true);
    }

    public function statement($query, $bindings = [])
    {
        throw new \LogicException('Probably isn\'t going to be used with MongoDB');
    }

    public function table($table, $as = null): Builder
    {
        $query = new Builder($this);

        return $query->from($table);
    }

    public function update($query, $bindings = [])
    {
        return $this->affectingCallback($query, function ($query) {
            return $this
                ->getCollection($query['collection'])
                ->updateMany($query['filter'], ['$set' => $query['values']])
                ->getModifiedCount();
        }, $bindings);
    }
}
