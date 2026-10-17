<picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
    <img alt="Logo for Laravel Starter Kit CLI" src="art/header-light.png">
</picture>

<br/>
<br/>

<p>
    <a href="https://github.com/shire-studio/laravel-starter-kit-cli/actions"><img src="https://github.com/shire-studio/laravel-starter-kit-cli/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
    <a href="https://packagist.org/packages/shire-studio/laravel-starter-kit-cli"><img src="https://img.shields.io/packagist/v/shire-studio/laravel-starter-kit-cli" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/shire-studio/laravel-starter-kit-cli"><img src="https://img.shields.io/packagist/l/shire-studio/laravel-starter-kit-cli" alt="License"></a>
</p>

The installer for the [Shire Studio Laravel starter kit](https://github.com/shire-studio/laravel-starter-kit). Use `new` to start a project from the starter kit, and `update` to bring newer starter kit changes into a project you've already built with it. Think of it as the `laravel` installer, but for our starter kit.

> Not released yet. Both commands work, but there is no tagged release to install.

## Requirements

- [PHP 8.5+](https://php.net/releases/)
- [Composer](https://getcomposer.org)
- [git](https://git-scm.com)
- [pnpm](https://pnpm.io), if you want `new` to install and build the frontend
- A supported coding agent, if you want `update` to apply the changes for you (see below)

## Installation

Coming with the first release. Until then, run the CLI from a clone. An alias keeps the commands short:

```bash
alias starter-kit="php /path/to/laravel-starter-kit-cli/application"
```

## Usage

### Create a new project

```bash
starter-kit new example-app
```

This creates `example-app` from the latest stable starter kit release. You'll be asked whether to install the frontend dependencies and build the assets, and whether to initialise a git repository. Skip the questions with these options:

| Option | What it does |
| --- | --- |
| `--pnpm` / `--no-pnpm` | Install the frontend dependencies and build the assets, or don't |
| `--git` / `--no-git` | Initialise a git repository with a first commit, or don't |
| `--force`, `-f` | Overwrite the project directory if it already exists |

Every new project gets a `.starter-kit.json` file recording the release it was created from. Commit it, because `update` reads it to work out which changes the project hasn't received yet.

### Update an existing project

Run `update` from the root of a project created from the starter kit:

```bash
cd example-app
starter-kit update
```

The project has to be a git repository with no uncommitted changes, so everything the update does can be reviewed with `git diff` and undone with `git restore`.

The CLI works out what changed between the project's starter kit release and the latest one, then hands those changes to a coding agent of your choice to apply. The agent does the judgement work: merging upstream changes into files you've customised, keeping your own changes, and fixing anything the update breaks. These agents are supported, and only the ones you have installed are offered:

- [Claude Code](https://claude.com/claude-code) (`claude`)
- [Codex](https://github.com/openai/codex) (`codex`)
- [Gemini CLI](https://github.com/google-gemini/gemini-cli) (`gemini`)
- [OpenCode](https://opencode.ai) (`opencode`)
- [Cursor](https://cursor.com/cli) (`cursor-agent`)

An update runs in these steps:

1. The CLI downloads both starter kit releases and writes the changes between them to `.starter-kit-update/` in the project. It holds the diff, the old and new version of every changed file, the upstream commit messages and a prompt for the agent. The directory is excluded from git.
2. You choose an agent, and it starts in an interactive session with the prompt. Follow along, answer its questions, and exit the session when it's done.
3. The CLI shows the changed files and asks whether to mark the project as updated. If you say yes, it updates `.starter-kit.json` and removes `.starter-kit-update/`. If you say no, both are kept so you can carry on by hand.

Nothing is committed. Review the changes, then commit them yourself.

| Option | What it does |
| --- | --- |
| `--agent=<agent>` | Skip the question and use `claude`, `codex`, `gemini`, `opencode`, `cursor` or `none` |
| `--force`, `-f` | Run even if the project has uncommitted changes |

With `--agent=none`, or with `--no-interaction` and no `--agent`, the CLI only prepares `.starter-kit-update/`. Follow `.starter-kit-update/PROMPT.md` yourself, or hand it to any coding agent you like. The prompt ends with the steps to mark the project as updated.

#### Removed features stay removed

If you removed a starter kit feature from your project, such as authentication, the update won't bring it back. The CLI lists every starter kit file the project no longer has, and the agent is told never to restore them, to skip changes that only touch removed code, and to ask you when it isn't sure. When the agent finds features you removed on purpose, it suggests notes about them, and the CLI saves the ones you accept in `.starter-kit.json` so future updates remember them.

#### Projects without `.starter-kit.json`

Starter kit releases published with its release workflow ship `.starter-kit.json` themselves, so even projects created with a plain `composer create-project` have one. For an older project without it, the agent first works out which starter kit release the project started from. You confirm the version, the CLI records it in `.starter-kit.json`, and the update carries on as normal.

## Local Development

> **Requires [PHP 8.5+](https://php.net/releases/), [Composer](https://getcomposer.org), and a code coverage driver like [Xdebug](https://xdebug.org/docs/install) or [PCOV](https://github.com/krakjoe/pcov).**

```bash
git clone git@github.com:shire-studio/laravel-starter-kit-cli.git
cd laravel-starter-kit-cli
composer install
composer test
```

### Available Commands

- `composer lint`: fix code style with Rector and Pint
- `composer test:lint`: check code style without changing anything
- `composer test:types`: PHPStan (Larastan, level max)
- `composer test:type-coverage`: 100% type coverage
- `composer test:unit`: Pest in parallel, 100% code coverage
- `composer test`: all of the above

The unit suite also runs Pest's `php`, `strict` and `security` arch presets.

CI runs `composer test` on PHP 8.5 for every push and pull request to `main`. Dependabot handles GitHub Actions and Composer updates.

### Vetting Dependencies

[laravel/vet](https://github.com/laravel/vet) checks every installed package against the hashes in `vet.json`, and holds back anything released in the last 2 days (`minimum-release-age`).

When you add, remove or update a package, run vet and commit the updated `vet.json` with your lock file:

```bash
vendor/bin/vet
```

**Note:** `brianium/paratest` is held below 7.26.1 by a `conflict` entry in `composer.json`. Its new `WrapperWorker` constructor breaks parallel runs on Pest 5.3.1. Drop the conflict once Pest fixes it.

### Building the PHAR

```bash
php application app:build
```

The PHAR ends up in `builds/application`. You'll be asked for a version unless you pass `--build-version`. What goes into it is set in `box.json`.

## Contributing

1. Branch off `main` (`feat/my-feature` or `fix/my-fix`)
2. Make your changes
3. Run `composer lint`, then `composer test`
4. Write commits as [Conventional Commits](https://www.conventionalcommits.org), e.g. `feat: add update command`
5. Open a pull request against `main`

## License

Released under the [MIT license](https://opensource.org/licenses/MIT).
