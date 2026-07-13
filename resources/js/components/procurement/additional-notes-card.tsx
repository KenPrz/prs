import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export interface NoteData {
    id?: number;
    content: string;
}

interface AdditionalNotesCardProps {
    notes: NoteData[];
    onNotesChange: (notes: NoteData[]) => void;
    errors: Record<string, string>;
    isReadOnly?: boolean;
}

export function AdditionalNotesCard({
    notes,
    onNotesChange,
    errors,
    isReadOnly = false,
}: AdditionalNotesCardProps) {
    const addNote = () => {
        onNotesChange([...notes, { content: '' }]);
    };

    const updateNote = (index: number, value: string) => {
        const updated = [...notes];
        updated[index] = { ...updated[index], content: value };
        onNotesChange(updated);
    };

    const removeNote = (index: number) => {
        if (notes.length === 1) {
            return;
        }

        const updated = [...notes];
        updated.splice(index, 1);
        onNotesChange(updated);
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                <CardTitle className="text-lg">Additional Notes</CardTitle>
                {!isReadOnly && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1.5 font-normal"
                        onClick={addNote}
                    >
                        <Plus className="h-3.5 w-3.5" />
                        Add Note
                    </Button>
                )}
            </CardHeader>
            <CardContent className="grid gap-4">
                {notes.map((note, index) => (
                    <div key={index} className="flex items-start gap-4">
                        <div className="w-full">
                            <textarea
                                value={note.content}
                                onChange={(e) =>
                                    updateNote(index, e.target.value)
                                }
                                placeholder="Any additional notes or special instructions..."
                                rows={2}
                                className="min-h-[60px] w-full rounded-md border border-input bg-muted/20 px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground/70 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:bg-muted/30 disabled:text-foreground disabled:opacity-100"
                                disabled={isReadOnly}
                            />
                            {errors[`notes.${index}.content`] && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors[`notes.${index}.content`]}
                                </p>
                            )}
                        </div>
                        {!isReadOnly && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled={notes.length === 1}
                                className="mt-1 h-8 w-8 shrink-0 text-muted-foreground/60 hover:bg-destructive/10 hover:text-destructive"
                                onClick={() => removeNote(index)}
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
