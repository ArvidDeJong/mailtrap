# Contributing

Contributions are welcome: bug reports, fixes, documentation and ideas.

## Before you start

- **Bugs:** open an [issue](https://github.com/ArvidDeJong/mailtrap/issues/new/choose) with the steps to reproduce.
- **Features:** open an issue first, so we can agree it fits before you build it.
- **Security issues:** don't open an issue; see [SECURITY.md](SECURITY.md).

## Development

```bash
git clone https://github.com/ArvidDeJong/mailtrap.git
cd mailtrap
composer install

composer test      # Pest
composer lint      # Pint, check only (composer format fixes)
composer analyse   # Larastan, level 5
```

CI runs the tests on PHP 8.2-8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies.

## Pull requests

- Add or update tests for every change in behaviour.
- Keep the public API compatible within 1.x. Don't add return types to existing public methods that host apps may override; deprecate first and remove in 2.0.
- A new migration must also be added to the list in `tests/TestCase.php`, otherwise it is never tested.
- No Doctrine DBAL and no driver-specific SQL: use the native schema builder, because host apps run their tests on SQLite.
- Write code, comments, messages and the inbox UI in English.
- Update `docs/`, `CHANGELOG.md` (under `Unreleased`) and `resources/boost/` when users will notice the change.
- The documentation in `docs/` is also the website. Don't write `{{ }}` or `{% %}` in code examples; Jekyll would render it.

## Code of conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).
