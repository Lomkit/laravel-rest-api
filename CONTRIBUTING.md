# Contributing

Thank you for your help. This guide explains how to set up the project, run the checks and open a pull request.

## Before you start

- For a bug, open an issue with the bug report template.
- For a new feature or a change of behaviour, open an issue first and wait for an answer before you write code.
- For a documentation issue, open a pull request on [lomkit/laravel-rest-api-doc](https://github.com/Lomkit/laravel-rest-api-doc).
- Do not report a security issue in a public issue. See [Security issues](#security-issues).

## Branches

- The `3.x` branch holds the development of version 3.
- Open your pull request against `3.x`.

## Setup

You need PHP 8.3 or later and Composer.

```bash
composer install
```

## Tests

The suite runs on an in-memory SQLite database by default:

```bash
composer test
```

The CI also runs the suite on MySQL 8 and PostgreSQL 16. To run it on these databases, start them with Docker:

```bash
docker run -d --rm --name lrapi-mysql -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=rest -p 33061:3306 mysql:8
docker run -d --rm --name lrapi-pg -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=rest -p 54321:5432 postgres:16
```

Then run the suite on each database:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=33061 DB_DATABASE=rest DB_USERNAME=root composer test
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=54321 DB_DATABASE=rest DB_USERNAME=postgres DB_PASSWORD=postgres composer test
```

Stop the containers when you are done:

```bash
docker stop lrapi-mysql lrapi-pg
```

The CI also tests the lowest versions allowed by `composer.json`:

```bash
composer update --prefer-lowest --prefer-stable
composer test
composer update
```

Add a test for each bug fix and each new feature.

### Writing tests

Write new tests in `tests/Feature` or `tests/Unit`:

- Feature tests extend `Lomkit\Rest\Tests\PackageTestCase` and declare the fixtures they need in `fixtures()`.
- A fixture is a small domain in `tests/Fixtures/<Domain>`: models, factories, migrations, resources and routes. It implements `Lomkit\Rest\Tests\Fixtures\Fixture`.
- Test methods use the `#[Test]` attribute and are named `it_...`.
- Use fixed values, not Faker, and write the expected responses by hand.

Pure unit tests that need neither the container nor the database extend PHPUnit's `TestCase`.

The legacy suite is every test that extends `Lomkit\Rest\Tests\TestCase`, directly or through `tests/Feature/TestCase.php`, with its helpers in `tests/Support`. It is being replaced and must only shrink:

- Do not add tests, resources or helpers to the legacy suite.
- When you change an area, rewrite its legacy tests in the new suite, then delete them.
- The legacy suite, `tests/Support` and `tests/TestCase.php` are removed before the 3.0 release.

## Code style

[Pint](https://laravel.com/docs/pint) checks the code style with the `laravel` preset. To fix the style:

```bash
composer format
```

To check the style without changes, as the CI does:

```bash
composer lint
```

New code follows these rules:

- Declare the type of every parameter, return value and property.
- Use early returns instead of nested conditions.
- Do not write comments that describe the code. Use PHPDoc only for types that PHP cannot declare, for example `array<string, mixed>` or `Collection<int, Model>`.

## Static analysis

[Larastan](https://github.com/larastan/larastan) analyses `src` at level 8:

```bash
composer analyse
```

`phpstan-baseline.neon` lists the errors that existed when the analysis was added. The baseline must only shrink:

- Do not add entries to the baseline. Fix the new error instead.
- When you fix an error that is in the baseline, remove its entry. The analysis fails when an entry no longer matches an error.

## Commits

Write commit messages with [Conventional Commits](https://www.conventionalcommits.org): `feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `build:`, `ci:`, `chore:`. Add `!` after the type for a breaking change, for example `feat!:`.

## Pull requests

- Keep one subject per pull request.
- Write `Closes #123` in the description when the pull request fixes an issue.
- Make sure that the tests, Pint and Larastan pass. The CI runs them on each pull request.
- Update the README when the change affects how the package is used.

## Security issues

Do not open a public issue for a security issue. Report it in private from the [Security tab](https://github.com/Lomkit/laravel-rest-api/security) of the repository, with "Report a vulnerability".
