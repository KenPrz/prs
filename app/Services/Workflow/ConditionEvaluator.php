<?php

namespace App\Services\Workflow;

use App\Contracts\WorkflowSubject;

/**
 * Evaluates a step's conditional-routing rule against the workflow subject.
 * A null/empty condition always runs. Otherwise the rule is a map of
 * subject attribute => { operator: value }; all entries must pass (AND).
 *
 * Example: { "gross_total": { "gte": 50000 }, "price_type": { "eq": "VAT_INCLUSIVE" } }
 *
 * Supported operators: eq, ne, gt, gte, lt, lte, in, not_in.
 */
class ConditionEvaluator
{
    /**
     * @param  array<string, mixed>|null  $condition
     * @return bool true when the step should run; false when it should be skipped
     */
    public function shouldRun(?array $condition, WorkflowSubject $subject): bool
    {
        if (empty($condition)) {
            return true;
        }

        foreach ($condition as $field => $rule) {
            if (! is_array($rule)) {
                $rule = ['eq' => $rule];
            }

            $actual = data_get($subject, $field);

            foreach ($rule as $operator => $expected) {
                if (! $this->compare($actual, (string) $operator, $expected)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'eq' => $actual == $expected,
            'ne' => $actual != $expected,
            'gt' => $actual > $expected,
            'gte' => $actual >= $expected,
            'lt' => $actual < $expected,
            'lte' => $actual <= $expected,
            'in' => is_array($expected) && in_array($actual, $expected),
            'not_in' => is_array($expected) && ! in_array($actual, $expected),
            default => true,
        };
    }
}
