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

    $response = $this->postSignedWebhook($payload);

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

    $response = $this->postSignedWebhook($payload);

    $response->assertOk();

    expect(MailLog::where('message_id', 'webhook-update-1')->count())->toBe(1);

    $mailLog = MailLog::where('message_id', 'webhook-update-1')->first();
    expect($mailLog)->not->toBeNull();
    expect($mailLog->status_code)->toBe('550');
    expect($mailLog->recipient)->toBe('existing@example.org');
    expect($mailLog->subject)->toBe('Original subject');
});

it('handles events for different messages to the same address in one batch', function (): void {
    foreach (['message-a', 'message-b'] as $messageId) {
        MailLog::create([
            'message_id' => $messageId,
            'sender' => 'sender@example.org',
            'recipient' => 'same@example.org',
            'subject' => 'Subject',
        ]);
    }

    $this->postSignedWebhook(['events' => [
        ['event_id' => 'e1', 'email' => 'same@example.org', 'event' => 'bounce', 'message_id' => 'message-a', 'response' => 'Mailbox full', 'response_code' => 552],
        ['event_id' => 'e2', 'email' => 'same@example.org', 'event' => 'delivery', 'message_id' => 'message-b'],
    ]])->assertOk()->assertJsonPath('stats.skipped', 0);

    expect(MailLog::where('message_id', 'message-a')->value('status_code'))->toBe('552')
        ->and(MailLog::where('message_id', 'message-b')->value('status_code'))->toBe('200');
});

it('skips an event it has already handled in the same batch', function (): void {
    $event = ['event_id' => 'e1', 'email' => 'twice@example.org', 'event' => 'delivery', 'message_id' => 'message-twice'];

    $this->postSignedWebhook(['events' => [$event, $event]])
        ->assertOk()
        ->assertJsonPath('stats.skipped', 1)
        ->assertJsonPath('stats.valid_emails', 1);
});

it('stores the bounce reason on an existing mail log', function (): void {
    MailLog::create([
        'message_id' => 'message-reason',
        'sender' => 'sender@example.org',
        'recipient' => 'reason@example.org',
        'subject' => 'Subject',
        'status_code' => '200',
    ]);

    $this->postSignedWebhook(['events' => [
        ['email' => 'reason@example.org', 'event' => 'bounce', 'message_id' => 'message-reason', 'response' => 'User unknown', 'response_code' => 550],
    ]])->assertOk();

    expect(MailLog::where('message_id', 'message-reason')->value('error_message'))->toBe('User unknown');
});

it('keeps an earlier error message when a later event carries no reason', function (): void {
    MailLog::create([
        'message_id' => 'message-keep',
        'sender' => 'sender@example.org',
        'recipient' => 'keep@example.org',
        'subject' => 'Subject',
        'error_message' => 'Flagged before sending',
    ]);

    $this->postSignedWebhook(['events' => [
        ['email' => 'keep@example.org', 'event' => 'delivery', 'message_id' => 'message-keep'],
    ]])->assertOk();

    expect(MailLog::where('message_id', 'message-keep')->value('error_message'))->toBe('Flagged before sending');
});

it('only updates the recipient the event is about when a message had several recipients', function (): void {
    foreach (['bounced@example.org', 'delivered@example.org'] as $recipient) {
        MailLog::create([
            'message_id' => 'shared-message',
            'sender' => 'sender@example.org',
            'recipient' => $recipient,
            'subject' => 'Subject',
            'status_code' => '200',
        ]);
    }

    $this->postSignedWebhook(['events' => [
        ['email' => 'bounced@example.org', 'event' => 'bounce', 'message_id' => 'shared-message', 'response' => 'User unknown', 'response_code' => 550],
    ]])->assertOk();

    expect(MailLog::where('recipient', 'bounced@example.org')->value('status_code'))->toBe('550')
        ->and(MailLog::where('recipient', 'delivered@example.org')->value('status_code'))->toBe('200');
});

it('finds the mail log through the custom variable when Mailtrap uses its own message id', function (): void {
    MailLog::create([
        'message_id' => 'local-uuid',
        'sender' => 'sender@example.org',
        'recipient' => 'linked@example.org',
        'subject' => 'Subject',
        'status_code' => '200',
    ]);

    $this->postSignedWebhook(['events' => [[
        'email' => 'linked@example.org',
        'event' => 'bounce',
        'message_id' => 'mailtrap-own-id',
        'response_code' => 550,
        'custom_variables' => ['x_message_id' => 'local-uuid'],
    ]]])->assertOk();

    expect(MailLog::count())->toBe(1)
        ->and(MailLog::where('message_id', 'local-uuid')->value('status_code'))->toBe('550');
});
