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
    
    $emailTitle = "$eventIcon Webex Interact: $eventTitleSafe";
    $emailStamp = "$dateSafe";
    $body = <<<HTML

        <div class="section">
            <div class="section-title">📋 Event Information</div>
            <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td class="metadata-label">Event Type</td>
                    <td class="metadata-value">$statusSafe</td>
                </tr>
                <tr>
                    <td class="metadata-label">IP Address</td>
                    <td class="metadata-value">$ipSafe</td>
                </tr>
            </table>
        </div>

        $eventContent
HTML;
    $html = emailShell($emailTitle, $emailStamp, $body, "This Webex Interact webhook was automatically forwarded to your email address.");

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
        <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td class="metadata-label">Message ID</td>
                <td class="metadata-value">$messageId</td>
            </tr>
            <tr>
                <td class="metadata-label">To</td>
                <td class="metadata-value">$to</td>
            </tr>
            <tr>
                <td class="metadata-label">From</td>
                <td class="metadata-value">$from</td>
            </tr>
            <tr>
                <td class="metadata-label">Campaign ID</td>
                <td class="metadata-value">$campaignId</td>
            </tr>
            <tr>
                <td class="metadata-label">Submitted At</td>
                <td class="metadata-value">$submittedAt</td>
            </tr>
        </table>
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
        <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td class="metadata-label">Message ID</td>
                <td class="metadata-value">$messageId</td>
            </tr>
            <tr>
                <td class="metadata-label">To</td>
                <td class="metadata-value">$to</td>
            </tr>
            <tr>
                <td class="metadata-label">From</td>
                <td class="metadata-value">$from</td>
            </tr>
            <tr>
                <td class="metadata-label">Campaign ID</td>
                <td class="metadata-value">$campaignId</td>
            </tr>
            <tr>
                <td class="metadata-label">Delivered At</td>
                <td class="metadata-value">$deliveredAt</td>
            </tr>
        </table>
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
        <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td class="metadata-label">Message ID</td>
                <td class="metadata-value">$messageId</td>
            </tr>
            <tr>
                <td class="metadata-label">To</td>
                <td class="metadata-value">$to</td>
            </tr>
            <tr>
                <td class="metadata-label">From</td>
                <td class="metadata-value">$from</td>
            </tr>
            <tr>
                <td class="metadata-label">Campaign ID</td>
                <td class="metadata-value">$campaignId</td>
            </tr>
            <tr>
                <td class="metadata-label">Failed At</td>
                <td class="metadata-value">$failedAt</td>
            </tr>
        </table>
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
        <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td class="metadata-label">Shortlink</td>
                <td class="metadata-value"><a href="$shortlink" style="color: #7c3aed;">$shortlink</a></td>
            </tr>
            <tr>
                <td class="metadata-label">Original URL</td>
                <td class="metadata-value"><a href="$originalUrl" style="color: #7c3aed;">$originalUrl</a></td>
            </tr>
            <tr>
                <td class="metadata-label">Phone Number</td>
                <td class="metadata-value">$phoneNumber</td>
            </tr>
            <tr>
                <td class="metadata-label">Message ID</td>
                <td class="metadata-value">$messageId</td>
            </tr>
            <tr>
                <td class="metadata-label">Campaign ID</td>
                <td class="metadata-value">$campaignId</td>
            </tr>
            <tr>
                <td class="metadata-label">Clicked At</td>
                <td class="metadata-value">$clickedAt</td>
            </tr>
            <tr>
                <td class="metadata-label">IP Address</td>
                <td class="metadata-value">$ipAddress</td>
            </tr>
        </table>
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
        <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td class="metadata-label">Message ID</td>
                <td class="metadata-value">$messageId</td>
            </tr>
            <tr>
                <td class="metadata-label">From</td>
                <td class="metadata-value">$from</td>
            </tr>
            <tr>
                <td class="metadata-label">To</td>
                <td class="metadata-value">$to</td>
            </tr>
            <tr>
                <td class="metadata-label">Keyword</td>
                <td class="metadata-value">$keyword</td>
            </tr>
            <tr>
                <td class="metadata-label">Received At</td>
                <td class="metadata-value">$receivedAt</td>
            </tr>
        </table>
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
        <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td class="metadata-label">Phone Number</td>
                <td class="metadata-value">$phoneNumber</td>
            </tr>
            <tr>
                <td class="metadata-label">From Number</td>
                <td class="metadata-value">$from</td>
            </tr>
            <tr>
                <td class="metadata-label">Keyword</td>
                <td class="metadata-value">$keyword</td>
            </tr>
            <tr>
                <td class="metadata-label">Message ID</td>
                <td class="metadata-value">$messageId</td>
            </tr>
            <tr>
                <td class="metadata-label">Opt Out At</td>
                <td class="metadata-value">$optOutAt</td>
            </tr>
        </table>
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
        $customFieldsHtml = '<div class="section"><div class="section-title">🏷️  Custom Fields</div><table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">';
        foreach ($data['custom_fields'] as $key => $value) {
            $keySafe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $valueSafe = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $customFieldsHtml .= <<<HTML
            <tr>
                <td class="metadata-label">$keySafe</td>
                <td class="metadata-value">$valueSafe</td>
            </tr>
HTML;
        }
        $customFieldsHtml .= '</table></div>';
    }
    
    return <<<HTML
    <div class="section">
        <div class="section-title">👤 Contact Details</div>
        <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td class="metadata-label">Contact ID</td>
                <td class="metadata-value">$contactId</td>
            </tr>
            <tr>
                <td class="metadata-label">Phone Number</td>
                <td class="metadata-value">$phoneNumber</td>
            </tr>
            <tr>
                <td class="metadata-label">First Name</td>
                <td class="metadata-value">$firstName</td>
            </tr>
            <tr>
                <td class="metadata-label">Last Name</td>
                <td class="metadata-value">$lastName</td>
            </tr>
            <tr>
                <td class="metadata-label">Email</td>
                <td class="metadata-value">$email</td>
            </tr>
            <tr>
                <td class="metadata-label">Status</td>
                <td class="metadata-value">$status</td>
            </tr>
            <tr>
                <td class="metadata-label">Created At</td>
                <td class="metadata-value">$createdAt</td>
            </tr>
            <tr>
                <td class="metadata-label">Updated At</td>
                <td class="metadata-value">$updatedAt</td>
            </tr>
        </table>
    </div>
    
    $customFieldsHtml
HTML;
}
