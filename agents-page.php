<?php
require_once __DIR__ . '/includes/auth.php';
require_agent(1); // Only allow agents with access_level == 1
if ((int)($_SESSION['access_level'] ?? 0) !== 1) {
    http_response_code(403);
    $prev = $_SERVER['HTTP_REFERER'] ?? '/';
    echo '<div style="margin:2rem auto;max-width:500px;text-align:center;font-family:sans-serif;color:#b91c1c;background:#fee2e2;padding:2rem;border-radius:8px;">
        Access denied: Only agents with access level 1 can access this page.<br><br>
        Redirecting in <span id="countdown">5</span> seconds...<br>
    </div>';
    echo '<script>
        let c = 5;
        const cd = document.getElementById("countdown");
        const prev = ' . json_encode($prev) . ';
        const timer = setInterval(function() {
            c--;
            cd.textContent = c;
            if (c <= 0) {
                clearInterval(timer);
                window.location.href = prev;
            }
        }, 1000);
    </script>';
    exit;
}
require_once __DIR__ . '/dbConnect.php';
$pdo = getPDO();

$error = '';
$success = '';

// Handle Add Agent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_agent'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $access_level = intval($_POST['access_level'] ?? 1);
    if ($username && $password && $name && $email) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO Agents (username, password, name, email, access_level) VALUES (?, ?, ?, ?, ?)');
        try {
            $stmt->execute([$username, $hash, $name, $email, $access_level]);
            $success = 'Agent added.';
        } catch (PDOException $e) {
            $error = 'Error adding agent: ' . $e->getMessage();
        }
    } else {
        $error = 'All fields are required.';
    }
}

// Handle Edit Agent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_agent'])) {
    $agent_id = intval($_POST['agent_id']);
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $access_level = intval($_POST['access_level'] ?? 1);
    $password = $_POST['password'] ?? '';
    if ($username && $name && $email) {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE Agents SET username=?, password=?, name=?, email=?, access_level=? WHERE agent_id=?');
            $stmt->execute([$username, $hash, $name, $email, $access_level, $agent_id]);
        } else {
            $stmt = $pdo->prepare('UPDATE Agents SET username=?, name=?, email=?, access_level=? WHERE agent_id=?');
            $stmt->execute([$username, $name, $email, $access_level, $agent_id]);
        }
        $success = 'Agent updated.';
    } else {
        $error = 'All fields except password are required.';
    }
}

// Handle Delete Agent
if (isset($_GET['delete'])) {
    $agent_id = intval($_GET['delete']);
    if ($agent_id) {
        $stmt = $pdo->prepare('DELETE FROM Agents WHERE agent_id=?');
        $stmt->execute([$agent_id]);
        $success = 'Agent deleted.';
    }
}

// Fetch all agents
$agents = $pdo->query('SELECT * FROM Agents ORDER BY agent_id ASC')->fetchAll(PDO::FETCH_ASSOC);

// If editing, fetch agent
$edit_agent = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare('SELECT * FROM Agents WHERE agent_id=?');
    $stmt->execute([$edit_id]);
    $edit_agent = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents</title>
    <link rel="stylesheet" href="dist/styles.css">
</head>
<body class="bg-gray-100 text-gray-800">
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold mb-4">Agents</h1>
        <?php if ($error): ?><div class="bg-red-100 text-red-700 p-2 mb-4 rounded"> <?php echo htmlspecialchars($error); ?> </div><?php endif; ?>
        <?php if ($success): ?><div class="bg-green-100 text-green-700 p-2 mb-4 rounded"> <?php echo htmlspecialchars($success); ?> </div><?php endif; ?>

        <!-- Add Agent Modal Trigger -->
        <button id="openAddModal" class="mb-4 bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Add Agent</button>

        <!-- Add Agent Modal -->
        <div id="addAgentModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50 hidden">
            <div class="bg-white p-6 rounded shadow-lg w-full max-w-md relative">
                <button id="closeAddModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700">&times;</button>
                <h2 class="text-lg font-semibold mb-2">Add Agent</h2>
                <form method="post" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium">Username</label>
                        <input type="text" name="username" class="mt-1 w-full border rounded p-2" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Name</label>
                        <input type="text" name="name" class="mt-1 w-full border rounded p-2" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Email</label>
                        <input type="email" name="email" class="mt-1 w-full border rounded p-2" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Access Level</label>
                        <input type="number" name="access_level" class="mt-1 w-full border rounded p-2" min="1" max="3" required value="1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Password</label>
                        <input type="password" name="password" class="mt-1 w-full border rounded p-2" required autocomplete="new-password">
                    </div>
                    <div>
                        <button type="submit" name="add_agent" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Add Agent</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Agent Modal -->
        <div id="editAgentModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50 hidden">
            <div class="bg-white p-6 rounded shadow-lg w-full max-w-md relative">
                <button id="closeEditModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700">&times;</button>
                <h2 class="text-lg font-semibold mb-2">Edit Agent</h2>
                <form id="editAgentForm" method="post" class="space-y-3">
                    <input type="hidden" name="agent_id" id="edit_agent_id">
                    <div>
                        <label class="block text-sm font-medium">Username</label>
                        <input type="text" name="username" id="edit_username" class="mt-1 w-full border rounded p-2" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Name</label>
                        <input type="text" name="name" id="edit_name" class="mt-1 w-full border rounded p-2" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Email</label>
                        <input type="email" name="email" id="edit_email" class="mt-1 w-full border rounded p-2" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Access Level</label>
                        <input type="number" name="access_level" id="edit_access_level" class="mt-1 w-full border rounded p-2" min="1" max="3" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="mt-1 w-full border rounded p-2" autocomplete="new-password">
                    </div>
                    <div>
                        <button type="submit" name="edit_agent" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Update Agent</button>
                        <button type="button" id="cancelEditModal" class="ml-2 text-gray-600 hover:underline">Cancel</button>
                    </div>
                </form>
            </div>

        </div>

        <!-- Agents List -->
        <div class="bg-white p-4 rounded shadow">
            <h2 class="text-lg font-semibold mb-2">All Agents</h2>
            <div class="overflow-x-auto">
            <table class="min-w-full text-sm w-full table-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left">ID</th>
                        <th class="px-4 py-2 text-left">Username</th>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">Email</th>
                        <th class="px-4 py-2 text-left">Access</th>
                        <th class="px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $a): ?>
                        <tr class="border-t">
                            <td class="px-4 py-2"> <?php echo $a['agent_id']; ?> </td>
                            <td class="px-4 py-2"> <?php echo htmlspecialchars($a['username']); ?> </td>
                            <td class="px-4 py-2"> <?php echo htmlspecialchars($a['name']); ?> </td>
                            <td class="px-4 py-2"> <?php echo htmlspecialchars($a['email']); ?> </td>
                            <td class="px-4 py-2 text-center"> <?php echo $a['access_level']; ?> </td>
                            <td class="px-4 py-2 text-center">
                                <button class="editBtn text-indigo-600 hover:underline" 
                                    data-agent='<?php echo json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>'>Edit</button>
                                <a href="agents-page.php?delete=<?php echo $a['agent_id']; ?>" class="text-red-600 hover:underline ml-2" onclick="return confirm('Delete this agent?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-500">
            © 2026 Ateneo de Iloilo — HelpDesk maintained by <a href="https://www.github.com/VinTristanSollesta" class="text-indigo-600 hover:underline">vtgsollesta</a>
        </footer>
    </div>
    <script>
        // Modal open/close logic for Add
        const openBtn = document.getElementById('openAddModal');
        const addModal = document.getElementById('addAgentModal');
        const closeAddBtn = document.getElementById('closeAddModal');
        if (openBtn && addModal && closeAddBtn) {
            openBtn.addEventListener('click', () => { addModal.classList.remove('hidden'); });
            closeAddBtn.addEventListener('click', () => { addModal.classList.add('hidden'); });
            window.addEventListener('click', (e) => {
                if (e.target === addModal) addModal.classList.add('hidden');
            });
        }
        // Modal open/close logic for Edit
        const editModal = document.getElementById('editAgentModal');
        const closeEditBtn = document.getElementById('closeEditModal');
        const cancelEditBtn = document.getElementById('cancelEditModal');
        const editForm = document.getElementById('editAgentForm');
        document.querySelectorAll('.editBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const agent = JSON.parse(this.getAttribute('data-agent'));
                document.getElementById('edit_agent_id').value = agent.agent_id;
                document.getElementById('edit_username').value = agent.username;
                document.getElementById('edit_name').value = agent.name;
                document.getElementById('edit_email').value = agent.email;
                document.getElementById('edit_access_level').value = agent.access_level;
                editModal.classList.remove('hidden');
            });
        });
        if (closeEditBtn && editModal) {
            closeEditBtn.addEventListener('click', () => { editModal.classList.add('hidden'); });
        }
        if (cancelEditBtn && editModal) {
            cancelEditBtn.addEventListener('click', () => { editModal.classList.add('hidden'); });
        }
        window.addEventListener('click', (e) => {
            if (e.target === editModal) editModal.classList.add('hidden');
        });
    </script>
</body>
</html>