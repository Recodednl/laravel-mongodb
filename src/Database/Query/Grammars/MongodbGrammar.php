<?php

namespace Recoded\MongoDB\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use MongoDB\BSON\ObjectId;

class MongodbGrammar extends Grammar
{
    protected $selectComponents = [
//        'aggregate',
//        'columns',
//        'from',
//        'joins',
        'wheres',
//        'groups',
//        'havings',
//        'orders',
//        'limit',
//        'offset',
//        'lock',
    ];

    protected function compileComponents(Builder $query): array
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if (isset($query->{$component})) {
                $method = 'compile' . ucfirst($component);

                $sql = array_merge($sql, $this->$method($query, $query->{$component}));
            }
        }

        return $sql;
    }

    public function compileSelect(Builder $query): array
    {
        return $this->compileComponents($query);
    }

    public function compileWheres(Builder $query): array
    {
        if (is_null($query->wheres)) {
            return [];
        }

        if (count($sql = $this->compileWheresToArray($query)) > 0) {
            return $sql;
        }

        return [];
    }

    protected function compileWheresToArray($query): array
    {
        return collect($query->wheres)->map(function (array $where, int $i) use ($query) {
            if ($i == 0 && count($query->wheres) > 1 && $where['boolean'] == 'and') {
                $where['boolean'] = $query->wheres[$i + 1]['boolean'];
            }

            $result = $this->{"where{$where['type']}"}($query, $where);

            if ($where['boolean'] == 'or') {
                $result = ['$or' => [$result]];
            } elseif (count($query->wheres) > 1) {
                $result = ['$and' => [$result]];
            }

            return $result;
        })->reduce('array_merge', []);
    }

    protected function whereBasic(Builder $query, $where): array
    {
        return [$column = $where['column'] => $this->convertKey($column, $where['value'])]; // TODO different operator logic
    }

    protected function whereNested(Builder $query, $where): array
    {
        return $where['query']->compileWheres();
    }

    protected function whereIn(Builder $query, $where): array
    {
        $column = $where['column'];

        $values = array_map(fn ($value) => $this->convertKey($column, $value), $where['values']);

        return [$column => ['$in' => array_values($values)]];
    }

    protected function whereNotIn(Builder $query, $where): array
    {
        $column = $where['column'];

        $values = array_map(fn ($value) => $this->convertKey($column, $value), $where['values']);

        return [$column => ['$nin' => array_values($values)]];
    }

    protected function whereNull(Builder $query, $where): array
    {
        $where['operator'] = '=';
        $where['value'] = null;

        return $this->whereBasic($query, $where);
    }

    protected function whereNotNull(Builder $query, $where): array
    {
        $where['operator'] = '!=';
        $where['value'] = null;

        return $this->whereBasic($query, $where);
    }

    protected function whereBetween(Builder $query, $where): array
    {
        $column = $where['column'];
        $values = $where['values'];

        if ($where['not']) {
            return [
                '$or' => [
                    [
                        $column => [
                            '$lte' => $values[0],
                        ],
                    ],
                    [
                        $column => [
                            '$gte' => $values[1],
                        ],
                    ],
                ],
            ];
        }

        return [
            $column => [
                '$gte' => $values[0],
                '$lte' => $values[1],
            ],
        ];
    }

    protected function whereRaw(Builder $query, $where): array
    {
        return $where['sql'];
    }

    protected function convertKey(string $column, $value): array
    {
        if (preg_match('/(.*\.)?_id$/', $column) && is_string($value)) {
            return new ObjectId($value);
        }

        return $value;
    }
}
