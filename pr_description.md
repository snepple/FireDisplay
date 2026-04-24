## Migrate dynamic data from JSON files to MySQL Database

This PR implements the requested transition of dynamic application data (burn permits, fire danger, locations, chores, and events) from flat JSON files to a local MySQL database, optimizing performance and maintainability for the Bluehost environment.

### Changes Included
- **Database Connection & Schema:** Introduced `api/db.php` (utilizing PDO for MySQL with an SQLite fallback for the Jest testing environment) and created `api/db_schema.sql` outlining the necessary tables.
- **Data Migration Script:** Created `api/migrate_data.php`. Running this script once will safely port all existing data from `config.json` and the `data/*.json` files into the newly created database tables.
- **Refactored Read Endpoints:** Updated `api/get_config.php`, `api/get_permits.php`, `api/get_locations.php`, and `api/get_fire_danger.php` to query the MySQL database instead of the legacy JSON files. `get_config.php` correctly merges database arrays with sensitive configuration data stored locally while securely redacting database credentials to prevent exposure.
- **Admin Interface Overhaul:** Refactored `admin.php` to fetch and render the user interface lists dynamically from MySQL. The POST handlers have been updated so that adding, editing, or deleting apparatus, stations, headers, events, chores, and announcements actively interacts with the database while preserving the core system properties within `config.json`.
- **Data Ingestion Refactor:** Refactored `api/process_email.php` and `api/save_locations.php` to execute database inserts/deletions instead of appending JSON strings to flat files.
- **Testing:** Updated `tests/get_permits.test.js` and `tests/get_fire_danger.test.js` to utilize ephemeral SQLite connections during integration tests.

### Instructions for Deployment
1. Update `config.json` manually with `db_host` (typically `localhost`), `db_name`, `db_user`, and `db_pass`.
2. Run `api/init_db.php` or manually import `api/db_schema.sql` via PHPMyAdmin to establish the database schema.
3. Run `api/migrate_data.php` via browser or CLI once to port the existing flat-file data over to the MySQL tables.
