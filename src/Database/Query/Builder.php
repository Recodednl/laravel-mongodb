<?php

namespace Recoded\MongoDB\Database\Query;

use Illuminate\Database\Query\Builder as IlluminateBuilder;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

class Builder extends IlluminateBuilder
{
    protected Collection $collection;

    public function addBinding($value, $type = 'where'): self
    {
        return $this;
    }

    public function dd(): void
    {
        dd($this->toSql());
    }

    public function from($collection, $as = null)
    {
        $this->collection = $this->connection->getCollection($collection);

        return parent::from($collection);
    }

    public function mergeBindings(IlluminateBuilder $query)
    {
        return $this;
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
                $value = $value->toDateTime()->format('Y-m-d H:i:s');
            }
        });

        return $data;
    }

    protected function runSelect(): array
    {
        /** @var \MongoDB\Model\BSONDocument[] $results */
        $results = iterator_to_array(
            $this->collection->find($this->toSql()),
        );

        return array_map(function (BSONDocument $document) {
            return $this->parseMongo(
                $document->getArrayCopy(),
            );
        }, $results);
    }

    public function setBindings(array $bindings, $type = 'where')
    {
        return $this;
    }
}
