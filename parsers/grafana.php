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
        <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td class="metadata-label">Status</td>
                <td class="metadata-value"><strong style="color: $statusColor;">$statusIcon $statusSafe</strong></td>
            </tr>
            <tr>
                <td class="metadata-label">Receiver</td>
                <td class="metadata-value">$receiverSafe</td>
            </tr>
            <tr>
                <td class="metadata-label">Alert Count</td>
                <td class="metadata-value">$alertCount</td>
            </tr>
HTML;
    
    if ($truncatedAlerts > 0) {
        $alertSummary .= <<<HTML
            <tr>
                <td class="metadata-label">Truncated Alerts</td>
                <td class="metadata-value">$truncatedAlerts</td>
            </tr>
HTML;
    }
    
    if ($externalURL) {
        $externalURLSafe = htmlspecialchars($externalURL, ENT_QUOTES, 'UTF-8');
        $alertSummary .= <<<HTML
            <tr>
                <td class="metadata-label">Alertmanager URL</td>
                <td class="metadata-value"><a href="$externalURLSafe" style="color: #7c3aed;">$externalURLSafe</a></td>
            </tr>
HTML;
    }
    
    $alertSummary .= <<<HTML
        </table>
    </div>
HTML;
    
    // Build common labels section
    $labelsContent = '';
    if (!empty($groupLabels) || !empty($commonLabels)) {
        $labelsContent .= '<div class="section"><div class="section-title">Labels</div>';
        
        if (!empty($groupLabels)) {
            $labelsContent .= '<div class="subsection-title">Group Labels</div><table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
            foreach ($groupLabels as $key => $value) {
                $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                $valueSafe = esc($value);
                $labelsContent .= <<<HTML
                <tr>
                    <td class="metadata-label">$keySafe</td>
                    <td class="metadata-value">$valueSafe</td>
                </tr>
HTML;
            }
            $labelsContent .= '</table>';
        }
        
        if (!empty($commonLabels)) {
            $labelsContent .= '<div class="subsection-title">Common Labels</div><table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
            foreach ($commonLabels as $key => $value) {
                $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                $valueSafe = esc($value);
                $labelsContent .= <<<HTML
                <tr>
                    <td class="metadata-label">$keySafe</td>
                    <td class="metadata-value">$valueSafe</td>
                </tr>
HTML;
            }
            $labelsContent .= '</table>';
        }
        
        $labelsContent .= '</div>';
    }
    
    // Build common annotations section
    $annotationsContent = '';
    if (!empty($commonAnnotations)) {
        $annotationsContent .= '<div class="section"><div class="section-title">Common Annotations</div><table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
        foreach ($commonAnnotations as $key => $value) {
            $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $valueSafe = esc($value);
            $annotationsContent .= <<<HTML
            <tr>
                <td class="metadata-label">$keySafe</td>
                <td class="metadata-value">$valueSafe</td>
            </tr>
HTML;
        }
        $annotationsContent .= '</table></div>';
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
                <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
HTML;
            
            // Add alert labels
            if (!empty($alertLabels)) {
                $alertsContent .= '</table><div class="subsection-title">Labels</div><table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
                foreach ($alertLabels as $key => $value) {
                    $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                    $valueSafe = esc($value);
                    $alertsContent .= <<<HTML
                    <tr>
                        <td class="metadata-label">$keySafe</td>
                        <td class="metadata-value">$valueSafe</td>
                    </tr>
HTML;
                }
                $alertsContent .= '</table><table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
            }
            
            // Add alert annotations
            if (!empty($alertAnnotations)) {
                $alertsContent .= '</table><div class="subsection-title">Annotations</div><table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
                foreach ($alertAnnotations as $key => $value) {
                    $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                    $valueSafe = esc($value);
                    $alertsContent .= <<<HTML
                    <tr>
                        <td class="metadata-label">$keySafe</td>
                        <td class="metadata-value">$valueSafe</td>
                    </tr>
HTML;
                }
                $alertsContent .= '</table><table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
            }
            
            // Add timing and metadata
            if ($startsAt) {
                $startsAtSafe = htmlspecialchars($startsAt, ENT_QUOTES, 'UTF-8');
                $alertsContent .= <<<HTML
                <tr>
                    <td class="metadata-label">Started At</td>
                    <td class="metadata-value">$startsAtSafe</td>
                </tr>
HTML;
            }
            
            if ($endsAt) {
                $endsAtSafe = htmlspecialchars($endsAt, ENT_QUOTES, 'UTF-8');
                $alertsContent .= <<<HTML
                <tr>
                    <td class="metadata-label">Ended At</td>
                    <td class="metadata-value">$endsAtSafe</td>
                </tr>
HTML;
            }
            
            if ($generatorURL) {
                $generatorURLSafe = htmlspecialchars($generatorURL, ENT_QUOTES, 'UTF-8');
                $alertsContent .= <<<HTML
                <tr>
                    <td class="metadata-label">Generator URL</td>
                    <td class="metadata-value"><a href="$generatorURLSafe" style="color: #7c3aed;">$generatorURLSafe</a></td>
                </tr>
HTML;
            }
            
            if ($fingerprint) {
                $fingerprintSafe = htmlspecialchars($fingerprint, ENT_QUOTES, 'UTF-8');
                $alertsContent .= <<<HTML
                <tr>
                    <td class="metadata-label">Fingerprint</td>
                    <td class="metadata-value">$fingerprintSafe</td>
                </tr>
HTML;
            }
            
            $alertsContent .= '</table></div>';
        }
        
        $alertsContent .= '</div>';
    }
    
    $emailTitle = "$statusIcon Grafana Alert: $statusSafe";
    $emailStamp = "$dateSafe";
    $body = <<<HTML

        $alertSummary

        $labelsContent

        $annotationsContent

        $alertsContent

        <div class="section">
            <div class="section-title">📋 Request Information</div>
            <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td class="metadata-label">Receiver</td>
                    <td class="metadata-value">$receiverSafe</td>
                </tr>
                <tr>
                    <td class="metadata-label">IP Address</td>
                    <td class="metadata-value">$ipSafe</td>
                </tr>
            </table>
        </div>
HTML;
    $html = emailShell($emailTitle, $emailStamp, $body, "This Grafana/Prometheus alert was automatically forwarded to your email address.");

    // Create subject line
    $alertCountText = $alertCount === 1 ? '1 alert' : "$alertCount alerts";
    $subject = "$statusIcon Grafana Alert: $statusSafe - $alertCountText";
    
    return [
        'html' => $html,
        'subject' => $subject
    ];
}
