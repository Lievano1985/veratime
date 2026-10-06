<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

class RemoveCompanyBrandingImageAction
{
    public function handle(Company $company): void
    {
        $settings = $company->setting;
        $path = $settings?->branding_image_path;

        if (blank($path)) {
            return;
        }

        $settings->forceFill(['branding_image_path' => null])->save();
        Storage::disk('public')->delete($path);
    }
}
