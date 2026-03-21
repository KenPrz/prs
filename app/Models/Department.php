<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => 'string',
        ];
    }

    /**
     * Get the users for the department.
     *
     * @return HasMany<User, Department>
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
