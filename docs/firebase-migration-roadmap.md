# giftOH Firebase Migration Roadmap

This is the development map after Phase 5. Each phase has one owner, one checkpoint, and one rollback action. Do not start the next phase until its checkpoint passes.

## Current State

- Firebase Realtime Database connection works.
- Firebase repositories exist for users, funding, donations, approvals, appeals, logs, verification codes, and password resets.
- User authentication and verification flows can be switched between SQL and Firebase.
- SQL remains the default rollback path.
- Existing Firebase data must be backed up before every write migration.

Check the current runtime with:

```text
php artisan firebase:health
php artisan firebase:repositories
php artisan firebase:verification-smoke
```

## Phase 6: Funding Requests

### Goal

Move funding-request reads and writes to `funding_requests` while preserving the current user workflow.

### Firebase paths

```text
funding_requests/{requestId}
funding_documents/{documentId}
funding_categories/{categoryId}
request_statuses/{statusId}
```

### Development work

1. Add category and status repositories.
2. Add document repository.
3. Update the funding request controller to use `FirebaseFundingRepository`.
4. Preserve `user_id`, `category_id`, and `status_id`.
5. Replace Eloquent relationship loading with repository queries.
6. Keep uploaded files in Laravel storage until file migration is separately tested.
7. Verify AI score fields are stored as nested JSON.

### Checkpoint

A test user can create, view, and list a funding request from Firebase.

### Rollback

Set the funding feature back to its Eloquent controller path. Do not delete Firebase records.

## Phase 7: Approvals and Appeals

### Goal

Move administrative decisions to Firebase without losing auditability.

### Firebase paths

```text
funding_approvals/{approvalId}
funding_appeals/{appealId}
```

### Development work

1. Use `FirebaseApprovalRepository` for approval history.
2. Use `FirebaseAppealRepository` for appeals.
3. Store `approved_by` as the Firebase user ID.
4. Store `request_id` on every approval.
5. Update the funding request status in the same controlled operation.
6. Preserve approval notes, decision time, and AI details.
7. Add an audit log for every decision.

### Checkpoint

An admin can approve, reject, and appeal a request, and the request, approval, and audit records agree.

### Rollback

Switch admin reads and writes back to SQL. Keep Firebase decision records for comparison.

## Phase 8: Donations, Beneficiaries, Logs, and IoT

### Goal

Move secondary system data after the core funding workflow is stable.

### Firebase paths

```text
donations/{donationId}
beneficiary_profiles/{profileId}
iot_boxes/{iotId}
audit_logs/{logId}
activity_logs/{logId}
notification_logs/{notificationId}
```

### Development work

1. Add repositories for beneficiary profiles and IoT boxes.
2. Use the donation repository for donor and IoT relationships.
3. Store `user_id` and `iot_box_id` explicitly.
4. Write audit records after important changes.
5. Keep notification delivery separate from notification history.
6. Test IoT updates with a small sample payload first.

### Checkpoint

Donation history, beneficiary data, logs, and IoT display work without SQL reads.

### Rollback

Return reads and writes to the existing SQL models. Do not erase Firebase data.

## Phase 9: Import Existing SQL Data

### Goal

Copy existing SQL records to Firebase while keeping SQL unchanged.

### Dry run first

```text
php artisan firebase:import --table=users --limit=5
```

The command is dry-run by default. It reads SQL and reports counts without writing Firebase.

### Write selected data

```text
php artisan firebase:import --table=users --write
php artisan firebase:import --table=funding_request_tb --write
```

### Write all supported data

```text
php artisan firebase:import --write
```

### Important behavior

- SQL is never deleted.
- Existing SQL IDs are preserved.
- Dates are converted to ISO-8601 strings.
- JSON columns are decoded when valid.
- `role_permission` keys become `{role_id}_{permission_id}`.
- Use `--limit` for a small test import.
- Review Firebase Console after every selected import.

### Checkpoint

SQL and Firebase record counts match for every imported node, and relationship IDs resolve.

### Rollback

Do not delete SQL. Remove only the imported Firebase test node if a test import was wrong.

## Phase 10: Dual Write

### Goal

Write new records to both SQL and Firebase temporarily.

### Development work

1. Write the SQL record first.
2. Write the Firebase record using the same logical ID.
3. Log Firebase failures without hiding SQL failures.
4. Compare records periodically.
5. Do not dual-write verification codes unless one system is clearly authoritative.

### Checkpoint

New test records match in both systems for at least one complete user workflow.

### Rollback

Disable Firebase writes and continue with SQL.

## Phase 11: Firebase Read Cutover

### Goal

Read from Firebase while retaining SQL writes temporarily.

### Development work

1. Switch one page or feature at a time.
2. Compare Firebase result with SQL result during testing.
3. Monitor missing relationships and status mismatches.
4. Keep a feature-level fallback while validating.

### Checkpoint

All selected pages show correct Firebase data for existing and new records.

### Rollback

Switch reads back to SQL without changing the stored records.

## Phase 12: Firebase Write Cutover

### Goal

Make Firebase the primary data store.

### Development work

1. Disable SQL writes for the migrated feature.
2. Keep SQL backups and read-only comparison tools.
3. Test create, update, delete, and authorization behavior.
4. Monitor Laravel logs and Firebase usage.

### Checkpoint

A full user journey works using Firebase reads and writes only.

### Rollback

Restore the previous feature controller path and use the SQL backup if records must be restored.

## Phase 13: Cleanup

Only begin after all features pass acceptance testing.

1. Remove unused SQL queries from migrated features.
2. Keep migrations as historical documentation.
3. Remove database-backed sessions only after session behavior is replaced and tested.
4. Remove database-backed cache only after cache behavior is replaced and tested.
5. Remove database-backed queues only after queue behavior is replaced and tested.
6. Keep `firebase:health` and smoke commands.
7. Keep SQL backups and a rollback branch.

## Development Concepts to Understand

### Repository

A repository translates application operations into Firebase paths. Controllers should not contain raw Firebase SDK calls.

### Node

A top-level JSON collection such as `users` or `funding_requests`.

### Record key

The child key under a node, such as `users/{id}`. Preserve IDs while importing so relationships remain valid.

### Relationship ID

Firebase does not enforce foreign keys. Store fields such as `user_id`, `request_id`, and `approved_by` and query them with indexes.

### Index

A Firebase rule required for filtered queries. For example, email login needs `.indexOn: ["email"]` under `users`.

### Service account

The private JSON credential used by Laravel's server-side Admin SDK. Never place it in `public/` or expose it to browser JavaScript.

### Source of truth

During migration, choose one authoritative writer. SQL is the current rollback source. Firebase becomes authoritative only after read and write cutover passes.

## What You Should Learn in Order

1. Read the Firebase node map in `docs/firebase-data-map.md`.
2. Read `FirebaseRepository.php` and one feature repository.
3. Run `firebase:health`.
4. Run `firebase:verification-smoke`.
5. Run the import command without `--write`.
6. Import five users with `--write` only after reviewing the dry run.
7. Test one repository before changing a controller.
8. Complete funding migration before approvals.
9. Complete core workflows before logs and IoT.
10. Switch providers only after Firebase data exists and indexes are published.

## Stop Conditions

Stop and investigate if:

- A Firebase query says an index is missing.
- A Firebase record has a missing relationship ID.
- A write succeeds in Firebase but fails in SQL during dual-write.
- A password or verification code appears in plain text.
- The Firebase credential file is outside private storage.
- A controller still writes both systems without a documented authority.
- A migration changes IDs unexpectedly.
