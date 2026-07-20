<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ScheduleBatch;
use Illuminate\Support\Collection;

class ScheduleBatchVersionChainResult
{
    /**
     * @param Collection<int, ScheduleBatch> $versions
     * @param list<string> $errors
     */
    public function __construct(
        public Collection $versions,
        public ?ScheduleBatch $currentPublished,
        public array $errors = [],
    ) {
    }

    public function valid(): bool
    {
        return $this->errors === [];
    }
}
