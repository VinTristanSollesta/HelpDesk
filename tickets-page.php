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
		<?php require_once __DIR__ . '/includes/auth.php'; require_agent(); ?>
		<?php require_once __DIR__ . '/includes/header.php'; ?>

		<header class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
			<h1 class="text-3xl font-bold">Tickets</h1>
			<p class="mt-2 text-sm text-gray-600">List and manage support tickets.</p>
            <button onclick="window.location.href='new-ticket.php'" class="mt-4 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-500 hover:bg-blue-700">New Ticket</button>
		</header>

		<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="bg-white p-6 rounded-lg shadow-sm">
                <!-- Search form (GET) -->
                <form method="get" action="" class="mb-4">
                    <div class="max-w-md flex items-center">
                        <input name="q" type="search" placeholder="Search by subject, client, or agent" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" />
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-r-md hover:bg-indigo-700">Search</button>
                        <?php if (!empty($_GET['q'])): ?>
                            <a href="tickets-page.php" class="ml-2 text-sm text-gray-600">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php
                require_once __DIR__ . '/dbConnect.php';
                $pdo = getPDO();

                $q = trim((string)($_GET['q'] ?? ''));

                if ($q !== '') {
                    $sql = "SELECT t.ticket_ID, t.public_token, t.subject, t.created_at, c.full_name AS client_name, a.name AS agent_name, s.label AS status_label, s.hexcolor AS status_hex
                        FROM Tickets t
                        LEFT JOIN Clients c ON t.client_ID = c.client_ID
                        LEFT JOIN Agents a ON t.agent_id = a.agent_id
                        LEFT JOIN Status s ON t.status_id = s.status_id
                        WHERE (t.subject LIKE :q OR c.full_name LIKE :q OR a.name LIKE :q)
                        ORDER BY t.created_at DESC
                        LIMIT 200";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':q' => "%{$q}%"]);
                } else {
                    $sql = "SELECT t.ticket_ID, t.public_token, t.subject, t.created_at, c.full_name AS client_name, a.name AS agent_name, s.label AS status_label, s.hexcolor AS status_hex
                        FROM Tickets t
                        LEFT JOIN Clients c ON t.client_ID = c.client_ID
                        LEFT JOIN Agents a ON t.agent_id = a.agent_id
                        LEFT JOIN Status s ON t.status_id = s.status_id
                        ORDER BY t.created_at DESC
                        LIMIT 100";

                    $stmt = $pdo->query($sql);
                }

                $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                            </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-500" colspan="7">No tickets found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php
                                    $hex = $ticket['status_hex'] ?? '#e5e7eb';
                                    $hex = preg_match('/^#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $hex) ? (strpos($hex, '#') === 0 ? $hex : '#'.$hex) : '#e5e7eb';
                                    $h = ltrim($hex, '#');
                                    if (strlen($h) === 3) {
                                        $r = hexdec(str_repeat($h[0],2));
                                        $g = hexdec(str_repeat($h[1],2));
                                        $b = hexdec(str_repeat($h[2],2));
                                    } else {
                                        $r = hexdec(substr($h,0,2));
                                        $g = hexdec(substr($h,2,2));
                                        $b = hexdec(substr($h,4,2));
                                    }
                                    $luminance = 0.299*$r + 0.587*$g + 0.114*$b;
                                    $textColor = $luminance < 128 ? '#ffffff' : '#111827';
                                ?>
                                <?php
                                    $public = $ticket['public_token'] ?? '';
                                    if (!empty($public)) {
                                        $rowUrl = 'ticket-conversation.php?public_token=' . urlencode($public);
                                    } else {
                                        $rowUrl = 'ticket-conversation.php?ticket_id=' . urlencode($ticket['ticket_ID']);
                                    }
                                ?>
                                <tr role="link" tabindex="0" onclick="window.location.href='<?php echo $rowUrl; ?>'" onkeypress="if(event.key==='Enter'){window.location.href='<?php echo $rowUrl; ?>'}" class="hover:bg-gray-50 cursor-pointer">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($ticket['ticket_ID']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($ticket['client_name'] ?? '—'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($ticket['agent_name'] ?? '—'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" style="background-color: <?php echo htmlspecialchars($hex); ?>; color: <?php echo htmlspecialchars($textColor); ?>;">
                                            <?php echo htmlspecialchars($ticket['status_label'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($ticket['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </section>
		</main>

        <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-500">
            © 2026 Ateneo de Iloilo — HelpDesk maintained by <a href="https://www.github.com/VinTristanSollesta" class="text-indigo-600 hover:underline">vtgsollesta</a>
        </footer>
	</div>
</body>
</html>
