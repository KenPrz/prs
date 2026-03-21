<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<User, Department>
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
