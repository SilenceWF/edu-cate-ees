<?php

namespace Tests;

use App\Models\User;
use App\Packs\PackLoader;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Install the public demo pack (fictional company) as test data. */
    protected function installDemo(): void
    {
        app(PackLoader::class)->install(base_path('packs/demo'));
        app(Settings::class)->flush();
    }

    protected function person(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function employee(): User
    {
        return $this->person('employee@helios-foods.example');
    }
}
