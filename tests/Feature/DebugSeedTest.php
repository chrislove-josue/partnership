<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_first(): void
    {
        $this->seed();
        fwrite(STDERR, "\n[first] users=".User::count().' emails='.User::pluck('email')->implode(',')."\n");
        $this->assertTrue(true);
    }

    public function test_b_second(): void
    {
        $this->seed();
        fwrite(STDERR, "\n[second] users=".User::count().' emails='.User::pluck('email')->implode(',')."\n");
        $this->assertTrue(true);
    }

    public function test_c_third(): void
    {
        $this->seed();
        fwrite(STDERR, "\n[third] users=".User::count().' emails='.User::pluck('email')->implode(',')."\n");
        $this->assertTrue(true);
    }
}
