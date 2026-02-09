<?php
// Messenger-style ticket conversation view
// Left: ticket list, Middle: conversation, Right: attachments / public link
require_once __DIR__ . '/dbConnect.php';

// If no public_token provided, require agent login
$publicToken = $_GET['public_token'] ?? null;
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : null;

// start session early so UI logic (logged-in checks) works consistently
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!$publicToken) {
  // require agent session
  if (empty($_SESSION['agent_id'])) {
    $_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
    header('Location: /login.php');
    exit;
  }
}

$pdo = getPDO();

// Handle posting a reply (agents only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $msg = trim($_POST['message']);
    $tid = (int)($_POST['ticket_id'] ?? 0);
    if (!empty($_SESSION['agent_id']) && $tid) {
        $stmt = $pdo->prepare('INSERT INTO Comments (ticket_ID, author_ID, messages, created_at) VALUES (:t, :a, :m, NOW())');
        $stmt->execute([':t' => $tid, ':a' => $_SESSION['agent_id'], ':m' => $msg]);
        header('Location: ticket-conversation.php?ticket_id=' . $tid);
        exit;
    }
}

// Handle resolving a ticket (agents only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve') {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['agent_id'])) {
    // not authorized
  } else {
    $tid = (int)($_POST['ticket_id'] ?? 0);
    if ($tid) {
      $note = trim($_POST['resolution_note'] ?? '');
      try {
        $pdo->beginTransaction();
        $up = $pdo->prepare('UPDATE Tickets SET status_id = 3 WHERE ticket_ID = :t');
        $up->execute([':t' => $tid]);
        // Insert resolution record
        $ticketStmt = $pdo->prepare('SELECT description FROM Tickets WHERE ticket_ID = :id LIMIT 1');
        $ticketStmt->execute([':id' => $tid]);
        $ticketRow = $ticketStmt->fetch(PDO::FETCH_ASSOC);
        $insRes = $pdo->prepare('INSERT INTO resolution (ticket_ID, agent_ID, resolution, resolved_at) VALUES (:ticket, :agent, :resolution, NOW())');
        $insRes->execute([
          ':ticket' => $tid,
          ':agent' => $_SESSION['agent_id'],
          ':resolution' => $note
        ]);
        if ($note !== '') {
          $insc = $pdo->prepare('INSERT INTO Comments (ticket_ID, author_ID, messages, created_at) VALUES (:t, :a, :m, NOW())');
          $insc->execute([':t' => $tid, ':a' => $_SESSION['agent_id'], ':m' => $note]);
        }
        $pdo->commit();
      } catch (Exception $e) {
        $pdo->rollBack();
      }
      header('Location: ticket-conversation.php?ticket_id=' . $tid);
      exit;
    }
  }
}

// Load tickets for left column (recent)
// include status_id and basic metadata so UI can inspect ticket state when using the left list
$ticketsStmt = $pdo->query("SELECT t.ticket_ID, t.status_id, t.created_at, t.client_ID, t.description, t.subject, t.public_token, c.full_name AS client_name, s.hexcolor AS hex_color, s.label AS status_label
    FROM Tickets t
    LEFT JOIN Clients c ON t.client_ID = c.client_ID
    LEFT JOIN Status s ON t.status_id = s.status_id
    ORDER BY t.created_at DESC
    LIMIT 50");
$tickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

// Determine current ticket
$currentTicket = null;
if ($publicToken) {
    $s = $pdo->prepare('SELECT * FROM Tickets WHERE public_token = :pt LIMIT 1');
    $s->execute([':pt' => $publicToken]);
    $currentTicket = $s->fetch(PDO::FETCH_ASSOC);
} elseif ($ticketId) {
    $s = $pdo->prepare('SELECT * FROM Tickets WHERE ticket_ID = :id LIMIT 1');
    $s->execute([':id' => $ticketId]);
    $currentTicket = $s->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($tickets)) {
    $currentTicket = $tickets[0];
}

// Load conversation messages
$messages = [];
if ($currentTicket) {
    $tid = (int)$currentTicket['ticket_ID'];
    $cstmt = $pdo->prepare('SELECT comments_ID, ticket_ID, author_ID, messages, created_at FROM Comments WHERE ticket_ID = :t ORDER BY created_at ASC');
    $cstmt->execute([':t' => $tid]);
    $messages = $cstmt->fetchAll(PDO::FETCH_ASSOC);
}

// Try to load attachments if table exists
$attachments = [];
try {
    $ast = $pdo->prepare('SELECT attachment_ID, file_name, uploaded_at FROM Attachments WHERE ticket_ID = :t ORDER BY uploaded_at DESC');
    if (!empty($currentTicket)) {
        $ast->execute([':t' => (int)$currentTicket['ticket_ID']]);
        $attachments = $ast->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // ignore if Attachments table doesn't exist
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket Conversation</title>
    <link rel="stylesheet" href="dist/styles.css">
</head>
<body class="bg-gray-100 text-gray-800">
  <div class="min-h-screen">
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left: ticket list -->
        <aside class="col-span-3 bg-white rounded-lg shadow p-4 h-[70vh] overflow-auto">
          <h3 class="text-sm font-medium text-gray-700 mb-3">Tickets</h3>
          <ul class="space-y-2">
            <?php foreach ($tickets as $t): ?>
              <li class="p-2 rounded hover:bg-gray-50 <?php echo ($currentTicket && $currentTicket['ticket_ID']==$t['ticket_ID']) ? 'bg-indigo-50' : ''; ?>">
                <a href="ticket-conversation.php?ticket_id=<?php echo h($t['ticket_ID']); ?>" class="block">
                  <div class="flex justify-between">
                    <div class="text-sm font-medium text-gray-900"><?php echo h($t['subject']); ?></div>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" style="background-color: #<?php echo htmlspecialchars($t['hex_color'] ?? '#ffffff'); ?>; color: <?php echo htmlspecialchars($t['text_color'] ?? '#000000'); ?>;">
                        <?php echo htmlspecialchars($t['status_label'] ?? 'Unknown'); ?>
                    </span>
                  </div>
                  <div class="text-xs text-gray-500"><?php echo h($t['client_name']); ?></div>
                  <div class="text-xs text-gray-400 mt-1">Public link: <a class="text-indigo-600" href="<?php echo h((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http'). '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/ticket-conversation.php?public_token=' . urlencode($t['public_token'])); ?>"></a></div>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </aside>

        <!-- Middle: conversation -->
        <section class="col-span-6 bg-white rounded-lg shadow p-4 h-[70vh] flex flex-col">
          <?php if (!$currentTicket): ?>
            <div class="text-sm text-gray-500">No ticket selected.</div>
          <?php else: ?>
            <header class="border-b pb-3 mb-3">
              <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold"><?php echo h($currentTicket['subject']); ?></h2>
                <?php if (($currentTicket['status_id'] ?? null) == 3): ?>
                  <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-800 ml-2">Resolved</span>
                <?php endif; ?>
              </div>
              <div class="text-xs text-gray-500">Ticket #<?php echo h($currentTicket['ticket_ID']); ?> — Created: <?php echo h($currentTicket['created_at'] ?? ''); ?></div>
              <?php if (($currentTicket['status_id'] ?? null) == 3): ?>
                <?php
                  // Fetch resolver info from resolution table
                  $resolvedBy = null;
                  $resolvedAt = null;
                  $tid = (int)$currentTicket['ticket_ID'];
                  $resStmt = $pdo->prepare('SELECT agent_ID, resolved_at FROM resolution WHERE ticket_ID = :tid ORDER BY resolved_at DESC LIMIT 1');
                  $resStmt->execute([':tid' => $tid]);
                  $row = $resStmt->fetch(PDO::FETCH_ASSOC);
                  if ($row && !empty($row['agent_ID'])) {
                    $as = $pdo->prepare('SELECT name FROM Agents WHERE agent_id = :id LIMIT 1');
                    $as->execute([':id' => $row['agent_ID']]);
                    $resolvedBy = $as->fetchColumn();
                    $resolvedAt = $row['resolved_at'];
                  }
                ?>
                <div class="text-xs text-green-700 mt-1">
                  <?php if ($resolvedBy): ?>
                    Resolved by <span class="font-semibold"><?php echo h($resolvedBy); ?></span><?php if ($resolvedAt): ?> on <?php echo h($resolvedAt); ?><?php endif; ?>
                  <?php else: ?>
                    Resolved by agent
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($_SESSION['agent_id']) && (($currentTicket['status_id'] ?? null) != 3)): ?>
              <div class="mt-2 mb-3">
                <form method="post" class="flex items-start space-x-2">
                  <input type="hidden" name="ticket_id" value="<?php echo h($currentTicket['ticket_ID']); ?>">
                  <input type="hidden" name="action" value="resolve">
                  <textarea name="resolution_note" rows="2" placeholder="Optional resolution note" class="flex-1 border border-gray-300 rounded p-2 text-sm" ></textarea>
                  <button type="submit" class="ml-2 inline-flex items-center px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">Resolve</button>
                </form>
              </div>
              <?php endif; ?>

            </header>

            <div class="flex-1 overflow-auto mb-4 space-y-4 px-2" id="messages">
              <?php
                // show ticket description first as initial client message
                if (!empty($currentTicket)) {
                    // try to resolve client name
                    $clientName = 'Client';
                    if (!empty($currentTicket['client_ID'])) {
                        $cs = $pdo->prepare('SELECT full_name FROM Clients WHERE client_ID = :id LIMIT 1');
                        $cs->execute([':id' => $currentTicket['client_ID']]);
                        $cn = $cs->fetchColumn();
                        if ($cn) $clientName = $cn;
                    }

                    if (!empty($currentTicket['description'])) {
                        ?>
                        <div>
                          <div class="text-xs text-gray-500"><?php echo h($clientName); ?> • <?php echo h($currentTicket['created_at'] ?? ''); ?></div>
                          <div class="mt-1 text-sm text-gray-800 bg-blue-100 p-3 rounded"><?php echo nl2br(h($currentTicket['description'])); ?></div>
                        </div>
                        <?php
                    }
                }

                if (empty($messages)) {
                    // if there are no further comments, we still show the description above
                    if (empty($currentTicket['description'])) {
                        echo '<div class="text-sm text-gray-500">No messages yet.</div>';
                    }
                } else {
                    foreach ($messages as $m) {
                        // determine author name
                        $author = 'Client';
                        if (!empty($m['author_ID'])) {
                            $as = $pdo->prepare('SELECT name FROM Agents WHERE agent_id = :id LIMIT 1');
                            $as->execute([':id' => $m['author_ID']]);
                            $an = $as->fetchColumn();
                            if ($an) $author = $an;
                        }
                        ?>
                        <div>
                          <div class="text-xs text-gray-500"><?php echo h($author); ?> • <?php echo h($m['created_at']); ?></div>
                          <div class="mt-1 text-sm text-gray-800 bg-blue-300 p-3 rounded"><?php echo nl2br(h($m['messages'])); ?></div>
                        </div>
                        <?php
                    }
                }
              ?>
            </div>

            <?php if (!empty($_SESSION['agent_id'])): ?>
            <form method="post" class="mt-2">
              <input type="hidden" name="ticket_id" value="<?php echo h($currentTicket['ticket_ID']); ?>">
              <div>
                <textarea name="message" rows="3" class="w-full border border-gray-300 rounded p-2" placeholder="Write a reply..."></textarea>
              </div>
              <div class="mt-2 text-right">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Send</button>
              </div>
            </form>
            <?php endif; ?>

          <?php endif; ?>
        </section>

        <!-- Right: attachments / public link -->
        <aside class="col-span-3 bg-white rounded-lg shadow p-4 h-[70vh] overflow-auto">
          <h3 class="text-sm font-medium text-gray-700 mb-3">Attachments</h3>
          <?php if (empty($attachments)): ?>
            <div class="text-sm text-gray-500">No attachments.</div>
          <?php else: ?>
            <div class="grid grid-cols-1 gap-3 text-sm">
              <?php foreach ($attachments as $a): 
                    $ext = strtolower(pathinfo($a['file_name'], PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp'], true);
                    $url = 'uploads/' . $a['file_name'];
              ?>
                <div class="flex items-center space-x-3">
                  <?php if ($isImage): ?>
                    <a href="<?php echo h($url); ?>" class="image-attach" data-src="<?php echo h($url); ?>">
                      <img src="<?php echo h($url); ?>" alt="attachment" class="w-20 h-16 object-cover rounded border" />
                    </a>
                    <div class="flex-1">
                      <div class="text-sm text-gray-700 break-all"><?php echo h($a['file_name']); ?></div>
                      <div class="text-xs text-gray-400"><?php echo h($a['uploaded_at']); ?></div>
                    </div>
                  <?php else: ?>
                    <a class="text-indigo-600 break-all" href="<?php echo h($url); ?>" target="_blank"><?php echo h($a['file_name']); ?></a>
                    <div class="text-xs text-gray-400"><?php echo h($a['uploaded_at']); ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($currentTicket): ?>
            <div class="mt-6 text-sm">
              Public link: <a class="text-indigo-600 break-all" href="<?php echo h((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http'). '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/ticket-conversation.php?public_token=' . urlencode($currentTicket['public_token'])); ?>">Open public ticket</a>
            </div>
          <?php endif; ?>
        </aside>
      </div>
    </main>
    <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-500">
        © 2026 Ateneo de Iloilo — HelpDesk maintained by <a href="https://www.github.com/VinTristanSollesta" class="text-indigo-600 hover:underline">vtgsollesta</a>
    </footer>
  </div>
    <!-- Fullscreen image overlay -->
    <div id="img-overlay" class="hidden fixed inset-0 z-50 bg-black bg-opacity-80 flex items-center justify-center p-4">
      <button id="overlay-close" class="absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full p-2">✕</button>
      <img id="overlay-img" src="" alt="" class="max-w-full max-h-full rounded" />
    </div>
    <script>
      (function(){
        const overlay = document.getElementById('img-overlay');
        const overlayImg = document.getElementById('overlay-img');
        const closeBtn = document.getElementById('overlay-close');
        document.querySelectorAll('a.image-attach').forEach(a => {
          a.addEventListener('click', function(e){
            e.preventDefault();
            const src = this.dataset.src || this.getAttribute('href');
            overlayImg.src = src;
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
          });
        });
        function closeOverlay(){ overlay.classList.add('hidden'); overlayImg.src=''; document.body.style.overflow=''; }
        closeBtn.addEventListener('click', closeOverlay);
        overlay.addEventListener('click', function(e){ if (e.target === overlay) closeOverlay(); });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeOverlay(); });
      })();
    </script>
</body>
</html>
