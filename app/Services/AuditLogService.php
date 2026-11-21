<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Append-only audit trail for sensitive operations (approvals, payouts,
 * settings changes, role changes). Records actor, subject and before/after
 * values; actor is nullOnDelete so history survives account removal.
 */
class AuditLogService
{
    public function record(
        ?User $user,
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Recent audit trail entries, newest first.
     *
     * @return Collection<int, AuditLog>
     */
    public function recent(?string $action = null, int $limit = 100): Collection
    {
        return AuditLog::query()
            ->with('user:id,name,email,role')
            ->when($action, fn ($q) => $q->where('action', $action))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
