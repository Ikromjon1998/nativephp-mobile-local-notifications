# Epic 15: Improved SharedPreferences Persistence (Android)

**Priority:** Low
**Status:** Not Started

## Related Changes

- v1.2.0 added `synchronized` blocks around all SharedPreferences read-modify-write operations
- v1.3.4 added tap payload storage and cleanup in SharedPreferences
- v1.4.0 added `channelId` field to persisted notification info for BootReceiver restoration

## Description

Harden the Android notification storage layer to prevent data loss and improve reliability. Currently, notification metadata is stored as raw JSON in SharedPreferences with no versioning, validation, encryption, or migration strategy.

## Scope

- Migrate from raw SharedPreferences JSON strings to a structured storage approach using a versioned schema. Store a `schema_version` key to enable future migrations
- Add data integrity checks: validate JSON structure when reading notifications from storage, and discard corrupted entries with a warning log rather than crashing
- Encrypt sensitive custom data payloads using Android Keystore + `EncryptedSharedPreferences` from the AndroidX Security library
- Add a migration mechanism: when the schema version changes, run a migration function that transforms old data to the new format. This prevents data loss on plugin updates
- Implement a `clearExpired()` method that removes one-shot notifications whose trigger time has passed (currently only done on boot)
- Add periodic cleanup: when any notification operation runs, opportunistically clean up expired entries
- Handle the edge case where `SharedPreferences` file is deleted by the user clearing app data — fail gracefully and log a warning instead of crashing
- Add a `exportNotifications()` / `importNotifications()` API for backup/restore scenarios

## Acceptance Criteria

- [ ] Schema versioning and migration works without data loss
- [ ] Corrupted entries are handled gracefully without crashes
- [ ] Sensitive data is encrypted at rest
- [ ] Expired notifications are cleaned up automatically
- [ ] Export/import works for backup scenarios
- [ ] No regressions in boot receiver notification restoration
