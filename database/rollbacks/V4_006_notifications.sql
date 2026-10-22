-- Rollback V4_006: Notifications

START TRANSACTION;

DROP TABLE IF EXISTS `wa_templates`;
DROP TABLE IF EXISTS `notification_outbox`;

COMMIT;