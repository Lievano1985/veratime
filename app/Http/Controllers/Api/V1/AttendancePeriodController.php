<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Attendance\Actions\ExportAttendancePeriodPayrollCsvAction;
use App\Domains\Attendance\Actions\ExportAttendancePeriodPayrollXlsxAction;
use App\Domains\Attendance\Actions\ListAttendancePeriodsAction;
use App\Domains\Attendance\Actions\ResolvePayrollExportTemplateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListAttendancePeriodsRequest;
use App\Http\Resources\Api\V1\AttendancePeriodResource;
use App\Models\AttendancePeriod;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendancePeriodController extends Controller
{
    public function index(ListAttendancePeriodsRequest $request, ListAttendancePeriodsAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        Gate::authorize('viewAny', [AttendancePeriod::class, $company]);
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 25);
        unset($filters['per_page']);
        $periods = $action->handle($company, $filters, $perPage);

        return response()->json(['data' => AttendancePeriodResource::collection($periods->getCollection())->resolve(), 'meta' => ['current_page' => $periods->currentPage(), 'per_page' => $periods->perPage(), 'total' => $periods->total(), 'trace_id' => $request->attributes->get('api.trace_id')]]);
    }

    public function show(Request $request, int $periodId): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $period = AttendancePeriod::query()->with(['center', 'scopes.organizationalUnit'])->where('company_id', $company->id)->findOrFail($periodId);
        Gate::authorize('view', $period);

        return response()->json(['data' => (new AttendancePeriodResource($period))->resolve(), 'meta' => ['trace_id' => $request->attributes->get('api.trace_id')]]);
    }

    public function exportPayrollCsv(Request $request, int $periodId, ExportAttendancePeriodPayrollCsvAction $action, ResolvePayrollExportTemplateAction $resolveTemplate): StreamedResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $period = AttendancePeriod::query()->where('company_id', $company->id)->findOrFail($periodId);
        Gate::authorize('exportPayrollCsv', $period);

        return $action->handle($period, $resolveTemplate->handle($company, $request->integer('template_id') ?: null));
    }

    public function exportPayrollXlsx(Request $request, int $periodId, ExportAttendancePeriodPayrollXlsxAction $action, ResolvePayrollExportTemplateAction $resolveTemplate): StreamedResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $period = AttendancePeriod::query()->where('company_id', $company->id)->findOrFail($periodId);
        Gate::authorize('exportPayrollXlsx', $period);

        return $action->handle($period, $resolveTemplate->handle($company, $request->integer('template_id') ?: null));
    }
}
