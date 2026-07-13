<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['user_id', 'department_id'])]
class UserDepartment extends Pivot
{
    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'department_id' => 'integer',
        ];
    }

    /**
     * The user that the user department belongs to.
     *
     * @return BelongsTo<User, UserDepartment>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The department that the user department belongs to.
     *
     * @return BelongsTo<Department, UserDepartment>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
