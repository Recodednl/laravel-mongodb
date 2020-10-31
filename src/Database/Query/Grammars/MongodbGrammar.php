<?php

namespace Recoded\MongoDB\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

class MongodbGrammar extends Grammar
{
    use \Recoded\MongoDB\Database\Grammar;

    protected $selectComponents = [
        'wheres',
        'aggregations',
        'offset',
        'limit',
        'columns',
        'orders',
        'aggregate',
//        'groups',
    ];

    protected function compileAggregate(Builder $query, $aggregate)
    {
        return [
            $aggregate['function'] => $aggregate['columns'] ?? 'aggregate',
        ];
    }

    protected function compileColumns(Builder $query, $columns): ?array
    {
        if ($query->aggregate !== null || $columns === ['*']) {
            return null;
        }

        $project = array_fill_keys($columns, true);

        if (!in_array('_id', $columns)) {
            $project['_id'] = false;
        }

        return [
            '$project' => $project,
        ];
    }

    protected function compileComponents(Builder $query): array
    {
        $match = [];
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if (isset($query->{$component})) {
                $method = 'compile' . ucfirst($component);

                $value = $this->$method($query, $query->{$component});

                if ($value === null) {
                    continue;
                }

                if ($component === 'wheres') {
                    $match = array_merge($match, $value);
                } else {
                    $sql[] = $value;
                }
            }
        }

        $final = empty($match) ? [] : [['$match' => $match]];

        return [...$final, ...$sql];
    }

    public function compileDelete(Builder $query): array
    {
        return [
            'collection' => $query->from,
            'filter' => $this->compileWheres($query),
        ];
    }

    public function compileInsert(Builder $query, array $values): array
    {
        $shouldWrap = !empty(array_filter($values, fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY));

        return [
            'collection' => $query->from,
            'values' => $shouldWrap ? [$values] : $values,
        ];
    }

    protected function compileLimit(Builder $query, $limit): array
    {
        return [
            '$limit' => abs($limit),
        ];
    }

    protected function compileOffset(Builder $query, $offset): array
    {
        return [
            '$skip' => abs($offset),
        ];
    }

    protected function compileOrders(Builder $query, $orders): array
    {
        if (empty($orders)) {
            return [];
        }

        return [
            '$sort' => $this->compileOrdersToArray($query, $orders),
        ];
    }

    protected function compileOrdersToArray(Builder $query, $orders): array
    {
        return array_reduce($orders, function (array $carry, array $order) {
            $carry[$order['column']] = $order['direction'];

            return $carry;
        }, []);
    }

    public function compileSelect(Builder $query): array
    {
        return $this->compileComponents($query);
    }

    public function compileUpdate(Builder $query, array $values): array
    {
        return [
            'collection' => $this->wrapTable($query->from),
            'filter' => $this->compileWheres($query),
            'values' => $this->compileUpdateColumns($query, $values),
        ];
    }

    protected function compileUpdateColumns(Builder $query, array $values): array
    {
        return Collection::make($values)->mapWithKeys(function ($value, $key) {
            return [$this->wrap($key) => $this->parameter($value)];
        })->all();
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
        })->reduce('array_merge_recursive', []);
    }

    protected function convertKey(string $column, $value)
    {
        if (preg_match('/(.*\.)?_id$/', $column) && is_string($value)) {
            return new ObjectId($value);
        }

        return $value;
    }

    protected function isValidPattern(string $pattern): bool
    {
        try {
            preg_match($pattern, '');

            return true;
        } catch (\Throwable $e) {
            return $e instanceof \ErrorException;
        }
    }

    protected function whereBasic(Builder $query, $where): array
    {
        $column = $where['column'];
        $operator = $where['operator'];
        $value = $where['value'];
        $not = false;

        if (in_array($operator, ['like', 'not like'])) {
            $value = preg_quote($value, '/');
            $value = preg_replace('/(%$|^%)/', '.*', $value);

            $value = new Regex($value, 'i');
            $not = $operator === 'not like';
            $operator = 'regex';
        } elseif (in_array($operator, ['regexp', 'not regexp', 'regex', 'not regex'])) {
            $not = preg_match('/^not/', $operator);
            $operator = 'regex';

            if (!$value instanceof Regex && is_string($value)) {
                if ($this->isValidPattern($value)) {
                    preg_match('/^([\/\#\+\%])(.*)\1([a-zA-Z]+)?$/', $value, $matches);

                    $value = new Regex($matches[2], $matches[3] ?? '');
                } else {
                    $value = new Regex($value);
                }
            }
        }

        $operator = [
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
            '!=' => 'ne',
            '<>' => 'ne',
            '<=>' => '=',
        ][$operator] ?? $operator;

        $value = $this->convertKey($column, $value);

        if (!isset($operator) || $operator == '=') {
            $result = $value;
        } else {
            $result = ['$' . $operator => $value];
        }

        return [$column => $not ? ['$not' => $result] : $result];
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
}
