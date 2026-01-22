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
