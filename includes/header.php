<?php
// Reusable header/nav include.
// Determines the current path and provides a navClass helper for active link styling.
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
        <a href="dashboard.php" class="<?php echo navClass('dashboard.php', $current); ?>">Dashboard</a>
        <a href="tickets-page.php" class="<?php echo navClass('tickets-page.php', $current); ?>">Tickets</a>
        <a href="resolved-page.php" class="<?php echo navClass('resolved-page.php', $current); ?>">Resolved</a>
      </div>
    </div>
  </div>
</nav>
