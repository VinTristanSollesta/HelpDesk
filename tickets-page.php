<?php
// Tickets page — uses the shared header/nav which sets active link based on $_SERVER
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Tickets — Ateneo HelpDesk</title>
	<link rel="stylesheet" href="dist/styles.css">
	<meta name="robots" content="noindex">
</head>
<body class="bg-gray-100 text-gray-800">
	<div class="min-h-screen">
		<?php require_once __DIR__ . '/includes/header.php'; ?>

		<header class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
			<h1 class="text-3xl font-bold">Tickets</h1>
			<p class="mt-2 text-sm text-gray-600">List and manage support tickets.</p>
		</header>

		<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
			<section class="bg-white p-6 rounded-lg shadow-sm">
				<p class="text-sm text-gray-700">No tickets yet — this is a placeholder.</p>
			</section>
		</main>

		<footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-500">
			© 2026 Ateneo de Iloilo — HelpDesk
		</footer>
	</div>
</body>
</html>
