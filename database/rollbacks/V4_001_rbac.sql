-- V4_001 RBAC rollback.
-- WARNING: removes RBAC assignments and role metadata.
-- Run only on an isolated database after owner approval.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE admin DROP COLUMN IF EXISTS role_id;
