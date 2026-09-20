<?php

declare(strict_types=1);

use Darvis\Mailtrap\Support\MailtrapConfig;

/**
 * MailtrapConfig is the one place that reads the package config. These tests guard the two things
 * that go wrong once a default is written down twice: an accessor that disagrees with the config
 * file, and a caller that reaches past the accessor and keeps its own stale fallback.
 */
function packageRoot(string $path = ''): string
{
    return dirname(__DIR__).($path === '' ? '' : '/'.$path);
}

it('returns the values the config file ships', function () {
    $config = require packageRoot('config/manta_mailtrap.php');

    expect(MailtrapConfig::apiAccountUrl())->toBe($config['api']['account_url'])
        ->and(MailtrapConfig::apiTimeout())->toBe((int) $config['api']['timeout'])
        ->and(MailtrapConfig::validationEnabled())->toBe($config['validation']['enabled'])
        ->and(MailtrapConfig::blockInvalid())->toBe($config['validation']['block_invalid'])
        ->and(MailtrapConfig::validationCacheDuration())->toBe((int) $config['validation']['cache_duration'])
        ->and(MailtrapConfig::loggingEnabled())->toBe($config['logging']['enabled'])
        ->and(MailtrapConfig::logSuccessful())->toBe($config['logging']['log_successful'])
        ->and(MailtrapConfig::logFailed())->toBe($config['logging']['log_failed'])
        ->and(MailtrapConfig::cleanupAfterDays())->toBe((int) $config['logging']['cleanup_after_days'])
        ->and(MailtrapConfig::logToLaravel())->toBe($config['logging']['log_to_laravel'])
        ->and(MailtrapConfig::webhookEnabled())->toBe($config['webhook']['enabled'])
        ->and(MailtrapConfig::webhookVerifySignature())->toBe($config['webhook']['verify_signature'])
        ->and(MailtrapConfig::uiEnabled())->toBe($config['ui']['enabled'])
        ->and(MailtrapConfig::uiRoute())->toBe($config['ui']['route'])
        ->and(MailtrapConfig::uiMiddleware())->toBe($config['ui']['middleware'])
        ->and(MailtrapConfig::uiLayout())->toBe($config['ui']['layout'])
        ->and(MailtrapConfig::uiPerPage())->toBe($config['ui']['per_page']);
});

it('follows a changed setting', function () {
    config([
        'manta_mailtrap.api.timeout' => 5,
        'manta_mailtrap.ui.route' => 'admin/mail',
        'manta_mailtrap.ui.per_page' => 100,
        'manta_mailtrap.webhook.secret' => 'a-secret',
    ]);

    expect(MailtrapConfig::apiTimeout())->toBe(5)
        ->and(MailtrapConfig::uiRoute())->toBe('admin/mail')
        ->and(MailtrapConfig::uiPerPage())->toBe(100)
        ->and(MailtrapConfig::webhookSecret())->toBe('a-secret');
});

it('reports an empty api token as none at all', function () {
    config(['manta_mailtrap.api.token' => '']);

    expect(MailtrapConfig::apiToken())->toBeNull();

    config(['manta_mailtrap.api.token' => 'abc123']);

    expect(MailtrapConfig::apiToken())->toBe('abc123');
});

it('gives the inbox route as a path, whether or not it is configured with a slash', function () {
    config(['manta_mailtrap.ui.route' => 'mailtrap']);
    expect(MailtrapConfig::uiPath())->toBe('/mailtrap');

    config(['manta_mailtrap.ui.route' => '/mailtrap']);
    expect(MailtrapConfig::uiPath())->toBe('/mailtrap');
});

it('switches off logging a sent or failed mail as soon as logging itself is off', function () {
    config([
        'manta_mailtrap.logging.enabled' => false,
        'manta_mailtrap.logging.log_successful' => true,
        'manta_mailtrap.logging.log_failed' => true,
    ]);

    expect(MailtrapConfig::logSuccessful())->toBeFalse()
        ->and(MailtrapConfig::logFailed())->toBeFalse();
});

it('is the only place in the package that reads the config', function () {
    $offenders = [];

    foreach (['src', 'resources', 'routes'] as $directory) {
        $path = packageRoot($directory);

        if (! is_dir($path)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(packageRoot('').'/', '', $file->getPathname());

            if (str_contains($relative, 'MailtrapConfig.php')) {
                continue;
            }

            if (preg_match("/config\(['\"]manta_mailtrap\./", (string) file_get_contents($file->getPathname()))) {
                $offenders[] = $relative;
            }
        }
    }

    expect($offenders)->toBe([], 'these read the config directly instead of through MailtrapConfig');
});

it('keeps the config keys in alphabetical order, in every group', function () {
    $lines = file(packageRoot('config/manta_mailtrap.php'), FILE_IGNORE_NEW_LINES);

    $top = [];
    $groups = [];
    $current = null;

    foreach ($lines as $line) {
        if (preg_match("/^    '([a-z_0-9]+)' =>/", $line, $match)) {
            $top[] = $match[1];
            $current = $match[1];
            $groups[$current] = [];

            continue;
        }

        if ($current !== null && preg_match("/^        '([a-z_0-9]+)' =>/", $line, $match)) {
            $groups[$current][] = $match[1];
        }
    }

    $sortedTop = $top;
    sort($sortedTop);

    expect($top)->toBe($sortedTop, 'the groups are not in alphabetical order');

    foreach ($groups as $group => $keys) {
        $sorted = $keys;
        sort($sorted);

        expect($keys)->toBe($sorted, "the keys in '{$group}' are not in alphabetical order");
    }
});
