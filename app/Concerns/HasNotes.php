<?php

namespace App\Concerns;

use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotes
{
    /**
     * The notes of the model.
     *
     * @return MorphMany<Note, static>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'subject');
    }

    /**
     * Create a new note for the model.
     *
     *
     * @return Note<static>
     */
    public function createNote(string $content): Note
    {
        return $this->notes()->create(['content' => $content]);
    }

    /**
     * Update the note for the model.
     */
    public function updateNote(Note $note, string $content): bool
    {
        return $note->update(['content' => $content]);
    }

    /**
     * Delete the note for the model.
     */
    public function deleteNote(Note $note): bool
    {
        return $note->delete();
    }
}
