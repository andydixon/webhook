<?php
/**
 * Webex Interact Webhook Parser
 * Parses Webex Interact SMS webhook events into a formatted email
 * 
 * @param string $rawBody The raw request body
 * @param array $headers The request headers
 * @param array $metadata Request metadata (date, ip, method, contentType)
 * @return array|false Array with 'html' and 'subject' keys, or false on failure
 */
function wxinteractParse($rawBody, $headers, $metadata) {
    $payload = json_decode($rawBody, true);
    if (!$payload || !isset($payload['data'])) {
        return false;
    }
    
    $data = $payload['data'];
    $status = $data['status'] ?? 'Unknown';
    
    // Sanitize common fields
    $statusSafe = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
    $dateSafe = htmlspecialchars($metadata['date'], ENT_QUOTES, 'UTF-8');
    $ipSafe = htmlspecialchars($metadata['ip'], ENT_QUOTES, 'UTF-8');
    
    // Determine event type and icon
    $eventIcon = '📱';
    $eventTitle = 'SMS Event';
    $eventContent = '';
    
    switch ($status) {
        case 'submitted':
            $eventIcon = '📤';
            $eventTitle = 'Outbound SMS - Submitted';
            $eventContent = buildOutboundSmsSubmittedContent($data);
            break;
            
        case 'delivered':
            $eventIcon = '✅';
            $eventTitle = 'Outbound SMS - Delivered';
            $eventContent = buildOutboundSmsDeliveredContent($data);
            break;
            
        case 'failed':
            $eventIcon = '❌';
            $eventTitle = 'Outbound SMS - Failed';
            $eventContent = buildOutboundSmsFailedContent($data);
            break;
            
        case 'shortlink_clicked':
            $eventIcon = '🔗';
            $eventTitle = 'Shortlink Clicked';
            $eventContent = buildShortlinkClickedContent($data);
            break;
            
        case 'received':
            $eventIcon = '📥';
            $eventTitle = 'Inbound SMS Received';
            $eventContent = buildInboundSmsContent($data);
            break;
            
        case 'opt_out':
            $eventIcon = '🚫';
            $eventTitle = 'SMS Opt Out Received';
            $eventContent = buildOptOutContent($data);
            break;
            
        case 'contact_created':
        case 'contact_updated':
            $eventIcon = '👤';
            $eventTitle = 'Contacts Callback';
            $eventContent = buildContactsCallbackContent($data);
            break;
            
        default:
            // Unknown event type - display all data
            $eventIcon = '📱';
            $eventTitle = "SMS Event: $statusSafe";
            $eventContent = '<div class="section"><div class="section-title">Event Details</div>';
            $eventContent .= renderDataAsTable($data);
            $eventContent .= '</div>';
    }
    
    $eventTitleSafe = htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8');
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webex Interact - $eventTitleSafe</title>
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
        .data-box {
            border: 1px solid #d0d0d0;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 10px 0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.7;
            color: #2a2a2a;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-x: auto;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            background: #f8f8f8;
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
        }
        .message-box {
            border-left: 4px solid #0066cc;
            background: #f8f8f8;
            padding: 16px 20px;
            margin: 15px 0;
            border-radius: 4px;
            font-size: 15px;
            line-height: 1.6;
        }
        .error-box {
            border-left: 4px solid #cc0000;
            background: #fff5f5;
            padding: 16px 20px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .subsection {
            margin: 20px 0;
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
            <h1>$eventIcon Webex Interact: $eventTitleSafe</h1>
            <p class="timestamp">$dateSafe</p>
        </div>

        <div class="section">
            <div class="section-title">📋 Event Information</div>
            <div class="metadata">
                <div class="metadata-row">
                    <div class="metadata-label">Event Type</div>
                    <div class="metadata-value">$statusSafe</div>
                </div>
                <div class="metadata-row">
                    <div class="metadata-label">IP Address</div>
                    <div class="metadata-value">$ipSafe</div>
                </div>
            </div>
        </div>

        $eventContent

        <div class="footer">
            <p>This Webex Interact webhook was automatically forwarded to your email address.</p>
        </div>
    </div>
</body>
</html>
HTML;

    $subject = "$eventIcon Webex Interact: $eventTitle";
    
    return [
        'html' => $html,
        'subject' => $subject
    ];
}

/**
 * Build content for Outbound SMS Submitted event
 */
function buildOutboundSmsSubmittedContent($data) {
    $messageId = htmlspecialchars($data['message_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $to = htmlspecialchars($data['to'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $from = htmlspecialchars($data['from'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($data['message'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $submittedAt = htmlspecialchars($data['submitted_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $campaignId = htmlspecialchars($data['campaign_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
    <div class="section">
        <div class="section-title">📤 SMS Details</div>
        <div class="metadata">
            <div class="metadata-row">
                <div class="metadata-label">Message ID</div>
                <div class="metadata-value">$messageId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">To</div>
                <div class="metadata-value">$to</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">From</div>
                <div class="metadata-value">$from</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Campaign ID</div>
                <div class="metadata-value">$campaignId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Submitted At</div>
                <div class="metadata-value">$submittedAt</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">💬 Message Content</div>
        <div class="message-box">$message</div>
    </div>
HTML;
}

/**
 * Build content for Outbound SMS Delivered event
 */
function buildOutboundSmsDeliveredContent($data) {
    $messageId = htmlspecialchars($data['message_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $to = htmlspecialchars($data['to'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $from = htmlspecialchars($data['from'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $deliveredAt = htmlspecialchars($data['delivered_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $campaignId = htmlspecialchars($data['campaign_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
    <div class="section">
        <div class="section-title">✅ Delivery Details</div>
        <div class="metadata">
            <div class="metadata-row">
                <div class="metadata-label">Message ID</div>
                <div class="metadata-value">$messageId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">To</div>
                <div class="metadata-value">$to</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">From</div>
                <div class="metadata-value">$from</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Campaign ID</div>
                <div class="metadata-value">$campaignId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Delivered At</div>
                <div class="metadata-value">$deliveredAt</div>
            </div>
        </div>
    </div>
HTML;
}

/**
 * Build content for Outbound SMS Failed event
 */
function buildOutboundSmsFailedContent($data) {
    $messageId = htmlspecialchars($data['message_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $to = htmlspecialchars($data['to'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $from = htmlspecialchars($data['from'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $failedAt = htmlspecialchars($data['failed_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $errorCode = htmlspecialchars($data['error_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $errorMessage = htmlspecialchars($data['error_message'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $campaignId = htmlspecialchars($data['campaign_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
    <div class="section">
        <div class="section-title">❌ Failure Details</div>
        <div class="metadata">
            <div class="metadata-row">
                <div class="metadata-label">Message ID</div>
                <div class="metadata-value">$messageId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">To</div>
                <div class="metadata-value">$to</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">From</div>
                <div class="metadata-value">$from</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Campaign ID</div>
                <div class="metadata-value">$campaignId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Failed At</div>
                <div class="metadata-value">$failedAt</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">⚠️  Error Information</div>
        <div class="error-box">
            <strong>Error Code:</strong> $errorCode<br>
            <strong>Error Message:</strong> $errorMessage
        </div>
    </div>
HTML;
}

/**
 * Build content for Shortlink Clicked event
 */
function buildShortlinkClickedContent($data) {
    $shortlink = htmlspecialchars($data['shortlink'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $originalUrl = htmlspecialchars($data['original_url'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $clickedAt = htmlspecialchars($data['clicked_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $phoneNumber = htmlspecialchars($data['phone_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $messageId = htmlspecialchars($data['message_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $campaignId = htmlspecialchars($data['campaign_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $userAgent = htmlspecialchars($data['user_agent'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $ipAddress = htmlspecialchars($data['ip_address'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
    <div class="section">
        <div class="section-title">🔗 Click Details</div>
        <div class="metadata">
            <div class="metadata-row">
                <div class="metadata-label">Shortlink</div>
                <div class="metadata-value"><a href="$shortlink" style="color: #0066cc;">$shortlink</a></div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Original URL</div>
                <div class="metadata-value"><a href="$originalUrl" style="color: #0066cc;">$originalUrl</a></div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Phone Number</div>
                <div class="metadata-value">$phoneNumber</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Message ID</div>
                <div class="metadata-value">$messageId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Campaign ID</div>
                <div class="metadata-value">$campaignId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Clicked At</div>
                <div class="metadata-value">$clickedAt</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">IP Address</div>
                <div class="metadata-value">$ipAddress</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">🌐 Browser Information</div>
        <div class="data-box">$userAgent</div>
    </div>
HTML;
}

/**
 * Build content for Inbound SMS Received event
 */
function buildInboundSmsContent($data) {
    $messageId = htmlspecialchars($data['message_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $from = htmlspecialchars($data['from'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $to = htmlspecialchars($data['to'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($data['message'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $receivedAt = htmlspecialchars($data['received_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $keyword = htmlspecialchars($data['keyword'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
    <div class="section">
        <div class="section-title">📥 Inbound SMS Details</div>
        <div class="metadata">
            <div class="metadata-row">
                <div class="metadata-label">Message ID</div>
                <div class="metadata-value">$messageId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">From</div>
                <div class="metadata-value">$from</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">To</div>
                <div class="metadata-value">$to</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Keyword</div>
                <div class="metadata-value">$keyword</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Received At</div>
                <div class="metadata-value">$receivedAt</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">💬 Message Content</div>
        <div class="message-box">$message</div>
    </div>
HTML;
}

/**
 * Build content for SMS Opt Out event
 */
function buildOptOutContent($data) {
    $phoneNumber = htmlspecialchars($data['phone_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $optOutAt = htmlspecialchars($data['opt_out_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $keyword = htmlspecialchars($data['keyword'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $messageId = htmlspecialchars($data['message_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $from = htmlspecialchars($data['from'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
    <div class="section">
        <div class="section-title">🚫 Opt Out Details</div>
        <div class="metadata">
            <div class="metadata-row">
                <div class="metadata-label">Phone Number</div>
                <div class="metadata-value">$phoneNumber</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">From Number</div>
                <div class="metadata-value">$from</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Keyword</div>
                <div class="metadata-value">$keyword</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Message ID</div>
                <div class="metadata-value">$messageId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Opt Out At</div>
                <div class="metadata-value">$optOutAt</div>
            </div>
        </div>
    </div>
HTML;
}

/**
 * Build content for Contacts Callback event
 */
function buildContactsCallbackContent($data) {
    $contactId = htmlspecialchars($data['contact_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $phoneNumber = htmlspecialchars($data['phone_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $firstName = htmlspecialchars($data['first_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $lastName = htmlspecialchars($data['last_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($data['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $status = htmlspecialchars($data['status'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $createdAt = htmlspecialchars($data['created_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    $updatedAt = htmlspecialchars($data['updated_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
    
    $customFieldsHtml = '';
    if (isset($data['custom_fields']) && is_array($data['custom_fields']) && !empty($data['custom_fields'])) {
        $customFieldsHtml = '<div class="section"><div class="section-title">🏷️  Custom Fields</div><div class="metadata">';
        foreach ($data['custom_fields'] as $key => $value) {
            $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $valueSafe = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $customFieldsHtml .= <<<HTML
            <div class="metadata-row">
                <div class="metadata-label">$keySafe</div>
                <div class="metadata-value">$valueSafe</div>
            </div>
HTML;
        }
        $customFieldsHtml .= '</div></div>';
    }
    
    return <<<HTML
    <div class="section">
        <div class="section-title">👤 Contact Details</div>
        <div class="metadata">
            <div class="metadata-row">
                <div class="metadata-label">Contact ID</div>
                <div class="metadata-value">$contactId</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Phone Number</div>
                <div class="metadata-value">$phoneNumber</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">First Name</div>
                <div class="metadata-value">$firstName</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Last Name</div>
                <div class="metadata-value">$lastName</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Email</div>
                <div class="metadata-value">$email</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Status</div>
                <div class="metadata-value">$status</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Created At</div>
                <div class="metadata-value">$createdAt</div>
            </div>
            <div class="metadata-row">
                <div class="metadata-label">Updated At</div>
                <div class="metadata-value">$updatedAt</div>
            </div>
        </div>
    </div>
    
    $customFieldsHtml
HTML;
}
