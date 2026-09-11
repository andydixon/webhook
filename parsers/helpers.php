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
                $html .= '<div class="metadata">';
                foreach ($item as $key => $value) {
                    $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                    
                    if (is_array($value)) {
                        $html .= '<div class="metadata-row">';
                        $html .= '<div class="metadata-label">' . $keySafe . '</div>';
                        $html .= '<div class="metadata-value">';
                        $html .= renderDataAsTable($value, $depth + 1);
                        $html .= '</div>';
                        $html .= '</div>';
                    } else {
                        $valueSafe = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                        $html .= '<div class="metadata-row">';
                        $html .= '<div class="metadata-label">' . $keySafe . '</div>';
                        $html .= '<div class="metadata-value">' . $valueSafe . '</div>';
                        $html .= '</div>';
                    }
                }
                $html .= '</div>';
                $html .= '</div>';
            } else {
                $valueSafe = htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8');
                $html .= '<div class="array-item-simple">• ' . $valueSafe . '</div>';
            }
        }
    } else {
        // Render as key-value table
        $html .= '<div class="metadata">';
        foreach ($data as $key => $value) {
            $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            
            if (is_array($value) && !empty($value)) {
                // Close current table and start a subsection
                $html .= '</div>';
                $html .= '<div class="subsection">';
                $html .= '<div class="subsection-title">' . $keySafe . '</div>';
                $html .= renderDataAsTable($value, $depth + 1);
                $html .= '</div>';
                $html .= '<div class="metadata">';
            } else if (is_array($value) && empty($value)) {
                $html .= '<div class="metadata-row">';
                $html .= '<div class="metadata-label">' . $keySafe . '</div>';
                $html .= '<div class="metadata-value"><em>empty</em></div>';
                $html .= '</div>';
            } else {
                $valueSafe = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                $html .= '<div class="metadata-row">';
                $html .= '<div class="metadata-label">' . $keySafe . '</div>';
                $html .= '<div class="metadata-value">' . $valueSafe . '</div>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';
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
    return <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<title>$plainTitle</title>
<style>
  body { margin:0; padding:0; background:#f3f1fb; color:#15151f; -webkit-font-smoothing:antialiased;
         font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; font-size:15px; line-height:1.6; }
  a { color:#7c3aed; }
  .wrap { padding:28px 12px; }
  .container { max-width:640px; margin:0 auto; background:#ffffff; border-radius:16px; overflow:hidden;
               box-shadow:0 12px 40px rgba(40,20,90,.10); border:1px solid #e9e6f5; }
  .stripe { height:6px; background:#8b5cf6; background-image:linear-gradient(90deg,#8b5cf6,#ec4899 55%,#f97316); }
  .header { padding:28px 32px 20px; border-bottom:1px solid #eeecf6; }
  .header h1 { margin:0 0 6px; font-size:22px; font-weight:800; letter-spacing:-.02em; line-height:1.25; color:#15151f; }
  .timestamp { margin:0; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12px; color:#8b8ba3; letter-spacing:.06em; text-transform:uppercase; }
  .section { padding:22px 32px 0; }
  .section-title { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:11px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:#7c3aed; margin:0 0 10px; }
  .subsection { margin:14px 0; }
  .subsection-title { font-size:13px; font-weight:700; color:#15151f; margin:16px 0 8px; padding-left:10px; border-left:3px solid #ec4899; }
  .metadata { display:table; width:100%; border:1px solid #e9e6f5; border-radius:10px; border-collapse:separate; overflow:hidden; }
  .metadata-row { display:table-row; }
  .metadata-label, .metadata-value { display:table-cell; padding:10px 14px; border-bottom:1px solid #f0eef8; vertical-align:top; }
  .metadata-row:last-child .metadata-label, .metadata-row:last-child .metadata-value { border-bottom:0; }
  .metadata-label { width:150px; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12px; color:#6b6b80; background:#faf9fe; white-space:nowrap; }
  .metadata-value { font-size:14px; color:#15151f; word-break:break-word; }
  .data-box { margin:0; padding:16px 18px; background:#0f0f17; color:#e6e6f0; border-radius:10px; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
              font-size:12.5px; line-height:1.65; white-space:pre-wrap; word-break:break-word; overflow-x:auto; }
  .path-title { margin:18px 0 8px; padding:8px 12px; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12px; font-weight:700;
                color:#db2777; background:#fdf2f8; border-left:3px solid #ec4899; border-radius:0 8px 8px 0; }
  .array-item, .array-item-simple { margin:0 0 12px; padding:14px 16px; background:#faf9fe; border:1px solid #e9e6f5; border-radius:10px; }
  .array-item-title { font-size:13px; font-weight:700; margin:0 0 10px; color:#15151f; }
  .array-item .metadata { background:#fff; }
  .message-box { margin:12px 0; padding:14px 16px; background:#faf9fe; border:1px solid #e9e6f5; border-radius:10px; font-size:15px; line-height:1.6; }
  .error-box { margin:12px 0; padding:12px 14px; background:#fef2f2; border-left:3px solid #ef4444; border-radius:0 8px 8px 0; }
  .footer { margin-top:28px; padding:18px 32px 24px; border-top:1px solid #eeecf6; text-align:center; color:#8b8ba3;
            font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:11px; letter-spacing:.04em; }
  .footer p { margin:0 0 4px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="container">
    <div class="stripe"></div>
    <div class="header">
      <h1>$title</h1>
      <p class="timestamp">$stamp</p>
    </div>
$body
    <div class="footer">
      <p>$footer</p>
      <p>⚡ $host</p>
    </div>
  </div>
</div>
</body>
</html>
HTML;
}
