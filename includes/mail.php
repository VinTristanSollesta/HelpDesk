<?php
/**
 * HelpDesk mail helper using PHPMailer.
 * Requires: composer require phpmailer/phpmailer
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

function getMailConfig() {
    $path = __DIR__ . '/mail_config.php';
    if (is_file($path)) {
        return (array) require $path;
    }
    return [
        'from_email' => 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
        'from_name'  => 'Ateneo HelpDesk',
        'smtp_host'  => 'smtp.gmail.com',
        'smtp_port'  => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_secure' => 'tls',
    ];
}

function createMailer() {
    static $autoload = null;
    if ($autoload === null) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoload)) {
            return null;
        }
        require_once $autoload;
    }
    $cfg = getMailConfig();
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($cfg['from_email'], $cfg['from_name'] ?? '');
        if (!empty($cfg['smtp_host'])) {
            $mail->isSMTP();
            $mail->Host       = $cfg['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['smtp_username'] ?? '';
            $mail->Password   = $cfg['smtp_password'] ?? '';
            $mail->SMTPSecure = $cfg['smtp_secure'] ?? 'tls';
            $mail->Port       = (int)($cfg['smtp_port'] ?? 587);
        } else {
            $mail->isMail();
        }
        return $mail;
    } catch (MailException $e) {
        return null;
    }
}

/**
 * Send ticket copy to the client who created the ticket.
 */
function sendTicketCopyToClient($toEmail, $clientName, $subject, $description, $ticketId, $publicLink) {
    $mail = createMailer();
    if (!$mail) return false;
    try {
        $mail->addAddress($toEmail, $clientName);
        $mail->Subject = 'Ticket #' . $ticketId . ' – Your copy – Ateneo HelpDesk';
        $body = "Hello " . htmlspecialchars($clientName) . ",\n\n";
        $body .= "Your support ticket has been received. Here is your copy for reference.\n\n";
        $body .= "Ticket #: " . $ticketId . "\n";
        $body .= "Subject: " . $subject . "\n\n";
        $body .= "Description:\n" . $description . "\n\n";
        $body .= "View your ticket (public link): " . $publicLink . "\n\n";
        $body .= "— Ateneo HelpDesk";
        $mail->Body = $body;
        $mail->AltBody = $body;
        $mail->send();
        return true;
    } catch (MailException $e) {
        return false;
    }
}

/**
 * Send notification to the agent when they are assigned to a ticket.
 */
function sendAssignmentToAgent($toEmail, $agentName, $ticketId, $ticketSubject, $conversationUrl) {
    $mail = createMailer();
    if (!$mail) return false;
    try {
        $mail->addAddress($toEmail, $agentName);
        $mail->Subject = 'Ticket #' . $ticketId . ' assigned to you – Ateneo HelpDesk';
        $body = "Hello " . htmlspecialchars($agentName) . ",\n\n";
        $body .= "You have been assigned to the following ticket.\n\n";
        $body .= "Ticket #: " . $ticketId . "\n";
        $body .= "Subject: " . $ticketSubject . "\n\n";
        $body .= "Open ticket: " . $conversationUrl . "\n\n";
        $body .= "— Ateneo HelpDesk";
        $mail->Body = $body;
        $mail->AltBody = $body;
        $mail->send();
        return true;
    } catch (MailException $e) {
        return false;
    }
}

function sendResolvedConfirmationToClient($toEmail, $clientName, $ticketId, $ticketSubject, $publicLink) {
    $mail = createMailer();
    if (!$mail) return false;
    try {
        $mail->addAddress($toEmail, $clientName);
        $mail->Subject = 'Ticket #' . $ticketId . ' resolved – Ateneo HelpDesk';
        $body = "Hello " . htmlspecialchars($clientName) . ",\n\n";
        $body .= "Your support ticket has been marked as resolved.\n\n";
        $body .= "Ticket #: " . $ticketId . "\n";
        $body .= "Subject: " . $ticketSubject . "\n\n";
        $body .= "View your ticket: " . $publicLink . "\n\n";
        $body .= "— Ateneo HelpDesk";
        $mail->Body = $body;
        $mail->AltBody = $body;
        $mail->send();
        return true;
    } catch (MailException $e) {
        return false;
    }
}
