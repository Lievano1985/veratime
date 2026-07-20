<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\ScheduleBatchVersionChainResult;
use App\Models\ScheduleBatch;
use Illuminate\Support\Collection;

class ResolveScheduleBatchVersionChainAction
{
    public function handle(ScheduleBatch $batch): ScheduleBatchVersionChainResult
    {
        $root = $batch;
        $seen = [];
        while ($root->previous_batch_id) {
            if (in_array($root->id, $seen, true)) {
                return new ScheduleBatchVersionChainResult(collect([$root]), null, ['La cadena de versiones contiene un ciclo.']);
            }
            $seen[] = $root->id;
            $root = ScheduleBatch::query()->findOrFail($root->previous_batch_id);
        }

        $versions = collect();
        $current = $root;
        $errors = [];
        $expectedVersion = 1;
        while ($current) {
            $siblings = ScheduleBatch::query()->where('previous_batch_id', $current->id)->orderBy('version')->get();
            $versions->push($current);

            if ((int) $current->version !== $expectedVersion++) {
                $errors[] = 'La cadena de versiones contiene saltos.';
            }

            if ($siblings->count() > 1) {
                $errors[] = 'La cadena de versiones contiene ramas.';
                break;
            }

            $current = $siblings->first();
        }

        $published = $versions->first(fn (ScheduleBatch $item): bool => $item->status === 'published');
        if ($versions->where('status', 'published')->count() > 1) {
            $errors[] = 'La cadena contiene mas de una version publicada.';
        }

        return new ScheduleBatchVersionChainResult($versions->values(), $published, array_values(array_unique($errors)));
    }
}
