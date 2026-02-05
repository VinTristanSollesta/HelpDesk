<?php
// Database connection using PDO
// Update these values or set environment variables: DB_HOST, DB_NAME, DB_USER, DB_PASS
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'adieduph_helpdesk';
$user = getenv('DB_USER') ?: 'adieduph_adi25';
$pass = getenv('DB_PASS') ?: '@Adismcs-2025!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
	PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
	$pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
	http_response_code(500);
	// In production, log the error instead of echoing
	exit('Database connection failed: ' . $e->getMessage());
}

function getPDO()
{
	global $pdo;
	return $pdo;
}

?>
<?php

?>