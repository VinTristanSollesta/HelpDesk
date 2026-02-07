<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ateneo de Iloilo HelpDesk</title>
        <link rel="stylesheet" href="dist/styles.css">
        <meta name="robots" content="noindex">
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="min-h-screen">
        <?php require_once __DIR__ . '/includes/header.php'; ?>

        <header class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-3xl font-bold">Dashboard</h1>
            <p class="mt-2 text-sm text-gray-600">Manage tickets, view stats, and monitor activity.</p>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <?php 
                include_once __DIR__ . '/dbConnect.php';
                $pdo = getPDO();

                $sql = "SELECT 
                    (SELECT COUNT(*) FROM Tickets WHERE status_id = 1) AS open_tickets,
                    (SELECT COUNT(*) FROM Tickets WHERE status_id = 2) AS in_progress_tickets,
                    (SELECT COUNT(*) FROM Tickets WHERE status_id = 3) AS resolved_tickets
                        FROM Tickets";

                $stmt = $pdo->query($sql);
                $status = $stmt->fetch(PDO::FETCH_ASSOC);

                ?>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="text-sm text-gray-500">New Tickets</div>
                    <div class="mt-4 text-2xl font-semibold text-indigo-600"><?php echo htmlspecialchars($status['open_tickets'] ?? 0); ?></div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="text-sm text-gray-500">In Progress</div>
                    <div class="mt-4 text-2xl font-semibold text-yellow-500"><?php echo htmlspecialchars($status['in_progress_tickets'] ?? 0); ?></div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="text-sm text-gray-500">Resolved</div>
                    <div class="mt-4 text-2xl font-semibold text-green-600"><?php echo htmlspecialchars($status['resolved_tickets'] ?? 0); ?></div>
                </div>
            </section>

            <section class="bg-white p-6 rounded-lg shadow-sm">
                <!-- <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-medium">Recent Tickets</h2>
                    <div class="flex items-center space-x-4">
                        <a href="#" class="text-sm text-indigo-600">Search</a>
                        <input type="text" placeholder="Search tickets..." class="ml-4 px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div> -->
                <?php
                // Prepare time-series data for the last 30 days
                require_once __DIR__ . '/dbConnect.php';
                $pdo = getPDO();

                $days = [];
                $period = 30;
                $today = new DateTimeImmutable('today');
                for ($i = $period - 1; $i >= 0; $i--) {
                    $d = $today->sub(new DateInterval('P' . $i . 'D'));
                    $days[] = $d->format('Y-m-d');
                }

                // Fetch counts per day for New (status_id = 1) and Resolved (status_id = 3)
                $sqlNew = "SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM Tickets WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :period DAY) AND status_id = 1 GROUP BY day";
                $sqlResolved = "SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM Tickets WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :period DAY) AND status_id = 3 GROUP BY day";

                $stmtNew = $pdo->prepare($sqlNew);
                $stmtNew->execute([':period' => $period]);
                $rowsNew = $stmtNew->fetchAll(PDO::FETCH_KEY_PAIR); // day => cnt

                $stmtRes = $pdo->prepare($sqlResolved);
                $stmtRes->execute([':period' => $period]);
                $rowsRes = $stmtRes->fetchAll(PDO::FETCH_KEY_PAIR);

                $dataNew = [];
                $dataRes = [];
                foreach ($days as $d) {
                    $dataNew[] = isset($rowsNew[$d]) ? (int)$rowsNew[$d] : 0;
                    $dataRes[] = isset($rowsRes[$d]) ? (int)$rowsRes[$d] : 0;
                }

                // Totals for quick stats
                $totNew = array_sum($dataNew);
                $totRes = array_sum($dataRes);
                ?>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-white p-4 rounded-lg shadow">
                        <div class="text-sm text-gray-500">New (30d)</div>
                        <div class="mt-2 text-2xl font-semibold text-indigo-600"><?php echo htmlspecialchars($totNew); ?></div>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <div class="text-sm text-gray-500">Resolved (30d)</div>
                        <div class="mt-2 text-2xl font-semibold text-green-600"><?php echo htmlspecialchars($totRes); ?></div>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <div class="text-sm text-gray-500">Range</div>
                        <div class="mt-2">
                            <select id="rangeSelect" class="mt-1 block w-full border border-gray-300 rounded-md p-2 text-sm">
                                <option value="7">Last 7 days</option>
                                <option value="14">Last 14 days</option>
                                <option value="30" selected>Last 30 days</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Chart legend supports toggling series; explicit checkboxes removed for cleaner UI -->

                <div class="bg-white p-4 rounded-lg shadow">
                    <canvas id="ticketsChart" height="120"></canvas>
                </div>

                <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
                <script>
                    (function(){
                        const labels = <?php echo json_encode($days); ?>;
                        const dataNew = <?php echo json_encode($dataNew); ?>;
                        const dataRes = <?php echo json_encode($dataRes); ?>;

                        const ctx = document.getElementById('ticketsChart').getContext('2d');
                        let currentRange = 30;

                        const cfg = {
                            type: 'bar',
                            data: {
                                labels: labels.slice(-currentRange),
                                datasets: [
                                    {
                                        label: 'New',
                                        data: dataNew.slice(-currentRange),
                                        backgroundColor: 'rgba(79,70,229,0.85)',
                                        borderColor: 'rgba(79,70,229,1)',
                                        borderWidth: 1,
                                        stack: 'tickets'
                                    },
                                    {
                                        label: 'Resolved',
                                        data: dataRes.slice(-currentRange),
                                        backgroundColor: 'rgba(16,163,74,0.85)',
                                        borderColor: 'rgba(16,163,74,1)',
                                        borderWidth: 1,
                                        stack: 'tickets'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    x: { stacked: true, grid: { display: false } },
                                    y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
                                },
                                plugins: { legend: { position: 'top' } }
                            }
                        };

                        let chart = new Chart(ctx, cfg);

                        function updateChart(){
                            const range = parseInt(document.getElementById('rangeSelect').value, 10);
                            cfg.data.labels = labels.slice(-range);
                            cfg.data.datasets[0].data = dataNew.slice(-range);
                            cfg.data.datasets[1].data = dataRes.slice(-range);
                            chart.update();
                        }

                        document.getElementById('rangeSelect').addEventListener('change', function(){
                            updateChart();
                        });

                        document.getElementById('toggleNew').addEventListener('change', function(){
                            cfg.data.datasets[0].hidden = !this.checked;
                            chart.update();
                        });
                        document.getElementById('toggleRes').addEventListener('change', function(){
                            cfg.data.datasets[1].hidden = !this.checked;
                            chart.update();
                        });
                    })();
                </script>
            </section>
        </main>

        <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-500">
            © 2026 Ateneo de Iloilo — HelpDesk maintained by <a href="https://www.github.com/VinTristanSollesta" class="text-indigo-600 hover:underline">vtgsollesta</a>
        </footer>
    </div>
</body>
</html>
