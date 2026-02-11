<?php
require_once __DIR__ . '/includes/auth.php';
require_agent();
require_once __DIR__ . '/dbConnect.php';
$pdo = getPDO();

$agent_id = $_SESSION['agent_id'] ?? null;
if (!$agent_id) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name && $email) {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE Agents SET name=?, email=?, password=? WHERE agent_id=?');
            $stmt->execute([$name, $email, $hash, $agent_id]);
        } else {
            $stmt = $pdo->prepare('UPDATE Agents SET name=?, email=? WHERE agent_id=?');
            $stmt->execute([$name, $email, $agent_id]);
        }
        $success = 'Profile updated.';
        $_SESSION['name'] = $name;
    } else {
        $error = 'Name and email are required.';
    }
}

// Fetch agent details
$stmt = $pdo->prepare('SELECT username, name, email FROM Agents WHERE agent_id=?');
$stmt->execute([$agent_id]);
$agent = $stmt->fetch(PDO::FETCH_ASSOC);

// Tickets resolved by this agent
$resolvedStmt = $pdo->prepare('SELECT r.ticket_ID, r.resolution, r.resolved_at, t.subject
    FROM resolution r
    INNER JOIN Tickets t ON r.ticket_ID = t.ticket_ID
    WHERE r.agent_ID = ?
    ORDER BY r.resolved_at DESC');
$resolvedStmt->execute([$agent_id]);
$resolvedByMe = $resolvedStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Profile</title>
    <link rel="stylesheet" href="dist/styles.css">
</head>
<body class="bg-gray-100 text-gray-800">
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <h1 class="text-2xl font-bold mb-4">My Profile</h1>
        <?php if ($error): ?><div class="bg-red-100 text-red-700 p-2 mb-4 rounded"> <?php echo htmlspecialchars($error); ?> </div><?php endif; ?>
        <?php if ($success): ?><div class="bg-green-100 text-green-700 p-2 mb-4 rounded"> <?php echo htmlspecialchars($success); ?> </div><?php endif; ?>

        <div class="bg-white p-6 rounded shadow mb-6">
            <div class="mb-4">
                <div class="text-sm text-gray-500">Username</div>
                <div class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($agent['username'] ?? ''); ?></div>
            </div>
            <div class="mb-4">
                <div class="text-sm text-gray-500">Name</div>
                <div class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($agent['name'] ?? ''); ?></div>
            </div>
            <div class="mb-4">
                <div class="text-sm text-gray-500">Email</div>
                <div class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($agent['email'] ?? ''); ?></div>
            </div>
            <button id="openEditModal" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Edit Profile</button>
        </div>

        <div class="bg-white p-6 rounded shadow mb-6">
            <h2 class="text-lg font-semibold mb-4">Tickets I resolved</h2>
            <?php if (empty($resolvedByMe)): ?>
                <p class="text-sm text-gray-500">You have not resolved any tickets yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resolved</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">View</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($resolvedByMe as $r): ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($r['subject']); ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?php echo htmlspecialchars($r['resolved_at']); ?></td>
                                    <td class="px-4 py-2"><a href="ticket-conversation.php?ticket_id=<?php echo (int)$r['ticket_ID']; ?>" class="text-indigo-600 hover:underline text-sm">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Edit Profile Modal -->
        <div id="editProfileModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50 hidden">
            <div class="bg-white p-6 rounded shadow-lg w-full max-w-md relative">
                <button id="closeEditModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700">&times;</button>
                <h2 class="text-lg font-semibold mb-2">Edit Profile</h2>
                <form method="post" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium">Name</label>
                        <input type="text" name="name" class="mt-1 w-full border rounded p-2" required value="<?php echo htmlspecialchars($agent['name'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Email</label>
                        <input type="email" name="email" class="mt-1 w-full border rounded p-2" required value="<?php echo htmlspecialchars($agent['email'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="mt-1 w-full border rounded p-2" autocomplete="new-password">
                    </div>
                    <div>
                        <button type="submit" name="edit_profile" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
        <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-500">
            © 2026 Ateneo de Iloilo — HelpDesk maintained by <a href="https://www.github.com/VinTristanSollesta" class="text-indigo-600 hover:underline">vtgsollesta</a>
        </footer>
    </div>
    <script>
        const openBtn = document.getElementById('openEditModal');
        const modal = document.getElementById('editProfileModal');
        const closeBtn = document.getElementById('closeEditModal');
        if (openBtn && modal && closeBtn) {
            openBtn.addEventListener('click', () => { modal.classList.remove('hidden'); });
            closeBtn.addEventListener('click', () => { modal.classList.add('hidden'); });
            window.addEventListener('click', (e) => {
                if (e.target === modal) modal.classList.add('hidden');
            });
        }
    </script>
</body>
</html>