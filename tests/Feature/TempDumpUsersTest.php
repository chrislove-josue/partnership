<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TempDumpUsersTest extends TestCase
{

    public function test_dump_users(): void
    {
        dump(User::query()->limit(20)->pluck('email', 'id')->all());
        $this->assertTrue(true);
    }
}
