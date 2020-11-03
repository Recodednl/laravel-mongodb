<?php

namespace Recoded\MongoDB\Tests\Unit\Database\Query;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Recoded\MongoDB\Database\Query\Builder;
use Recoded\MongoDB\Tests\TestCase;

class BuilderTest extends TestCase
{
    protected ConnectionInterface $connection;
    protected Builder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new Builder(
            $this->connection = \Mockery::spy(DB::connection()),
        );
    }

    protected function tearDown(): void
    {
        Schema::drop('test');

        parent::tearDown();
    }

    public function testDeleteWithId(): void
    {
        $id = '5f9d6ca7ebe15136db16b122';

        $this->connection->shouldReceive('delete')->once()->withArgs(function ($first) {
            if (!isset($first['collection'], $first['filter']['_id'])) {
                return false;
            }

            return $first['collection'] === 'test' && (string) $first['filter']['_id'] === '5f9d6ca7ebe15136db16b122';
        })->andReturn(1);

        $this->builder->from('test')->delete($id);
    }

    public function testDeleteWithoutId(): void
    {
        $this->connection->shouldReceive('delete')->once()
            ->withArgs([['collection' => 'test_collection', 'filter' => []], []])
            ->andReturn(1);

        $this->builder->from('test_collection')->delete();
    }
}
