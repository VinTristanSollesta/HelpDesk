<?php
require_once __DIR__ . '/dbConnect.php';

try {
	$pdo = getPDO();
	$stmt = $pdo->query('SELECT NOW() AS now');
	$row = $stmt->fetch();
	echo 'Connected. Server time: ' . htmlspecialchars($row['now']);
} catch (Exception $e) {
	echo 'Connection error: ' . htmlspecialchars($e->getMessage());
}

?>
