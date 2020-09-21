<?php

namespace Recoded\MongoDB;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\ServiceProvider;
use Recoded\MongoDB\Database\MongodbConnection;

class MongodbServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving('db', function (ConnectionResolverInterface $db) {
            $db->extend('mongodb', function ($config, $name) {
                $config['name'] = $name;
                return new MongodbConnection($config);
            });
        });
    }
}
