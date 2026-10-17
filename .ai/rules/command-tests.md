---
paths:
  - 'tests/Feature/**/*Test.php'
---

# Command Tests

## Command test resolution
Exercise commands through `$this->artisan()`. Assert prompts with `expectsQuestion()`, `expectsConfirmation()`, or `expectsChoice()` when the offered options matter, output with `expectsOutputToContain()`, and the result with `assertSuccessful()` or `assertFailed()`.

## Validation test cases
Write each input validation scenario as its own named test instead of combining inputs in a dataset.
