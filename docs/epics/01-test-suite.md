# Epic 1: Test Suite

**Priority:** Critical
**Status:** Not Started

## Description

Add comprehensive test coverage to ensure reliability and prevent regressions. The package currently has zero tests, making it risky for production use and difficult for contributors to verify changes.

## Scope

- Set up PHPUnit/Pest for the PHP layer with unit tests for `LocalNotifications` class, service provider registration, facade resolution, and all event classes
- Add mock-based tests for the `nativephp_call()` bridge to verify correct function names and argument structures are passed for every method (`schedule`, `cancel`, `cancelAll`, `getPending`, `requestPermission`, `checkPermission`)
- Test input validation: missing required fields, invalid types, conflicting options (`delay` + `at`), out-of-range values
- Test edge cases: empty strings for id/title/body, negative delay values, past timestamps, unknown repeat intervals
- Add CI pipeline (GitHub Actions) to run tests on every push and pull request
- Include static analysis (PHPStan/Larastan level 8) in the CI pipeline

## Acceptance Criteria

- [ ] 90%+ code coverage on PHP layer
- [ ] All public methods have positive and negative test cases
- [ ] CI runs automatically and blocks merge on failure
- [ ] Static analysis passes at level 8
