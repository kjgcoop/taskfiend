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
 * `MAIL_MAILER=mailgun` instead. `from_domain` is this class's own addition,
 * with no Laravel-native equivalent: the domain Mailgun lets an account send
 * *as* (a verified custom domain, or a free/trial account's
 * sandboxXXXX.mailgun.org, which is further restricted to a manually
 * authorized recipient list) isn't necessarily MAILGUN_BASE, the domain used
 * to authenticate and build the API URL — so it's forced separately here
 * rather than trusted to whatever MAIL_FROM_ADDRESS happens to contain.
 */
class MailgunClient
{
    public function __construct(
        private readonly ?string $domain = null,
        private readonly ?string $secret = null,
        private readonly ?string $endpoint = null,
        private readonly ?string $fromDomain = null,
    ) {
    }

    /**
     * Whether MAILGUN_API_KEY, MAILGUN_BASE, and MAILGUN_FROM_DOMAIN are all
     * set. Check this before attempting to send, rather than relying on
     * send() to throw — a caller looping over many recipients (the digest
     * command, and later a scheduled job) wants one clear "not configured"
     * message, not one per recipient.
     */
    public function isConfigured(): bool
    {
        [$domain, $secret, , $fromDomain] = $this->credentials();

        return !empty($domain) && !empty($secret) && !empty($fromDomain);
    }

    /**
     * The "from" header to use when nothing more specific is supplied — the
     * app's configured display name (MAIL_FROM_NAME), with the address's
     * domain forced to MAILGUN_FROM_DOMAIN so it's always a domain Mailgun
     * will actually let this account send from (a verified domain, or a
     * sandbox domain's authorized-recipients-only domain — MAILGUN_BASE, the
     * domain used to authenticate and build the API URL, isn't necessarily
     * the same one you're allowed to send mail *as*).
     */
    public function defaultFrom(): string
    {
        [, , , $fromDomain] = $this->credentials();

        $name = config('mail.from.name', config('app.name'));
        $localPart = strstr(config('mail.from.address', 'noreply@example.com'), '@', true) ?: 'noreply';

        return "{$name} <{$localPart}@{$fromDomain}>";
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
                'Mailgun is not configured — set MAILGUN_API_KEY, MAILGUN_BASE, and MAILGUN_FROM_DOMAIN in .env.'
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

    /** @return array{0: ?string, 1: ?string, 2: string, 3: ?string} [domain, secret, endpoint, fromDomain] */
    private function credentials(): array
    {
        return [
            $this->domain ?? config('services.mailgun.domain'),
            $this->secret ?? config('services.mailgun.secret'),
            $this->endpoint ?? config('services.mailgun.endpoint', 'api.mailgun.net'),
            $this->fromDomain ?? config('services.mailgun.from_domain'),
        ];
    }
}
