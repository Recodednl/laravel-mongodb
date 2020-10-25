<?php

namespace Recoded\MongoDB\Database\Query;

use Illuminate\Database\Query\Builder as IlluminateBuilder;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use Recoded\MongoDB\Exceptions\UnsupportedByMongoDBException;

class Builder extends IlluminateBuilder
{
    protected Collection $collection;

    public $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'not like',
        'regexp', 'not regexp', 'regex', 'not regex',
    ];

    public function addBinding($value, $type = 'where'): self
    {
        return $this;
    }

    public function addWhereExistsQuery(IlluminateBuilder $query, $boolean = 'and', $not = false)
    {
        throw new UnsupportedByMongoDBException('(Doesnt)-exists Query');
    }

    public function avg($column)
    {
        return $this->aggregateGroupedColumn(__FUNCTION__, $column);
    }

    public function count($columns = '*')
    {
        return (int) $this->aggregate(__FUNCTION__, null);
    }

    public function dd(): void
    {
        dd($this->collection->getCollectionName(), $this->toSql());
    }

    public function dump(): self
    {
        dump($this->collection->getCollectionName(), $this->toSql());

        return $this;
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function from($collection, $as = null)
    {
        $this->collection = $this->connection->getCollection($collection);

        return parent::from($collection);
    }

    public function aggregateGroupedColumn($function, $column)
    {
        $function = '$' . ltrim($function, '$');
        $column = '$' . ltrim($column, '$');

        return $this->aggregate('group', [
            '_id' => '',
            'aggregate' => [$function => $column],
        ]);
    }

    public function insertOrIgnore(array $values)
    {
        throw new UnsupportedByMongoDBException('InsertOrIgnore');
    }

    public function insertUsing(array $columns, $query)
    {
        throw new UnsupportedByMongoDBException('InsertUsing');
    }

    public function max($column)
    {
        return $this->aggregateGroupedColumn(__FUNCTION__, $column);
    }

    public function mergeBindings(IlluminateBuilder $query)
    {
        return $this;
    }

    public function min($column)
    {
        return $this->aggregateGroupedColumn(__FUNCTION__, $column);
    }

    public function orderBy($column, $direction = 'asc')
    {
        $direction = is_string($direction) ? strtolower($direction) : $direction;

        if (!is_array($direction) && !in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Order direction must be "asc", "desc" or an array.');
        }

        $direction = $direction === 'asc' ? 1 : $direction;
        $direction = $direction === 'desc' ? -1 : $direction;

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    public function orderByRaw($sql, $bindings = [])
    {
        throw new UnsupportedByMongoDBException('OrderByRaw');
    }

    protected function parseMongo(array $data): array
    {
        array_walk($data, function (&$value) {
            if ($value instanceof ObjectId) {
                $value = (string)$value;
            }

            if ($value instanceof BSONArray) {
                $value = $this->parseMongo((array)$value);
            }

            if ($value instanceof BSONDocument) {
                $value = $this->parseMongo($value->getArrayCopy());
            }

            if ($value instanceof UTCDateTime) {
                $value = $value->toDateTime()->format(
                    $this->grammar->getDateFormat(),
                );
            }
        });

        return $data;
    }

    protected function runSelect(): array
    {
        /** @var \MongoDB\Model\BSONDocument[] $results */
        $results = iterator_to_array(
            $this->collection->aggregate($this->toSql()),
        );

        return array_map(function (BSONDocument $document) {
            return $this->parseMongo(
                $document->getArrayCopy(),
            );
        }, $results);
    }

    protected function setAggregate($function, $columns)
    {
        return parent::setAggregate('$' . ltrim($function, '$'), $columns);
    }

    public function setBindings(array $bindings, $type = 'where')
    {
        return $this;
    }

    public function sum($column)
    {
        return $this->aggregateGroupedColumn(__FUNCTION__, $column) ?: 0;
    }

    public function truncate(): bool
    {
        return $this->collection
            ->deleteMany([])
            ->isAcknowledged();
    }
}
