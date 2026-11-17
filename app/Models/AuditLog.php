<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail. Rows cannot be updated or deleted (enforced here and by
 * database triggers) and each row's hash covers the previous row's hash.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('audit_logs is append-only'));
        static::deleting(fn () => throw new LogicException('audit_logs is append-only'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function computeHash(?string $prevHash, array $row): string
    {
        return hash('sha256', implode('|', [
            $prevHash ?? '',
            $row['actor_user_id'] ?? '',
            $row['target_user_id'] ?? '',
            $row['action'],
            json_encode($row['metadata'] ?? null, JSON_UNESCAPED_UNICODE),
            $row['ip_address'] ?? '',
            $row['user_agent'] ?? '',
            $row['created_at'],
        ]));
    }
}
