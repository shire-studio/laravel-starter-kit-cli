<picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
    <img alt="Logo for Laravel Starter Kit CLI" src="art/header-light.png">
</picture>

<br/>
<br/>

<p>
    <a href="https://github.com/shire-studio/laravel-starter-kit-cli/actions"><img src="https://github.com/shire-studio/laravel-starter-kit-cli/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
    <a href="https://packagist.org/packages/shire-studio/laravel-starter-kit-cli"><img src="https://img.shields.io/packagist/v/shire-studio/laravel-starter-kit-cli" alt="Latest Stable Version"></a>
    <a href="LICENSE.md"><img src="https://img.shields.io/packagist/l/shire-studio/laravel-starter-kit-cli" alt="License"></a>
</p>

The installer for the [Shire Studio Laravel starter kit](https://github.com/shire-studio/laravel-starter-kit). Use `new` to start a project from the starter kit, and `update` to bring newer starter kit changes into a project you've already built with it. Think of it as the `laravel` installer, but for our starter kit.

## Requirements

- [PHP 8.5+](https://php.net/releases/)
- [Composer](https://getcomposer.org)
- [git](https://git-scm.com)
- [pnpm](https://pnpm.io), if you want `new` to install and build the frontend
- A supported coding agent, if you want `update` to apply the changes for you (see below)

## Installation

Install the CLI globally with Composer:

```bash
composer global require shire-studio/laravel-starter-kit-cli
```

This installs the `shire-kit` command. Make sure Composer's global bin directory is on your `PATH` (run `composer global config bin-dir --absolute` to find it), then check it works:

```bash
shire-kit --version
```

To upgrade to the latest release later, run `composer global update shire-studio/laravel-starter-kit-cli`.

## Usage

### Create a new project

```bash
shire-kit new example-app
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
shire-kit update
```

The project has to be in a git repository and have no uncommitted changes, so everything the update does can be reviewed with `git diff` and undone with `git restore`. It can sit inside a larger repository, such as a monorepo. Only changes inside the project directory count.

The CLI works out what changed between the project's starter kit release and the latest one, then hands those changes to a coding agent of your choice to apply. The agent does the judgement work: merging upstream changes into files you've customised, keeping your own changes, and fixing anything the update breaks. These agents are supported, and only the ones you have installed are offered:

- [Claude Code](https://claude.com/claude-code) (`claude`)
- [Codex](https://github.com/openai/codex) (`codex`)
- [Gemini CLI](https://github.com/google-gemini/gemini-cli) (`gemini`)
- [OpenCode](https://opencode.ai) (`opencode`)
- [Cursor](https://cursor.com/cli) (`cursor-agent`)

An update runs in these steps:

1. You choose an agent, or pass one with `--agent`.
2. The CLI downloads both starter kit releases and writes the changes between them to `.starter-kit-update/` in the project. It holds the diff, the old and new version of every changed file, the upstream commit messages (when they can be fetched) and a prompt for the agent. The directory is excluded from git.
3. The agent starts in an interactive session with the prompt. Follow along, answer its questions, and exit the session when it's done.
4. The CLI shows the changed files. If the agent found features you removed on purpose, you pick which ones to record (see below). Then the CLI asks whether to mark the project as updated. If you say yes, it updates `.starter-kit.json` and removes `.starter-kit-update/`.

If you say no, the project keeps its current version in `.starter-kit.json`, and `.starter-kit-update/` stays in place with the agent's changes still in your working tree. To finish by hand, copy `.starter-kit-update/.starter-kit.json` (which already includes the removed features you picked) to `.starter-kit.json`, then delete `.starter-kit-update/`. The end of `.starter-kit-update/PROMPT.md` lists the same steps. Running `update` again doesn't pick up where you left off. It needs `--force` because of the uncommitted changes, and it prepares `.starter-kit-update/` from scratch.

If the newer release has nothing to apply to the project (for example, only its lock files changed), the CLI marks the project as updated straight away, without an agent. This also happens with `--agent=none` and `--no-interaction`.

Nothing is committed. Review the changes, then commit them yourself.

| Option | What it does |
| --- | --- |
| `--agent=<agent>` | Skip the question and use `claude`, `codex`, `gemini`, `opencode`, `cursor` or `none` |
| `--force`, `-f` | Run even if the project has uncommitted changes |

With `--agent=none`, or with `--no-interaction` and no `--agent`, the CLI only prepares `.starter-kit-update/` (unless there is nothing to apply, as described above). Follow `.starter-kit-update/PROMPT.md` yourself, or hand it to any coding agent you like. The prompt ends with the steps to mark the project as updated.

#### Removed features stay removed

If you removed a starter kit feature from your project, such as authentication, the update won't bring it back. The CLI lists every starter kit file the project no longer has, and the agent is told never to restore them, to skip changes that only touch removed code, and to ask you when it isn't sure. When the agent finds features you removed on purpose, it suggests notes about them. The CLI asks which ones to keep and saves them in `.starter-kit.json`, so future updates remember them.

#### Projects without `.starter-kit.json`

Starter kit releases published with its release workflow ship `.starter-kit.json` themselves, so even projects created with a plain `composer create-project` have one. For an older project without it, the agent first works out which starter kit release the project started from. You confirm the version, the CLI records it in `.starter-kit.json`, and the update carries on as normal. This needs an agent, so with `--agent=none` or `--no-interaction` the update fails until you add `.starter-kit.json` by hand.

`.starter-kit.json` has to name a stable starter kit release. If it records a pre-release or a branch, `update` stops instead of guessing what changed.

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
php shire-kit app:build
```

The PHAR ends up in `builds/shire-kit`. You'll be asked for a version unless you pass `--build-version`. What goes into it is set in `box.json`.

## Contributing

1. Branch off `main` (`feat/my-feature` or `fix/my-fix`)
2. Make your changes
3. Run `composer lint`, then `composer test`
4. Write commits as [Conventional Commits](https://www.conventionalcommits.org), e.g. `feat: add update command`
5. Open a pull request against `main`

## License

This project was created by **Shire Studio** under the **[MIT license](LICENSE.md)**.
