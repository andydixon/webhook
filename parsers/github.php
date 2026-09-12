<?php
/**
 * GitHub Webhook Parser
 * Parses GitHub webhook events into a formatted email
 * 
 * @param string $rawBody The raw request body
 * @param array $headers The request headers
 * @param array $metadata Request metadata (date, ip, method, contentType)
 * @return array|false Array with 'html' and 'subject' keys, or false on failure
 */
function githubParse($rawBody, $headers, $metadata) {
    $payload = json_decode($rawBody, true);
    if (!$payload) {
        return false;
    }
    
    // Header names arrive lowercase over HTTP/2, so match case-insensitively
    $headers = array_change_key_case($headers, CASE_LOWER);
    $event = $headers['x-github-event'] ?? 'Unknown';
    $delivery = $headers['x-github-delivery'] ?? 'Unknown';
    
    // Extract common data
    $action = $payload['action'] ?? null;
    $repository = $payload['repository']['full_name'] ?? 'Unknown';
    $sender = $payload['sender']['login'] ?? 'Unknown';
    
    // Sanitize for HTML
    $eventSafe = htmlspecialchars($event, ENT_QUOTES, 'UTF-8');
    $actionSafe = $action ? htmlspecialchars($action, ENT_QUOTES, 'UTF-8') : null;
    $repoSafe = htmlspecialchars($repository, ENT_QUOTES, 'UTF-8');
    $senderSafe = htmlspecialchars($sender, ENT_QUOTES, 'UTF-8');
    $dateSafe = htmlspecialchars($metadata['date'], ENT_QUOTES, 'UTF-8');
    $ipSafe = htmlspecialchars($metadata['ip'], ENT_QUOTES, 'UTF-8');
    
    // Build event-specific content
    $eventContent = '';
    $eventIcon = '🔔';
    
    switch ($event) {
        case 'push':
            $eventIcon = '📤';
            $ref = htmlspecialchars($payload['ref'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
            $commits = $payload['commits'] ?? [];
            $commitCount = count($commits);
            
            $eventContent = <<<HTML
            <div class="section">
                <div class="section-title">Push Details</div>
                <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td class="metadata-label">Branch</td>
                        <td class="metadata-value">$ref</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">Commits</td>
                        <td class="metadata-value">$commitCount</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">Pusher</td>
                        <td class="metadata-value">$senderSafe</td>
                    </tr>
                </table>
            </div>
HTML;
            
            if ($commitCount > 0) {
                $eventContent .= '<div class="section"><div class="section-title">Commits</div>';
                foreach (array_slice($commits, 0, 10) as $commit) {
                    $message = esc($commit['message'] ?? '');
                    $author = htmlspecialchars($commit['author']['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                    $sha = htmlspecialchars(substr($commit['id'] ?? '', 0, 7), ENT_QUOTES, 'UTF-8');
                    $url = htmlspecialchars($commit['url'] ?? '', ENT_QUOTES, 'UTF-8');
                    $shaHtml = $url ? "<a class=\"sha\" href=\"$url\">$sha</a>" : "<span class=\"sha\">$sha</span>";
                    $eventContent .= "<div class=\"commit-item\">$shaHtml <span class=\"who\">$author</span><div class=\"msg\">$message</div></div>";
                }
                $eventContent .= '</div>';
            }
            break;
            
        case 'pull_request':
            $eventIcon = '🔀';
            $pr = $payload['pull_request'] ?? [];
            $prNumber = htmlspecialchars($pr['number'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $prTitle = htmlspecialchars($pr['title'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $prUrl = htmlspecialchars($pr['html_url'] ?? '#', ENT_QUOTES, 'UTF-8');
            $prState = htmlspecialchars($pr['state'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $prBase = htmlspecialchars($pr['base']['ref'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $prHead = htmlspecialchars($pr['head']['ref'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            
            $eventContent = <<<HTML
            <div class="section">
                <div class="section-title">Pull Request Details</div>
                <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td class="metadata-label">Number</td>
                        <td class="metadata-value">#$prNumber</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">Title</td>
                        <td class="metadata-value">$prTitle</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">State</td>
                        <td class="metadata-value">$prState</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">Branch</td>
                        <td class="metadata-value">$prHead → $prBase</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">URL</td>
                        <td class="metadata-value"><a href="$prUrl" style="color: #7c3aed;">$prUrl</a></td>
                    </tr>
                </table>
            </div>
HTML;
            break;
            
        case 'issues':
            $eventIcon = '📝';
            $issue = $payload['issue'] ?? [];
            $issueNumber = htmlspecialchars($issue['number'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $issueTitle = htmlspecialchars($issue['title'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $issueUrl = htmlspecialchars($issue['html_url'] ?? '#', ENT_QUOTES, 'UTF-8');
            $issueState = htmlspecialchars($issue['state'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            
            $eventContent = <<<HTML
            <div class="section">
                <div class="section-title">Issue Details</div>
                <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td class="metadata-label">Number</td>
                        <td class="metadata-value">#$issueNumber</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">Title</td>
                        <td class="metadata-value">$issueTitle</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">State</td>
                        <td class="metadata-value">$issueState</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">URL</td>
                        <td class="metadata-value"><a href="$issueUrl" style="color: #7c3aed;">$issueUrl</a></td>
                    </tr>
                </table>
            </div>
HTML;
            break;
            
        case 'release':
            $eventIcon = '🚀';
            $release = $payload['release'] ?? [];
            $tagName = htmlspecialchars($release['tag_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $releaseName = htmlspecialchars($release['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $releaseUrl = htmlspecialchars($release['html_url'] ?? '#', ENT_QUOTES, 'UTF-8');
            
            $eventContent = <<<HTML
            <div class="section">
                <div class="section-title">Release Details</div>
                <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td class="metadata-label">Tag</td>
                        <td class="metadata-value">$tagName</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">Name</td>
                        <td class="metadata-value">$releaseName</td>
                    </tr>
                    <tr>
                        <td class="metadata-label">URL</td>
                        <td class="metadata-value"><a href="$releaseUrl" style="color: #7c3aed;">$releaseUrl</a></td>
                    </tr>
                </table>
            </div>
HTML;
            break;
            
        default:
            // Generic event display - render as structured tables
            $eventContent = '<div class="section"><div class="section-title">Event Details</div>';
            $eventContent .= renderDataAsTable($payload);
            $eventContent .= '</div>';
    }
    
    $actionDisplay = $actionSafe ? " ($actionSafe)" : '';
    
    $emailTitle = "$eventIcon GitHub: $eventSafe$actionDisplay";
    $emailStamp = "$dateSafe";
    $body = <<<HTML

        <div class="section">
            <div class="section-title">📋 Repository Information</div>
            <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td class="metadata-label">Repository</td>
                    <td class="metadata-value">$repoSafe</td>
                </tr>
                <tr>
                    <td class="metadata-label">Event</td>
                    <td class="metadata-value">$eventSafe</td>
                </tr>
                <tr>
                    <td class="metadata-label">Sender</td>
                    <td class="metadata-value">$senderSafe</td>
                </tr>
                <tr>
                    <td class="metadata-label">IP Address</td>
                    <td class="metadata-value">$ipSafe</td>
                </tr>
            </table>
        </div>

        $eventContent
HTML;
    $html = emailShell($emailTitle, $emailStamp, $body, "This GitHub webhook was automatically forwarded to your email address.");

    $subject = "$eventIcon GitHub $eventSafe$actionDisplay - $repoSafe";
    
    return [
        'html' => $html,
        'subject' => $subject
    ];
}
