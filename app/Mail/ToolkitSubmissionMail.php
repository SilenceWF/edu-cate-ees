<?php

namespace App\Mail;

use App\Models\ToolkitSubmission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Symfony\Component\Mime\MimeTypes;

/**
 * A filled-in toolkit form, sent to the company's security contact with the file attached
 * (GraphTransport passes attachments on to Microsoft Graph). Written in the company's default
 * language, since it goes to the security officer rather than to the person submitting.
 */
class ToolkitSubmissionMail extends Mailable
{
    private string $mailLocale;

    public function __construct(public ToolkitSubmission $submission)
    {
        $this->submission->loadMissing('item', 'user');
        $this->mailLocale = (string) setting('default_locale', 'el');
        $this->locale($this->mailLocale);
    }

    public function envelope(): Envelope
    {
        $user = $this->submission->user;

        return new Envelope(
            subject: $this->subjectLine(),
            replyTo: $user->email ? [new Address($user->email, $user->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.plain', with: ['plainText' => $this->plainText()]);
    }

    public function attachments(): array
    {
        $ext = strtolower(pathinfo($this->submission->file_path, PATHINFO_EXTENSION));

        return [Attachment::fromPath($this->submission->path())
            ->as($this->submission->original_filename)
            ->withMime(MimeTypes::getDefault()->getMimeTypes($ext)[0] ?? 'application/octet-stream')];
    }

    public function plainText(bool $short = false): string
    {
        return $this->withLocale($this->mailLocale, function () use ($short) {
            $s = $this->submission;
            $lines = [
                $short ? __('ui.toolkit.mail.intro_self') : __('ui.toolkit.mail.intro', ['name' => $s->user->name]),
                '',
                __('ui.toolkit.mail.item').': '.$s->item->code.' · '.loc($s->item->title),
                __('ui.toolkit.mail.reference').': '.($s->reference_note ?: '—'),
                __('ui.toolkit.mail.by').': '.$s->user->name.($s->user->email ? ' <'.$s->user->email.'>' : ''),
                __('ui.toolkit.mail.when').': '.local_dt($s->submitted_at),
                __('ui.toolkit.mail.file').': '.$s->original_filename,
                __('ui.toolkit.mail.ref_no').': #'.$s->id,
                '',
                $short ? __('ui.toolkit.mail.attach_note') : __('ui.toolkit.mail.attached'),
            ];
            if (! $short) {
                array_push($lines, '', __('ui.toolkit.mail.footer', ['company' => (string) setting('company.name', config('app.name')), 'url' => route('toolkit.index')]));
            }

            return implode("\n", $lines);
        });
    }

    /** A mailto: link for when the server does not send mail; the person attaches the file themselves. */
    public function mailtoHref(): string
    {
        return 'mailto:'.setting('company.contact_email')
            .'?subject='.rawurlencode($this->subjectLine())
            .'&body='.rawurlencode($this->plainText(short: true));
    }

    private function subjectLine(): string
    {
        return $this->withLocale($this->mailLocale, fn () => __('ui.toolkit.mail.subject', [
            'code' => $this->submission->item->code,
            'title' => loc($this->submission->item->title),
            'name' => $this->submission->user->name,
        ]));
    }
}
