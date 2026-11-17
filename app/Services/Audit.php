<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Audit
{
    /**
     * Append an entry to the audit trail. The actor defaults to the signed-in user
     * (in kiosk mode that is the supervising user).
     */
    public static function log(string $action, User|int|null $target = null, array $metadata = [], User|int|null $actor = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return DB::transaction(function () use ($action, $target, $metadata, $actor, $request) {
            $prev = AuditLog::query()->lockForUpdate()->latest('id')->value('hash');

            $row = [
                'actor_user_id' => $actor instanceof User ? $actor->id : ($actor ?? auth()->id()),
                'target_user_id' => $target instanceof User ? $target->id : $target,
                'action' => $action,
                'metadata' => $metadata ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 1000) : 'console',
                'created_at' => now()->utc()->format('Y-m-d H:i:s'),
            ];

            return AuditLog::create($row + ['prev_hash' => $prev, 'hash' => AuditLog::computeHash($prev, $row)]);
        });
    }

    /** Verify the hash chain. Returns the id of the first broken row, or null if intact. */
    public static function firstBrokenLink(): ?int
    {
        $prev = null;
        foreach (AuditLog::query()->orderBy('id')->cursor() as $log) {
            $expected = AuditLog::computeHash($prev, [
                'actor_user_id' => $log->actor_user_id,
                'target_user_id' => $log->target_user_id,
                'action' => $log->action,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ]);
            if ($log->prev_hash !== $prev || $log->hash !== $expected) {
                return $log->id;
            }
            $prev = $log->hash;
        }

        return null;
    }
}
