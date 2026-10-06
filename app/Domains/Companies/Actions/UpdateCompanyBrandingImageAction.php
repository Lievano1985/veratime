<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class UpdateCompanyBrandingImageAction
{
    public function handle(Company $company, UploadedFile $image): CompanySetting
    {
        $extension = mb_strtolower((string) $image->getClientOriginalExtension());

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('La imagen debe ser JPG, PNG o WebP.');
        }

        $disk = 'public';
        $directory = sprintf('companies/%d/branding', $company->id);
        $filename = sprintf('%s.%s', (string) Str::uuid(), $extension);
        $path = Storage::disk($disk)->putFileAs($directory, $image, $filename);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No fue posible guardar la imagen de empresa.');
        }

        $settings = $company->setting()->firstOrCreate([], Company::defaultSettings());
        $previousPath = $settings->branding_image_path;

        try {
            $settings->forceFill(['branding_image_path' => $path])->save();
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        if (filled($previousPath) && $previousPath !== $path) {
            Storage::disk($disk)->delete($previousPath);
        }

        return $settings;
    }
}
