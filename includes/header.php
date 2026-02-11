<?php
// Reusable header/nav include.
// Determines the current path and provides a navClass helper for active link styling.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$current = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

function navClass($page, $current) {
    return $page === $current ? 'text-sm font-medium text-indigo-600' : 'text-sm font-medium text-gray-700';
}
?>
<nav class="bg-white shadow">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between h-16">
      <div class="flex items-center">
        <a href="index.php" class="text-xl font-semibold text-indigo-600">Ateneo HelpDesk</a>
      </div>
      <div class="flex items-center space-x-4">
          <?php if (!empty($_SESSION['agent_id'])): ?>
            <a href="dashboard.php" class="<?php echo navClass('dashboard.php', $current); ?>">Dashboard</a>
            <a href="tickets-page.php" class="<?php echo navClass('tickets-page.php', $current); ?>">Tickets</a>
            <?php if ((isset($_SESSION['access_level']) && (int)$_SESSION['access_level'] === 1)): ?>
              <a href="agents-page.php" class="<?php echo navClass('agents-page.php', $current); ?>">Agents</a>
            <?php endif; ?>
            <span class="text-sm text-gray-500">|</span>
            <a href="agent_profile.php" class="text-sm font-medium text-gray-600">Hello, <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? ''); ?></a>
            <span class="text-sm text-gray-500">|</span>
            <a href="logout.php" class="text-sm font-medium text-red-600">Logout</a>
        <?php else: ?>
          <a href="login.php" class="text-sm font-medium text-gray-700">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
