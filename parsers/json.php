<?php
/**
 * JSON Webhook Parser
 * Parses JSON payloads and displays them as formatted tables
 * Sub-arrays are split into separate tables with path-based titles
 * 
 * @param string $rawBody The raw request body
 * @param array $headers The request headers
 * @param array $metadata Request metadata (date, ip, method, contentType)
 * @return array|false Array with 'html' and 'subject' keys, or false on failure
 */
function jsonParse($rawBody, $headers, $metadata) {
    $payload = json_decode($rawBody, true);
    if (!$payload || !is_array($payload)) {
        return false;
    }
    
    // Sanitize metadata for HTML
    $dateSafe = htmlspecialchars($metadata['date'], ENT_QUOTES, 'UTF-8');
    $ipSafe = htmlspecialchars($metadata['ip'], ENT_QUOTES, 'UTF-8');
    $methodSafe = htmlspecialchars($metadata['method'], ENT_QUOTES, 'UTF-8');
    $contentTypeSafe = htmlspecialchars($metadata['contentType'], ENT_QUOTES, 'UTF-8');
    
    // Start building HTML
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON Webhook Data</title>
    <style>
        body {
            background-color: #ffffff;
            color: #1a1a1a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .header {
            text-align: center;
            padding-bottom: 30px;
            border-bottom: 3px solid #000000;
            margin-bottom: 40px;
        }
        .header h1 {
            color: #000000;
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 10px 0;
            letter-spacing: -0.5px;
        }
        .header .timestamp {
            color: #666666;
            font-size: 14px;
            font-weight: 500;
            margin: 0;
        }
        .section {
            margin-bottom: 35px;
        }
        .section-title {
            color: #000000;
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e0e0;
            letter-spacing: -0.3px;
        }
        .path-title {
            color: #0066cc;
            font-size: 16px;
            font-weight: 600;
            margin: 25px 0 12px 0;
            padding: 8px 12px;
            background-color: #f0f7ff;
            border-left: 4px solid #0066cc;
            border-radius: 4px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
        }
        .metadata {
            display: table;
            width: 100%;
            border: 1px solid #d0d0d0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }
        .metadata-row {
            display: table-row;
        }
        .metadata-row:not(:last-child) .metadata-label,
        .metadata-row:not(:last-child) .metadata-value {
            border-bottom: 1px solid #e8e8e8;
        }
        .metadata-label {
            display: table-cell;
            padding: 12px 16px;
            font-weight: 600;
            color: #000000;
            width: 30%;
            vertical-align: top;
            font-size: 14px;
            background-color: #f9f9f9;
        }
        .metadata-value {
            display: table-cell;
            padding: 12px 16px;
            color: #333333;
            font-size: 13px;
            vertical-align: top;
            word-break: break-word;
        }
        .array-item {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background-color: #fafafa;
        }
        .array-item-title {
            font-weight: 700;
            color: #000000;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 25px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #888888;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 JSON Webhook Data</h1>
            <p class="timestamp">$dateSafe</p>
        </div>

        <div class="section">
            <div class="section-title">📋 Request Information</div>
            <div class="metadata">
                <div class="metadata-row">
                    <div class="metadata-label">IP Address</div>
                    <div class="metadata-value">$ipSafe</div>
                </div>
                <div class="metadata-row">
                    <div class="metadata-label">Method</div>
                    <div class="metadata-value">$methodSafe</div>
                </div>
                <div class="metadata-row">
                    <div class="metadata-label">Content-Type</div>
                    <div class="metadata-value">$contentTypeSafe</div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">📦 JSON Data</div>

HTML;
    
    // Process the JSON data
    $html .= renderJsonData($payload);
    
    $html .= <<<HTML
        </div>

        <div class="footer">
            <p>JSON data automatically parsed and formatted.</p>
        </div>
    </div>
</body>
</html>
HTML;
    
    $subject = "📊 JSON Webhook - " . $metadata['date'];
    
    return [
        'html' => $html,
        'subject' => $subject
    ];
}

/**
 * Recursively render JSON data as tables
 * Sub-arrays are extracted into separate tables with path-based titles
 * 
 * @param mixed $data The data to render
 * @param string $path The current path (e.g., "Foo->Bar->Baz")
 * @return string HTML representation
 */
function renderJsonData($data, $path = '') {
    $html = '';
    
    if (!is_array($data)) {
        $safe = htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
        return "<div class=\"metadata-value\">$safe</div>";
    }
    
    // Check if it's an indexed array (numeric keys in sequence)
    $isIndexedArray = array_keys($data) === range(0, count($data) - 1);
    
    if ($isIndexedArray && count($data) > 0) {
        // It's an array of items
        foreach ($data as $index => $item) {
            $itemPath = $path ? $path . '->[' . $index . ']' : '[' . $index . ']';
            
            if (is_array($item)) {
                // Check if this item has any sub-arrays
                $subArrays = [];
                $simpleData = [];
                
                foreach ($item as $key => $value) {
                    if (is_array($value)) {
                        $subArrays[$key] = $value;
                    } else {
                        $simpleData[$key] = $value;
                    }
                }
                
                // Render simple data for this item
                if (!empty($simpleData)) {
                    if ($path) {
                        $html .= '<div class="path-title">' . htmlspecialchars($itemPath, ENT_QUOTES, 'UTF-8') . '</div>';
                    }
                    
                    $html .= '<div class="array-item">';
                    $html .= '<div class="array-item-title">Item ' . ($index + 1) . '</div>';
                    $html .= '<div class="metadata">';
                    
                    foreach ($simpleData as $key => $value) {
                        $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                        $valueSafe = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                        $html .= '<div class="metadata-row">';
                        $html .= '<div class="metadata-label">' . $keySafe . '</div>';
                        $html .= '<div class="metadata-value">' . $valueSafe . '</div>';
                        $html .= '</div>';
                    }
                    
                    $html .= '</div>';
                    $html .= '</div>';
                }
                
                // Recursively render sub-arrays as separate tables
                foreach ($subArrays as $key => $value) {
                    $subPath = $itemPath . '->' . $key;
                    $html .= renderJsonData($value, $subPath);
                }
            } else {
                // Simple value in array
                $valueSafe = htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8');
                $html .= '<div class="metadata">';
                $html .= '<div class="metadata-row">';
                $html .= '<div class="metadata-label">Item ' . ($index + 1) . '</div>';
                $html .= '<div class="metadata-value">' . $valueSafe . '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
    } else {
        // It's an associative array (object-like)
        // Separate simple values from arrays
        $subArrays = [];
        $simpleData = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $subArrays[$key] = $value;
            } else {
                $simpleData[$key] = $value;
            }
        }
        
        // Render simple key-value pairs
        if (!empty($simpleData)) {
            if ($path) {
                $html .= '<div class="path-title">' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            
            $html .= '<div class="metadata">';
            
            foreach ($simpleData as $key => $value) {
                $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                $valueSafe = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                $html .= '<div class="metadata-row">';
                $html .= '<div class="metadata-label">' . $keySafe . '</div>';
                $html .= '<div class="metadata-value">' . $valueSafe . '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        // Recursively render sub-arrays as separate tables with path titles
        foreach ($subArrays as $key => $value) {
            $subPath = $path ? $path . '->' . $key : $key;
            $html .= renderJsonData($value, $subPath);
        }
    }
    
    return $html;
}
