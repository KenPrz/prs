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
     * @param string $content
     *
     * @return Note<static>
     */
    public function createNote(string $content): Note
    {
        return $this->notes()->create(['content' => $content]);
    }

    /**
     * Update the note for the model.
     *
     * @param string $content
     *
     * @return bool
     */
    public function updateNote(Note $note, string $content): bool
    {
        return $note->update(['content' => $content]);
    }

    /**
     * Delete the note for the model.
     *
     * @param Note $note
     *
     * @return bool
     */
    public function deleteNote(Note $note): bool
    {
        return $note->delete();
    }
}
