<?php

namespace Recoded\MongoDB\Tests;

use Recoded\MongoDB\MongodbServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            MongodbServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'mongo');
        $app['config']->set('database.connections.mongo', [
            'driver' => 'mongodb',
            'dsn' => env('DATABASE_DSN', 'mongodb://127.0.0.1:27017/testing'),
        ]);
    }
}
