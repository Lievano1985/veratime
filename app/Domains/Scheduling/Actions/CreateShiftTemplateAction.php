<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ShiftTemplate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateShiftTemplateAction
{
    public function __construct(private ValidateShiftTemplateSegmentsAction $validateSegments)
    {
    }

    public function handle(Company $company, array $data, array $segments): ShiftTemplate
    {
        $this->assertCompany($company);
        $normalized = $this->validateSegments->handle($segments);
        $code = $this->normalizeCode($data['code'] ?? null);

        if (ShiftTemplate::query()->where('company_id', $company->id)->where('code', $code)->exists()) {
            throw new InvalidArgumentException('Ya existe una plantilla con el mismo codigo en esta empresa.');
        }

        return DB::transaction(function () use ($company, $data, $normalized, $code): ShiftTemplate {
            $template = new ShiftTemplate([
                'code' => $code,
                'name' => $this->requiredString($data['name'] ?? null, 'El nombre de la plantilla es requerido.'),
                'description' => blank($data['description'] ?? null) ? null : trim((string) $data['description']),
                'status' => in_array($data['status'] ?? 'active', ['active', 'inactive'], true) ? ($data['status'] ?? 'active') : 'active',
                'metadata' => $data['metadata'] ?? [],
            ]);
            $template->company()->associate($company);
            $template->save();

            $this->persistSegments($company, $template, $normalized);

            return $template->refresh()->load('segments');
        });
    }

    private function persistSegments(Company $company, ShiftTemplate $template, array $segments): void
    {
        foreach ($segments as $segment) {
            $model = $template->segments()->make($segment);
            $model->company()->associate($company);
            $model->save();
        }
    }

    private function assertCompany(Company $company): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('La plantilla requiere una empresa activa.');
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
