<?php
session_start();
require_once __DIR__ . '/dbConnect.php';

$error = '';

// Generate CSRF token on GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $error = 'Invalid request.';
    } else {
        $user = trim($_POST['user'] ?? '');
        $pass = $_POST['password'] ?? '';

        if ($user === '' || $pass === '') {
            $error = 'Please provide username/email and password.';
        } else {
            $pdo = getPDO();
            // Use distinct parameter names for drivers that don't allow repeated named params
            $stmt = $pdo->prepare('SELECT agent_id, username, password, name, email, access_level FROM Agents WHERE username = :u OR email = :u2 LIMIT 1');
            $stmt->execute([':u' => $user, ':u2' => $user]);
            $row = $stmt->fetch();

            if ($row) {
                $stored = $row['password'];
                $ok = false;

                // Prefer password_verify for hashed passwords
                if (strlen($stored) > 0 && (password_get_info($stored)['algo'] !== 0)) {
                    if (password_verify($pass, $stored)) {
                        $ok = true;
                    }
                }

                // Fallback: legacy plaintext comparison (avoid if possible)
                if (!$ok && hash_equals($stored, $pass)) {
                    $ok = true;
                }

                if ($ok) {
                    // Regenerate session id to prevent fixation
                    session_regenerate_id(true);
                    $_SESSION['agent_id'] = $row['agent_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['name'] = $row['name'];
                    $_SESSION['access_level'] = $row['access_level'];

                    // Optional: remove CSRF token
                    unset($_SESSION['csrf_token']);

                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid credentials.';
                }
            } else {
                $error = 'Invalid credentials.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agent Login — Ateneo HelpDesk</title>
    <link rel="stylesheet" href="dist/styles.css">
    <meta name="robots" content="noindex">
</head>
<body class="bg-gray-50">
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
      <div class="bg-white py-8 px-6 shadow rounded-lg sm:px-10">
        <div class="flex items-center justify-center mb-6">
          <div class="h-10 w-10 bg-indigo-600 rounded-full flex items-center justify-center text-white font-bold">AH</div>
        </div>
        <h2 class="mt-1 text-center text-2xl font-extrabold text-gray-900">Agent sign in</h2>
        <p class="mt-2 text-center text-sm text-gray-600">Sign in to manage tickets and respond to clients</p>

        <?php if ($error): ?>
          <div class="mt-6 rounded-md bg-red-50 p-3 text-sm text-red-800"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="" class="mt-6 space-y-6">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

          <div>
            <label for="user" class="block text-sm font-medium text-gray-700">Username or email</label>
            <div class="mt-1">
              <input id="user" name="user" type="text" autocomplete="username" required
                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                value="<?php echo htmlspecialchars($_POST['user'] ?? ''); ?>">
            </div>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <div class="mt-1">
              <input id="password" name="password" type="password" autocomplete="current-password" required
                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
              <label for="remember" class="ml-2 block text-sm text-gray-900">Remember me</label>
            </div>
            <div class="text-sm">
              <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">Forgot password?</a>
            </div>
          </div>

          <div>
            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Sign in</button>
          </div>
        </form>
      </div>
      <p class="mt-6 text-center text-sm text-gray-600">Not an agent? <a href="index.php" class="text-indigo-600 hover:text-indigo-500">Return to site</a></p>
    </div>
  </div>
</body>
</html>
