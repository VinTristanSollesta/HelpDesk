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
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="text-sm text-gray-500">Open Tickets</div>
                    <div class="mt-4 text-2xl font-semibold text-indigo-600">24</div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="text-sm text-gray-500">In Progress</div>
                    <div class="mt-4 text-2xl font-semibold text-yellow-500">8</div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <div class="text-sm text-gray-500">Resolved</div>
                    <div class="mt-4 text-2xl font-semibold text-green-600">112</div>
                </div>
            </section>

            <section class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-medium">Recent Tickets</h2>
                    <a href="#" class="text-sm text-indigo-600">Search</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr>
                                <td class="px-4 py-3 text-sm">#1023</td>
                                <td class="px-4 py-3 text-sm">Cannot access email</td>
                                <td class="px-4 py-3 text-sm"><span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">In Progress</span></td>
                                <td class="px-4 py-3 text-sm">Juan Dela Cruz</td>
                                <td class="px-4 py-3 text-sm">2026-02-05</td>
                            </tr>
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
