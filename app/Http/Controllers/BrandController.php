<?php

namespace App\Http\Controllers;

class BrandController extends Controller
{
    public function logo()
    {
        $path = self::logoPath();
        abort_unless($path, 404);

        return response()->file($path, [
            'Content-Type' => str_ends_with($path, '.svg') ? 'image/svg+xml' : mime_content_type($path),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public static function logoPath(): ?string
    {
        $logo = setting('branding.logo');
        $path = $logo ? storage_path('app/pack/branding/'.$logo) : null;

        return $path && is_file($path) ? $path : null;
    }

    /** Logo embedded as a data URI, for PDFs (which cannot fetch authenticated URLs). */
    public static function logoDataUri(): ?string
    {
        $path = self::logoPath();
        if (! $path) {
            return null;
        }
        $mime = str_ends_with($path, '.svg') ? 'image/svg+xml' : mime_content_type($path);

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }
}
