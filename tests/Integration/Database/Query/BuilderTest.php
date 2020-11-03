<?php

namespace Recoded\MongoDB\Tests\Integration\Database\Query;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Recoded\MongoDB\Tests\TestCase;

class BuilderTest extends TestCase
{
    public function tearDown(): void
    {
        Schema::drop('users');
        Schema::drop('items');
    }

    public function testDeleteWithId()
    {
        $user = DB::table('users')->insertGetId([
            ['name' => 'Jane Doe', 'age' => 20],
        ]);

        $userId = (string) $user;

        DB::table('items')->insert([
            ['name' => 'one thing', 'user_id' => $userId],
            ['name' => 'last thing', 'user_id' => $userId],
            ['name' => 'another thing', 'user_id' => $userId],
            ['name' => 'one more thing', 'user_id' => $userId],
        ]);

        $product = DB::table('items')->first();

        $pid = (string) $product['_id'];

        DB::table('items')->where('user_id', $userId)->delete($pid);

        $this->assertEquals(3, DB::table('items')->count());

        $product = DB::table('items')->first();

        $pid = $product['_id'];

        DB::table('items')->where('user_id', $userId)->delete($pid);

        $this->assertEquals(2, DB::table('items')->count());
    }
}
