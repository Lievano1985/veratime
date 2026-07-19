<?php

namespace App\Domains\Scheduling\Data;

class VerifyScheduleBatchSnapshotResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public bool $valid,
        public ?string $expectedHash,
        public ?string $actualHash,
        public ?string $schemaVersion,
        public bool $jsonValid,
        public array $errors = [],
    ) {
    }
}
