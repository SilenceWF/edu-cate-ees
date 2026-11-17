<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Key/value store for everything that is company-specific (set by the installed pack
 * or by an admin). Top-level keys are rows; nested values are read with dot notation.
 */
class Settings
{
    private ?array $cache = null;

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }
            $this->cache = DB::table('settings')->pluck('value', 'key')
                ->map(fn ($v) => json_decode($v, true))->all();
        } catch (Throwable) {
            return [];
        }

        return $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /** Set a top-level key. */
    public function set(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()],
        );
        $this->cache = null;
    }

    public function flush(): void
    {
        $this->cache = null;
    }

    public function installed(): bool
    {
        return (bool) $this->get('pack.slug');
    }
}
