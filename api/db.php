<?php
// db.php
// Handles connection to the local MySQL database for the application

function getDbConnection() {
    // Check if we're in a testing/dev environment
    if (getenv('APP_ENV') === 'test') {
        $dsn = "sqlite:" . __DIR__ . "/../data/test.sqlite";
        try {
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Recreate basic schema for SQLite for tests
            $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (`setting_key` TEXT PRIMARY KEY, `setting_value` TEXT)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `permits` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `permit_number` TEXT, `address` TEXT, `expires` TEXT, `details` TEXT, `created_at` TEXT)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `locations` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `lat` REAL, `lng` REAL, `name` TEXT)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `chores` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `name` TEXT, `display_order` INTEGER DEFAULT 0)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `everyday_chores` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `name` TEXT, `display_order` INTEGER DEFAULT 0)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `special_chores` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `details` TEXT)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `manual_events` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `details` TEXT)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `announcements` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `details` TEXT)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `stations` (`id` TEXT PRIMARY KEY, `number` TEXT, `address` TEXT, `rooms_json` TEXT)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `apparatus` (`id` TEXT PRIMARY KEY, `name` TEXT, `abbr` TEXT, `category` TEXT, `type` TEXT, `status` TEXT, `display_order` INTEGER DEFAULT 0)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `schedule_headers` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `title` TEXT, `display_order` INTEGER DEFAULT 0)");

            return $pdo;
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    $configFile = __DIR__ . '/../config.json';
    $config = [];
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true) ?: [];
    }

    $host = $config['db_host'] ?? 'localhost';
    $db   = $config['db_name'] ?? '';
    $user = $config['db_user'] ?? '';
    $pass = $config['db_pass'] ?? '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}
