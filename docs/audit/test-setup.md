# Test database setup

## Separate instance

Tests use a separate native MySQL 9.4 instance on `127.0.0.1:3307`.
The development XAMPP MySQL remains on port `3306`.

Test datadir:

```text
/Users/haedarwezzer/mysql_test_data
```

Start test server:

```bash
/opt/homebrew/Cellar/mysql/9.4.0_1/bin/mysqld \
  --datadir=/Users/haedarwezzer/mysql_test_data \
  --port=3307 \
  --socket=/Users/haedarwezzer/mysql_test_data/mysql_test.sock \
  --pid-file=/Users/haedarwezzer/mysql_test_data/mysql_test.pid \
  --log-error=/Users/haedarwezzer/mysql_test_data/mysql_test.err \
  --bind-address=127.0.0.1
```

Create and seed test database:

```bash
/opt/homebrew/Cellar/mysql/9.4.0_1/bin/mysql -u root -h 127.0.0.1 -P 3307 \
  -e "CREATE DATABASE IF NOT EXISTS db_ujikom_test CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

/opt/homebrew/Cellar/mysql/9.4.0_1/bin/mysql -u root -h 127.0.0.1 -P 3307 db_ujikom_test \
  < tests/seed_test.sql
```

Verify server separation:

```bash
mysql -u root -h 127.0.0.1 -P 3306 -e "SELECT @@port, DATABASE(); SHOW DATABASES;"
mysql -u root -h 127.0.0.1 -P 3307 -e "SELECT @@port, DATABASE(); SHOW DATABASES;"
```

Expected: development server reports port `3306`; test server reports port `3307`.

## Test environment

Set local variables without committing credentials:

```bash
export APP_ENV=test
export DB_HOST=127.0.0.1
export DB_PORT=3307
export DB_DATABASE=db_ujikom_test
export DB_USER=root
export DB_PASSWORD=
```

Test mode refuses incomplete connection settings and has no fallback to XAMPP socket or port `3306`.
The runner also verifies `SELECT DATABASE()` ends with `_test` and checks configured port `3307`.
