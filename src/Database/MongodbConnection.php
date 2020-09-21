<?php

namespace Recoded\MongoDB\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use InvalidArgumentException;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Recoded\MongoDB\Database\Query\Builder;
use Recoded\MongoDB\Database\Query\Grammars\MongodbGrammar;

class MongodbConnection extends Connection
{
    public const DSN_PATTERN = '/^mongodb(?:[+]srv)?:\\/\\/.+\\/([^?&]+)/s';

    protected Client $connection;

    protected Database $db;

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

    protected function getDefaultQueryGrammar(): Grammar
    {
        return new MongodbGrammar();
    }

    public function query(): Builder
    {
        return new Builder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor(),
        );
    }

    public function table($table, $as = null): Builder
    {
        $query = new Builder($this);

        return $query->from($table);
    }
}
