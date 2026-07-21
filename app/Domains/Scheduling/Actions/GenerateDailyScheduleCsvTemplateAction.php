<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Support\DailyScheduleCsvSchema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateDailyScheduleCsvTemplateAction
{
    public function handle(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'wb');
            fputcsv($output, DailyScheduleCsvSchema::headers());
            fclose($output);
        }, 'vera-time-programacion-diaria-v1.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
