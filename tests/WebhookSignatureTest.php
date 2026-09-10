<?php

use Darvis\Mailtrap\Http\Middleware\VerifyMailtrapWebhookSignature;
use Darvis\Mailtrap\Models\MailLog;

$payload = [
    'events' => [
        [
            'email' => 'signed@example.org',
            'event' => 'bounce',
            'message_id' => 'signature-test-1',
            'response' => 'Mailbox unavailable',
            'response_code' => 550,
        ],
    ],
];

it('accepts a webhook carrying a valid signature', function () use ($payload): void {
    $this->postSignedWebhook($payload)->assertOk();

    expect(MailLog::where('message_id', 'signature-test-1')->count())->toBe(1);
});

it('rejects a webhook without a signature header', function () use ($payload): void {
    $this->postJson('/api/webhooks/mailtrap', $payload)->assertForbidden();

    expect(MailLog::count())->toBe(0);
});

it('rejects a webhook signed with the wrong secret', function () use ($payload): void {
    $this->postSignedWebhook($payload, 'ffffffffffffffffffffffffffffffff')->assertForbidden();

    expect(MailLog::count())->toBe(0);
});

it('rejects every webhook while no signing secret is configured', function () use ($payload): void {
    config()->set('manta_mailtrap.webhook.secret', null);

    $this->postSignedWebhook($payload)->assertForbidden();

    expect(MailLog::count())->toBe(0);
});

it('accepts unsigned webhooks when verification is switched off', function () use ($payload): void {
    config()->set('manta_mailtrap.webhook.verify_signature', false);

    $this->postJson('/api/webhooks/mailtrap', $payload)->assertOk();

    expect(MailLog::where('message_id', 'signature-test-1')->count())->toBe(1);
});

it('signs the raw body rather than the re-encoded payload', function () use ($payload): void {
    // A signature over re-encoded JSON must not be accepted: Mailtrap signs the
    // bytes as sent, so any normalisation on our side would break real calls.
    $body = json_encode($payload, JSON_PRETTY_PRINT);

    $this->call(
        'POST',
        '/api/webhooks/mailtrap',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_MAILTRAP_SIGNATURE' => hash_hmac('sha256', json_encode($payload), '0123456789abcdef0123456789abcdef'),
        ],
        content: $body,
    )->assertForbidden();
});

it('exposes the header name Mailtrap uses', function (): void {
    expect(VerifyMailtrapWebhookSignature::HEADER)->toBe('Mailtrap-Signature');
});
