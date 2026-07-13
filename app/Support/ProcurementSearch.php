<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ProcurementSearch
{
    /**
     * Case-insensitive substring match on a column. SQL `LIKE` is case-sensitive on PostgreSQL,
     * so this normalizes both sides and matches user input to stored values regardless of casing.
     *
     * @param  Builder<Model>  $query
     */
    public static function applyCaseInsensitiveLike(Builder $query, string $column, string $search): void
    {
        $query->whereRaw('LOWER('.$column.') LIKE ?', ['%'.mb_strtolower($search).'%']);
    }

    /**
     * Distinct substrings to match against document number columns (pr_number, po_number, rr_number).
     * Includes the raw search and, when the search looks like a prefixed document id (RR-/PR-/PO-),
     * the remainder after the prefix so stored values like 2026-001 still match.
     *
     * @return list<string>
     */
    public static function documentNumberSearchPatterns(string $search): array
    {
        $trimmed = trim($search);

        if ($trimmed === '') {
            return [];
        }

        $patterns = [$trimmed];

        if (preg_match('/^(?i)(rr|pr|po)[-_\s]*(.+)$/', $trimmed, $matches)) {
            $rest = trim($matches[2]);

            if ($rest !== '') {
                $patterns[] = $rest;
            }
        }

        return array_values(array_unique($patterns));
    }
}
