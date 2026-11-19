<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\User;
use App\Services\Progress;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The training reminder: a branded HTML email with a plain-text part, personalised with the
 * person's own track (their modules, what is already done) and the company details from the pack.
 * The same content, shortened, feeds the mailto: fallback used while server sending is off.
 */
class TrainingReminderMail extends Mailable
{
    public Campaign $campaign;

    public array $progress;

    /** @var array<int, array{label: string, done: bool}> */
    public array $steps = [];

    public int $minutes;

    public string $portalUrl;

    public string $companyName;

    public string $legalName;

    public string $frameworks;

    public ?string $signatoryName;

    public string $signatoryTitle;

    public ?string $contactEmail;

    public bool $sso;

    public array $colors;

    public string $recipientLocale;

    /** @param array|null $progress pass an already computed Progress::of() result to avoid recomputing it */
    public function __construct(public User $user, ?Campaign $campaign = null, ?array $progress = null)
    {
        $this->campaign = $campaign ?? Campaign::current();
        $this->progress = $progress ?? Progress::forUser($user, $this->campaign);

        $locales = (array) setting('locales', ['el', 'en']);
        $this->recipientLocale = in_array($user->locale, $locales, true) ? $user->locale : (string) setting('default_locale', 'el');
        $this->locale($this->recipientLocale);

        $this->portalUrl = url('/');
        $this->companyName = (string) setting('company.name', config('app.name'));
        $this->legalName = (string) (setting('company.legal_name') ?: $this->companyName);
        $this->signatoryName = setting('company.signatory.name') ?: null;
        $this->contactEmail = setting('company.contact_email') ?: null;
        $this->sso = filled(config('services.microsoft.client_id'));
        $this->colors = [
            'header' => setting('branding.colors.header', '#0F172A'),
            'primary' => setting('branding.colors.primary', '#2563EB'),
            'on_primary' => setting('branding.colors.on_primary', '#FFFFFF'),
        ];

        // Translatable pack values and the step labels depend on the recipient's language.
        $this->withLocale($this->recipientLocale, function () {
            $this->frameworks = loc(setting('company.frameworks'));
            $this->signatoryTitle = loc(setting('company.signatory.title'));
            $this->steps = $this->buildSteps();
        });
        $this->minutes = (int) $this->progress['modules']->sum('estimated_minutes') + 5;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->withLocale($this->recipientLocale, fn () => __('admin.reminder.subject', [
            'company' => $this->companyName,
            'campaign' => $this->campaign->name,
        ])));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.training-reminder',
            text: 'emails.plain',
            with: ['plainText' => $this->plainText()],
        );
    }

    /** The plain-text part of the email (built here, not in Blade, so paragraph spacing is exact). */
    public function plainText(): string
    {
        return $this->withLocale($this->recipientLocale, function () {
            $steps = collect($this->steps)->values()->map(fn ($s, $i) => ($i + 1).'. '.$s['label']
                .($s['done'] ? ' ['.__('admin.reminder.step_done').']' : ''))->implode("\n");

            return $this->paragraphs([
                __('admin.reminder.greeting', ['name' => $this->user->name]),
                __('admin.reminder.intro_text', ['company' => $this->legalName, 'campaign' => $this->campaign->name]),
                $this->campaign->ends_on ? __('admin.reminder.deadline_label').': '.$this->campaign->ends_on->format('d/m/Y') : null,
                __('admin.reminder.steps_heading', ['minutes' => $this->minutes])."\n".$steps,
                __('admin.reminder.cta').': '.$this->portalUrl,
                __('admin.reminder.access_heading')."\n"
                    .($this->sso ? '- '.__('admin.reminder.access_sso')."\n" : '')
                    .'- '.__('admin.reminder.access_network'),
                $this->contactEmail ? __('admin.reminder.closing_note', ['contact' => $this->contactEmail]) : __('admin.reminder.closing_note_plain'),
                $this->signature(),
            ]);
        });
    }

    /**
     * A mailto: link with a shortened message: Windows/Outlook cut mailto links at about
     * 2,000 characters, and Greek text grows about six times when URL-encoded.
     */
    public function mailtoHref(): string
    {
        return $this->withLocale($this->recipientLocale, function () {
            $body = $this->paragraphs([
                __('admin.reminder.greeting', ['name' => $this->user->name]),
                __('admin.reminder.short_intro', ['campaign' => $this->campaign->name, 'minutes' => $this->minutes])
                    .($this->campaign->ends_on ? "\n".__('admin.reminder.deadline_label').': '.$this->campaign->ends_on->format('d/m/Y') : ''),
                $this->portalUrl,
                __('admin.reminder.signoff')."\n".($this->signatoryName ?: $this->legalName),
            ]);

            return 'mailto:'.$this->user->email
                .'?subject='.rawurlencode((string) $this->envelope()->subject)
                .'&body='.rawurlencode($body);
        });
    }

    private function signature(): string
    {
        return implode("\n", array_filter([
            __('admin.reminder.signoff'), $this->signatoryName, $this->signatoryTitle ?: null, $this->legalName,
        ]));
    }

    private function paragraphs(array $parts): string
    {
        return implode("\n\n", array_filter($parts, fn ($p) => filled($p)));
    }

    private function buildSteps(): array
    {
        $p = $this->progress;
        $steps = [];

        if ($p['required_policies']->isNotEmpty()) {
            $steps[] = ['label' => __('admin.reminder.step_policies'), 'done' => (bool) $p['policies_done']];
        }
        foreach ($p['modules'] as $module) {
            $steps[] = [
                'label' => __('admin.reminder.step_module', ['title' => loc($module->title)]),
                'done' => in_array($module->id, $p['completed_module_ids'], true),
            ];
        }
        $steps[] = ['label' => __('admin.reminder.step_quiz'), 'done' => (bool) $p['quiz_passed']];

        return $steps;
    }
}
