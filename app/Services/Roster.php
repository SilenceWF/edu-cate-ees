<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Collection;

/** The active roster with each person's progress in a campaign. */
class Roster
{
    /** @return Collection<int, array{user: User, p: array}> */
    public static function rows(Campaign $campaign): Collection
    {
        $users = User::where('is_active', true)->with(['group', 'department'])->orderBy('name')->get();
        $progress = new Progress($campaign);

        return $users->map(fn (User $u) => ['user' => $u, 'p' => $progress->of($u)]);
    }

    public static function percent(int $part, int $whole): int
    {
        return $whole ? (int) round($part / $whole * 100) : 0;
    }
}
