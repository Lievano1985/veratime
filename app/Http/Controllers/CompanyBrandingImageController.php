<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyBrandingImageController extends Controller
{
    public function __invoke(Company $company): StreamedResponse
    {
        abort_unless($company->status === 'active', 404);

        $path = $company->setting?->branding_image_path;

        abort_if(blank($path) || ! Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=3600, stale-while-revalidate=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
