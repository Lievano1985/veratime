<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

class ResolveCompanyBrandingImageUrlAction
{
    public function handle(Company $company): ?string
    {
        $company->loadMissing('setting');
        $path = $company->setting?->branding_image_path;

        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return route('company-branding.show', $company);
    }
}
