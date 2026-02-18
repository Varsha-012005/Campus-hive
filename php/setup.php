<?php
require_once __DIR__ . '/db_connect.php';

// Run schema.sql to create tables
$schema = file_get_contents(__DIR__ . '/../schema.sql');
$pdo->exec($schema);

echo "Database setup complete!";
?>