<?php

namespace App\Support;

/**
 * Whether the platform can send email itself, and whether an admin has allowed it to.
 * Until both are true, reminders fall back to mailto: links in the admin's own mail client.
 */
class MailStatus
{
    public function __construct(private Settings $settings) {}

    public function mailer(): string
    {
        return (string) config('mail.default');
    }

    public function from(): ?string
    {
        return config('mail.from.address') ?: null;
    }

    /** @return string[] what is missing for the configured mailer to work (empty = ready) */
    public function missing(): array
    {
        $missing = [];
        if (in_array($this->mailer(), ['log', 'array'], true)) {
            return ['MAIL_MAILER'];
        }
        if (! $this->from()) {
            $missing[] = 'MAIL_FROM_ADDRESS';
        }
        if ($this->mailer() === 'graph') {
            foreach (['client_id' => 'MICROSOFT_CLIENT_ID', 'client_secret' => 'MICROSOFT_CLIENT_SECRET'] as $key => $env) {
                if (blank(config("services.microsoft.$key"))) {
                    $missing[] = $env;
                }
            }
            // App-only tokens need the real tenant, not the multi-tenant "common" endpoint used for sign-in.
            if (in_array((string) config('services.microsoft.tenant'), ['', 'common', 'organizations', 'consumers'], true)) {
                $missing[] = 'MICROSOFT_TENANT_ID';
            }
        }

        return $missing;
    }

    public function configured(): bool
    {
        return $this->missing() === [];
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('mail.sending_enabled', false);
    }

    /** The server sends email (otherwise: mailto links). */
    public function canSend(): bool
    {
        return $this->configured() && $this->enabled();
    }
}
