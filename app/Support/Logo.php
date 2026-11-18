<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** The company logo shown in the header and PDFs (and as the favicon when none is set). */
class Logo
{
    public const MAX_KB = 2048;

    public const TYPES = ['svg', 'png', 'jpg', 'jpeg', 'webp'];

    /** Key under settings.branding, file name prefix and route name. */
    protected const KEY = 'logo';

    public function __construct(private Settings $settings) {}

    public static function dir(): string
    {
        return storage_path('app/pack/branding');
    }

    public function path(): ?string
    {
        $logo = $this->settings->get('branding.'.static::KEY);
        $path = $logo ? self::dir().'/'.basename($logo) : null;

        return $path && is_file($path) ? $path : null;
    }

    public function url(): ?string
    {
        $path = $this->path();

        return $path ? route('brand.'.static::KEY, ['v' => filemtime($path)]) : null;
    }

    /** Store an admin-uploaded logo. Throws InvalidArgumentException with a user-facing message. */
    public function store(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, static::TYPES, true)) {
            throw new InvalidArgumentException(__('admin.'.static::KEY.'.bad_type'));
        }
        $this->assertValid($ext, (string) file_get_contents($file->getRealPath()), $file->getRealPath());

        File::ensureDirectoryExists(self::dir());
        $name = static::KEY.'-'.Str::lower(Str::random(8)).'.'.($ext === 'jpeg' ? 'jpg' : $ext);
        $file->move(self::dir(), $name);

        $this->deleteCurrent();
        $this->settings->set('branding', array_merge((array) $this->settings->get('branding', []), [static::KEY => $name, static::KEY.'_source' => 'admin']));
    }

    public function remove(): void
    {
        $this->deleteCurrent();
        $this->settings->set('branding', array_merge((array) $this->settings->get('branding', []), [static::KEY => null, static::KEY.'_source' => 'admin']));
    }

    protected function assertValid(string $ext, string $contents, string $path): void
    {
        if ($ext === 'svg') {
            self::assertSafeSvg($contents);
        } elseif (! @getimagesize($path)) {
            throw new InvalidArgumentException(__('admin.logo.bad_image'));
        }
    }

    /**
     * SVG can carry scripts. Accept only plain drawings: no scripts, event handlers,
     * embedded HTML or references to anything outside the file.
     */
    public static function assertSafeSvg(string $svg): void
    {
        $forbidden = [
            '/<\s*script/i', '/<\s*foreignObject/i', '/<\s*iframe/i', '/<\s*embed/i', '/<\s*object/i',
            '/\son[a-z]+\s*=/i', '/javascript\s*:/i', '/<!ENTITY/i',
            '/(?:xlink:)?href\s*=\s*["\'](?!#|data:image\/(?:png|jpe?g|gif|webp);)/i',
            '/url\(\s*["\']?(?!#)/i', '/@import/i',
        ];
        if (! preg_match('/<svg[\s>]/i', $svg)) {
            throw new InvalidArgumentException(__('admin.logo.bad_image'));
        }
        foreach ($forbidden as $pattern) {
            if (preg_match($pattern, $svg)) {
                throw new InvalidArgumentException(__('admin.logo.unsafe_svg'));
            }
        }
    }

    private function deleteCurrent(): void
    {
        if ($path = $this->path()) {
            File::delete($path);
        }
    }
}
