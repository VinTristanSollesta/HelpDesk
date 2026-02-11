<?php
/**
 * HelpDesk mail helper using PHPMailer.
 */

// Use absolute paths to reach the vendor directory from the includes directory
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\SMTP;

function getMailConfig() {
    $path = __DIR__ . '/mail_config.php';
    if (is_file($path)) {
        return (array) require $path;
    }
    // Default fallback configuration
    return [
        'from_email' => 'mict@adi.edu.ph',
        'from_name'  => 'Ateneo HelpDesk',
        'smtp_host'  => 'smtp.gmail.com',
        'smtp_port'  => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_secure' => 'tls',
    ];
}

function createMailer() {
    $cfg = getMailConfig();
    $mail = new PHPMailer(true); // Now the class will definitely be found
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
        error_log("Mailer Error: " . $e->getMessage());
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
        $mail->isHTML(true); // Enable HTML formatting
        $mail->Subject = 'Ticket #' . $ticketId . ' – Your copy – Ateneo HelpDesk';

        // Professional HTML Body with Inline CSS
        $body = "
        <div style='font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; border: 1px solid #eee; padding: 20px; border-radius: 8px;'>
            <h2 style='color: #4f46e5; border-bottom: 2px solid #f3f4f6; padding-bottom: 10px;'>Ticket Received</h2>
            <p>Hello <strong>" . htmlspecialchars($clientName) . "</strong>,</p>
            <p>Your support ticket has been received. Here is your copy for reference:</p>
            
            <div style='background-color: #f9fafb; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p style='margin: 0;'><strong>Ticket #:</strong> " . $ticketId . "</p>
                <p style='margin: 5px 0 0 0;'><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
            </div>

            <p><strong>Description:</strong></p>
            <blockquote style='background: #fff; border-left: 4px solid #e5e7eb; padding: 10px 15px; margin: 0; font-style: italic;'>
                " . nl2br(htmlspecialchars($description)) . "
            </blockquote>

            <div style='margin-top: 30px; text-align: center;'>
                <a href='" . $publicLink . "' style='background-color: #4f46e5; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>View Ticket Status</a>
            </div>

            <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
            <p style='font-size: 12px; color: #6b7280;'>
                <em>CONFIDENTIALITY NOTICE: This email is intended only for the use of the addressee and may contain privileged information.</em><br>
                — Ateneo HelpDesk
            </p>
        </div>";

        $mail->Body = $body;
        
        // Plain text version for non-HTML mail clients
        $mail->AltBody = "Hello $clientName,\n\nYour ticket #$ticketId has been received.\nSubject: $subject\n\nView here: $publicLink";

        $mail->send();
        return true;
    } catch (MailException $e) {
        error_log("Email error: " . $mail->ErrorInfo);
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
