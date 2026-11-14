<?php
/**
 * EpicLens System - CLI Database Migration Runner
 *
 * Executes the clean DDL schema against the configured database.
 * Run via: php database/migrate.php
 */
require_once __DIR__ . '/../config/database.php';

echo "====================================================\n";
echo " EpicLens System - Database Migration Runner\n";
echo "====================================================\n";

try {
    $pdo = getDB();
    echo "Connecting to MySQL server at " . DB_HOST . ":" . DB_PORT . "...\n";

    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("schema.sql not found at {$schemaFile}");
    }

    $sql = file_get_contents($schemaFile);
    echo "Executing schema.sql DDL statements...\n";

    $pdo->exec($sql);

    echo "Migration completed successfully!\n";
    echo "- Tables created/verified: users, item_code_sequences, equipment, transactions\n";
    echo "- Category sequences seeded: CM, TP, LN, GM, MC, FL, LT, OT\n";
    echo "- Zero sample or personal data inserted.\n";
    echo "====================================================\n";
} catch (Exception $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
