<?php

namespace App\Events\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class DocumentCancelled
{
    use Dispatchable;

    /**
     * A post-approval cancellation of a PR, PO or PRF (outside the workflow).
     */
    public function __construct(
        public Model $document,
        public ?string $reason,
    ) {}
}
