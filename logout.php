<?php
// Logout script: use POST to perform logout, GET shows a confirmation.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Destroy session completely
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logout — Ateneo HelpDesk</title>
    <link rel="stylesheet" href="dist/styles.css">
</head>
<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full bg-white p-8 rounded-lg shadow text-center">
      <h2 class="mb-4 text-lg font-medium">Sign out</h2>
      <p class="text-sm text-gray-700 mb-6">Are you sure you want to sign out?</p>
      <form method="post" action="">
        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">Sign out</button>
        <a href="dashboard.php" class="ml-4 inline-block text-sm text-gray-600">Cancel</a>
      </form>
    </div>
  </div>
</body>
</html>
