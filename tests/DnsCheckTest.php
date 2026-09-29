<?php

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Support\DnsLookup;
use Illuminate\Support\Facades\Log;

/**
 * Swap the DNS lookups for fixed answers.
 *
 * @param  array<int, array{priority: int, target: string}>|null  $mx
 * @param  array<string, bool|null>  $hosts
 */
function fakeDns(?array $mx, array $hosts = []): void
{
    app()->instance(DnsLookup::class, new class($mx, $hosts) extends DnsLookup
    {
        /**
         * @param  array<int, array{priority: int, target: string}>|null  $mx
         * @param  array<string, bool|null>  $hosts
         */
        public function __construct(private ?array $mx, private array $hosts) {}

        public function mxRecords(string $domain): ?array
        {
            return $this->mx;
        }

        public function resolves(string $host): ?bool
        {
            return array_key_exists($host, $this->hosts) ? $this->hosts[$host] : false;
        }
    });
}

it('accepts an address whose MX host resolves', function (): void {
    fakeDns([['priority' => 10, 'target' => 'mx.example.org']], ['mx.example.org' => true]);

    expect(EmailValidation::validateEmail('someone@example.org'))->toBeNull()
        ->and(EmailValidation::where('email', 'someone@example.org')->value('status'))->toBe(EmailValidation::VALID);
});

it('refuses a domain without MX record', function (): void {
    fakeDns([]);

    expect(EmailValidation::validateEmail('someone@example.org'))->toBe('No valid mail server found for domain')
        ->and(EmailValidation::isBlocked('someone@example.org'))->toBeTrue();
});

it('refuses a domain with a Null MX', function (): void {
    fakeDns([['priority' => 0, 'target' => '']]);

    expect(EmailValidation::validateEmail('someone@example.org'))->toBe('Domain does not accept mail (Null MX)')
        ->and(EmailValidation::isBlocked('someone@example.org'))->toBeTrue();
});

it('lets a Null MX block expire like the other local checks', function (): void {
    expect(EmailValidation::LOCAL_CHECK_REASONS)->toContain('Domain does not accept mail (Null MX)');
});

it('refuses a domain whose MX hosts have no address', function (): void {
    fakeDns([['priority' => 10, 'target' => 'mx.example.org']], ['mx.example.org' => false]);

    expect(EmailValidation::validateEmail('someone@example.org'))->toBe('MX record does not resolve to a valid IP address');
});

it('lets an address through without a verdict when the MX lookup fails', function (): void {
    fakeDns(null);

    expect(EmailValidation::validateEmail('someone@example.org'))->toBeNull()
        ->and(EmailValidation::isBlocked('someone@example.org'))->toBeFalse()
        ->and(EmailValidation::where('email', 'someone@example.org')->exists())->toBeFalse();
});

it('lets an address through when no MX host resolves but one lookup failed', function (): void {
    fakeDns(
        [['priority' => 10, 'target' => 'mx1.example.org'], ['priority' => 20, 'target' => 'mx2.example.org']],
        ['mx1.example.org' => false, 'mx2.example.org' => null],
    );

    expect(EmailValidation::validateEmail('someone@example.org'))->toBeNull()
        ->and(EmailValidation::where('email', 'someone@example.org')->exists())->toBeFalse();
});

it('accepts an address when a later MX host resolves after an earlier lookup failed', function (): void {
    fakeDns(
        [['priority' => 10, 'target' => 'mx1.example.org'], ['priority' => 20, 'target' => 'mx2.example.org']],
        ['mx1.example.org' => null, 'mx2.example.org' => true],
    );

    expect(EmailValidation::validateEmail('someone@example.org'))->toBeNull()
        ->and(EmailValidation::where('email', 'someone@example.org')->value('status'))->toBe(EmailValidation::VALID);
});

it('logs an inconclusive lookup when logging to Laravel is on', function (): void {
    config(['manta_mailtrap.logging.log_to_laravel' => true]);
    Log::spy();
    fakeDns(null);

    EmailValidation::validateEmail('someone@example.org');

    Log::shouldHaveReceived('log')->once()->withArgs(fn (string $level, string $message, array $context): bool => $level === 'warning'
        && str_contains($message, 'inconclusive')
        && $context['domain'] === 'example.org'
        && $context['reason'] === 'MX lookup failed');
});

it('checks again on the next mail after an inconclusive lookup', function (): void {
    fakeDns(null);
    EmailValidation::validateEmail('someone@example.org');

    fakeDns([]);

    expect(EmailValidation::validateEmail('someone@example.org'))->toBe('No valid mail server found for domain');
});

it('reads the MX records from DNS', function (): void {
    $records = (new DnsLookup)->mxRecords('invalid');

    // The .invalid top-level domain never exists (RFC 2606): an empty list or, offline, a failed lookup.
    expect($records === [] || $records === null)->toBeTrue();
});
