# Contributing

Thanks for your interest in contributing to NativePHP Mobile Local Notifications! This guide will help you get set up and understand the project workflow.

## Prerequisites

- PHP 8.3+
- Composer
- A NativePHP Mobile app for testing native changes on a real device

## Getting Started

1. Fork the repository and clone your fork:

```bash
git clone https://github.com/<your-username>/nativephp-mobile-local-notifications.git
cd nativephp-mobile-local-notifications
```

2. Install dependencies:

```bash
composer install
```

3. Run the full check suite to make sure everything passes:

```bash
composer check
```

## Project Structure

```
src/                        # PHP source code
  LocalNotifications.php    # Main class — bridge calls, config injection
  Validation/               # Input validation (NotificationValidator)
  Data/                     # DTOs (NotificationOptions, NotificationAction)
  Events/                   # Laravel events dispatched by native code
  Contracts/                # Interface definitions
  Facades/                  # Laravel facade
  Enums/                    # RepeatInterval enum

resources/
  android/src/              # Kotlin native code (Android)
  ios/Sources/              # Swift native code (iOS)
  js/                       # JavaScript client library
  boost/guidelines/         # AI assistant guidelines (Boost)

config/                     # Publishable Laravel config
tests/Unit/                 # Pest test suite
docs/epics/                 # Feature planning documents
```

## Development Workflow

### Running Checks

```bash
# Run everything (lint, refactor, analyse, test)
composer check

# Individual commands
composer test              # Run Pest tests
composer analyse           # PHPStan level 8
composer lint              # Fix code style with Pint
composer lint:check        # Check code style without fixing
composer refactor          # Apply Rector refactoring
composer refactor:check    # Check refactoring without applying
```

### Auto-fix code style and refactoring

```bash
composer fix
```

This runs both `pint` (code style) and `rector` (refactoring).

### Quality Standards

All PRs must pass these checks (enforced by CI):

- **Code style** — Laravel Pint preset
- **Refactoring** — Rector with PHP 8.3 rules, code quality, dead code, early return, and type declarations
- **Static analysis** — PHPStan level 8 with Larastan
- **Tests** — Pest with minimum 90% code coverage

## Making Changes

### PHP Code

1. Write your code in `src/`
2. Add or update tests in `tests/Unit/`
3. Run `composer check` to verify everything passes
4. If you change config-related behavior, update `config/local-notifications.php`

### Native Code (Android/iOS)

Native Kotlin and Swift code lives in `resources/android/src/` and `resources/ios/Sources/`. These files are **not unit tested** — they are verified at runtime on real devices via NativePHP's build process.

When modifying native code:

- Keep changes consistent across both platforms where applicable
- Read config values from the `_config` bridge parameter (injected on every bridge call)
- Call `applyConfig()` at the start of every bridge function (Android)
- Read from the `config` local variable extracted from `parameters["_config"]` (iOS)
- Test on a real device by building a NativePHP app that uses the plugin

### Documentation

When making code changes, **always update the relevant docs in the same commit**:

- `README.md` — User-facing documentation
- `CHANGELOG.md` — Add entry under `[Unreleased]` or the next version
- `resources/boost/guidelines/core.blade.php` — AI assistant guidelines
- `docs/epics/` — Feature planning docs (if relevant to an epic)

## Submitting a Pull Request

1. Create a feature branch from `main`:

```bash
git checkout -b feature/your-feature-name
```

2. Make your changes with clear, focused commits
3. Run `composer check` to ensure all checks pass
4. Push your branch and open a PR against `main`
5. Fill in the PR description with:
   - What the change does and why
   - How to test it
   - Any platform-specific notes (Android only, iOS only, or both)

## Reporting Issues

- Use [GitHub Issues](https://github.com/Ikromjon1998/nativephp-mobile-local-notifications/issues) for bug reports and feature requests
- Include your PHP version, NativePHP version, and platform (Android/iOS)
- For notification delivery issues, mention whether the app was in the foreground, background, or killed

## Testing Native Changes

Since native code can't be unit tested, use your own NativePHP Mobile app:

```bash
# In your NativePHP app, point to your local fork
composer config repositories.local-notifications path /path/to/your/fork

# Install from local path
composer require ikromjon/nativephp-mobile-local-notifications:@dev

# Build and run
php artisan native:run android
# or
php artisan native:run ios
```

## Code of Conduct

Be respectful and constructive. We're all here to build something useful together.
