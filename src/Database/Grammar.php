<?php

namespace Recoded\MongoDB\Database;

use Illuminate\Database\Query\Expression;

trait Grammar
{
    public function columnize(array $columns)
    {
        return array_map([$this, 'wrap'], $columns);
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
        // TODO only remove table prefix. Allow dot notation
        $value = $value instanceof Expression ? $value->getValue() : $value;
        $segments = explode('.', $value);

        return end($segments);
    }
}
