<?php

namespace Recoded\MongoDB\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Recoded\MongoDB\Database\Query\JoinClause;

trait QueriesRelationships
{
    /**
     * @param \Recoded\MongoDB\Database\Query\JoinClause $clause
     * @param \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @param $operator
     * @param $count
     * @param $boolean
     * @return mixed
     */
    protected function addHasWhere($clause, Relation $relation, $operator, $count, $boolean)
    {
        $clause->mergeConstraintsFrom($relation->getQuery());

        return $this->canUseExistsForExistenceCheck($operator, $count)
            ? $this->addWhereExistsQuery($clause, $boolean, $operator === '<' && $count === 1)
            : $this->addWhereCountQuery($clause, $operator, $count, $boolean);
    }

    /**
     * @param \Recoded\MongoDB\Database\Query\JoinClause $clause
     * @param string $operator
     * @param int $count
     * @param string $boolean
     * @return mixed
     */
    protected function addWhereCountQuery($clause, $operator = '>=', $count = 1, $boolean = 'and')
    {
        $clause->whereCount($operator, $count);

        return $this->addWhereExistsQuery($clause, $boolean, $operator === '<' && $count === 1);
    }

    public function has($relation, $operator = '>=', $count = 1, $boolean = 'and', \Closure $callback = null)
    {
        if (is_string($relation)) {
            if (strpos($relation, '.') !== false) {
                return $this->hasNested($relation, $operator, $count, $boolean, $callback);
            }

            $relation = $this->getRelationWithoutConstraints($relation);
        }
        /** @var \Illuminate\Database\Eloquent\Relations\Relation $relation */

        if ($relation instanceof MorphTo) {
            throw new \RuntimeException('Please use whereHasMorph() for MorphTo relationships.');
        }

        $hasQuery = $relation->getRelationExistenceQuery(
            new JoinClause($this->toBase(), 'embed', $relation->getRelated()->getTable()), $this
        );

        if ($callback) {
            $hasQuery->callScope($callback);
        }

        return $this->addHasWhere(
            $hasQuery, $relation, $operator, $count, $boolean
        );
    }
}
