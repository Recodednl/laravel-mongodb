<?php

namespace Recoded\MongoDB\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as IlluminateBelongsTo;

class BelongsTo extends IlluminateBelongsTo
{
    /**
     * @inheritDoc
     */
    protected function getEagerModelKeys(array $models)
    {
        $keys = array_reduce($models, function (array $keys, Model $model) {
            $values = array_values((array) $model->{$this->foreignKey});

            array_key_exists(0, $values) && $keys[] = $values[0];

            return $keys;
        }, []);

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

    /**
     * @param \Illuminate\Database\Eloquent\Model[] $models
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @param string $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $results = $results->keyBy($this->ownerKey);

        foreach ($models as $model) {
            $key = array_values((array) $model->{$this->foreignKey})[0];

            if ($results->offsetExists($key)) {
                $model->setRelation($relation, $results->get($key));
            }
        }

        return $models;
    }
}
