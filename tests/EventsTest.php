<?php

use Darvis\Mailtrap\Events\MailBlocked;
use Darvis\Mailtrap\Events\MailtrapEventReceived;
use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.from', ['address' => 'sender@example.org', 'name' => 'Sender']);
});

it('dispatches MailBlocked before aborting a send', function (): void {
    Event::fake([MailBlocked::class]);

    EmailValidation::markAsBlocked('dead@example.org', 'Spam complaint');

    expect(fn () => Mail::raw('Body', fn ($message) => $message->to('dead@example.org')->subject('Blocked')))
        ->toThrow(TransportException::class);

    Event::assertDispatched(MailBlocked::class, fn (MailBlocked $event): bool => $event->email === 'dead@example.org'
        && $event->reason === 'Spam complaint'
        && $event->mailLog?->status_code === MailLog::STATUS_BLOCKED);
});

it('dispatches MailtrapEventReceived for every event, including the ones the package ignores', function (): void {
    Event::fake([MailtrapEventReceived::class]);

    $this->postSignedWebhook(['events' => [
        ['event_id' => 'e1', 'email' => 'a@example.org', 'event' => 'bounce', 'message_id' => 'm1', 'response_code' => 550],
        ['event_id' => 'e2', 'email' => 'b@example.org', 'event' => 'unsubscribe', 'message_id' => 'm2'],
    ]])->assertOk();

    Event::assertDispatchedTimes(MailtrapEventReceived::class, 2);
    Event::assertDispatched(MailtrapEventReceived::class, fn (MailtrapEventReceived $event): bool => $event->type === 'bounce'
        && $event->email === 'a@example.org'
        && $event->mailLog?->status_code === '550');
    Event::assertDispatched(MailtrapEventReceived::class, fn (MailtrapEventReceived $event): bool => $event->type === 'unsubscribe'
        && $event->mailLog === null
        && $event->payload['message_id'] === 'm2');
});

it('keeps processing the batch when a listener for MailtrapEventReceived throws', function (): void {
    Event::listen(MailtrapEventReceived::class, fn () => throw new RuntimeException('Listener failed'));

    $this->postSignedWebhook(['events' => [
        ['event_id' => 'e1', 'email' => 'a@example.org', 'event' => 'delivery', 'message_id' => 'm1'],
        ['event_id' => 'e2', 'email' => 'b@example.org', 'event' => 'delivery', 'message_id' => 'm2'],
    ]])->assertOk()->assertJsonPath('stats.valid_emails', 2);

    expect(MailLog::where('message_id', 'm2')->value('status_code'))->toBe('200');
});
