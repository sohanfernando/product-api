<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseBootstrapTest extends TestCase
{
    public function test_resolving_sqlite_connection_does_not_require_a_database_file(): void
    {
        config(['database.connections.uninitialized' => [
            'driver' => 'sqlite',
            'database' => database_path('not-created-yet.sqlite'),
        ]]);

        $connection = DB::connection('uninitialized');

        $this->assertInstanceOf(Closure::class, $connection->getRawPdo());
    }

    public function test_unicode_lowercase_is_registered_when_sqlite_opens_or_reconnects(): void
    {
        config(['database.connections.unicode_check' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]]);

        $first = DB::connection('unicode_check')->selectOne('select lower(?) as value', ['ÉCLAIR']);
        DB::reconnect('unicode_check');
        $second = DB::connection('unicode_check')->selectOne('select lower(?) as value', ['CRÈME']);

        $this->assertSame('éclair', $first->value);
        $this->assertSame('crème', $second->value);
    }
}
