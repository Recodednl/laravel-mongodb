<?php

namespace Recoded\MongoDB\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use Recoded\MongoDB\Database\Query\JoinClause;
use Recoded\MongoDB\Exceptions\UnsupportedByMongoDBException;

class MongodbGrammar extends Grammar
{
    use \Recoded\MongoDB\Database\Grammar;

    protected $selectComponents = [
        'joins',
        'existWheres',
        'wheres',
        'aggregations',
        'offset',
        'limit',
        'columns',
        'orders',
        'aggregate',
//        'groups',
    ];

    protected function arrayifyWheres(Builder $query, array $wheres): array
    {
        return collect($wheres)->map(function (array $where, int $i) use ($query, $wheres) {
            if ($i == 0 && count($wheres) > 1 && $where['boolean'] == 'and') {
                $where['boolean'] = $wheres[$i + 1]['boolean'];
            }

            $result = $this->{"where{$where['type']}"}($query, $where);

            if ($where['boolean'] == 'or') {
                $result = ['$or' => [$result]];
            } elseif (count($wheres) > 1) {
                $result = ['$and' => [$result]];
            }

            return $result;
        })->reduce('array_merge_recursive', []);
    }

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
        $types = ['preMatch', 'match', 'aggregations'];
        $sql = array_fill_keys($types, []);

        foreach ($this->selectComponents as $component) {
            if (isset($query->{$component})) {
                $method = 'compile' . ucfirst($component);

                $values = $this->$method($query, $query->{$component});

                if ($values === null) {
                    continue;
                }

                $add = function (?string $type, array $values) use (&$sql) {
                    $type ??= 'aggregations';

                    $sql[$type] = array_merge($sql[$type], $values);
                };

                // TODO optimize adding multiple types
                $whereAssocArrayTypes = array_filter($values, function ($value) use ($types) {
                    if (!is_array($value) || !Arr::isAssoc($value)) {
                        return false;
                    }

                    $keysTypes = array_filter(array_keys($value), fn ($key) => in_array($key, $types));

                    return count($keysTypes) === count($value);
                });

                $containsOnlyAssocArrays = count($whereAssocArrayTypes) === count($values);

                if (!Arr::isAssoc($values) && $containsOnlyAssocArrays) {
                    foreach ($values as $subValues) {
                        foreach ($subValues as $type => $typeValues) {
                            $add($type, $typeValues);
                        }
                    }
                } else {
                    $type = $component === 'wheres' ? 'match' : ($component === 'joins' ? 'preMatch' : null);

                    $add($type, $type === null ? [$values] : $values);
                }
            }
        }

        $sql['match'] = empty($sql['match']) ? [] : [['$match' => $this->arrayifyWheres($query, $sql['match'])]];

        return Arr::flatten($sql, 1);
    }

    public function compileDelete(Builder $query): array
    {
        return [
            'collection' => $query->from,
            'filter' => $this->arrayifyWheres($query, $this->compileWheres($query)),
        ];
    }

    protected function compileEmbedJoin(JoinClause $clause): array
    {
        return ['$lookup' => [
            'from' => $clause->table,
            'localField' => $clause->first,
            'foreignField' => $clause->second,
            'as' => $clause->name,
        ]];
    }

    protected function compileExistWheres(Builder $query, array $wheres)
    {
        return array_map(function (array $where) use ($query) {
            $type = $where['type'];

            return $this->{'where' . $type}($query, $where);
        }, $wheres);
    }

    public function compileInsert(Builder $query, array $values): array
    {
        $shouldWrap = !empty(array_filter($values, fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY));

        return [
            'collection' => $query->from,
            'values' => $shouldWrap ? [$values] : $values,
        ];
    }

    protected function compileJoins(Builder $query, $joins)
    {
        return array_map(function (JoinClause $clause) {
            if (!method_exists($this, $method = 'compile' . ucfirst($clause->type) . 'Join')) {
                throw new UnsupportedByMongoDBException($clause->type . ' join');
            }

            return $this->$method($clause);
        }, $joins);
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
        return array_reduce($orders, function (array $carry, array $order) use ($query) {
            $carry[$this->mongoWrap($order['column'], $query)] = $order['direction'];

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
            'filter' => $this->arrayifyWheres($query, $this->compileWheres($query)),
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
        if (is_null($wheres = $query->wheres)) {
            return [];
        }

        return count($wheres) > 0 ? $wheres : [];
    }

    protected function compileWheresToArray($query): array
    {
        return $this->arrayifyWheres($query, $query->wheres);
    }

    public function convertKey($column, $value)
    {
        if (is_string($column) && preg_match('/^(.*\.)?_id$/', $column) && is_string($value)) {
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

        return [$this->mongoWrap($column, $query) => $not ? ['$not' => $result] : $result];
    }

    protected function whereNested(Builder $query, $where): array
    {
        return $where['query']->compileWheres();
    }

    protected function whereIn(Builder $query, $where): array
    {
        $column = $this->mongoWrap($where['column'], $query);

        $values = array_map(fn ($value) => $this->convertKey($column, $value), $where['values']);

        return [$column => ['$in' => array_values($values)]];
    }

    protected function whereNotIn(Builder $query, $where): array
    {
        $column = $this->mongoWrap($where['column'], $query);

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
        $column = $this->mongoWrap($where['column'], $query);
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

    protected function whereExists(Builder $query, $where, bool $not = false)
    {
        [
            'boolean' => $boolean,
            'query' => $constrain,
        ] = $where;

        if (!$constrain instanceof JoinClause) {
            throw new UnsupportedByMongoDBException('Non-join where exists queries');
        }

        $name = sprintf('where_%s_exists_%s', $constrain->table, uniqid());

        $constrain->as($name);

        $preMatch = $this->compileJoins($query, [$constrain]);

        if ($constrain->countConstrain !== null) {
            $preMatch[] = [
                '$addFields' => [
                    $name => ['$size' => "$${name}"],
                ],
            ];

            $match = [
                'boolean' => $boolean,
                'column' => $name,
                'operator' => $constrain->countConstrain[0],
                'type' => 'Basic',
                'value' => $constrain->countConstrain[1],
            ];
        } else {
            $match = $not ? ['$size' => 0] : ['$not' => ['$size' => 0]];
            $match = [
                'boolean' => $boolean,
                'sql' => [$name => $match],
                'type' => 'Raw',
            ];
        }

        return [
            'preMatch' => $preMatch,
            'match' => [$match],
            'aggregations' => [
                ['$project' => [$name => false]],
            ],
        ];
    }

    protected function whereNotExists(Builder $query, $where)
    {
        return $this->whereExists($query, $where, true);
    }

    protected function whereRaw(Builder $query, $where): array
    {
        return $where['sql'];
    }
}
