<?php
// 1. Error Reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/dbConnect.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$error = '';
$success = '';

// Generate CSRF token if not exists
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
        
        // Handle Subject logic: If Others is selected, use the custom text field
        $subject_choice = $_POST['subject'] ?? '';
        $subject_other = trim($_POST['subject_other'] ?? '');
        $subject = ($subject_choice === 'Others') ? $subject_other : $subject_choice;

        $description = trim($_POST['description'] ?? '');

        if ($full_name === '' || $email === '' || $subject === '' || $description === '') {
            $error = 'Please fill required fields.';
        } else {
            try {
                $pdo = getPDO();
                // Start transaction to ensure atomicity (Ticket + Client + Attachments)
                $pdo->beginTransaction();

                // 1. Check/Insert Client
                $stmt = $pdo->prepare('SELECT client_ID FROM clients WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $client = $stmt->fetch();

                if ($client) {
                    $client_id = $client['client_ID'];
                } else {
                    $ins = $pdo->prepare('INSERT INTO clients (full_name, email, phone_number, department) VALUES (:full_name, :email, :phone, :dept)');
                    $ins->execute([
                        ':full_name' => $full_name,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':dept' => $department
                    ]);
                    $client_id = (int)$pdo->lastInsertId();
                }

                $public_token = bin2hex(random_bytes(16));
                $agent_id = null;

                // 2. Insert Ticket
                $t = $pdo->prepare('INSERT INTO tickets (subject, description, client_ID, agent_id, status_id, priority_id, public_token, created_at) VALUES (:subject, :desc, :client, :agent, 1, 1, :token, NOW())');
                $t->execute([
                    ':subject' => $subject,
                    ':desc' => $description,
                    ':client' => $client_id,
                    ':agent' => $agent_id, 
                    ':token' => $public_token,
                ]);
                $ticket_id = (int)$pdo->lastInsertId();

                // 3. Handle Attachments
                if (!empty($_FILES['attachments']['name'][0])) {
                    $uploadDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                    $allowedMax = 100 * 1024 * 1024; // 100MB
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $insAttach = $pdo->prepare('INSERT INTO attachments (ticket_ID, file_name, uploaded_by, uploaded_at) VALUES (:ticket, :fname, :uploaded_by, NOW())');

                    foreach ($_FILES['attachments']['name'] as $i => $name) {
                        if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $tmp = $_FILES['attachments']['tmp_name'][$i];
                        if (filesize($tmp) > $allowedMax) continue;
                        $mime = $finfo->file($tmp);
                        if (!str_starts_with($mime, 'image/')) continue;

                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $safe = bin2hex(random_bytes(8)) . '_' . $ticket_id . '.' . ($ext ?: 'dat');
                        if (@move_uploaded_file($tmp, $uploadDir . '/' . $safe)) {
                            $insAttach->execute([
                                ':ticket' => $ticket_id,
                                ':fname' => $safe,
                                ':uploaded_by' => $client_id
                            ]);
                        }
                    }
                }

                // Commit the Transaction
                $pdo->commit();

                // 4. SEND EMAIL (Post-Commit)
                require_once __DIR__ . '/includes/mail.php';
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $dir = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/');
                $publicLink = "$protocol://$host$dir/ticket-conversation.php?public_token=" . urlencode($public_token);
                $mailSent = false;
                try {
                    $mailSent = sendTicketCopyToClient($email, $full_name, $subject, $description, $ticket_id, $publicLink);
                } catch (Exception $mailEx) {
                    error_log("Mail failure: " . $mailEx->getMessage());
                }

                // 6. REDIRECT
                header('Location: tickets-page.php?success=1' . (!$mailSent ? '&mail_error=1' : ''));
                exit;
            } catch (Exception $e) {
                if (isset($pdo)) $pdo->rollBack();
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
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="min-h-screen">
        <?php require_once __DIR__ . '/includes/header.php'; ?>
        <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <h1 class="text-2xl font-bold mb-4">Create New Ticket</h1>
                <?php if ($error): ?>
                    <div class="mb-4 text-sm text-red-700 bg-red-100 p-3 rounded"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form id="ticketForm" method="post" action="" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Client full name *</label>
                            <input name="full_name" type="text" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-indigo-500" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Client email *</label>
                            <input name="email" type="email" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-indigo-500" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Department *</label>
                            <select name="department" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-indigo-500 bg-white">
                                <option value="">-- Select Department or Office--</option>
                                <?php
                                $depts = ["Early Education", "Grade School Faculty", "Junior High School Faculty", "Senior High School Faculty", "MICT Office", "Discipline Office", "Student Development Office", "Guidance Office", "Physical Plants and Facilities Office", "Accounting Office", "HRMD Office", "CMSI Office", "Director of Services", "Admin Office", "Library", "IMRC", "Clinic", "Registrar Office", "Director of Formation", "President's Office", "Science Lab"];
                                foreach ($depts as $dept) {
                                    $selected = (isset($_POST['department']) && $_POST['department'] === $dept) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($dept) . "\" $selected>" . htmlspecialchars($dept) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Room / Location *</label>
                            <input name="phone" type="text" placeholder="e.g. Room 302" class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-indigo-500" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Subject *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                            <?php
                            $options = ["Printers", "Repair", "Camera", "AIMS", "Microsoft and Google Accounts", "Internet Connection", "Website Page", "Others"];
                            foreach ($options as $opt) {
                                $checked = (isset($_POST['subject']) && $_POST['subject'] === $opt) ? 'checked' : '';
                                echo "
                                <label class='inline-flex items-center text-sm'>
                                    <input type='radio' name='subject' value='$opt' required class='text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300' $checked>
                                    <span class='ml-2'>$opt</span>
                                </label>";
                            }
                            ?>
                        </div>
                        <div id="other-subject-container" class="hidden mt-2">
                            <input id="subject_other" name="subject_other" type="text" placeholder="Please specify..." class="block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-indigo-500" value="<?php echo htmlspecialchars($_POST['subject_other'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description *</label>
                        <textarea name="description" rows="4" required class="mt-1 block w-full border border-gray-300 rounded-md p-2 shadow-sm focus:ring-indigo-500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Attachments (Images only)</label>
                        <div id="drop-area" class="mt-1 block w-full border-2 border-dashed border-gray-300 rounded-md p-6 text-center bg-gray-50 hover:bg-gray-100 cursor-pointer transition">
                            <input id="attachments-input" name="attachments[]" type="file" accept="image/*" multiple class="hidden" />
                            <div class="text-sm text-gray-500">Click to upload or drag & drop images</div>
                            <div id="previews" class="mt-3 grid grid-cols-3 gap-2"></div>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" id="submitBtn" class="w-full md:w-auto inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none transition">
                            Create Ticket
                        </button>
                    </div>
                </form>
            </div>
        </main>
        <footer class="mt-8 text-center text-sm text-gray-500">
            © 2026 Ateneo de Iloilo — HelpDesk maintained by <a href="https://www.github.com/VinTristanSollesta" class="text-indigo-600 hover:underline">vtgsollesta</a>
        </footer>
    </div>

    <script>
        const ticketForm = document.getElementById('ticketForm');
        const submitBtn = document.getElementById('submitBtn');
        const subjectRadios = document.getElementsByName('subject');
        const otherContainer = document.getElementById('other-subject-container');
        const otherInput = document.getElementById('subject_other');

        // Toggle "Others" text box
        function toggleOtherBox() {
            let isOther = false;
            subjectRadios.forEach(radio => {
                if (radio.checked && radio.value === 'Others') isOther = true;
            });

            if (isOther) {
                otherContainer.classList.remove('hidden');
                otherInput.required = true;
            } else {
                otherContainer.classList.add('hidden');
                otherInput.required = false;
            }
        }

        subjectRadios.forEach(radio => radio.addEventListener('change', toggleOtherBox));
        window.addEventListener('load', toggleOtherBox);

        ticketForm.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Processing...';
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        });

        // Drag & Drop logic
        (function(){
            const dropArea = document.getElementById('drop-area');
            const input = document.getElementById('attachments-input');
            const previews = document.getElementById('previews');
            ['dragenter','dragover','dragleave','drop'].forEach(ev => {
                dropArea.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); });
            });
            dropArea.addEventListener('click', () => input.click());
            input.addEventListener('change', () => { renderPreviews(Array.from(input.files)); });
            function renderPreviews(files){
                previews.innerHTML = '';
                files.filter(f => f.type.startsWith('image/')).forEach(file => {
                    const reader = new FileReader();
                    const wrap = document.createElement('div');
                    wrap.className = 'border rounded p-1 bg-white';
                    reader.onload = (e) => {
                        wrap.innerHTML = `<img src="${e.target.result}" class="w-full h-20 object-cover rounded">`;
                    };
                    reader.readAsDataURL(file);
                    previews.appendChild(wrap);
                });
            }
        })();
    </script>
</body>
</html>