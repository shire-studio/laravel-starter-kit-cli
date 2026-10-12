---
paths:
  - 'app/**'
---

# Architecture

## Workflow decoupling
Call Actions directly for application workflow orchestration. Reserve events for framework lifecycle integration rather than introducing application event and listener indirection.

## External processes
Run external programs such as `git` and `composer` through Laravel's `Process` facade from an Action, so tests can fake them. Do not call `exec()`, `shell_exec()`, or `proc_open()` directly.
