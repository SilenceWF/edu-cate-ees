<?php

namespace App\Mail;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Microsoft Graph (POST /users/{from}/sendMail) with the app's own
 * Entra ID credentials (client-credentials flow). The Entra App Registration is the one
 * already used for sign-in; Exchange decides which mailbox it may send from.
 */
class GraphTransport extends AbstractTransport
{
    public function __construct(
        private string $tenant,
        private string $clientId,
        private string $clientSecret,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'graph';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom()[0] ?? null;
        if (! $from) {
            throw new TransportException(__('admin.mail.errors.no_from'));
        }

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(30)
            ->post('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from->getAddress()).'/sendMail', [
                'message' => $this->payload($email),
                'saveToSentItems' => true,
            ]);

        if (! $response->successful()) {
            throw new TransportException($this->explain($response, $from->getAddress()));
        }
    }

    private function token(): string
    {
        $key = 'graph-mail-token:'.sha1($this->tenant.'|'.$this->clientId);

        return Cache::remember($key, now()->addMinutes(50), function () {
            $response = Http::asForm()->timeout(30)->post(
                'https://login.microsoftonline.com/'.rawurlencode($this->tenant).'/oauth2/v2.0/token',
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                ],
            );

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new TransportException($this->explain($response, null));
            }

            return (string) $response->json('access_token');
        });
    }

    private function payload(Email $email): array
    {
        $recipients = fn (array $addresses) => array_map(fn (Address $a) => ['emailAddress' => array_filter([
            'address' => $a->getAddress(), 'name' => $a->getName() ?: null,
        ])], $addresses);

        $message = [
            'subject' => (string) $email->getSubject(),
            'body' => $email->getHtmlBody() !== null
                ? ['contentType' => 'HTML', 'content' => (string) $email->getHtmlBody()]
                : ['contentType' => 'Text', 'content' => (string) $email->getTextBody()],
            'toRecipients' => $recipients($email->getTo()),
        ];
        if ($email->getCc()) {
            $message['ccRecipients'] = $recipients($email->getCc());
        }
        if ($email->getBcc()) {
            $message['bccRecipients'] = $recipients($email->getBcc());
        }
        if ($email->getReplyTo()) {
            $message['replyTo'] = $recipients($email->getReplyTo());
        }
        foreach ($email->getAttachments() as $part) {
            $message['attachments'][] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $part->getFilename() ?? 'attachment',
                'contentType' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'contentBytes' => base64_encode($part->getBody()),
            ];
        }

        return $message;
    }

    /** Turn a Graph / Entra error into a message an admin can act on. */
    private function explain(Response $response, ?string $from): string
    {
        $code = (string) ($response->json('error.code') ?? $response->json('error') ?? '');
        $detail = (string) ($response->json('error.message') ?? $response->json('error_description') ?? '');

        $key = match (true) {
            in_array($code, ['invalid_client', 'unauthorized_client'], true) || str_contains($detail, 'AADSTS7000215') => 'bad_secret',
            str_contains($detail, 'AADSTS7000222') => 'expired_secret',
            in_array($code, ['invalid_request', 'invalid_tenant'], true) || str_contains($detail, 'AADSTS90002') => 'bad_tenant',
            $response->status() === 403 || $code === 'ErrorAccessDenied' => 'access_denied',
            $response->status() === 404 || in_array($code, ['ErrorInvalidUser', 'MailboxNotEnabledForRESTAPI', 'ResourceNotFound'], true) => 'mailbox_not_found',
            default => 'generic',
        };

        return __('admin.mail.errors.'.$key, ['from' => $from ?? '—', 'status' => $response->status(), 'detail' => mb_substr($detail, 0, 200)]);
    }
}
