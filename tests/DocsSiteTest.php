<?php

/**
 * Guards the rules of the GitHub Pages site in docs/: front matter, Liquid use,
 * single-source facts and the developer credit. Nothing here breaks loudly otherwise.
 */
function docsSitePath(string $path = ''): string
{
    return dirname(__DIR__).'/docs'.($path === '' ? '' : '/'.$path);
}

/**
 * @return array<string, string>
 */
function docsFrontMatter(string $file): array
{
    preg_match('/\A---\n(.*?)\n---\n/s', (string) file_get_contents($file), $match);

    $values = [];
    foreach (explode("\n", $match[1] ?? '') as $line) {
        if (preg_match('/^(\w+):\s*(.*)$/', $line, $pair)) {
            $value = trim($pair[2]);

            // An unquoted ": " makes the YAML invalid, and Jekyll then silently ignores all front matter.
            expect(str_contains($value, ': ') && ! str_starts_with($value, '"'))
                ->toBeFalse(basename($file).': quote the value of '.$pair[1]);

            $values[$pair[1]] = trim($value, '"');
        }
    }

    return $values;
}

/**
 * Site pages; docs/README.md is only for browsing on GitHub and has no front matter.
 *
 * @return array<int, string>
 */
function docsSitePages(): array
{
    $pages = array_merge(glob(docsSitePath('*.md')), glob(docsSitePath('email-validation/*.md')));

    return array_values(array_filter($pages, fn (string $page) => basename($page) !== 'README.md'));
}

test('every page has a title, a unique description and a unique nav order per level', function () {
    $pages = docsSitePages();
    $descriptions = [];
    $navOrders = [];

    foreach ($pages as $page) {
        $meta = docsFrontMatter($page);
        $inSection = str_contains($page, '/email-validation/');

        expect($meta)->toHaveKeys(['title', 'description', 'nav_order'], basename($page));
        expect($meta['parent'] ?? null)->toBe($inSection ? 'Email validation' : null, basename($page));

        $descriptions[] = $meta['description'];
        $navOrders[$inSection ? 'section' : 'top'][] = $meta['nav_order'];
    }

    expect(count($pages))->toBeGreaterThan(10);
    expect(array_unique($descriptions))->toHaveCount(count($pages));

    foreach ($navOrders as $orders) {
        expect(array_unique($orders))->toHaveCount(count($orders));
    }
});

test('pages use Liquid only where it is intended', function () {
    foreach (docsSitePages() as $page) {
        if (basename($page) === 'faq.md') {
            continue;
        }

        expect(file_get_contents($page))->not->toMatch('/\{\{|\{%/', basename($page));
    }
});

test('the FAQ, structured data and llms.txt read from the shared data', function () {
    $faq = (string) file_get_contents(docsSitePath('_data/faq.yml'));

    expect(substr_count($faq, "\n- q: ") + (str_starts_with(ltrim($faq), '- q: ') ? 1 : 0))->toBeGreaterThanOrEqual(6);
    expect(substr_count($faq, '- q: '))->toBe(substr_count($faq, '  a: '));

    expect(file_get_contents(docsSitePath('faq.md')))->toContain('site.data.faq');
    expect(file_get_contents(docsSitePath('llms.txt')))
        ->toContain('permalink: /llms.txt')
        ->toContain('site.data.faq')
        ->toContain('site.pages');
    expect(file_get_contents(docsSitePath('_includes/head_custom.html')))
        ->toContain('"FAQPage"')
        ->toContain('"SoftwareSourceCode"')
        ->toContain('site.data.faq');
});

test('the YAML files build, because one bad value fails the whole Pages build', function () {
    // An unquoted ": " makes the YAML invalid. Jekyll then aborts the build and GitHub keeps
    // serving the last version that did build, so the site looks fine while it is months old.
    foreach (['_config.yml', '_data/faq.yml'] as $file) {
        $path = docsSitePath($file);

        if (! is_file($path)) {
            continue;
        }

        $blockIndent = null;

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $number => $line) {
            $indent = strlen($line) - strlen(ltrim($line));

            if ($blockIndent !== null) {
                // Inside a > or | block every line is text, whatever it contains.
                if (trim($line) === '' || $indent > $blockIndent) {
                    continue;
                }

                $blockIndent = null;
            }

            if (! preg_match('/^(\s*)(?:-\s+)?(\w+):(?:\s+(\S.*))?$/', $line, $pair)) {
                continue;
            }

            $value = trim($pair[3] ?? '');

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, '>') || str_starts_with($value, '|')) {
                $blockIndent = strlen($pair[1]);

                continue;
            }

            $quoted = str_starts_with($value, '"')
                || str_starts_with($value, "'")
                || str_starts_with($value, '[');

            expect(str_contains($value, ': ') && ! $quoted)
                ->toBeFalse($file.' line '.($number + 1).': quote the value of '.$pair[2]);
        }
    }
});

test('the config holds the package facts and the sitemap plugin', function () {
    expect(file_get_contents(docsSitePath('_config.yml')))
        ->toContain('- jekyll-sitemap')
        ->toContain('name: darvis/mailtrap')
        ->toContain('company: ARVID.NL')
        ->toContain('url: https://arvid.nl')
        ->not->toContain('footer_content')
        ->toMatch('/exclude:\\n  - README\\.md/');
});

test('the site says it is not an official Mailtrap product', function () {
    expect(file_get_contents(docsSitePath('index.md')))->toContain('not an official Mailtrap product');
    expect(file_get_contents(docsSitePath('llms.txt')))->toContain('not an official Mailtrap product');
});

test('the footer credits ARVID.NL without a personal name', function () {
    $footer = (string) file_get_contents(docsSitePath('_includes/footer_custom.html'));

    expect($footer)->toContain('site.developer.company')
        ->toContain('site.developer.url')
        ->not->toContain('Arvid de Jong')
        ->not->toMatch('/developed by|made by/i');

    expect(file_get_contents(docsSitePath('_includes/head_custom.html')))->not->toContain('"Person"');
});
