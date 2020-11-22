<?php

namespace Recoded\MongoDB\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany as IlluminateHasMany;
use Recoded\MongoDB\Database\Query\Grammars\MongodbGrammar;

class HasMany extends IlluminateHasMany
{
    protected MongodbGrammar $grammar;

    public function __construct(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->grammar = $query->getGrammar();

        $foreignKey = $this->grammar->mongoWrap($foreignKey, $query);

        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    protected function buildDictionary(Collection $results): array
    {
        return $results->groupBy(
            $this->getForeignKeyName(),
        )->map(fn (Collection $collection) => $collection->all())->all();
    }

    protected function getKeys(array $models, $key = null)
    {
        return collect($models)->map(function ($value) use ($key) {
            return $key ? $value->getAttribute($key) : $value->getKey();
        })
            ->values()
            ->flatten()
            ->unique(null, true)
            ->sort()
            ->map(fn ($value) => $this->grammar->convertKey($key, $value))
            ->all();
    }

    public function getParentKey()
    {
        return $this->grammar->convertKey(
            $this->localKey,
            $this->parent->getAttribute($this->localKey),
        );
    }

    /**
     * @param \Recoded\MongoDB\Database\Query\JoinClause $query
     * @param \Illuminate\Database\Eloquent\Builder $parentQuery
     * @param string[] $columns
     * @return \Recoded\MongoDB\Database\Query\JoinClause
     */
    public function getRelationExistenceQuery($query, Builder $parentQuery, $columns = ['*'])
    {
        return $query->on($this->parent->getKeyName(), '=', $this->foreignKey);
    }
}
