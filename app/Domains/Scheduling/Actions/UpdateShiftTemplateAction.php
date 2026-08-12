<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\DailyScheduleSegment;
use App\Models\ShiftTemplate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateShiftTemplateAction
{
    public function __construct(private ValidateShiftTemplateSegmentsAction $validateSegments)
    {
    }

    public function handle(Company $company, ShiftTemplate $template, array $data, array $segments): ShiftTemplate
    {
        $this->assertTenant($company, $template);
        $normalized = $this->validateSegments->handle($segments);
        $code = $this->normalizeCode($data['code'] ?? $template->code);

        $duplicate = ShiftTemplate::query()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->whereKeyNot($template->id)
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('Ya existe una plantilla con el mismo codigo en esta empresa.');
        }

        return DB::transaction(function () use ($company, $template, $data, $normalized, $code): ShiftTemplate {
            $lockedTemplate = ShiftTemplate::query()
                ->where('company_id', $company->id)
                ->whereKey($template->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTemplate->fill([
                'code' => $code,
                'name' => $this->requiredString($data['name'] ?? $lockedTemplate->name, 'El nombre de la plantilla es requerido.'),
                'description' => blank($data['description'] ?? null) ? null : trim((string) $data['description']),
                'status' => in_array($data['status'] ?? $lockedTemplate->status, ['active', 'inactive'], true)
                    ? ($data['status'] ?? $lockedTemplate->status)
                    : $lockedTemplate->status,
                'metadata' => $data['metadata'] ?? $lockedTemplate->metadata ?? [],
            ]);
            $lockedTemplate->save();

            $segmentIds = $lockedTemplate->segments()->lockForUpdate()->pluck('id');

            if ($segmentIds->isNotEmpty()) {
                DailyScheduleSegment::query()
                    ->where('company_id', $company->id)
                    ->whereIn('shift_template_segment_id', $segmentIds)
                    ->update(['shift_template_segment_id' => null]);
            }

            $lockedTemplate->segments()->delete();

            foreach ($normalized as $segment) {
                $model = $lockedTemplate->segments()->make($segment);
                $model->company()->associate($company);
                $model->save();
            }

            return $lockedTemplate->refresh()->load('segments');
        });
    }

    private function assertTenant(Company $company, ShiftTemplate $template): void
    {
        if ($company->status !== 'active' || $template->company_id !== $company->id) {
            throw new InvalidArgumentException('La plantilla no pertenece a la empresa activa.');
        }
    }

    private function normalizeCode(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '' || ! preg_match('/^[A-Z0-9][A-Z0-9_-]{1,49}$/', $code)) {
            throw new InvalidArgumentException('El codigo de la plantilla no es valido.');
        }

        return $code;
    }

    private function requiredString(?string $value, string $message): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}
