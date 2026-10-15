<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Feature tests run logged in as an admin unless they set this to false. */
    protected bool $signIn = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->signIn && in_array(RefreshDatabase::class, class_uses_recursive($this))) {
            $this->actingAs(User::create(['name' => 'Tester', 'email' => 'tester@example.com', 'password' => 'secret-password', 'is_admin' => true]));
        }
    }
}
