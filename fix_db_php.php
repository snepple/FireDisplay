<?php
$db_file = 'api/db.php';
$db_content = file_get_contents($db_file);

// SQLite fallback in test mode needs to be fixed!
$old_test_schema = <<<'OLD'
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
OLD;

// I think the previous replace messed up the backticks or quotes, let's just do it directly.
