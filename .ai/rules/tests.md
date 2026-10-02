---
paths:
  - 'tests/**'
---

# Tests

## Pest test files share one global function namespace
Two traps that both fail as fatals, not assertion diffs. Global `function` helpers in Pest files share one namespace, so a generic name (`fakeSession`) will collide with an existing file and break the whole suite; prefix it. And `use RuntimeException;` / `use ZipArchive;` in a non-namespaced test file emits a warning, because a single-segment `use` in the global namespace has no effect.
