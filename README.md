<picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
    <img alt="Logo for Laravel Starter Kit CLI" src="art/header-light.png">
</picture>

<br/>
<br/>

<p>
    <a href="https://github.com/shire-studio/laravel-starter-kit-cli/actions"><img src="https://github.com/shire-studio/laravel-starter-kit-cli/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
</p>

A command line tool for the [Shire Studio Laravel starter kit](https://github.com/shire-studio/laravel-starter-kit). It works much like the official `laravel` installer. Use it to create new projects from the starter kit, and to pull later starter kit changes into projects you have already created.

> This CLI is in early development. Neither command is available yet.

## Requirements

- [PHP 8.5+](https://php.net/releases/)
- [Composer](https://getcomposer.org)
- [git](https://git-scm.com)

## Installation

Installation instructions will be added once the first release is published.

## Usage

### `new`

> In progress.

Creates a fresh project from the starter kit. Usage and options will be documented here once the command lands.

### `update`

> Planned.

Run inside a project you created from the starter kit to pull in later starter kit changes. Usage and options will be documented here once the command lands.

## Local Development

> **Requires [PHP 8.5+](https://php.net/releases/), [Composer](https://getcomposer.org), and a code coverage driver like [Xdebug](https://xdebug.org/docs/install) or [PCOV](https://github.com/krakjoe/pcov).**

```bash
git clone git@github.com:shire-studio/laravel-starter-kit-cli.git
cd laravel-starter-kit-cli
composer install
composer test
```

### Available Commands

- `composer lint`: Runs Rector (refactoring) and Pint (formatting)
- `composer test:lint`: Runs Pint and Rector in dry run mode
- `composer test:types`: Runs PHPStan (Larastan at level max)
- `composer test:type-coverage`: Ensures 100% type coverage with Pest
- `composer test:unit`: Runs the Pest suite in parallel with a 100% code coverage requirement
- `composer test`: Runs the complete suite (linting, static analysis, type coverage and unit tests)

The Pest suite also applies the `php`, `strict` and `security` architecture presets.

GitHub Actions runs `composer test` on PHP 8.5 for pushes to `main` and pull requests against it. Dependabot keeps GitHub Actions and Composer dependencies up to date.

### Dependency Trust

This project uses [laravel/vet](https://github.com/laravel/vet), a Composer plugin that checks installed packages against the hashes recorded in `vet.json`. It also holds back any release published less than 2 days ago (`minimum-release-age` in `vet.json`).

After adding, removing or updating a dependency, audit the changes and record the packages you trust:

```bash
vendor/bin/vet
```

Commit the updated `vet.json` together with `composer.json` and `composer.lock`.

> `composer.json` holds back `brianium/paratest` 7.26.1 and later with a `conflict` entry, because its new `WrapperWorker` constructor breaks parallel runs on Pest 5.3.1. Remove the entry once Pest ships a fix.

### Building the PHAR

```bash
php application app:build
```

This asks for a build version (or pass `--build-version`) and writes a single file executable to `builds/application`. The PHAR contents are configured in `box.json`.

## Contributing

1. Create a feature branch (`git checkout -b feat/my-feature` or `git checkout -b fix/my-fix`)
2. Make your changes
3. Run `composer lint`, then make sure `composer test` passes
4. Commit using [Conventional Commits](https://www.conventionalcommits.org) (for example `feat: add new command`)
5. Open a pull request against `main`

## License

This project is open source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
