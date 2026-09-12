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
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    
    // Sanitize metadata for HTML
    $dateSafe = htmlspecialchars($metadata['date'], ENT_QUOTES, 'UTF-8');
    $ipSafe = htmlspecialchars($metadata['ip'], ENT_QUOTES, 'UTF-8');
    $methodSafe = htmlspecialchars($metadata['method'], ENT_QUOTES, 'UTF-8');
    $contentTypeSafe = htmlspecialchars($metadata['contentType'], ENT_QUOTES, 'UTF-8');
    
    // Start building HTML
    $emailTitle = "📊 JSON Webhook Data";
    $emailStamp = "$dateSafe";
    $body = <<<HTML

        <div class="section">
            <div class="section-title">📋 Request Information</div>
            <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td class="metadata-label">IP Address</td>
                    <td class="metadata-value">$ipSafe</td>
                </tr>
                <tr>
                    <td class="metadata-label">Method</td>
                    <td class="metadata-value">$methodSafe</td>
                </tr>
                <tr>
                    <td class="metadata-label">Content-Type</td>
                    <td class="metadata-value">$contentTypeSafe</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">📦 JSON Data</div>

HTML;
    
    // Process the JSON data
    $body .= renderJsonData($payload);
    
    $body .= <<<HTML
        </div>
HTML;
    $html = emailShell($emailTitle, $emailStamp, $body, "JSON data automatically parsed and formatted.");
    
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
        // Scalar (or null): one-row table so it lines up with everything else
        $label = htmlspecialchars($path ?: 'value', ENT_QUOTES, 'UTF-8');
        $safe = esc(is_string($data) ? $data : json_encode($data));
        return '<table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">'
             . '<tr><td class="metadata-label">' . $label . '</td><td class="metadata-value">' . $safe . '</td></tr></table>';
    }
    
    // Check if it's an indexed array (numeric keys in sequence)
    $isIndexedArray = array_keys($data) === range(0, count($data) - 1);
    
    if ($isIndexedArray && count($data) > 0) {
        // Array of plain values: one table under a single breadcrumb
        if (!array_filter($data, 'is_array')) {
            if ($path) {
                $html .= '<div class="path-title">' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            $html .= '<table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
            foreach ($data as $index => $item) {
                $html .= '<tr><td class="metadata-label">Item ' . ($index + 1) . '</td><td class="metadata-value">' . esc($item) . '</td></tr>';
            }
            return $html . '</table>';
        }

        // Array of objects
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
                    $html .= '<table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
                    
                    foreach ($simpleData as $key => $value) {
                        $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                        $valueSafe = esc($value);
                        $html .= '<tr>';
                        $html .= '<td class="metadata-label">' . $keySafe . '</td>';
                        $html .= '<td class="metadata-value">' . $valueSafe . '</td>';
                        $html .= '</tr>';
                    }
                    
                    $html .= '</table>';
                    $html .= '</div>';
                }
                
                // Recursively render sub-arrays as separate tables
                foreach ($subArrays as $key => $value) {
                    $subPath = $itemPath . '->' . $key;
                    $html .= renderJsonData($value, $subPath);
                }
            } else {
                // Simple value in array
                $valueSafe = esc($item);
                $html .= '<table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
                $html .= '<tr>';
                $html .= '<td class="metadata-label">Item ' . ($index + 1) . '</td>';
                $html .= '<td class="metadata-value">' . $valueSafe . '</td>';
                $html .= '</tr>';
                $html .= '</table>';
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
            
            $html .= '<table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
            
            foreach ($simpleData as $key => $value) {
                $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                $valueSafe = esc($value);
                $html .= '<tr>';
                $html .= '<td class="metadata-label">' . $keySafe . '</td>';
                $html .= '<td class="metadata-value">' . $valueSafe . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</table>';
        }
        
        // Recursively render sub-arrays as separate tables with path titles
        foreach ($subArrays as $key => $value) {
            $subPath = $path ? $path . '->' . $key : $key;
            $html .= renderJsonData($value, $subPath);
        }
    }
    
    return $html;
}
