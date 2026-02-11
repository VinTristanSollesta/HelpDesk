<?php
// Generate Resolution Report as PDF for download/print
require_once __DIR__ . '/includes/auth.php';
require_agent();
require_once __DIR__ . '/dbConnect.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>PDF not available</title></head><body style="font-family:sans-serif;padding:2rem;">';
    echo '<h1>PDF export not configured</h1><p>Run <code>composer install</code> in the project folder to enable PDF download.</p>';
    echo '<p><a href="report.php">Back to Report</a></p></body></html>';
    exit;
}

require $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = getPDO();

$sql = "SELECT r.resolution_ID, r.ticket_ID, r.resolved_at, r.resolution AS resolution_note,
        t.subject, t.created_at AS ticket_created,
        a.name AS agent_name
        FROM resolution r
        INNER JOIN Tickets t ON r.ticket_ID = t.ticket_ID
        LEFT JOIN Agents a ON r.agent_ID = a.agent_id
        ORDER BY r.resolved_at DESC";
$stmt = $pdo->query($sql);
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);

function hoursBetween($from, $to) {
    $a = new DateTime($from);
    $b = new DateTime($to);
    return ($b->getTimestamp() - $a->getTimestamp()) / 3600;
}

$within24 = [];
$over24 = [];
foreach ($all as $row) {
    $row['hours_to_resolve'] = hoursBetween($row['ticket_created'], $row['resolved_at']);
    $h = (int)$row['hours_to_resolve'];
    if ($h <= 24) {
        $within24[] = $row;
    } else {
        $over24[] = $row;
    }
}

function fmtHours($hours) {
    $h = (int)$hours;
    if ($h < 24) return $h . ' h';
    $d = (int)($h / 24);
    $r = $h % 24;
    return $d . 'd ' . $r . 'h';
}
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function tableRows($rows, $timeClass) {
    $out = '';
    foreach ($rows as $r) {
        $out .= '<tr>';
        $out .= '<td style="padding:6px 8px;border:1px solid #e5e7eb;">' . h($r['ticket_ID']) . '</td>';
        $out .= '<td style="padding:6px 8px;border:1px solid #e5e7eb;">' . h($r['subject']) . '</td>';
        $out .= '<td style="padding:6px 8px;border:1px solid #e5e7eb;">' . h($r['ticket_created']) . '</td>';
        $out .= '<td style="padding:6px 8px;border:1px solid #e5e7eb;">' . h($r['resolved_at']) . '</td>';
        $out .= '<td style="padding:6px 8px;border:1px solid #e5e7eb;color:#b45309;">' . fmtHours($r['hours_to_resolve']) . '</td>';
        $out .= '<td style="padding:6px 8px;border:1px solid #e5e7eb;">' . h($r['agent_name'] ?? '—') . '</td>';
        $out .= '</tr>';
    }
    return $out;
}

$thStyle = 'padding:8px;border:1px solid #d1d5db;background:#f9fafb;font-size:10px;text-align:left;font-weight:bold;';
$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Resolution Report</title></head><body style="font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111;">';
$html .= '<h1 style="font-size:18px;margin-bottom:4px;">Resolution Report</h1>';
$html .= '<p style="color:#6b7280;font-size:10px;margin-bottom:16px;">Tickets solved within 24 hours vs more than a day. Generated ' . date('Y-m-d H:i') . '</p>';

$html .= '<table style="width:100%;margin-bottom:20px;border-collapse:collapse;">';
$html .= '<tr><td style="padding:12px;border:1px solid #e5e7eb;background:#ecfdf5;width:50%;"><strong>Resolved within 24 hours</strong><br><span style="font-size:18px;color:#059669;">' . count($within24) . '</span></td>';
$html .= '<td style="padding:12px;border:1px solid #e5e7eb;background:#fffbeb;width:50%;"><strong>Resolved in more than a day</strong><br><span style="font-size:18px;color:#d97706;">' . count($over24) . '</span></td></tr>';
$html .= '</table>';

$html .= '<h2 style="font-size:14px;margin-top:20px;margin-bottom:8px;color:#065f46;">Tickets resolved within 24 hours</h2>';
if (empty($within24)) {
    $html .= '<p style="color:#6b7280;">None.</p>';
} else {
    $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
    $html .= '<tr><th style="' . $thStyle . '">#</th><th style="' . $thStyle . '">Subject</th><th style="' . $thStyle . '">Created</th><th style="' . $thStyle . '">Resolved</th><th style="' . $thStyle . '">Time to resolve</th><th style="' . $thStyle . '">Resolved by</th></tr>';
    $html .= tableRows($within24, 'green');
    $html .= '</table>';
}

$html .= '<h2 style="font-size:14px;margin-top:20px;margin-bottom:8px;color:#92400e;">Tickets resolved in more than a day</h2>';
if (empty($over24)) {
    $html .= '<p style="color:#6b7280;">None.</p>';
} else {
    $html .= '<table style="width:100%;border-collapse:collapse;">';
    $html .= '<tr><th style="' . $thStyle . '">#</th><th style="' . $thStyle . '">Subject</th><th style="' . $thStyle . '">Created</th><th style="' . $thStyle . '">Resolved</th><th style="' . $thStyle . '">Time to resolve</th><th style="' . $thStyle . '">Resolved by</th></tr>';
    $html .= tableRows($over24, 'amber');
    $html .= '</table>';
}

$html .= '<p style="margin-top:24px;font-size:9px;color:#9ca3af;">© ' . date('Y') . ' Ateneo de Iloilo — HelpDesk</p>';
$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'resolution-report-' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
