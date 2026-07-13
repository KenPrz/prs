<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'subject_type',
    'subject_id',
    'content',
])]
class Note extends Model
{
    /**
     * The user that the note belongs to.
     *
     * @return BelongsTo<User, Note>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The subject that the note belongs to.
     *
     * @return MorphTo<Model, Note>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
