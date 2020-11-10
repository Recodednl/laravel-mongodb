<?php

namespace Recoded\MongoDB\Database\Eloquent;

use Illuminate\Database\Eloquent\Builder as IlluminateBuilder;
use Recoded\MongoDB\Database\Eloquent\Concerns\QueriesRelationships;

/**
 * @mixin \Recoded\MongoDB\Database\Query\Builder
 * @method \Recoded\MongoDB\Database\Query\Builder getQuery()
 */
class Builder extends IlluminateBuilder
{
    use QueriesRelationships;
}
