# Firebase Realtime Database Map

This document defines the Phase 2 data contract for migrating giftOH from SQL/Eloquent to Firebase Realtime Database.

## Rules

- Firebase Realtime Database uses JSON paths, not SQL tables.
- Keep the existing numeric SQL IDs during the first migration so relationships remain traceable.
- Every record is stored below a named top-level node and keyed by its ID.
- Store relationship IDs explicitly; Firebase does not create foreign keys or Eloquent relationships.
- Store timestamps as ISO-8601 strings, for example `2026-08-20T10:00:00Z`.
- Store JSON columns such as `changes`, `required_documents`, and `ai_scoring_details` as nested JSON objects or arrays.
- Do not store plain verification codes. Store only their hashes.

## Top-Level Nodes

| Existing SQL source | Firebase node | Record key | Main relationships |
| --- | --- | --- | --- |
| `users` | `users` | `id` | owns requests, donations, IoT boxes, logs |
| `roles` | `roles` | `id` | connected to permissions through `role_permissions` |
| `permissions` | `permissions` | `id` | connected to roles through `role_permissions` |
| `role_permission` | `role_permissions` | `{roleId}_{permissionId}` | `role_id`, `permission_id` |
| `request_status_tb` | `request_statuses` | `status_id` | referenced by funding requests |
| `funding_categories_tb` | `funding_categories` | `category_id` | referenced by funding requests |
| `funding_request_tb` | `funding_requests` | `id` | `user_id`, `category_id`, `status_id` |
| `funding_documents` | `funding_documents` | `document_id` | `request_id`, `verified_by` |
| `funding_approvals` | `funding_approvals` | `approval_id` | `request_id`, `approved_by` |
| `funding_appeals` | `funding_appeals` | `appeal_id` | original and resubmitted request IDs |
| `donations` | `donations` | `donation_id` | `iot_box_id`, `user_id` |
| `beneficiary_profiles` | `beneficiary_profiles` | `beneficiary_id` | `user_id`, `verified_by` |
| `iot_boxes` | `iot_boxes` | `iot_id` | owned by `user_id` |
| `audit_logs` | `audit_logs` | `log_id` | `user_id`, `subject_id` |
| `activity_logs` | `activity_logs` | `id` | `user_id` |
| `notification_logs` | `notification_logs` | `notification_id` | `user_id`, related record ID |
| verification tables | `verification_codes` | `{type}/{emailKey}` | email and verification type |
| `password_reset_tokens` | `password_reset_tokens` | email key | email |

## Record Shapes

### `users/{id}`

```json
{
  "fname": "John",
  "lname": "Smith",
  "mname": null,
  "email": "john@example.com",
  "email_verified_at": null,
  "password": "bcrypt-hash",
  "phone": "09123456789",
  "address": "Address",
  "gender": "male",
  "date_of_birth": "2000-01-01",
  "profile_picture": null,
  "role": "user",
  "is_active": true,
  "last_login": null,
  "last_logout": null,
  "remember_token": null,
  "created_at": "2026-08-20T10:00:00Z",
  "updated_at": "2026-08-20T10:00:00Z"
}
```

### `funding_requests/{id}`

```json
{
  "user_id": 1,
  "category_id": 1,
  "status_id": 1,
  "title": "Medical assistance",
  "description": "Request details",
  "doc_image": null,
  "id_image": null,
  "bank_statement": null,
  "amount_requested": 5000.0,
  "amount_paid": 0.0,
  "approved_by": null,
  "ai_score": null,
  "ai_score_breakdown": {},
  "admin_notes": null,
  "approved_at": null,
  "rejected_at": null,
  "completed_at": null,
  "created_at": "2026-08-20T10:00:00Z",
  "updated_at": "2026-08-20T10:00:00Z"
}
```

### Related records

Use these relationship fields:

- `funding_documents/{documentId}.request_id` points to `funding_requests/{requestId}`.
- `funding_approvals/{approvalId}.request_id` points to `funding_requests/{requestId}`.
- `funding_approvals/{approvalId}.approved_by` points to `users/{userId}`.
- `funding_appeals/{appealId}.original_request_id` points to the original request.
- `funding_appeals/{appealId}.resubmitted_request_id` points to the replacement request.
- `donations/{donationId}.iot_box_id` points to `iot_boxes/{iotId}`.
- `donations/{donationId}.user_id` points to `users/{userId}`.
- `beneficiary_profiles/{profileId}.user_id` points to `users/{userId}`.
- `beneficiary_profiles/{profileId}.verified_by` points to `users/{userId}`.
- `audit_logs/{logId}.user_id` points to `users/{userId}`.
- `notification_logs/{notificationId}.user_id` points to `users/{userId}`.

## Verification Paths

Use URL-safe email keys. The repository should encode emails consistently, for example with a URL-safe Base64 value or a SHA-256 hash.

```text
verification_codes/login/{emailKey}
verification_codes/register/{emailKey}
verification_codes/transaction/{emailKey}
verification_codes/approval/{emailKey}
password_reset_tokens/{emailKey}
```

Example record:

```json
{
  "email": "john@example.com",
  "token": "bcrypt-hash",
  "created_at": "2026-08-20T10:00:00Z"
}
```

## Migration Naming Notes

The Firebase names are intentionally normalized from the current SQL names:

- `funding_request_tb` becomes `funding_requests`.
- `request_status_tb` becomes `request_statuses`.
- `funding_categories_tb` becomes `funding_categories`.
- `fundin_iot_box_tb` and `iot_boxes` should become the single node `iot_boxes`.

The SQL migrations remain unchanged and are still the rollback source during migration.

## Phase 2 Checkpoint

Phase 2 is complete when:

1. `config/firebase.php` is the single source of truth for node names.
2. This document is reviewed against every migration.
3. Every relationship has an explicit ID field.
4. No Firebase data has been deleted or overwritten by this phase.
5. Repositories can use these paths in Phase 3.
