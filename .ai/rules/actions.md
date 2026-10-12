---
paths:
  - 'app/Actions/**'
---

# Actions

## Action structure
Business logic lives in reusable, composable Action classes under `app/Actions`. Name actions for what they do without an `Action` suffix. Actions are `final readonly` classes with one public `handle()` method.

Inject dependencies through constructor property promotion. Omit the constructor when an action has no dependencies. Mark password and secret string parameters with `#[SensitiveParameter]`.

## Validated attributes
Pass already-validated command input as `array $attributes`. Do not unpack input into individual `handle()` arguments. Keep value objects and paths the action operates on as typed parameters. Actions that take no input omit `$attributes`.

Do not validate in actions. Validation lives in the command. An action receives information that has already been validated.

Do not dispatch events or rely on listeners for application workflow. Compose other actions instead.

## Console output
Actions never write to the console or prompt for input. Return a value or throw an exception and let the calling command render the result.

## Composition
Keep each action focused on one responsibility. A top-level action may compose sub-actions, but each sub-action must do one thing.

Actions may be called from commands, other actions, and other application entry points.
