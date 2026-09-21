<?php

use Darvis\Mailtrap\Http\Middleware\AuthorizeInbox;
use Darvis\Mailtrap\Livewire\MailtrapInbox;
use Darvis\Mailtrap\MailtrapServiceProvider;
use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.from', ['address' => 'sender@example.org', 'name' => 'Sender']);

    // The default layout belongs to the host app; the test app has its own.
    app('view')->addLocation(__DIR__.'/fixtures/views');
    config()->set('manta_mailtrap.ui.layout', 'layouts.inbox-test');
});

/**
 * A host component that replaces boot() without calling the parent.
 */
class InboxWithoutBootCheck extends MailtrapInbox
{
    public function boot(): void {}
}

function authorizationLog(): MailLog
{
    return MailLog::create([
        'sender' => 'sender@example.org',
        'recipient' => 'private@example.org',
        'subject' => 'Private subject',
    ]);
}

/**
 * A gate the test can close after the component was rendered, the way a session can end
 * or a role can be taken away between the page load and the next Livewire update.
 */
function switchableInboxGate(): stdClass
{
    $state = new stdClass;
    $state->allow = true;

    Gate::define('viewMailtrap', fn (?User $user = null): bool => $state->allow);

    return $state;
}

it('answers 403 for the inbox page outside the local environment when the host defines no gate', function (): void {
    authorizationLog();

    $this->get('/mailtrap')
        ->assertForbidden()
        ->assertDontSee('private@example.org');
});

it('does not render the component outside the local environment when the host defines no gate', function (): void {
    authorizationLog();

    Livewire::test(MailtrapInbox::class)->assertForbidden();
});

it('shows the inbox page in the local environment without a gate', function (): void {
    $this->app['env'] = 'local';
    authorizationLog();

    $this->get('/mailtrap')
        ->assertOk()
        ->assertSee('private@example.org');
});

it('passes the host user to the host gate and follows its answer', function (): void {
    Gate::define('viewMailtrap', fn (?User $user): bool => $user?->email === 'admin@example.org');
    authorizationLog();

    $this->get('/mailtrap')->assertForbidden();

    $this->actingAs((new User)->forceFill(['id' => 1, 'email' => 'customer@example.org']))
        ->get('/mailtrap')
        ->assertForbidden();

    $this->actingAs((new User)->forceFill(['id' => 2, 'email' => 'admin@example.org']))
        ->get('/mailtrap')
        ->assertOk()
        ->assertSee('private@example.org');
});

it('lets a host gate that denies win in the local environment', function (): void {
    $this->app['env'] = 'local';
    Gate::define('viewMailtrap', fn (?User $user = null): bool => false);

    $this->get('/mailtrap')->assertForbidden();
    Livewire::test(MailtrapInbox::class)->assertForbidden();
});

it('runs the authorisation on Livewire update requests as well', function (): void {
    expect(Livewire::getPersistentMiddleware())->toContain(AuthorizeInbox::class);

    $middleware = app('router')->getRoutes()->getByName('mailtrap.inbox')->gatherMiddleware();

    expect($middleware)->toBe(['web', AuthorizeInbox::class]);
});

it('refuses every action once the gate no longer allows the visitor', function (string $method, array $arguments): void {
    $gate = switchableInboxGate();
    $log = authorizationLog();
    $log->forceFill(['created_at' => now()->subYear()])->save();
    EmailValidation::markAsValid('victim@example.org');

    $component = Livewire::test(MailtrapInbox::class)
        ->assertOk()
        ->set('testEmail', 'victim@example.org');

    $gate->allow = false;

    $component->call($method, ...array_map(fn ($argument) => $argument === ':id' ? $log->id : $argument, $arguments))
        ->assertForbidden();

    expect(MailLog::count())->toBe(1)
        ->and(MailLog::where('recipient', 'victim@example.org')->exists())->toBeFalse();
})->with([
    'select' => ['select', [':id']],
    'deleteLog' => ['deleteLog', [':id']],
    'cleanup' => ['cleanup', []],
    'sendTest' => ['sendTest', []],
]);

it('refuses a property update once the gate no longer allows the visitor', function (): void {
    $gate = switchableInboxGate();
    authorizationLog();

    $component = Livewire::test(MailtrapInbox::class)->assertOk();

    $gate->allow = false;

    $component->set('search', 'private')->assertForbidden();
});

it('checks the gate inside every action, also when a subclass replaces boot()', function (string $method, array $arguments): void {
    $gate = switchableInboxGate();
    $log = authorizationLog();
    $log->forceFill(['created_at' => now()->subYear()])->save();
    EmailValidation::markAsValid('victim@example.org');

    $component = Livewire::test(InboxWithoutBootCheck::class)
        ->assertOk()
        ->set('testEmail', 'victim@example.org');

    $gate->allow = false;

    $component->call($method, ...array_map(fn ($argument) => $argument === ':id' ? $log->id : $argument, $arguments))
        ->assertForbidden();

    expect(MailLog::count())->toBe(1);
})->with([
    'select' => ['select', [':id']],
    'deleteLog' => ['deleteLog', [':id']],
    'cleanup' => ['cleanup', []],
    'sendTest' => ['sendTest', []],
]);

it('does not render for a subclass that replaces boot() either', function (): void {
    authorizationLog();

    Livewire::test(InboxWithoutBootCheck::class)->assertForbidden();
});

it('leaves a gate of the host application alone', function (): void {
    $this->app['env'] = 'local';
    Gate::define('viewMailtrap', fn (?User $user = null): bool => false);

    $provider = app()->getProvider(MailtrapServiceProvider::class);
    (fn () => $this->defineInboxGate())->call($provider);

    expect(Gate::allows('viewMailtrap'))->toBeFalse();
});
