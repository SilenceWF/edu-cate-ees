<?php

use App\Support\Settings;
use Illuminate\Support\Carbon;

if (! function_exists('loc')) {
    /**
     * Resolve a translatable value. Accepts a plain string or a map of locale => text
     * (e.g. ['el' => '...', 'en' => '...']) and returns the text for the current locale.
     */
    function loc(mixed $value, ?string $locale = null): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '';
            }
            $locale ??= app()->getLocale();

            return (string) ($value[$locale]
                ?? $value[setting('default_locale', 'el')]
                ?? reset($value));
        }

        return (string) ($value ?? '');
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (! function_exists('ui_locales')) {
    /**
     * Languages offered by the language switch: the pack's own languages first, then every
     * language the interface is translated into (lang/*). Pack texts that are not translated
     * fall back to the pack's default language (see loc()).
     */
    function ui_locales(): array
    {
        static $interface = null;
        $interface ??= array_map('basename', glob(lang_path('*'), GLOB_ONLYDIR) ?: []);

        return array_values(array_unique(array_merge((array) setting('locales', ['el', 'en']), $interface)));
    }
}

if (! function_exists('local_dt')) {
    /** Format a UTC timestamp in the company's timezone. */
    function local_dt(mixed $value, string $format = 'd/m/Y H:i'): string
    {
        if (! $value) {
            return '—';
        }

        return Carbon::parse($value)->timezone(setting('timezone', 'Europe/Athens'))->format($format);
    }
}

if (! function_exists('icon_svg')) {
    /** Render a bundled Lucide icon by name, falling back to a neutral one if unknown. */
    function icon_svg(?string $name, string $class = 'size-6'): string
    {
        foreach ([$name, 'circle-check'] as $candidate) {
            if (! $candidate) {
                continue;
            }
            try {
                return svg('lucide-'.$candidate, $class)->toHtml();
            } catch (Throwable) {
                // try the fallback
            }
        }

        return '';
    }
}

if (! function_exists('upper')) {
    /** Uppercase that follows Greek typography (capitals carry no accent marks). For PDFs, where CSS text-transform gets this wrong. */
    function upper(?string $text): string
    {
        $text = mb_strtoupper((string) $text);

        return strtr($text, ['Ά' => 'Α', 'Έ' => 'Ε', 'Ή' => 'Η', 'Ί' => 'Ι', 'Ό' => 'Ο', 'Ύ' => 'Υ', 'Ώ' => 'Ω', 'ΐ' => 'Ϊ', 'ΰ' => 'Ϋ']);
    }
}
