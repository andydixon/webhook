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
    
    // Determine event type from headers
    $event = $headers['X-GitHub-Event'] ?? $headers['X-Github-Event'] ?? 'Unknown';
    $delivery = $headers['X-GitHub-Delivery'] ?? $headers['X-Github-Delivery'] ?? 'Unknown';
    
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
                <div class="metadata">
                    <div class="metadata-row">
                        <div class="metadata-label">Branch</div>
                        <div class="metadata-value">$ref</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">Commits</div>
                        <div class="metadata-value">$commitCount</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">Pusher</div>
                        <div class="metadata-value">$senderSafe</div>
                    </div>
                </div>
            </div>
HTML;
            
            if ($commitCount > 0) {
                $eventContent .= '<div class="section"><div class="section-title">Commits</div>';
                foreach (array_slice($commits, 0, 10) as $commit) {
                    $message = htmlspecialchars($commit['message'] ?? '', ENT_QUOTES, 'UTF-8');
                    $author = htmlspecialchars($commit['author']['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                    $sha = htmlspecialchars(substr($commit['id'] ?? '', 0, 7), ENT_QUOTES, 'UTF-8');
                    $eventContent .= "<div class=\"commit-item\"><strong>$sha</strong> $message <em>by $author</em></div>";
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
                <div class="metadata">
                    <div class="metadata-row">
                        <div class="metadata-label">Number</div>
                        <div class="metadata-value">#$prNumber</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">Title</div>
                        <div class="metadata-value">$prTitle</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">State</div>
                        <div class="metadata-value">$prState</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">Branch</div>
                        <div class="metadata-value">$prHead → $prBase</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">URL</div>
                        <div class="metadata-value"><a href="$prUrl" style="color: #7c3aed;">$prUrl</a></div>
                    </div>
                </div>
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
                <div class="metadata">
                    <div class="metadata-row">
                        <div class="metadata-label">Number</div>
                        <div class="metadata-value">#$issueNumber</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">Title</div>
                        <div class="metadata-value">$issueTitle</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">State</div>
                        <div class="metadata-value">$issueState</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">URL</div>
                        <div class="metadata-value"><a href="$issueUrl" style="color: #7c3aed;">$issueUrl</a></div>
                    </div>
                </div>
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
                <div class="metadata">
                    <div class="metadata-row">
                        <div class="metadata-label">Tag</div>
                        <div class="metadata-value">$tagName</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">Name</div>
                        <div class="metadata-value">$releaseName</div>
                    </div>
                    <div class="metadata-row">
                        <div class="metadata-label">URL</div>
                        <div class="metadata-value"><a href="$releaseUrl" style="color: #7c3aed;">$releaseUrl</a></div>
                    </div>
                </div>
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
            <div class="metadata">
                <div class="metadata-row">
                    <div class="metadata-label">Repository</div>
                    <div class="metadata-value">$repoSafe</div>
                </div>
                <div class="metadata-row">
                    <div class="metadata-label">Event</div>
                    <div class="metadata-value">$eventSafe</div>
                </div>
                <div class="metadata-row">
                    <div class="metadata-label">Sender</div>
                    <div class="metadata-value">$senderSafe</div>
                </div>
                <div class="metadata-row">
                    <div class="metadata-label">IP Address</div>
                    <div class="metadata-value">$ipSafe</div>
                </div>
            </div>
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
