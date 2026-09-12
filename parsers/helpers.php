<?php
/**
 * Helper functions for webhook parsers
 */

/**
 * Helper function to render nested arrays/objects as HTML tables
 * 
 * @param array $data The data to render
 * @param int $depth Current recursion depth
 * @return string HTML representation of the data
 */
function renderDataAsTable($data, $depth = 0) {
    if ($depth > 10) {
        return '<div class="data-box">Max depth reached</div>';
    }
    
    $html = '';
    
    if (!is_array($data)) {
        $safe = htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
        return "<div class=\"simple-value\">$safe</div>";
    }
    
    // Check if it's an indexed array of similar items (like commits)
    $isIndexedArray = array_keys($data) === range(0, count($data) - 1);
    
    if ($isIndexedArray && count($data) > 0) {
        // Render as a list of items
        foreach ($data as $index => $item) {
            if (is_array($item)) {
                $html .= '<div class="array-item">';
                $html .= '<div class="array-item-title">Item ' . ($index + 1) . '</div>';
                $html .= '<table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
                foreach ($item as $key => $value) {
                    $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                    
                    if (is_array($value)) {
                        $html .= '<tr>';
                        $html .= '<td class="metadata-label">' . $keySafe . '</td>';
                        $html .= '<td class="metadata-value">';
                        $html .= renderDataAsTable($value, $depth + 1);
                        $html .= '</td>';
                        $html .= '</tr>';
                    } else {
                        $valueSafe = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                        $html .= '<tr>';
                        $html .= '<td class="metadata-label">' . $keySafe . '</td>';
                        $html .= '<td class="metadata-value">' . $valueSafe . '</td>';
                        $html .= '</tr>';
                    }
                }
                $html .= '</table>';
                $html .= '</div>';
            } else {
                $valueSafe = htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8');
                $html .= '<div class="array-item-simple">• ' . $valueSafe . '</div>';
            }
        }
    } else {
        // Render as key-value table
        $html .= '<table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
        foreach ($data as $key => $value) {
            $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            
            if (is_array($value) && !empty($value)) {
                // Close current table and start a subsection
                $html .= '</table>';
                $html .= '<div class="subsection">';
                $html .= '<div class="subsection-title">' . $keySafe . '</div>';
                $html .= renderDataAsTable($value, $depth + 1);
                $html .= '</div>';
                $html .= '<table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
            } else if (is_array($value) && empty($value)) {
                $html .= '<tr>';
                $html .= '<td class="metadata-label">' . $keySafe . '</td>';
                $html .= '<td class="metadata-value"><em>empty</em></td>';
                $html .= '</tr>';
            } else {
                $valueSafe = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                $html .= '<tr>';
                $html .= '<td class="metadata-label">' . $keySafe . '</td>';
                $html .= '<td class="metadata-value">' . $valueSafe . '</td>';
                $html .= '</tr>';
            }
        }
        $html .= '</table>';
    }
    
    return $html;
}

/**
 * Shared email chrome: one stylesheet, one header, one footer, for every template.
 *
 * @param string $title  Header text (already HTML-safe)
 * @param string $stamp  Timestamp line (already HTML-safe)
 * @param string $body   Inner HTML made of .section / .metadata / .data-box blocks
 * @param string $footer Footer sentence
 * @return string Complete HTML document
 */
function emailShell($title, $stamp, $body, $footer = 'Automatically forwarded to your inbox.') {
    $host = htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'webhook', ENT_QUOTES, 'UTF-8');
    $plainTitle = strip_tags($title);
    $mono = "ui-monospace,SFMono-Regular,Menlo,Consolas,'Liberation Mono',monospace";
    $sans = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";
    return <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>$plainTitle</title>
<style>
  body { margin:0; padding:0; background:#0f0f17; color:#15151f; -webkit-font-smoothing:antialiased; font-family:$sans; font-size:15px; line-height:1.6; }
  a { color:#7c3aed; }
  table { border-collapse:separate; border-spacing:0; }
  .frame { width:100%; max-width:660px; }
  .hero { background:#7c3aed; background-image:linear-gradient(120deg,#8b5cf6 0%,#ec4899 55%,#f97316 100%); padding:32px 34px 28px; border-radius:20px 20px 0 0; }
  .kicker { font-family:$mono; font-size:11px; letter-spacing:.18em; text-transform:uppercase; color:rgba(255,255,255,.75); margin:0 0 10px; }
  .hero h1 { margin:0 0 14px; font-size:26px; font-weight:800; letter-spacing:-.02em; line-height:1.2; color:#ffffff; }
  .stamp { display:inline-block; font-family:$mono; font-size:12px; letter-spacing:.06em; color:#ffffff; background:rgba(15,15,23,.28); padding:6px 12px; border-radius:999px; }
  .card { background:#ffffff; padding:8px 0 14px; border-radius:0 0 20px 20px; }
  .section { padding:24px 34px 0; }
  .section-title { font-family:$mono; font-size:11px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:#7c3aed; margin:0 0 12px; padding-left:10px; border-left:3px solid #ec4899; line-height:1.4; }
  .subsection { margin:14px 0 0; }
  .subsection-title { font-size:13px; font-weight:700; color:#15151f; margin:16px 0 8px; }
  table.metadata { width:100%; border:1px solid #e6e3f2; border-radius:12px; overflow:hidden; background:#ffffff; }
  td.metadata-label, td.metadata-value { padding:11px 14px; border-bottom:1px solid #efedf7; vertical-align:top; text-align:left; }
  tr:last-child > td.metadata-label, tr:last-child > td.metadata-value { border-bottom:0; }
  td.metadata-label { width:150px; max-width:170px; font-family:$mono; font-size:12px; line-height:1.5; color:#6b6b80; background:#f8f7fd; word-break:break-word; }
  td.metadata-value { font-size:14px; color:#15151f; word-break:break-word; }
  td.metadata-value table.metadata { margin:2px 0; }
  .data-box { margin:0; padding:16px 18px; background:#15151f; color:#e6e6f0; border-radius:12px; border:1px solid #2a2a3a; font-family:$mono; font-size:12.5px; line-height:1.65; white-space:pre-wrap; word-break:break-word; overflow-x:auto; }
  .path-title { display:inline-block; margin:18px 0 8px; padding:5px 12px; font-family:$mono; font-size:12px; font-weight:700; color:#be185d; background:#fdf2f8; border:1px solid #fbcfe8; border-radius:999px; }
  .array-item { margin:0 0 12px; padding:16px; background:#f8f7fd; border:1px solid #e6e3f2; border-radius:14px; }
  .array-item-title { font-size:14px; font-weight:800; margin:0 0 10px; color:#15151f; letter-spacing:-.01em; }
  .array-item-simple { margin:0 0 6px; padding:9px 14px; background:#f8f7fd; border:1px solid #e6e3f2; border-radius:10px; font-size:14px; }
  .simple-value { font-size:14px; padding:4px 0; }
  .message-box { margin:12px 0; padding:16px 18px 16px 20px; background:#f8f7fd; border-left:4px solid #8b5cf6; border-radius:0 12px 12px 0; font-size:16px; line-height:1.55; color:#15151f; }
  .error-box { margin:12px 0; padding:12px 14px; background:#fef2f2; border-left:4px solid #ef4444; border-radius:0 10px 10px 0; }
  .footer { padding:22px 34px 8px; text-align:center; color:#6a6a86; font-family:$mono; font-size:11px; letter-spacing:.06em; line-height:1.8; }
  .footer a { color:#a78bfa; text-decoration:none; }
  .footer .bolt { color:#f97316; }
</style>
</head>
<body style="margin:0;padding:0;background:#0f0f17;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f17;">
  <tr><td align="center" style="padding:36px 12px 28px;">
    <table role="presentation" class="frame" cellpadding="0" cellspacing="0">
      <tr><td class="hero">
        <p class="kicker">Webhook &rsaquo; $host</p>
        <h1>$title</h1>
        <span class="stamp">$stamp</span>
      </td></tr>
      <tr><td class="card">
$body
      </td></tr>
      <tr><td class="footer">
        $footer<br>
        <span class="bolt">&#9889;</span> <a href="https://$host/">$host</a>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}
