<?php
// Default routing: public users -> new-ticket.php, logged-in agents -> dashboard.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// If an agent is logged in, send them to the dashboard; otherwise show the public new-ticket form
if (!empty($_SESSION['agent_id'])) {
	header('Location: dashboard.php');
	exit;
}

include('new-ticket.php');
exit;
?>
