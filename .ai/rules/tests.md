---
paths:
  - 'tests/**'
---

# Tests

## Test collaborators
Prefer real Actions and other first party collaborators over mocking internal classes.

Use Laravel and Pest fakes when asserting or isolating framework side effects such as processes and HTTP requests. Global test setup should freeze time and prevent stray processes. A test may exercise a real framework boundary when that behavior is under test and the underlying external transport remains isolated.

Fake or mock an external system at the application's integration boundary. Do not mock first party collaborators merely to isolate a unit.
