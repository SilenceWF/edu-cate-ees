<?php

namespace App\Support;

use InvalidArgumentException;

/** The browser-tab icon. Falls back to the logo when none is uploaded. */
class Favicon extends Logo
{
    public const MAX_KB = 512;

    public const TYPES = ['ico', 'png', 'svg'];

    protected const KEY = 'favicon';

    protected function assertValid(string $ext, string $contents, string $path): void
    {
        // ICO files start with reserved 0, type 1 (icon) and a non-zero image count.
        if ($ext === 'ico') {
            if (strlen($contents) < 22 || substr($contents, 0, 4) !== "\x00\x00\x01\x00" || unpack('v', substr($contents, 4, 2))[1] < 1) {
                throw new InvalidArgumentException(__('admin.logo.bad_image'));
            }

            return;
        }
        parent::assertValid($ext, $contents, $path);
    }

    /** The icon to put in <link rel="icon">: the favicon, else the logo. */
    public function urlOrLogo(): ?string
    {
        return $this->url() ?? app(Logo::class)->url();
    }
}
