<?php

namespace App\Concerns;

use App\Models\AdminAuditLog;
use App\Models\Operator;
use App\Services\AdminAuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin desk components record every state-changing action through this helper.
 */
trait RecordsAdminActions
{
    /**
     * @param  array<string, mixed>  $context
     */
    protected function audit(string $action, ?Model $subject = null, array $context = [], ?Operator $operator = null): AdminAuditLog
    {
        return app(AdminAuditLogger::class)->record($action, $subject, $context, $operator);
    }
}
