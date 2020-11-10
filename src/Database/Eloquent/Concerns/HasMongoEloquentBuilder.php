<?php

namespace Recoded\MongoDB\Database\Eloquent\Concerns;

use Recoded\MongoDB\Database\Eloquent\Builder;

trait HasMongoEloquentBuilder
{
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
}
