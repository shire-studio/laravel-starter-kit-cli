---
paths:
  - 'app/Commands/**'
---

# Commands

## Thin entry points
Commands are the console equivalent of controllers. A command collects input, validates it, calls Actions, and renders the result. Business logic never lives in a command. Commands are `final` classes under `app/Commands`.

## Method parameter order
Inject Actions into `handle()` instead of the constructor. Declare `handle()` parameters in this order:

1. Action classes, in invocation order.
2. Other services.

Omit slots that do not apply, but never reorder the remaining slots.

## Input validation
Validate arguments, options, and prompted answers in the command before calling an action. Use the `validate` argument of Laravel Prompts for interactive input. Actions receive already-validated input.

Narrow argument and option values to their expected scalar types before passing them on. Pass validated input to an action as `array $attributes` instead of unpacking it into individual `handle()` arguments.

## Output and exit codes
Commands own all console output. Actions return values or throw exceptions, and the command decides what to print. Return `self::SUCCESS` or `self::FAILURE` from `handle()`.
