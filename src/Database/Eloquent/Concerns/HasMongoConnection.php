<?php

namespace Recoded\MongoDB\Database\Eloquent\Concerns;

trait HasMongoConnection
{
    public function getKeyName()
    {
        return '_id';
    }

    public function getKeyType()
    {
        return 'string';
    }

    public function qualifyColumn($column)
    {
        return $column;
    }
}
