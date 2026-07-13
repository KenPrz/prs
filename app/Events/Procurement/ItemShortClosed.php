<?php

namespace App\Events\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class ItemShortClosed
{
    use Dispatchable;

    /**
     * @param  Model  $document  The PR or PO the omitted item belongs to.
     */
    public function __construct(
        public Model $document,
        public string $itemName,
        public ?string $reason,
    ) {}
}
