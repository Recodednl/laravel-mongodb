<?php

namespace Recoded\MongoDB\Database;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;

trait Grammar
{
    public function columnize(array $columns)
    {
        return array_map([$this, 'wrap'], $columns);
    }

    public function mongoWrap($value, $table = null): string
    {
        if ($table instanceof Builder || $table instanceof EloquentBuilder) {
            $table = $table instanceof Builder ? $table->from : $table->getQuery()->from;
        }

        $segments = explode('.', $value);

        // TODO remove quotes around segments

        if (count($segments) === 1) {
            return reset($segments);
        }

        if ($table !== null) {
            $wrappedTable = $this->wrapTable($table);

            if (in_array($segments[0], [$table, $wrappedTable])) {
                array_shift($segments);
            }
        }

        return implode('.', $segments);
    }

    public function parameter($value)
    {
        return $value instanceof Expression ? $value->getValue() : $value;
    }

    public function parameterize(array $values): array
    {
        return array_map([$this, 'parameter'], $values);
    }

    public function wrap($value, $prefixAlias = false)
    {
        return $this->mongoWrap($value);
    }

    public function wrapTable($table)
    {
        $table = $table instanceof Blueprint ? $table->getTable() : $table;

        if ($table instanceof Expression) {
            return $this->getValue($table);
        }

        return $this->tablePrefix . $table;
    }
}
