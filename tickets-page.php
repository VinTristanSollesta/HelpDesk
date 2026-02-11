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
            <button onclick="window.location.href='new-ticket.php'" class="mt-4 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-500 hover:bg-blue-700">New Ticket</button>
		</header>

		<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="bg-white p-6 rounded-lg shadow-sm">
                <?php
                require_once __DIR__ . '/dbConnect.php';
                $pdo = getPDO();

                $q = trim((string)($_GET['q'] ?? ''));
                $statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : null;
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $perPage = 15;
                $offset = ($page - 1) * $perPage;

                // Load all statuses for filter
                $statuses = $pdo->query('SELECT status_id, label FROM Status ORDER BY status_id ASC')->fetchAll(PDO::FETCH_ASSOC);

                // Build base SQL and count
                $baseSql = "FROM Tickets t
                    LEFT JOIN Clients c ON t.client_ID = c.client_ID
                    LEFT JOIN Agents a ON t.agent_id = a.agent_id
                    LEFT JOIN Status s ON t.status_id = s.status_id";
                $where = [];
                $params = [];
                if ($q !== '') {
                    $where[] = '(t.subject LIKE :q1 OR c.full_name LIKE :q2 OR a.name LIKE :q3)';
                    $params[':q1'] = $params[':q2'] = $params[':q3'] = "%{$q}%";
                }
                if ($statusFilter !== null && $statusFilter > 0) {
                    $where[] = 't.status_id = :status_id';
                    $params[':status_id'] = $statusFilter;
                }
                $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

                $countSql = "SELECT COUNT(*) $baseSql $whereClause";
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $total = (int)$countStmt->fetchColumn();
                $totalPages = max(1, (int)ceil($total / $perPage));

                $dataSql = "SELECT t.ticket_ID, t.public_token, t.subject, t.created_at, c.full_name AS client_name, a.name AS agent_name, s.label AS status_label, s.hexcolor AS status_hex
                    $baseSql $whereClause ORDER BY t.created_at DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
                $dataStmt = $pdo->prepare($dataSql);
                $dataStmt->execute($params);
                $tickets = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                $queryBase = [];
                if ($q !== '') $queryBase['q'] = $q;
                if ($statusFilter !== null && $statusFilter > 0) $queryBase['status'] = $statusFilter;
                function buildQuery(array $base, $page = null) {
                    $b = $base;
                    if ($page !== null && $page > 1) $b['page'] = $page;
                    return $b ? '?' . http_build_query($b) : '';
                }
                ?>
                <!-- Search + Status filter (GET) -->
                <form method="get" action="tickets-page.php" class="mb-4 flex flex-wrap items-center gap-3">
                    <input name="q" type="search" placeholder="Search by subject, client, or agent" value="<?php echo htmlspecialchars($q); ?>" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" />
                    <select name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All statuses</option>
                        <?php foreach ($statuses as $st): ?>
                            <option value="<?php echo (int)$st['status_id']; ?>" <?php echo ($statusFilter === (int)$st['status_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Filter</button>
                    <?php if ($q !== '' || ($statusFilter !== null && $statusFilter > 0)): ?>
                        <a href="tickets-page.php" class="text-sm text-gray-600 hover:underline">Clear</a>
                    <?php endif; ?>
                </form>

                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
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

                <?php if ($totalPages > 1): ?>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <div class="text-sm text-gray-500">
                        Showing <?php echo $total ? $offset + 1 : 0; ?>–<?php echo min($offset + $perPage, $total); ?> of <?php echo $total; ?> tickets
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($page > 1): ?>
                            <a href="tickets-page.php<?php echo buildQuery($queryBase, $page - 1); ?>" class="px-3 py-1.5 bg-gray-200 rounded hover:bg-gray-300 text-sm">Previous</a>
                        <?php endif; ?>
                        <?php
                        $range = 3;
                        $start = max(1, $page - $range);
                        $end = min($totalPages, $page + $range);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="tickets-page.php<?php echo buildQuery($queryBase, $i); ?>" class="px-3 py-1.5 rounded text-sm <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="tickets-page.php<?php echo buildQuery($queryBase, $page + 1); ?>" class="px-3 py-1.5 bg-gray-200 rounded hover:bg-gray-300 text-sm">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>
		</main>

        <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-500">
            © 2026 Ateneo de Iloilo — HelpDesk maintained by <a href="https://www.github.com/VinTristanSollesta" class="text-indigo-600 hover:underline">vtgsollesta</a>
        </footer>
	</div>
</body>
</html>
