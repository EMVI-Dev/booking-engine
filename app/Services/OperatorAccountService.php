<?php

namespace App\Services;

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\User;

/**
 * Platform-side changes to an operator account (approval, suspension, cached lookups).
 */
class OperatorAccountService
{
    public function __construct(
        private DomainResolverService $domains,
        private OperatorActivitySlackNotifier $slack,
        private AdminAuditLogger $audit,
    ) {}

    /**
     * Approve, suspend or return an operator to pending, and alert the team on Slack.
     */
    public function changeStatus(Operator $operator, OperatorStatus $status): void
    {
        $from = $operator->status;

        if ($from === $status) {
            return;
        }

        $operator->update(['status' => $status]);
        $this->forgetCachedLookups($operator);

        if (auth()->user()?->isAdmin()) {
            $this->audit->record('operator.status_changed', $operator, ['from' => $from->label(), 'to' => $status->label()]);
        }

        $this->slack->statusChanged($operator->fresh() ?? $operator, $from->label(), $status->label());
    }

    /**
     * Let a platform admin manage an operator's desk for a limited time (logged).
     */
    public function startManaging(Operator $operator): void
    {
        session([
            User::IMPERSONATION_SESSION_KEY => $operator->id,
            User::IMPERSONATION_STARTED_KEY => now()->getTimestamp(),
        ]);

        $this->audit->record('operator.manage_started', $operator);
    }

    /**
     * End the admin's managing session, if any.
     */
    public function stopManaging(): void
    {
        $operatorId = session(User::IMPERSONATION_SESSION_KEY);

        session()->forget([User::IMPERSONATION_SESSION_KEY, User::IMPERSONATION_STARTED_KEY]);

        if (is_string($operatorId) && ($operator = Operator::query()->find($operatorId))) {
            $this->audit->record('operator.manage_stopped', $operator);
        }
    }

    /**
     * Drop cached host → operator resolutions so plan, status and domain changes apply at once.
     */
    public function forgetCachedLookups(Operator $operator): void
    {
        $this->domains->clearOperatorDomainCache($operator);
    }

    /**
     * Map the admin UI's status keyword to the enum (unknown values fall back to pending).
     */
    public static function statusFromInput(string $status): OperatorStatus
    {
        return match ($status) {
            'approved' => OperatorStatus::Approved,
            'suspended' => OperatorStatus::Suspended,
            default => OperatorStatus::Pending,
        };
    }
}
