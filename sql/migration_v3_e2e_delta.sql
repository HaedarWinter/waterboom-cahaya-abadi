-- E2E delta: missing login rate-limit table.
-- ip_address: admin/handlers/cek_login.php:17, admin/handlers/cek_login.php:22, admin/handlers/cek_login.php:72, admin/handlers/cek_login.php:101.
-- username: admin/handlers/cek_login.php:18, admin/handlers/cek_login.php:22, admin/handlers/cek_login.php:72, admin/handlers/cek_login.php:101.
-- attempt_time: admin/handlers/cek_login.php:19, admin/handlers/cek_login.php:22.
-- No USE statement: caller must select the intended _test database explicitly.

CREATE TABLE IF NOT EXISTS login_attempts (
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50) NOT NULL,
    attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_ip_time (ip_address, attempt_time),
    INDEX idx_login_attempts_username_time (username, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
