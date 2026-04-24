<?php
// init_db.php
require_once __DIR__ . '/db.php';

try {
    $pdo = getDbConnection();

    // Read the schema file
    $sql = file_get_contents(__DIR__ . '/db_schema.sql');

    if ($sql === false) {
        die("Could not read db_schema.sql\n");
    }

    // Execute the SQL to create tables
    $pdo->exec($sql);
    echo "Database schema initialized successfully.\n";

} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage() . "\n");
}
