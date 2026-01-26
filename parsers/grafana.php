<?php
/**
 * Grafana/Prometheus Alertmanager Webhook Parser
 * Parses Grafana/Prometheus Alertmanager webhook events into a formatted email
 * 
 * @param string $rawBody The raw request body
 * @param array $headers The request headers
 * @param array $metadata Request metadata (date, ip, method, contentType)
 * @return array|false Array with 'html' and 'subject' keys, or false on failure
 */
function grafanaParse($rawBody, $headers, $metadata) {
    $payload = json_decode($rawBody, true);
    if (!$payload) {
        return false;
    }
    
    // Extract common data
    $status = $payload['status'] ?? 'unknown';
    $receiver = $payload['receiver'] ?? 'Unknown';
    $groupKey = $payload['groupKey'] ?? 'Unknown';
    $externalURL = $payload['externalURL'] ?? null;
    $alerts = $payload['alerts'] ?? [];
    $alertCount = count($alerts);
    $truncatedAlerts = $payload['truncatedAlerts'] ?? 0;
    $groupLabels = $payload['groupLabels'] ?? [];
    $commonLabels = $payload['commonLabels'] ?? [];
    $commonAnnotations = $payload['commonAnnotations'] ?? [];
    
    // Sanitize for HTML
    $statusSafe = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
    $receiverSafe = htmlspecialchars($receiver, ENT_QUOTES, 'UTF-8');
    $dateSafe = htmlspecialchars($metadata['date'], ENT_QUOTES, 'UTF-8');
    $ipSafe = htmlspecialchars($metadata['ip'], ENT_QUOTES, 'UTF-8');
    
    // Choose icon based on status
    $statusIcon = '🔔';
    $statusColor = '#666666';
    if ($status === 'firing') {
        $statusIcon = '🚨';
        $statusColor = '#dc3545';
    } elseif ($status === 'resolved') {
        $statusIcon = '✅';
        $statusColor = '#28a745';
    }
    
    // Build alert summary section
    $alertSummary = <<<HTML
    <div class="section">
        <div class="section-title">Alert Summary</div>
        <div class="metadata">
            <div class="metadata-row">
                <div class="metadata-label">Status</div>
                <div class="metadata-value"><strong style="color: $statusColor;">$statusIcon $statusSafe</strong></div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Receiver</div>
                <div class="metadata-value">$receiverSafe</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Alert Count</div>
                <div class="metadata-value">$alertCount</div>
            </div>
HTML;
    
    if ($truncatedAlerts > 0) {
        $alertSummary .= <<<HTML
            <div class="metadata-row">
                <div class="metadata-label">Truncated Alerts</div>
                <div class="metadata-value">$truncatedAlerts</div>
            </div>
HTML;
    }
    
    if ($externalURL) {
        $externalURLSafe = htmlspecialchars($externalURL, ENT_QUOTES, 'UTF-8');
        $alertSummary .= <<<HTML
            <div class="metadata-row">
                <div class="metadata-label">Alertmanager URL</div>
                <div class="metadata-value"><a href="$externalURLSafe" style="color: #0066cc;">$externalURLSafe</a></div>
            </div>
HTML;
    }
    
    $alertSummary .= <<<HTML
        </div>
    </div>
HTML;
    
    // Build common labels section
    $labelsContent = '';
    if (!empty($groupLabels) || !empty($commonLabels)) {
        $labelsContent .= '<div class="section"><div class="section-title">Labels</div>';
        
        if (!empty($groupLabels)) {
            $labelsContent .= '<div class="subsection-title">Group Labels</div><div class="metadata">';
            foreach ($groupLabels as $key => $value) {
                $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                $valueSafe = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $labelsContent .= <<<HTML
                <div class="metadata-row">
                    <div class="metadata-label">$keySafe</div>
                    <div class="metadata-value">$valueSafe</div>
                </div>
HTML;
            }
            $labelsContent .= '</div>';
        }
        
        if (!empty($commonLabels)) {
            $labelsContent .= '<div class="subsection-title">Common Labels</div><div class="metadata">';
            foreach ($commonLabels as $key => $value) {
                $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                $valueSafe = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $labelsContent .= <<<HTML
                <div class="metadata-row">
                    <div class="metadata-label">$keySafe</div>
                    <div class="metadata-value">$valueSafe</div>
                </div>
HTML;
            }
            $labelsContent .= '</div>';
        }
        
        $labelsContent .= '</div>';
    }
    
    // Build common annotations section
    $annotationsContent = '';
    if (!empty($commonAnnotations)) {
        $annotationsContent .= '<div class="section"><div class="section-title">Common Annotations</div><div class="metadata">';
        foreach ($commonAnnotations as $key => $value) {
            $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $valueSafe = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $annotationsContent .= <<<HTML
            <div class="metadata-row">
                <div class="metadata-label">$keySafe</div>
                <div class="metadata-value">$valueSafe</div>
            </div>
HTML;
        }
        $annotationsContent .= '</div></div>';
    }
    
    // Build individual alerts section
    $alertsContent = '';
    if (!empty($alerts)) {
        $alertsContent .= '<div class="section"><div class="section-title">Alerts</div>';
        
        foreach ($alerts as $index => $alert) {
            $alertStatus = $alert['status'] ?? 'unknown';
            $alertLabels = $alert['labels'] ?? [];
            $alertAnnotations = $alert['annotations'] ?? [];
            $startsAt = $alert['startsAt'] ?? null;
            $endsAt = $alert['endsAt'] ?? null;
            $generatorURL = $alert['generatorURL'] ?? null;
            $fingerprint = $alert['fingerprint'] ?? null;
            
            $alertStatusSafe = htmlspecialchars($alertStatus, ENT_QUOTES, 'UTF-8');
            $alertNumber = $index + 1;
            
            // Choose alert icon
            $alertIcon = '🔔';
            $alertColor = '#666666';
            if ($alertStatus === 'firing') {
                $alertIcon = '🔥';
                $alertColor = '#dc3545';
            } elseif ($alertStatus === 'resolved') {
                $alertIcon = '✅';
                $alertColor = '#28a745';
            }
            
            $alertsContent .= <<<HTML
            <div class="array-item">
                <div class="array-item-title">$alertIcon Alert $alertNumber - <span style="color: $alertColor;">$alertStatusSafe</span></div>
                <div class="metadata">
HTML;
            
            // Add alert labels
            if (!empty($alertLabels)) {
                $alertsContent .= '</div><div class="subsection-title">Labels</div><div class="metadata">';
                foreach ($alertLabels as $key => $value) {
                    $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                    $valueSafe = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    $alertsContent .= <<<HTML
                    <div class="metadata-row">
                        <div class="metadata-label">$keySafe</div>
                        <div class="metadata-value">$valueSafe</div>
                    </div>
HTML;
                }
                $alertsContent .= '</div><div class="metadata">';
            }
            
            // Add alert annotations
            if (!empty($alertAnnotations)) {
                $alertsContent .= '</div><div class="subsection-title">Annotations</div><div class="metadata">';
                foreach ($alertAnnotations as $key => $value) {
                    $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                    $valueSafe = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    $alertsContent .= <<<HTML
                    <div class="metadata-row">
                        <div class="metadata-label">$keySafe</div>
                        <div class="metadata-value">$valueSafe</div>
                    </div>
HTML;
                }
                $alertsContent .= '</div><div class="metadata">';
            }
            
            // Add timing and metadata
            if ($startsAt) {
                $startsAtSafe = htmlspecialchars($startsAt, ENT_QUOTES, 'UTF-8');
                $alertsContent .= <<<HTML
                <div class="metadata-row">
                    <div class="metadata-label">Started At</div>
                    <div class="metadata-value">$startsAtSafe</div>
                </div>
HTML;
            }
            
            if ($endsAt) {
                $endsAtSafe = htmlspecialchars($endsAt, ENT_QUOTES, 'UTF-8');
                $alertsContent .= <<<HTML
                <div class="metadata-row">
                    <div class="metadata-label">Ended At</div>
                    <div class="metadata-value">$endsAtSafe</div>
                </div>
HTML;
            }
            
            if ($generatorURL) {
                $generatorURLSafe = htmlspecialchars($generatorURL, ENT_QUOTES, 'UTF-8');
                $alertsContent .= <<<HTML
                <div class="metadata-row">
                    <div class="metadata-label">Generator URL</div>
                    <div class="metadata-value"><a href="$generatorURLSafe" style="color: #0066cc;">$generatorURLSafe</a></div>
                </div>
HTML;
            }
            
            if ($fingerprint) {
                $fingerprintSafe = htmlspecialchars($fingerprint, ENT_QUOTES, 'UTF-8');
                $alertsContent .= <<<HTML
                <div class="metadata-row">
                    <div class="metadata-label">Fingerprint</div>
                    <div class="metadata-value">$fingerprintSafe</div>
                </div>
HTML;
            }
            
            $alertsContent .= '</div></div>';
        }
        
        $alertsContent .= '</div>';
    }
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafana Alert - $statusSafe</title>
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
            max-width: 700px;
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
        .metadata {
            display: table;
            width: 100%;
            border: 1px solid #d0d0d0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
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
            width: 35%;
            vertical-align: top;
            font-size: 14px;
        }
        .metadata-value {
            display: table-cell;
            padding: 12px 16px;
            color: #333333;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
            font-size: 13px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .subsection-title {
            color: #000000;
            font-size: 16px;
            font-weight: 600;
            margin: 15px 0 10px 0;
            padding: 8px 12px;
            background: #f0f0f0;
            border-left: 4px solid #666666;
            border-radius: 4px;
        }
        .array-item {
            margin-bottom: 20px;
            padding: 12px;
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
        }
        .array-item-title {
            font-weight: 600;
            color: #000000;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #d0d0d0;
            font-size: 16px;
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
            <h1>$statusIcon Grafana Alert: $statusSafe</h1>
            <p class="timestamp">$dateSafe</p>
        </div>

        $alertSummary

        $labelsContent

        $annotationsContent

        $alertsContent

        <div class="section">
            <div class="section-title">📋 Request Information</div>
            <div class="metadata">
                <div class="metadata-row">
                    <div class="metadata-label">Receiver</div>
                    <div class="metadata-value">$receiverSafe</div>
                </div>
                <div class="metadata-row">
                    <div class="metadata-label">IP Address</div>
                    <div class="metadata-value">$ipSafe</div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This Grafana/Prometheus alert was automatically forwarded to your email address.</p>
        </div>
    </div>
</body>
</html>
HTML;

    // Create subject line
    $alertCountText = $alertCount === 1 ? '1 alert' : "$alertCount alerts";
    $subject = "$statusIcon Grafana Alert: $statusSafe - $alertCountText";
    
    return [
        'html' => $html,
        'subject' => $subject
    ];
}
