<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records every platform-admin action (who, what, on which record, for which operator).
 */
class AdminAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, ?Model $subject = null, array $context = [], ?Operator $operator = null): AdminAuditLog
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        return AdminAuditLog::query()->create([
            'user_id' => $actor?->getKey(),
            'actor_email' => $actor?->email,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey() !== null ? (string) $subject->getKey() : null,
            'operator_id' => $operator?->getKey() ?? $this->operatorIdFor($subject),
            'context' => $context === [] ? null : $context,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    private function operatorIdFor(?Model $subject): ?string
    {
        if ($subject instanceof Operator) {
            return (string) $subject->getKey();
        }

        $operatorId = $subject?->getAttribute('operator_id');

        return is_string($operatorId) && $operatorId !== '' ? $operatorId : null;
    }
}
