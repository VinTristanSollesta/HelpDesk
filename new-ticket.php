<?php

require_once __DIR__ . '/dbConnect.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$error = '';
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $error = 'Invalid request.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($full_name === '' || $email === '' || $subject === '' || $description === '') {
            $error = 'Please fill required fields.';
        } else {
            try {
                $pdo = getPDO();

                // Check if client exists by email
                $stmt = $pdo->prepare('SELECT client_ID FROM Clients WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $client = $stmt->fetch();

                if ($client) {
                    $client_id = $client['client_ID'];
                } else {
                    // Insert new client
                    $ins = $pdo->prepare('INSERT INTO Clients (full_name, email, phone_number, department) VALUES (:full_name, :email, :phone, :dept)');
                    $ins->execute([':full_name' => $full_name, ':email' => $email, ':phone' => $phone, ':dept' => $department]);
                    $client_id = (int)$pdo->lastInsertId();
                }

                // Insert ticket
                $public_token = bin2hex(random_bytes(16));
                $agent_id = null;
                if (!empty($_SESSION['agent_id'])) {
                    $agent_id = (int)$_SESSION['agent_id'];
                } else {
                    
                    try {
                        $fb = $pdo->query('SELECT agent_id FROM Agents ORDER BY access_level DESC LIMIT 1')->fetchColumn();
                        $agent_id = $fb !== false ? (int)$fb : 0;
                    } catch (Exception $e) {
                        
                        $agent_id = 0;
                    }
                }
                $status_id = 1; 
                $priority_id = 1; 

                $t = $pdo->prepare('INSERT INTO Tickets (subject, description, client_ID, agent_id, status_id, priority_id, public_token) VALUES (:subject, :desc, :client, :agent, :status, :priority, :token)');
                $t->execute([
                    ':subject' => $subject,
                    ':desc' => $description,
                    ':client' => $client_id,
                    ':agent' => $agent_id,
                    ':status' => $status_id,
                    ':priority' => $priority_id,
                    ':token' => $public_token,
                ]);

                $ticket_id = (int)$pdo->lastInsertId();

                // Send ticket copy to the client who created it
                require_once __DIR__ . '/includes/mail.php';
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/');
                $publicLink = $baseUrl . '/ticket-conversation.php?public_token=' . urlencode($public_token);
                sendTicketCopyToClient($email, $full_name, $subject, $description, $ticket_id, $publicLink);

                if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
                    $uploadDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }

                    $allowedMax = 100 * 1024 * 1024; 
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $insAttach = $pdo->prepare('INSERT INTO Attachments (ticket_ID, file_name, uploaded_by, uploaded_at) VALUES (:ticket, :fname, :uploaded_by, NOW())');

                    foreach ($_FILES['attachments']['name'] as $i => $name) {
                        $error = $_FILES['attachments']['error'][$i];
                        if ($error !== UPLOAD_ERR_OK) continue;
                        $tmp = $_FILES['attachments']['tmp_name'][$i];
                        $size = filesize($tmp);
                        if ($size === false || $size > $allowedMax) continue;
                        $mime = $finfo->file($tmp);
                        if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true)) continue;

                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $safe = bin2hex(random_bytes(8)) . '_' . $ticket_id . '.' . ($ext ?: 'dat');
                        $dest = $uploadDir . DIRECTORY_SEPARATOR . $safe;
                        if (@move_uploaded_file($tmp, $dest)) {
                            // store relative filename and record the client who uploaded
                            $insAttach->execute([':ticket' => $ticket_id, ':fname' => $safe, ':uploaded_by' => $client_id]);
                        }
                    }
                }

                // Redirect to tickets page or show success
                header('Location: tickets-page.php');
                exit;

            } catch (Exception $e) {
                $error = 'Failed to create ticket: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Ticket — Ateneo HelpDesk</title>
    <link rel="stylesheet" href="dist/styles.css">
    <meta name="robots" content="noindex">
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="min-h-screen">
        <?php require_once __DIR__ . '/includes/header.php'; ?>

        <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <h1 class="text-2xl font-bold mb-4">Create New Ticket</h1>
                <?php if ($error): ?>
                    <div class="mb-4 text-sm text-red-700 bg-red-100 p-2 rounded"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="post" action="" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Client full name *</label>
                        <input name="full_name" type="text" required class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Client email *</label>
                        <input name="email" type="email" required class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone</label>
                            <input name="phone" type="text" class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Department</label>
                            <input name="department" type="text" class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Subject *</label>
                        <input name="subject" type="text" required class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description *</label>
                        <textarea name="description" rows="3" required class="mt-1 block w-full border border-gray-300 rounded-md p-2"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Attachments (images only, max 5MB each)</label>
                        <div id="drop-area" class="mt-1 block w-full border-2 border-dashed border-gray-300 rounded-md p-4 justify-center text-center bg-gray-50 hover:bg-gray-100 hover:border-indigo-400 cursor-pointer min-h-[7rem]">
                            <input id="attachments-input" name="attachments[]" type="file" accept="image/*" multiple class="hidden" />
                            <div id="drop-text" class="text-sm text-gray-500">Drag & drop images here, or click to browse</div>
                            <div id="previews" class="mt-3 grid grid-cols-3 gap-2"></div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Create Ticket</button>
                        <?php if (!empty($_SESSION['agent_id'])): ?>
                            <a href="dashboard.php" class="text-sm text-gray-600">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>
        <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-500">
            © 2026 Ateneo de Iloilo — HelpDesk maintained by <a href="https://www.github.com/VinTristanSollesta" class="text-indigo-600 hover:underline">vtgsollesta</a>
        </footer>
    </div>
                        <script>
                        (function(){
                            const dropArea = document.getElementById('drop-area');
                            const input = document.getElementById('attachments-input');
                            const previews = document.getElementById('previews');

                            function prevent(e){ e.preventDefault(); e.stopPropagation(); }
                            ['dragenter','dragover','dragleave','drop'].forEach(ev => dropArea.addEventListener(ev, prevent));

                            dropArea.addEventListener('click', () => input.click());

                            dropArea.addEventListener('drop', (e) => {
                                const dt = e.dataTransfer;
                                if (!dt) return;
                                const files = Array.from(dt.files).filter(f => f.type.startsWith('image/'));
                                if (!files.length) return;
                                setFiles(files);
                            });

                            input.addEventListener('change', () => {
                                const files = Array.from(input.files || []).filter(f => f.type.startsWith('image/'));
                                setFiles(files);
                            });

                            function setFiles(files){
                                // set input.files via DataTransfer
                                const dt = new DataTransfer();
                                files.forEach(f => dt.items.add(f));
                                input.files = dt.files;
                                renderPreviews(files);
                            }

                            function renderPreviews(files){
                                previews.innerHTML = '';
                                files.forEach((file, idx) => {
                                    const reader = new FileReader();
                                    const wrap = document.createElement('div');
                                    wrap.className = 'relative border rounded overflow-hidden';
                                    reader.onload = function(ev){
                                        const img = document.createElement('img');
                                        img.src = ev.target.result;
                                        img.className = 'w-full h-24 object-cover';
                                        wrap.appendChild(img);
                                        const rem = document.createElement('button');
                                        rem.type = 'button';
                                        rem.className = 'absolute top-1 right-1 bg-black bg-opacity-50 text-white text-xs rounded px-1';
                                        rem.textContent = '×';
                                        rem.addEventListener('click', function(){ removeFileAt(idx); });
                                        wrap.appendChild(rem);
                                    };
                                    reader.readAsDataURL(file);
                                    previews.appendChild(wrap);
                                });
                            }

                            function removeFileAt(index){
                                const cur = Array.from(input.files || []);
                                if (index < 0 || index >= cur.length) return;
                                cur.splice(index, 1);
                                const dt = new DataTransfer();
                                cur.forEach(f => dt.items.add(f));
                                input.files = dt.files;
                                renderPreviews(Array.from(input.files));
                            }

                            // If there are pre-selected files (rare), render them
                            if (input.files && input.files.length) renderPreviews(Array.from(input.files));
                        })();
                        </script>
</body>
</html>