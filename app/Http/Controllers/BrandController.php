<?php

namespace App\Http\Controllers;

use App\Support\Logo;

class BrandController extends Controller
{
    public function logo(Logo $logo)
    {
        $path = $logo->path();
        abort_unless($path, 404);
        $svg = str_ends_with($path, '.svg');

        return response()->file($path, [
            'Content-Type' => $svg ? 'image/svg+xml' : mime_content_type($path),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            // Even if an SVG slipped through, nothing in it can run or load.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Logo embedded as a data URI, for PDFs (which cannot fetch authenticated URLs). */
    public static function logoDataUri(): ?string
    {
        $path = app(Logo::class)->path();
        if (! $path) {
            return null;
        }
        $mime = str_ends_with($path, '.svg') ? 'image/svg+xml' : mime_content_type($path);

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }
}
