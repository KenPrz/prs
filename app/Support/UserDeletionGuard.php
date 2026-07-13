<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Users referenced by restrict foreign keys cannot be deleted — this reports
 * the blocker before the database turns it into a 500.
 */
class UserDeletionGuard
{
    /** @var array<string, string> table => user FK column */
    private const BLOCKING_REFERENCES = [
        'purchase_requisitions' => 'requestor_id',
        'payment_request_forms' => 'requestor_id',
        'receiving_reports' => 'received_by_id',
        'workflow_actions' => 'actor_user_id',
        'workflow_assignments' => 'user_id',
        'notes' => 'user_id',
    ];

    /**
     * Returns an error message when the user cannot be deleted, null otherwise.
     */
    public static function blocker(User $user): ?string
    {
        foreach (self::BLOCKING_REFERENCES as $table => $column) {
            if (DB::table($table)->where($column, $user->id)->exists()) {
                return 'This account has documents or workflow activity and cannot be deleted.';
            }
        }

        return null;
    }
}
