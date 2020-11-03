<?php

namespace Recoded\MongoDB\Database\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as IlluminateProcessor;
use Recoded\MongoDB\Exceptions\UnsupportedByMongoDBException;

class Processor extends IlluminateProcessor
{
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $sequence ??= '_id';

        /** @var \Recoded\MongoDB\Database\MongodbConnection $connection */
        $connection = $query->getConnection();

        $connection->insert($sql, $values);

        if ($sequence !== '_id') {
            throw new UnsupportedByMongoDBException('$sequence other than "_id"');
        }

        return $connection->getLastInserted();
    }
}
