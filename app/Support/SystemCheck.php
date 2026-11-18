<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Checks the server for what the platform needs. Used by Admin → Settings and to turn
 * low-level failures (e.g. a missing PHP extension) into a message an admin can act on.
 */
class SystemCheck
{
    /** PHP extensions and what breaks without them. */
    public const EXTENSIONS = [
        'zip' => 'excel',      // .xlsx files and company pack .zip uploads
        'xml' => 'excel',
        'xmlreader' => 'excel',
        'simplexml' => 'excel',
        'gd' => 'pdf',         // images in PDFs and spreadsheets
        'mbstring' => 'core',
        'intl' => 'core',
        'fileinfo' => 'core',
        'openssl' => 'core',
    ];

    public function loaded(string $extension): bool
    {
        return extension_loaded($extension);
    }

    /** @return string[] missing extensions needed for a feature ('excel', 'pdf', 'core') */
    public function missingFor(string $feature): array
    {
        return array_values(array_filter(
            array_keys(array_filter(self::EXTENSIONS, fn ($f) => $f === $feature)),
            fn ($ext) => ! $this->loaded($ext),
        ));
    }

    /** Every check, for the Settings page. */
    public function report(): array
    {
        $db = DB::connection();
        $driver = $db->getDriverName();
        $pdo = 'pdo_'.($driver === 'mariadb' ? 'mysql' : $driver);

        $rows = [];
        foreach (array_merge(array_keys(self::EXTENSIONS), [$pdo]) as $ext) {
            $rows[] = ['key' => 'ext', 'label' => 'PHP: '.$ext, 'ok' => $this->loaded($ext),
                'detail' => $this->loaded($ext) ? '' : __('admin.system.needed_for.'.(self::EXTENSIONS[$ext] ?? 'core'))];
        }
        $rows[] = ['key' => 'php', 'label' => 'PHP '.PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '8.3.0', '>='), 'detail' => ''];
        $rows[] = ['key' => 'storage', 'label' => __('admin.system.storage'), 'ok' => is_writable(storage_path('app')), 'detail' => storage_path('app')];
        $rows[] = ['key' => 'db', 'label' => __('admin.system.database'), 'ok' => true, 'detail' => $driver === 'sqlite'
            ? 'SQLite · '.$db->getConfig('database')
            : strtoupper($driver).' · '.$db->getConfig('host').' / '.$db->getConfig('database')];
        $mail = app(MailStatus::class);
        $rows[] = ['key' => 'mail', 'label' => __('admin.system.mail'), 'ok' => $mail->canSend(), 'optional' => true,
            'detail' => $mail->configured()
                ? $mail->mailer().' · '.$mail->from().' · '.($mail->enabled() ? __('admin.mail.on') : __('admin.mail.off'))
                : $mail->mailer().($mail->missing() !== ['MAIL_MAILER'] ? ' · '.__('admin.mail.missing', ['keys' => implode(', ', $mail->missing())]) : '')];
        $rows[] = ['key' => 'sso', 'label' => __('admin.system.sso'), 'ok' => filled(config('services.microsoft.client_id')),
            'detail' => filled(config('services.microsoft.client_id')) ? 'Microsoft Entra ID' : '—', 'optional' => true];

        return $rows;
    }
}
