<?php

namespace Recoded\MongoDB\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;

class BelongsToMultiple extends Relation
{
    protected Model $child;
    protected string $foreignKey;
    protected string $ownerKey;
    protected string $relationName;

    public function __construct(Builder $query, Model $child, $foreignKey, $ownerKey, $relationName)
    {
        $this->child = $child;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        $this->relationName = $relationName;

        parent::__construct($query, $child);
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->whereIn($this->ownerKey, (array)$this->child->{$this->foreignKey});
        }
    }

    public function addEagerConstraints(array $models)
    {
        $whereIn = $this->whereInMethod($this->related, $this->ownerKey);

        $this->query->{$whereIn}($this->ownerKey, $this->getEagerModelKeys($models));
    }

    public function dissociate()
    {
        $this->child->setAttribute($this->foreignKey, []);

        return $this->child->setRelation($this->relationName, $this->related->newCollection());
    }

    protected function getEagerModelKeys(array $models)
    {
        $keys = Arr::flatten(
            Arr::pluck($models, $this->foreignKey),
        );

        sort($keys);

        return array_values(array_unique($keys));
    }

    /**
     * @param \Recoded\MongoDB\Database\Query\JoinClause $query
     * @param \Illuminate\Database\Eloquent\Builder $parentQuery
     * @param string[] $columns
     * @return \Recoded\MongoDB\Database\Query\JoinClause
     */
    public function getRelationExistenceQuery($query, Builder $parentQuery, $columns = ['*'])
    {
        return $query->on($this->foreignKey, '=', $this->parent->getKeyName());
    }

    public function getResults(): Collection
    {
        $key = $this->child->{$this->foreignKey};

        if ($key === null || (is_array($key) && empty($key))) {
            return $this->related->newCollection();
        }

        return $this->query->get();
    }

    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    public function match(array $models, Collection $results, $relation): array
    {
        $results = $results->keyBy($this->ownerKey);

        /** @var \Illuminate\Database\Eloquent\Model $model */
        foreach ($models as $model) {
            $keys = Arr::wrap($model->{$this->foreignKey});

            $collection = $this->related->newCollection(
                array_filter(array_map([$results, 'get'], $keys)),
            );

            $model->setRelation($relation, $collection);
        }

        return $models;
    }
}
