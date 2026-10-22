<?php
/**
 * tests/guard.php - Penjaga konfigurasi DB untuk skrip tes & skrip CLI.
 *
 * Menolak eksekusi bila konfigurasi bukan test DB.
 * Tidak ada fallback ke server dev (port 3306).
 */

/**
 * Hasil konsistensi guard-fungsi — pesan kedua yang tercetak sebelum exit.
 * Default = pesan run.php; skrip bisa menentukan pesan sendiri.
 */
const TEST_GUARD_NOTICE_CONFIG = 'CHARACTERIZATION: SKIPPED (guard rejected configuration)';
const TEST_GUARD_NOTICE_CONNECTION = 'CHARACTERIZATION: SKIPPED (guard rejected connection)';

/** Apakah lingkungan menunjuk ke test DB yang sebenarnya? */
function test_database_env_valid(): bool {
    return getenv('APP_ENV') === 'test'
        && getenv('DB_HOST') === '127.0.0.1'
        && (int)getenv('DB_PORT') === 3307
        && (bool)getenv('DB_DATABASE')
        && str_ends_with((string)getenv('DB_DATABASE'), '_test');
}

/**
 * Guard untuk skrip tes dan run.php (env hanya).
 * Menerima string opsional untuk pesan kedua.
 * Exit 2 pada refusal dengan pesan yang tepat untuk run.php.
 */
function require_test_database_guard(string $notice = TEST_GUARD_NOTICE_CONFIG): void {
    if (!test_database_env_valid()) {
        echo "GUARD: FAIL\n";
        echo $notice . "\n";
        exit(2);
    }
}

/**
 * Setelah DB terkoneksi, verifikasikan server yang benar.
 * Exit 2 bila SELECT DATABASE() atau @@port salah.
 * Prints "GUARD: PASS" ketika valid.
 */
function require_test_database_connection(mysqli $connection, string $notice = TEST_GUARD_NOTICE_CONNECTION): void {
    $result = $connection->query('SELECT DATABASE(), @@port');
    if ($result === false) {
        echo "GUARD: FAIL\n";
        echo $notice . "\n";
        exit(2);
    }
    $row = $result->fetch_row();
    $result->free();
    $ok = is_array($row)
        && isset($row[0], $row[1])
        && $row[0] !== ''
        && str_ends_with((string)$row[0], '_test')
        && (int)$row[1] === 3307;
    if (!$ok) {
        echo "GUARD: FAIL\n";
        echo $notice . "\n";
        exit(2);
    }
    echo "GUARD: PASS\n";
}
