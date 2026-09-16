<?php

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.from', ['address' => 'sender@example.org', 'name' => 'Sender']);
});

it('marks every recipient as sent when one message has multiple recipients', function (): void {
    // Pre-seed validation so the listener does not perform live DNS lookups.
    EmailValidation::saveValidation('first@example.org', 'valid', 'ok', 200);
    EmailValidation::saveValidation('second@example.org', 'valid', 'ok', 200);

    Mail::raw('Body', function ($message): void {
        $message->to(['first@example.org', 'second@example.org'])
            ->subject('Multi recipient');
    });

    expect(MailLog::count())->toBe(2)
        ->and(MailLog::where('recipient', 'first@example.org')->value('status_code'))->toBe('200')
        ->and(MailLog::where('recipient', 'second@example.org')->value('status_code'))->toBe('200')
        // Both recipient rows correlate to the same message via one shared id.
        ->and(MailLog::query()->distinct()->pluck('message_id'))->toHaveCount(1);
});

it('marks a single recipient as sent', function (): void {
    EmailValidation::saveValidation('solo@example.org', 'valid', 'ok', 200);

    Mail::raw('Body', function ($message): void {
        $message->to('solo@example.org')->subject('Single recipient');
    });

    expect(MailLog::count())->toBe(1)
        ->and(MailLog::where('recipient', 'solo@example.org')->value('status_code'))->toBe('200');
});

it('does not let one blocked address stop mail to the rest of its domain', function (): void {
    EmailValidation::markAsBlocked('dead@example.org', 'Manually blocked');
    EmailValidation::saveValidation('alive@example.org', 'valid', 'ok', 200);

    Mail::raw('Body', function ($message): void {
        $message->to('alive@example.org')->subject('Same domain');
    });

    expect(MailLog::where('recipient', 'alive@example.org')->value('status_code'))->toBe('200');
});

it('blocks a blocked address in cc or bcc', function (string $field): void {
    EmailValidation::saveValidation('alive@example.org', 'valid', 'ok', 200);
    EmailValidation::markAsBlocked('dead@example.org', 'Manually blocked');

    expect(fn () => Mail::raw('Body', function ($message) use ($field): void {
        $message->to('alive@example.org')->{$field}('dead@example.org')->subject('Hidden recipient');
    }))->toThrow(TransportException::class);

    expect(MailLog::where('recipient', 'dead@example.org')->value('status_code'))->toBe('550');
})->with(['cc', 'bcc']);

it('skips a duplicate log row when a legacy unique index is still present', function (): void {
    Schema::table('mail_logs', function (Blueprint $table): void {
        $table->unique('message_id', 'mail_logs_message_id_unique');
    });

    EmailValidation::saveValidation('first@example.org', 'valid', 'ok', 200);
    EmailValidation::saveValidation('second@example.org', 'valid', 'ok', 200);

    Mail::raw('Body', function ($message): void {
        $message->to(['first@example.org', 'second@example.org'])->subject('Legacy index');
    });

    expect(MailLog::count())->toBe(1)
        ->and(MailLog::value('status_code'))->toBe('200');
});

it('logs a message that has a Sender header but no From', function (): void {
    config()->set('mail.from', ['address' => null, 'name' => null]);

    EmailValidation::saveValidation('solo@example.org', 'valid', 'ok', 200);

    Mail::raw('Body', function ($message): void {
        $message->sender('robot@example.org')->to('solo@example.org')->subject('No from');
    });

    expect(MailLog::where('recipient', 'solo@example.org')->value('sender'))->toBe('robot@example.org');
});

it('passes the log message id to Mailtrap as a custom variable, keeping existing variables', function (): void {
    EmailValidation::saveValidation('solo@example.org', 'valid', 'ok', 200);

    Mail::raw('Body', function ($message): void {
        $message->to('solo@example.org')->subject('Custom variables');
        $message->getHeaders()->addTextHeader('X-MT-Custom-Variables', '{"user_id":"42"}');
    });

    $sent = app('mailer')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    $variables = json_decode($sent->getHeaders()->get('X-MT-Custom-Variables')->getBodyAsString(), true);

    expect($variables)->toBe([
        'user_id' => '42',
        'x_message_id' => MailLog::where('recipient', 'solo@example.org')->value('message_id'),
    ]);
});
