-- E2E delta rollback.
-- WARNING: this deletes login rate-limit history.
-- Do not run on a database that already contains production login data.

DROP TABLE IF EXISTS login_attempts;
