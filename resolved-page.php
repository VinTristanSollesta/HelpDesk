<?php
// Tickets page — uses the shared header/nav which sets active link based on $_SERVER
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Resolved — Ateneo HelpDesk</title>
	<link rel="stylesheet" href="dist/styles.css">
	<meta name="robots" content="noindex">
</head>
<body class="bg-gray-100 text-gray-800">
	<div class="min-h-screen">
		<?php require_once __DIR__ . '/includes/header.php'; ?>

		<header class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
			<h1 class="text-3xl font-bold">Resolved Tickets</h1>
			<p class="mt-2 text-sm text-gray-600">List and manage resolved support tickets.</p>
		</header>

		<?php
		$pdo = null;
		require_once __DIR__ . '/dbConnect.php';
		$pdo = getPDO();
		$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		$countStmt = $pdo->query('SELECT COUNT(*) FROM resolution');
		$total = (int)$countStmt->fetchColumn();
		$pages = max(1, ceil($total / $perPage));

		// Fetch resolved tickets with agent and ticket info
		$stmt = $pdo->prepare('SELECT r.resolution_ID, r.ticket_ID, r.agent_ID, r.resolution, r.resolved_at, t.subject, a.name AS agent_name
		FROM resolution r
		LEFT JOIN Tickets t ON r.ticket_ID = t.ticket_ID
		LEFT JOIN Agents a ON r.agent_ID = a.agent_id
		ORDER BY r.resolved_at DESC
		LIMIT :limit OFFSET :offset');
		$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();
		$resolved = $stmt->fetchAll(PDO::FETCH_ASSOC);
		function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
		?>
		<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
			<section class="bg-white p-6 rounded-lg shadow-sm">
				<?php if (empty($resolved)): ?>
					<p class="text-sm text-gray-700">No resolved tickets found.</p>
				<?php else: ?>
					<table class="min-w-full text-sm">
						<thead>
							<tr class="border-b">
								<th class="py-2 px-3 text-left">Subject</th>
								<th class="py-2 px-3 text-left">Resolved By</th>
								<th class="py-2 px-3 text-left">Resolved At</th>
								<th class="py-2 px-3 text-left">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($resolved as $r): ?>
								<tr class="border-b">
									<td class="py-2 px-3"><?php echo h($r['subject']); ?></td>
									<td class="py-2 px-3"><?php echo h($r['agent_name']); ?></td>
									<td class="py-2 px-3"><?php echo h($r['resolved_at']); ?></td>
									<td class="py-2 px-3">
										<a href="ticket-conversation.php?ticket_id=<?php echo h($r['ticket_ID']); ?>" class="text-indigo-600 hover:underline">View</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<div class="mt-4 flex justify-between items-center">
						<div class="text-xs text-gray-500">Page <?php echo $page; ?> of <?php echo $pages; ?></div>
						<div class="space-x-2">
							<?php if ($page > 1): ?>
								<a href="?page=<?php echo $page - 1; ?>" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 text-xs">Previous</a>
							<?php endif; ?>
							<?php if ($page < $pages): ?>
								<a href="?page=<?php echo $page + 1; ?>" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 text-xs">Next</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</section>
		</main>

		<footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-500">
			© 2026 Ateneo de Iloilo — HelpDesk
		</footer>
	</div>
</body>
</html>
