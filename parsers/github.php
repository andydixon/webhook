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
                        <div class="metadata-value"><a href="$prUrl" style="color: #0066cc;">$prUrl</a></div>
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
                        <div class="metadata-value"><a href="$issueUrl" style="color: #0066cc;">$issueUrl</a></div>
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
                        <div class="metadata-value"><a href="$releaseUrl" style="color: #0066cc;">$releaseUrl</a></div>
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
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Webhook - $eventSafe</title>
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
            margin: 0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.7;
            color: #2a2a2a;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-x: auto;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
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
        .commit-item {
            padding: 10px 16px;
            border-left: 3px solid #e0e0e0;
            margin-bottom: 8px;
            background: #f8f8f8;
            border-radius: 4px;
            font-size: 14px;
        }
        .commit-item strong {
            color: #0066cc;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
        }
        .commit-item em {
            color: #666666;
            font-style: normal;
            font-size: 13px;
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
        }
        .array-item-simple {
            padding: 8px 12px;
            margin: 4px 0;
            background: #f8f8f8;
            border-left: 3px solid #d0d0d0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
            font-size: 13px;
        }
        .simple-value {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
            font-size: 13px;
            color: #333333;
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
            <h1>$eventIcon GitHub: $eventSafe$actionDisplay</h1>
            <p class="timestamp">$dateSafe</p>
        </div>

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

        <div class="footer">
            <p>This GitHub webhook was automatically forwarded to your email address.</p>
        </div>
    </div>
</body>
</html>
HTML;

    $subject = "$eventIcon GitHub $eventSafe$actionDisplay - $repoSafe";
    
    return [
        'html' => $html,
        'subject' => $subject
    ];
}
