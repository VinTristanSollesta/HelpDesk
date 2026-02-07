<?php
// Simple auth helper for agent-only pages.
// Usage: require_once __DIR__ . '/auth.php'; require_agent();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_agent(int $min_access_level = 0)
{
    // If not logged in, store intended location and redirect to login
    if (empty($_SESSION['agent_id'])) {
        // Save current request to return after login
        $_SESSION['return_to'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: login.php');
        exit;
    }

    // Optionally check access level
    $lvl = intval($_SESSION['access_level'] ?? 0);
    if ($lvl < $min_access_level) {
        // Forbidden
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

?>
