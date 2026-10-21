-- db/migration_v3_security.sql
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(100),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (ip_address),
    INDEX (username)
);
