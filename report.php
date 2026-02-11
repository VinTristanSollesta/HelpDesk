<?php
// Resolution report: tickets solved within 24 hours vs more than a day
require_once __DIR__ . '/includes/auth.php';
require_agent();
require_once __DIR__ . '/dbConnect.php';

$pdo = getPDO();

// Fetch all resolved tickets (time-to-resolve computed in PHP).
$sql = "SELECT r.resolution_ID, r.ticket_ID, r.resolved_at, r.resolution AS resolution_note,
        t.subject, t.created_at AS ticket_created,
        a.name AS agent_name
        FROM resolution r
        INNER JOIN Tickets t ON r.ticket_ID = t.ticket_ID
        LEFT JOIN Agents a ON r.agent_ID = a.agent_id
        ORDER BY r.resolved_at DESC";
$stmt = $pdo->query($sql);
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);

function hoursBetween($from, $to) {
    $a = new DateTime($from);
    $b = new DateTime($to);
    return ($b->getTimestamp() - $a->getTimestamp()) / 3600;
}

$within24 = [];
$over24 = [];
foreach ($all as $row) {
    $row['hours_to_resolve'] = hoursBetween($row['ticket_created'], $row['resolved_at']);
    $h = (int)$row['hours_to_resolve'];
    if ($h <= 24) {
        $within24[] = $row;
    } else {
        $over24[] = $row;
    }
}

function fmtHours($hours) {
    $h = (int)$hours;
    if ($h < 24) return $h . ' h';
    $d = (int)($h / 24);
    $r = $h % 24;
    return $d . 'd ' . $r . 'h';
}
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resolution Report — Ateneo HelpDesk</title>
    <link rel="stylesheet" href="dist/styles.css">
    <meta name="robots" content="noindex">
    <style media="print">
        nav, .no-print, footer, button { display: none !important; }
        body { background: #fff; }
        a[href]:not([href^="#"])::after { content: none; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="min-h-screen">
        <?php require_once __DIR__ . '/includes/header.php'; ?>

        <header class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-3xl font-bold">Resolution Report</h1>
            <p class="mt-2 text-sm text-gray-600">Tickets solved within 24 hours vs more than a day.</p>
            <div class="mt-4 flex flex-wrap gap-3 no-print">
                <a href="dashboard.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Back to Dashboard</a>
                <a href="report-pdf.php" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Download PDF</a>
                <button type="button" onclick="window.print()" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Print / Save as PDF</button>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h2 class="text-lg font-semibold text-green-700">Resolved within 24 hours</h2>
                    <p class="text-2xl font-bold text-green-600 mt-1"><?php echo count($within24); ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h2 class="text-lg font-semibold text-amber-700">Resolved in more than a day</h2>
                    <p class="text-2xl font-bold text-amber-600 mt-1"><?php echo count($over24); ?></p>
                </div>
            </section>

            <section class="bg-white p-6 rounded-lg shadow-sm mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Tickets resolved within 24 hours</h2>
                <?php if (empty($within24)): ?>
                    <p class="text-sm text-gray-500">None.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resolved</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time to resolve</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resolved by</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">View</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($within24 as $r): ?>
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900"><?php echo h($r['subject']); ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?php echo h($r['ticket_created']); ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?php echo h($r['resolved_at']); ?></td>
                                        <td class="px-4 py-2 text-sm text-green-600"><?php echo fmtHours($r['hours_to_resolve']); ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?php echo h($r['agent_name'] ?? '—'); ?></td>
                                        <td class="px-4 py-2"><a href="ticket-conversation.php?ticket_id=<?php echo (int)$r['ticket_ID']; ?>" class="text-indigo-600 hover:underline text-sm">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="bg-white p-6 rounded-lg shadow-sm">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Tickets resolved in more than a day</h2>
                <?php if (empty($over24)): ?>
                    <p class="text-sm text-gray-500">None.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resolved</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time to resolve</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resolved by</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">View</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($over24 as $r): ?>
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-700"><?php echo h($r['ticket_ID']); ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-900"><?php echo h($r['subject']); ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?php echo h($r['ticket_created']); ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?php echo h($r['resolved_at']); ?></td>
                                        <td class="px-4 py-2 text-sm text-amber-600"><?php echo fmtHours($r['hours_to_resolve']); ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?php echo h($r['agent_name'] ?? '—'); ?></td>
                                        <td class="px-4 py-2"><a href="ticket-conversation.php?ticket_id=<?php echo (int)$r['ticket_ID']; ?>" class="text-indigo-600 hover:underline text-sm">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
