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
 * Config keys (config/services.php, `services.mailgun.*`) match the shape
 * Laravel's own built-in mailgun mail transport expects, so nothing here needs
 * to change if `symfony/mailgun-mailer` ever becomes installable and the app
 * switches to `MAIL_MAILER=mailgun` instead.
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
     * Whether both MAILGUN_API_KEY and MAILGUN_BASE are set. Check this before
     * attempting to send, rather than relying on send() to throw — a caller
     * looping over many recipients (the digest command, and later a scheduled
     * job) wants one clear "not configured" message, not one per recipient.
     */
    public function isConfigured(): bool
    {
        [$domain, $secret] = $this->credentials();

        return !empty($domain) && !empty($secret);
    }

    /**
     * Send a single email through Mailgun.
     *
     * @param array{from: string, to: string, subject: string, html?: string, text?: string} $message
     * @return array The decoded JSON response body from Mailgun.
     *
     * @throws RuntimeException if Mailgun credentials aren't configured, or the API call fails.
     */
    public function send(array $message): array
    {
        [$domain, $secret, $endpoint] = $this->credentials();

        if (empty($domain) || empty($secret)) {
            throw new RuntimeException(
                'Mailgun is not configured — set MAILGUN_API_KEY and MAILGUN_BASE in .env.'
            );
        }

        $response = Http::asMultipart()
            ->withBasicAuth('api', $secret)
            ->post("https://{$endpoint}/v3/{$domain}/messages", array_filter([
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
