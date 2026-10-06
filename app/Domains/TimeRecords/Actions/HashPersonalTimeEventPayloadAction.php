<?php

namespace App\Domains\TimeRecords\Actions;

class HashPersonalTimeEventPayloadAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): string
    {
        $payload = $this->canonicalize([
            'client_event_id' => (string) $data['idempotency_key'],
            'event_type' => (string) $data['event_type'],
            'occurred_at' => (string) $data['occurred_at'],
            'timezone' => $data['timezone'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'device' => $data['device'] ?? [],
            'security' => $data['security'] ?? [],
        ]);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
