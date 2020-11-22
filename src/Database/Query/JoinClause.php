<?php

namespace Recoded\MongoDB\Database\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause as IlluminateJoinClause;
use Recoded\MongoDB\Exceptions\UnsupportedByMongoDBException;

class JoinClause extends IlluminateJoinClause
{
    public ?array $countConstrain = null;
    public string $first;
    public string $name;
    public string $second;

    public function as(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function from($table, $as = null)
    {
        $result = parent::from($table, $as);

        $this->table = $this->from;

        return $result;
    }

    public function mergeConstraintsFrom(Builder $from)
    {
        $this->wheres = array_merge($this->wheres, $from->getQuery()->wheres);

        return $this;
    }

    public function on($first, $operator = null, $second = null, $boolean = 'and')
    {
        if ($second === null && $operator !== null && !in_array($operator, $this->newParentQuery()->operators)) {
            $second = $operator;
        }

        if ($second === null) {
            throw new UnsupportedByMongoDBException('Second null');
        }

        $this->first = $first;
        $this->second = $second;

        return $this;
    }

    public function whereCount(string $operator = '>=', int $amount = 1): self
    {
        $this->countConstrain = [$operator, $amount];

        return $this;
    }
}
