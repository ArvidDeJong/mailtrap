<?php

namespace Darvis\Mailtrap\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Mailtrap account webhooks API.
 *
 * @see https://docs.mailtrap.io/developers/email-sending/webhooks
 */
class MailtrapWebhookApi
{
    /**
     * Events the package's webhook controller acts on.
     */
    public const EVENT_TYPES = ['delivery', 'open', 'click', 'bounce', 'spam_complaint', 'reject'];

    public function __construct(private string $token) {}

    /**
     * List every webhook in the account.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->check($this->request()->get('/api/webhooks'))->json('data', []);
    }

    /**
     * Find the webhook pointing at the given URL.
     *
     * @return array<string, mixed>|null
     */
    public function findByUrl(string $url): ?array
    {
        return collect($this->all())->first(
            fn (array $webhook): bool => rtrim((string) ($webhook['url'] ?? ''), '/') === rtrim($url, '/')
        );
    }

    /**
     * Create an email sending webhook. The response carries the signing
     * secret, which Mailtrap only ever returns on creation.
     *
     * @return array<string, mixed>
     */
    public function create(string $url, string $sendingStream = 'transactional', ?int $domainId = null): array
    {
        $webhook = array_filter([
            'url' => $url,
            'webhook_type' => 'email_sending',
            'active' => true,
            'payload_format' => 'json',
            'sending_stream' => $sendingStream,
            'event_types' => self::EVENT_TYPES,
            'domain_id' => $domainId,
        ], fn (mixed $value): bool => $value !== null);

        $data = $this->check($this->request()->post('/api/webhooks', ['webhook' => $webhook]))->json('data', []);

        if (empty($data['signing_secret'])) {
            throw new RuntimeException('Mailtrap created the webhook but returned no signing secret.');
        }

        return $data;
    }

    public function delete(int $id): void
    {
        $this->check($this->request()->delete("/api/webhooks/{$id}"));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('manta_mailtrap.api.account_url', 'https://mailtrap.io'))
            ->withHeaders(['Api-Token' => $this->token])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('manta_mailtrap.api.timeout', 30));
    }

    private function check(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $errors = $response->json('errors') ?? $response->json('error') ?? $response->body();

        if (is_array($errors)) {
            $errors = collect($errors)->flatten()->implode(' ');
        }

        $hint = match ($response->status()) {
            401 => ' Check MAILTRAP_API_TOKEN.',
            403 => ' The API token needs admin access to the account.',
            default => '',
        };

        throw new RuntimeException("Mailtrap API returned {$response->status()}: {$errors}.{$hint}");
    }
}
