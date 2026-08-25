---

description: "Dependency-ordered remediation tasks from the FixTrack full error audit"
---

# FixTrack Error Audit Remediation Tasks

This backlog is a fallback generated from the confirmed source, log, test, build, and local-schema evidence because `.specify/feature.json` and a feature directory do not exist. The live MySQL comparison remains blocked until the configured database is reachable.

## Phase 1: Evidence and Environment Setup

**Purpose**: Establish a trustworthy MySQL baseline and preserve the observed failures before changing production data.

- [X] T001 [P] Capture `php artisan db:show --json`, `php artisan migrate:status`, table definitions, indexes, and foreign keys for the configured MySQL database in `storage/logs/laravel.log` and `output/error-audit/`
- [X] T002 [P] Recheck the current Laravel error signatures and trigger timestamps in `storage/logs/laravel.log` and `storage/logs/browser.log`, separating current failures from historical entries
- [X] T003 [P] Reconcile required runtime keys and optional integration keys in `.env`, `.env.example`, `config/services.php`, and `config/auth.php`, then audit history and rotate any deployed `APP_KEY` or passkey secret exposed by the old example

## Phase 2: Foundational Database Reliability

**Purpose**: Put the database schema ahead of application code; all booking and dispatch work depends on this phase.

- [ ] T004 Apply and verify every pending migration in `database/migrations/` against the confirmed FixTrack MySQL database, including `bookings.idempotency_key`, `booking_status_histories`, `notifications`, `quotations`, user profile columns, dashboard indexes, and `technician_request_declines`
- [X] T005 [P] Add a MySQL-backed schema contract test in `tests/Feature/DatabaseSchemaAuditTest.php` covering every runtime-required table, column, unique index, and foreign key used by `app/Models/`, `app/Livewire/`, and `app/Http/Controllers/RealtimeController.php`
- [X] T006 [P] Add a deployment/CI migration-status gate in `.github/workflows/ci.yml` and `composer.json` that fails before serving code when required migrations are pending or the database schema contract fails
- [X] T007 [P] Add a migration smoke test in `tests/Feature/DatabaseMigrationTest.php` that builds a fresh MySQL-compatible schema from `database/migrations/` and exercises booking creation, active-booking polling, decline recording, and status-history writes

**Checkpoint**: The configured MySQL schema matches the code, and a deployment cannot silently run ahead of migrations.

**Current gate**: T004 remains open until a writable PHP process can reach the confirmed FixTrack MySQL database and apply the two pending migrations.

**External verification gates**: T012's booking-first lock order is implemented, but live concurrent MySQL coverage remains open. T016 still needs the decline table and unique constraint verified on that database. T024 and T026 need a reachable served URL for browser smoke and historical-error revalidation.

## Phase 3: User Story 1 - Customers Can Reliably Book and Track Service (Priority: P1) 🎯 MVP

**Goal**: Booking creation and customer tracking must survive schema drift, retries, and dependency failures without losing the request.

**Independent test**: With the confirmed MySQL schema, create a booking, repeat the same idempotency key, poll customer tracking, and verify one booking plus a consistent status history.

- [X] T008 [US1] Add MySQL integration coverage for idempotent booking creation and duplicate-key recovery in `tests/Feature/CustomerWorkspaceTest.php` and `app/Livewire/Customer/ModulePage.php`
- [X] T009 [US1] Make customer active-booking tracking tolerate a missing optional technician profile field while the migration rollout is incomplete in `app/Http/Controllers/RealtimeController.php` and `app/Models/User.php`
- [X] T010 [US1] Make review creation idempotent under concurrent submissions and convert the `reviews.booking_id` unique violation into a stable user-facing response in `app/Livewire/Customer/ModulePage.php` and `tests/Feature/CustomerWorkspaceTest.php`
- [X] T011 [US1] Make walk-in queue-number and booking/support-reference generation retry or return a validation response on unique collisions in `app/Livewire/Customer/ModulePage.php` and `tests/Feature/CustomerWorkspaceTest.php`

**Checkpoint**: A customer can book, retry, track, and review without an unknown-column crash, duplicate record, or opaque 500 response.

## Phase 4: User Story 2 - Dispatch and Status Changes Preserve Assignment Integrity (Priority: P1)

**Goal**: Concurrent technician and admin actions must not create conflicting assignments, illegal status transitions, duplicate payment records, or orphaned technicians.

**Independent test**: Run MySQL concurrency tests for accept/assign/availability/status actions and verify one winning assignment, a valid status history, and consistent technician availability.

- [ ] T012 [US2] Define and enforce one lock order for booking and technician rows across `app/Livewire/Technician/ModulePage.php` and `app/Livewire/SuperAdmin/ModulePage.php`, then add deadlock/concurrency coverage in `tests/Feature/BookingInvariantsTest.php`
- [X] T013 [US2] Audit existing `payments` duplicates and add a safe unique `payments.booking_id` migration only after the audit passes in `database/migrations/` and `tests/Feature/BookingInvariantsTest.php`
- [X] T014 [P] [US2] Lock and transaction-wrap super-admin availability, walk-in status/counter, and payment-status mutations in `app/Livewire/SuperAdmin/ModulePage.php` with regression coverage in `tests/Feature/BookingLifecycleTest.php`
- [X] T015 [P] [US2] Add invariant tests that reject unassigned `en_route`, `in_progress`, and `completed` bookings and verify no payment is created on a rejected transition in `tests/Feature/BookingLifecycleTest.php`
- [ ] T016 [US2] Verify the decline table migration, model relation, and unique constraint on the live MySQL database in `database/migrations/2026_08_21_103558_create_technician_request_declines_table.php`, `app/Models/TechnicianRequestDecline.php`, and `tests/Feature/TechnicianWorkspaceTest.php`

**Checkpoint**: Assignment and lifecycle operations have one authoritative locking strategy and database-backed uniqueness for financial and dispatch invariants.

## Phase 5: User Story 3 - External Integrations Fail Safely and Visibly (Priority: P2)

**Goal**: Maps, geocoding, location publishing, and OAuth failures must degrade visibly without stale-success UI or swallowed errors.

**Independent test**: Inject timeout, non-2xx, malformed, and offline responses for each external call and verify bounded retry/backoff, visible state, and a useful log entry.

- [X] T017 [US3] Add timeout, non-2xx handling, and an explicit route-error state for OSRM in `resources/js/customer-map.js` and cover it in `tests/Feature/Phase4ReliabilityTest.php`
- [X] T018 [US3] Add bounded retry or request throttling plus a user-visible failure state for Nominatim address search in `app/Livewire/Customer/ModulePage.php` and `tests/Feature/CustomerWorkspaceTest.php`
- [X] T019 [P] [US3] Replace the empty location-update rejection path with a stale-location indicator and structured log event in `resources/js/technician-location.js` and `app/Livewire/Technician/ModulePage.php`
- [X] T020 [P] [US3] Ensure Leaflet CDN and tile failures leave a recoverable map state with a retry action in `resources/js/customer-map.js` and `tests/Feature/CustomerWorkspaceTest.php`
- [X] T021 [US3] Verify Google OAuth timeout, non-2xx, missing-config, and invalid-state behavior and document the optional keys in `app/Http/Controllers/Auth/GoogleAuthController.php`, `config/services.php`, and `.env.example`

**Checkpoint**: An external provider outage produces a bounded, visible failure instead of a hang, stale location, or silent route disappearance.

## Phase 6: User Story 4 - Operators Have Trustworthy Diagnostics (Priority: P2)

**Goal**: Static analysis, runtime telemetry, and browser smoke checks must identify regressions before users encounter them.

**Independent test**: Run the project quality commands and a served-browser smoke path; all required gates pass or report actionable failures.

- [X] T022 [US4] Resolve the current PHPStan errors, beginning with Eloquent generic types, dynamic model maps, nullable authenticated users, and response JSON types in `app/Http/Controllers/RealtimeController.php`, `app/Livewire/Customer/ModulePage.php`, `app/Livewire/SuperAdmin/ModulePage.php`, `app/Livewire/Technician/ModulePage.php`, and `app/Models/`
- [X] T023 [US4] Correct slow-query telemetry so the logged field distinguishes cumulative query-time threshold events from individual query duration in `app/Providers/AppServiceProvider.php` and `tests/Feature/Phase5ScalabilityTest.php`
- [ ] T024 [P] [US4] Add a served-app browser smoke test for registration, customer booking, technician request acceptance, admin status update, and map rendering in `tests/Browser/` or the existing browser-test location
- [X] T025 [P] [US4] Add structured error context and alertable event names for realtime polling failures, external map failures, and database connection failures in `resources/js/realtime.js`, `resources/js/customer-map.js`, and `bootstrap/app.php`
- [ ] T026 [US4] Revalidate the historical Alpine and Leaflet browser errors against the current served URL and record pass/fail evidence in `output/error-audit/`

**Checkpoint**: CI and a real served browser path catch schema, static-analysis, integration, and frontend runtime regressions.

## Dependencies and Execution Order

- Phase 1 is evidence-only and can begin immediately.
- Phase 2 depends on T001 and blocks all user stories because the live schema is currently unverified.
- User Story 1 depends on T004 and T005.
- User Story 2 depends on T004 and should follow the schema contract before concurrency testing.
- User Story 3 can begin after the current source inventory, but its browser verification depends on T024.
- User Story 4 can begin in parallel after Phase 1; its CI gates should be enabled only after existing failures are either fixed or explicitly baselined.
- T012 and T013 must be completed before declaring dispatch/payment integrity complete.

## Parallel Opportunities

- T002 and T003 can run in parallel with the read-only MySQL evidence capture in T001.
- T005, T006, and T007 can be prepared in parallel after the schema inventory is known.
- T014 and T015 are independent of the external-integration work in Phase 5.
- T019, T020, and T023 touch separate failure surfaces and can be implemented in parallel.

## MVP Scope

Complete T001, T004, T005, T008, T009, T012, T013, and T015 first. This restores the booking/dispatch data path and proves it against the actual MySQL schema before spending time on polish or provider resiliency.

## Verification Commands

- `php artisan test --compact --no-ansi`
- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/phpstan analyse --debug --no-progress --error-format=table`
- `npm run build`
- `php artisan view:cache --no-interaction --no-ansi`
- `php artisan db:show --no-interaction --json`
- `php artisan migrate:status --no-interaction --no-ansi`
