<?php

use Darvis\Mailtrap\Livewire\MailtrapInbox;
use Darvis\Mailtrap\MailtrapServiceProvider;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Every text of the inbox exists in every language the package ships, so a key added in one
 * language cannot go missing in another.
 */
function mailtrapLangKeys(string $locale): array
{
    return array_keys(Arr::dot(require __DIR__."/../resources/lang/{$locale}/inbox.php"));
}

it('has the same keys in every language', function (string $locale): void {
    expect(mailtrapLangKeys($locale))->toEqualCanonicalizing(mailtrapLangKeys('en'));
})->with(fn (): array => array_values(array_diff(array_map('basename', glob(__DIR__.'/../resources/lang/*', GLOB_ONLYDIR)), ['en'])));

it('ships English and Dutch', function (): void {
    expect(is_file(__DIR__.'/../resources/lang/en/inbox.php'))->toBeTrue()
        ->and(is_file(__DIR__.'/../resources/lang/nl/inbox.php'))->toBeTrue();
});

it('shows the inbox in Dutch when the application locale is Dutch', function (): void {
    Gate::define('viewMailtrap', fn ($user = null): bool => true);
    app()->setLocale('nl');

    MailLog::create(['sender' => 'a@example.org', 'recipient' => 'b@example.org', 'subject' => 'S', 'status_code' => MailLog::STATUS_BLOCKED]);

    Livewire::test(MailtrapInbox::class)
        ->assertSee('Testmail versturen')
        ->assertSee('Geblokkeerd')
        ->assertDontSee('Send test mail');
});

it('publishes the translations under the mailtrap-lang tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(MailtrapServiceProvider::class, 'mailtrap-lang');

    expect(array_values($paths))->toBe([lang_path('vendor/mailtrap')]);
});
