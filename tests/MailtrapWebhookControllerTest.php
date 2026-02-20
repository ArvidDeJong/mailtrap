<?php

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;

it('creates mail log when message id does not exist', function (): void {
    $payload = [
        'events' => [
            [
                'email' => 'new-log@example.org',
                'event' => 'bounce',
                'message_id' => 'webhook-create-1',
                'response' => 'Mailbox unavailable',
                'response_code' => 550,
                'category' => 'Webhook Test',
                'sending_domain_name' => 'example.org',
            ],
        ],
    ];

    $response = $this->postJson('/api/webhooks/mailtrap', $payload);

    $response->assertOk();

    expect(MailLog::where('message_id', 'webhook-create-1')->count())->toBe(1);

    $mailLog = MailLog::where('message_id', 'webhook-create-1')->first();
    expect($mailLog)->not->toBeNull();
    expect($mailLog->recipient)->toBe('new-log@example.org');
    expect($mailLog->subject)->toBe('Webhook Test');
    expect($mailLog->status_code)->toBe('550');
    expect($mailLog->error_message)->toBe('Mailbox unavailable');
    expect($mailLog->type)->toBe('webhook');

    $validation = EmailValidation::where('email', 'new-log@example.org')->first();
    expect($validation)->not->toBeNull();
    expect($validation->status)->toBe('invalid');
    expect($validation->status_code)->toBe('550');
});

it('updates existing mail log without creating duplicate', function (): void {
    MailLog::create([
        'message_id' => 'webhook-update-1',
        'sender' => 'sender@example.org',
        'recipient' => 'existing@example.org',
        'subject' => 'Original subject',
        'status_code' => '200',
        'type' => 'sent',
    ]);

    $payload = [
        'events' => [
            [
                'email' => 'existing@example.org',
                'event' => 'bounce',
                'message_id' => 'webhook-update-1',
                'response' => 'Hard bounce',
                'response_code' => 550,
            ],
        ],
    ];

    $response = $this->postJson('/api/webhooks/mailtrap', $payload);

    $response->assertOk();

    expect(MailLog::where('message_id', 'webhook-update-1')->count())->toBe(1);

    $mailLog = MailLog::where('message_id', 'webhook-update-1')->first();
    expect($mailLog)->not->toBeNull();
    expect($mailLog->status_code)->toBe('550');
    expect($mailLog->recipient)->toBe('existing@example.org');
    expect($mailLog->subject)->toBe('Original subject');
});
