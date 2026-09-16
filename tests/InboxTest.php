<?php

use Darvis\Mailtrap\Livewire\MailtrapInbox;
use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.from', ['address' => 'sender@example.org', 'name' => 'Sender']);
});

function inboxLog(array $attributes = []): MailLog
{
    return MailLog::create($attributes + [
        'sender' => 'sender@example.org',
        'recipient' => 'recipient@example.org',
        'subject' => 'Subject',
    ]);
}

it('registers the inbox route', function (): void {
    expect(route('mailtrap.inbox', absolute: false))->toBe('/mailtrap');
});

it('renders logs with their status', function (): void {
    inboxLog(['recipient' => 'sent@example.org', 'status_code' => MailLog::STATUS_SENT]);
    inboxLog(['recipient' => 'blocked@example.org', 'status_code' => MailLog::STATUS_BLOCKED]);

    Livewire::test(MailtrapInbox::class)
        ->assertOk()
        ->assertSee('sent@example.org')
        ->assertSee('blocked@example.org')
        ->assertSee('Blocked');
});

it('filters logs by status and search term', function (): void {
    inboxLog(['recipient' => 'sent@example.org', 'status_code' => MailLog::STATUS_SENT]);
    inboxLog(['recipient' => 'pending@example.org', 'status_code' => null]);
    inboxLog(['recipient' => 'other@example.org', 'status_code' => MailLog::STATUS_SENT, 'subject' => 'Invoice 42']);

    Livewire::test(MailtrapInbox::class)
        ->set('status', 'pending')
        ->assertSee('pending@example.org')
        ->assertDontSee('sent@example.org')
        ->set('status', '')
        ->set('search', 'Invoice')
        ->assertSee('other@example.org')
        ->assertDontSee('pending@example.org');
});

it('deletes a single log', function (): void {
    $log = inboxLog();

    Livewire::test(MailtrapInbox::class)
        ->call('deleteLog', $log->id)
        ->assertSet('notice', 'Mail log deleted.');

    expect(MailLog::count())->toBe(0);
});

it('cleans up logs older than the retention period', function (): void {
    config()->set('manta_mailtrap.logging.cleanup_after_days', 30);

    inboxLog()->forceFill(['created_at' => now()->subDays(31)])->save();
    inboxLog();

    Livewire::test(MailtrapInbox::class)
        ->call('cleanup')
        ->assertSet('notice', 'Deleted 1 log(s) older than 30 days.');

    expect(MailLog::count())->toBe(1);
});

it('sends a test mail and logs it', function (): void {
    EmailValidation::markAsValid('test@example.org');

    Livewire::test(MailtrapInbox::class)
        ->set('testEmail', 'test@example.org')
        ->call('sendTest')
        ->assertHasNoErrors()
        ->assertSet('testResult.ok', true);

    expect(MailLog::where('recipient', 'test@example.org')->value('status_code'))->toBe(MailLog::STATUS_SENT);
});

it('validates the test mail address', function (): void {
    Livewire::test(MailtrapInbox::class)
        ->set('testEmail', 'not an address')
        ->call('sendTest')
        ->assertHasErrors(['testEmail' => 'email']);
});
