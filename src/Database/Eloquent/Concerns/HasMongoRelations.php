<?php

namespace Recoded\MongoDB\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Recoded\MongoDB\Database\Eloquent\Relations\BelongsToMultiple;

/**
 * @mixin \Illuminate\Database\Eloquent\Concerns\HasRelationships
 */
trait HasMongoRelations
{
    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation) . '_' . ltrim($instance->getKeyName(), '_');
        }

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return $this->newBelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey, $relation);
    }

    public function belongsToMultiple($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        if (is_null($foreignKey)) {
            $foreignKey = Str::singular(Str::snake($relation)) . '_' . Str::plural(ltrim($instance->getKeyName(), '_'));
        }

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return $this->newBelongsToMultiple($instance->newQuery(), $this, $foreignKey, $ownerKey, $relation);
    }

    protected function newBelongsToMultiple(Builder $query, Model $child, $foreignKey, $ownerKey, $relation)
    {
        return new BelongsToMultiple($query, $child, $foreignKey, $ownerKey, $relation);
    }
}
