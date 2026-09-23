<?php

namespace App\Services\Mailgun;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around Mailgun's HTTP API (https://documentation.mailgun.com/en/latest/api-sending.html).
 *
 * Talks to Mailgun directly over HTTP rather than via the `mailgun/mailgun-php`
 * SDK or Laravel's Symfony-based "mailgun" mail transport, since both require a
 * composer package this environment cannot install (packagist is not reachable
 * here). Only Guzzle/Laravel's own Http client — already a framework dependency
 * — is needed.
 *
 * Config keys `domain`/`secret`/`endpoint` (config/services.php,
 * `services.mailgun.*`) match the shape Laravel's own built-in mailgun mail
 * transport expects, so nothing here needs to change if
 * `symfony/mailgun-mailer` ever becomes installable and the app switches to
 * `MAIL_MAILER=mailgun` instead. `domain` doubles as both the domain used to
 * authenticate/build the API URL and the domain the "from" address is built
 * with (defaultFrom()) — in every real setup this account has (a free
 * account's sandboxXXXX.mailgun.org, or a verified custom domain) those are
 * the same domain, so there's no second env var to keep in sync with it.
 */
class MailgunClient
{
    public function __construct(
        private readonly ?string $domain = null,
        private readonly ?string $secret = null,
        private readonly ?string $endpoint = null,
    ) {
    }

    /**
     * Human-readable problems with the current Mailgun configuration, empty
     * if it's usable. Includes the domain-looks-like-a-URL mistake that
     * otherwise fails opaquely (Mailgun's API 404s on a mangled path)
     * instead of with a message that says what's actually wrong.
     */
    public function configurationErrors(): array
    {
        [$domain, $secret] = $this->credentials();
        $errors = [];

        if (empty($domain)) {
            $errors[] = 'MAILGUN_BASE is not set.';
        } elseif (str_contains($domain, '://') || str_contains($domain, '/')) {
            $errors[] = "MAILGUN_BASE should be just the domain name Mailgun gave you (e.g. mg.example.com), not a URL — got \"{$domain}\".";
        }

        if (empty($secret)) {
            $errors[] = 'MAILGUN_API_KEY is not set.';
        }

        return $errors;
    }

    /**
     * Whether Mailgun is configured well enough to attempt a send. Check
     * this before attempting to send, rather than relying on send() to
     * throw — a caller looping over many recipients (the digest command,
     * and later a scheduled job) wants one clear message, not one per
     * recipient.
     */
    public function isConfigured(): bool
    {
        return empty($this->configurationErrors());
    }

    /**
     * The "from" header to use when nothing more specific is supplied — the
     * app's configured display name (MAIL_FROM_NAME) and address local part
     * (MAIL_FROM_ADDRESS), with the domain forced to MAILGUN_BASE so it's
     * always a domain this Mailgun account is actually allowed to send as.
     */
    public function defaultFrom(): string
    {
        [$domain] = $this->credentials();

        $name = config('mail.from.name', config('app.name'));
        $localPart = strstr(config('mail.from.address', 'noreply@example.com'), '@', true) ?: 'noreply';

        return "{$name} <{$localPart}@{$domain}>";
    }

    /**
     * Send a single email through Mailgun.
     *
     * `inline` files are embedded in the message and can be shown in the HTML
     * body via `<img src="cid:FILENAME">` — Mailgun uses each file's filename
     * as its Content-ID.
     *
     * @param array{from: string, to: string, subject: string, html?: string, text?: string, inline?: list<array{filename: string, contents: string}>} $message
     * @return array The decoded JSON response body from Mailgun.
     *
     * @throws RuntimeException if Mailgun isn't configured, or the API call fails.
     */
    public function send(array $message): array
    {
        $errors = $this->configurationErrors();

        if (!empty($errors)) {
            throw new RuntimeException('Mailgun is not configured: ' . implode(' ', $errors));
        }

        [$domain, $secret, $endpoint] = $this->credentials();

        $request = Http::asMultipart()->withBasicAuth('api', $secret);

        foreach ($message['inline'] ?? [] as $file) {
            $request->attach('inline', $file['contents'], $file['filename']);
        }

        $response = $request->post("https://{$endpoint}/v3/{$domain}/messages", array_filter([
                'from' => $message['from'],
                'to' => $message['to'],
                'subject' => $message['subject'],
                'html' => $message['html'] ?? null,
                'text' => $message['text'] ?? null,
            ], fn ($value) => $value !== null));

        if ($response->failed()) {
            throw new RuntimeException(
                "Mailgun API request failed ({$response->status()}): {$response->body()}"
            );
        }

        return $response->json();
    }

    /** @return array{0: ?string, 1: ?string, 2: string} [domain, secret, endpoint] */
    private function credentials(): array
    {
        return [
            $this->domain ?? config('services.mailgun.domain'),
            $this->secret ?? config('services.mailgun.secret'),
            $this->endpoint ?? config('services.mailgun.endpoint', 'api.mailgun.net'),
        ];
    }
}
