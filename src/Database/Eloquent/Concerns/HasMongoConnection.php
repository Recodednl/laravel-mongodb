<?php

namespace Recoded\MongoDB\Database\Eloquent\Concerns;

trait HasMongoConnection
{
    public function getKeyName()
    {
        return '_id';
    }

    public function qualifyColumn($column)
    {
        return $column;
    }
}
