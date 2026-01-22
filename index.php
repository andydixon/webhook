<?php
/**
 * Webhook Email Forwarder
 * 
 * This script captures incoming webhook requests and forwards the complete request details
 * (headers, body, metadata) to an email address specified in the URL path.
 * 
 * Usage: <?php echo $_SERVER['HTTP_HOST']; ?>/email%40domain.com
 * 
 * @author Dixon
 * @version 1.0
 */

// Prevent any output buffering issues
if (ob_get_level()) {
    ob_end_clean();
}

// Retrieve the complete request URI from the server superglobal
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// Parse the URL to extract only the path component, excluding any query strings
// This ensures that any GET parameters don't interfere with email extraction
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove leading and trailing forward slashes from the path
// Example: '/email%40domain.com/' or '/email%40domain.com/github' 
$pathParts = explode('/', trim($path, '/'));

// Extract email and optional parser
$emailEncoded = $pathParts[0] ?? '';
$parserName = $pathParts[1] ?? null;

// Decode the URL-encoded email address (e.g., %40 becomes @)
// Example: 'email%40domain.com' becomes 'email@domain.com'
$email = urldecode($emailEncoded);

if(empty($email)) {
 include'docs.htm';
 die();
}

// Validate the extracted email address using PHP's built-in filter
// This ensures the email is in a valid format before proceeding
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Set HTTP response code to 400 Bad Request
    http_response_code(400);
    // Return error message to the client
    echo 'Invalid email address provided in URL path.';
    // Terminate script execution
    exit;
}

// Capture the current date and time when the webhook request was received
// Format: YYYY-MM-DD HH:MM:SS (24-hour format)
$dateReceived = date('Y-m-d H:i:s');

// Retrieve the IP address of the client making the request
// Note: This may be a proxy IP if behind a load balancer or CDN
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// Capture the HTTP method used for this request (GET, POST, PUT, DELETE, etc.)
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'Unknown';

// Retrieve the Content-Type header sent by the client
// Use null coalescing operator to provide a default if not set
$contentType = $_SERVER['CONTENT_TYPE'] ?? 'N/A';

// Retrieve all HTTP headers sent with the request
// getallheaders() returns an associative array of all headers
$headersArray = getallheaders();

// Initialize an empty string to store formatted headers
$headersText = '';

// Iterate through each header and format it as "Key: Value"
foreach ($headersArray as $key => $value) {
    // Concatenate each header on a new line
    $headersText .= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . ': ' . 
                    htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\n";
}

// Read the raw request body from the input stream
// php://input allows access to the raw POST data regardless of Content-Type
$rawBody = file_get_contents('php://input');

// Sanitise the raw body for HTML output to prevent XSS attacks
$rawBodySafe = htmlspecialchars($rawBody, ENT_QUOTES, 'UTF-8');

// Compile all PHP superglobal variables into an array for debugging purposes
// This provides visibility of all data received with the request
$variables = [
    '_GET' => $_GET,
    '_POST' => $_POST,
    '_REQUEST' => $_REQUEST,
    '_FILES' => $_FILES,
];

// Convert the variables array to a human-readable string format
// The second parameter (true) makes print_r return the output instead of printing it
$variablesText = print_r($variables, true);

// Sanitise the variables text for HTML output
$variablesTextSafe = htmlspecialchars($variablesText, ENT_QUOTES, 'UTF-8');

// Sanitise individual metadata fields for HTML output
$dateReceivedSafe = htmlspecialchars($dateReceived, ENT_QUOTES, 'UTF-8');
$ipAddressSafe = htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8');
$requestMethodSafe = htmlspecialchars($requestMethod, ENT_QUOTES, 'UTF-8');
$contentTypeSafe = htmlspecialchars($contentType, ENT_QUOTES, 'UTF-8');

// Check if a parser is specified and exists
$html = '';
$subject = '';
if ($parserName && function_exists($parserName . 'Parse')) {
    $parserFunction = $parserName . 'Parse';
    $parsed = $parserFunction($rawBody, $headersArray, [
        'date' => $dateReceived,
        'ip' => $ipAddress,
        'method' => $requestMethod,
        'contentType' => $contentType
    ]);
    
    if ($parsed !== false) {
        $html = $parsed['html'];
        $subject = $parsed['subject'];
    }
}

// If no parser or parser failed, use default format
if (empty($html)) {
    // Construct the HTML email body using a heredoc string for better readability
    // This creates a styled HTML email with all webhook details
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Request Received</title>
    <style>
        /* Gmail-safe reset and main styling */
        body {
            background-color: #ffffff;
            color: #1a1a1a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }
        /* Container for better email client compatibility */
        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        /* Header styling with elegant separator */
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
        /* Section styling with elegant typography */
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
        /* Data display with subtle borders */
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
        /* Metadata grid for clean information display */
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
        /* Footer styling */
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
        <!-- Email Header -->
        <div class="header">
            <h1>⚡ Webhook Request Received</h1>
            <p class="timestamp">$dateReceivedSafe</p>
        </div>

        <!-- Request Information Section -->
        <div class="section">
            <div class="section-title">📋 Request Information</div>
            <div class="metadata">
                <div class="metadata-row">
                    <div class="metadata-label">IP Address</div>
                    <div class="metadata-value">$ipAddressSafe</div>
                </div>
                <div class="metadata-row">
                    <div class="metadata-label">Method</div>
                    <div class="metadata-value">$requestMethodSafe</div>
                </div>
                <div class="metadata-row">
                    <div class="metadata-label">Content-Type</div>
                    <div class="metadata-value">$contentTypeSafe</div>
                </div>
            </div>
        </div>

        <!-- Request Headers Section -->
        <div class="section">
            <div class="section-title">📨 Request Headers</div>
            <pre class="data-box">$headersText</pre>
        </div>

        <!-- Request Body Section -->
        <div class="section">
            <div class="section-title">📦 Request Body</div>
            <pre class="data-box">$rawBodySafe</pre>
        </div>

        <!-- PHP Variables Section -->
        <div class="section">
            <div class="section-title">🔧 PHP Variables</div>
            <pre class="data-box">$variablesTextSafe</pre>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This webhook was automatically forwarded to your email address.</p>
        </div>
    </div>
</body>
</html>
HTML;

    // Construct the email subject line with an attention-grabbing emoji and timestamp
    $subject = "‼️ Webhook Request Received - $dateReceived";
}

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

// Build email headers to ensure proper HTML rendering and sender information
// MIME-Version declares email format capabilities
$emailHeaders = "MIME-Version: 1.0\r\n";
// Content-type specifies HTML email with UTF-8 character encoding
$emailHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
// From header sets the sender address to a no-reply address at wh.dixon.cx
$emailHeaders .= "From: no-reply@wh.dixon.cx\r\n";

// Send the email using PHP's built-in mail function
// Parameters: recipient, subject, message body, headers
$mailSent = mail($email, $subject, $html, $emailHeaders);

// Check if the email was sent successfully
if (!$mailSent) {
    // Log error or handle failure (note: mail() return value is unreliable on some systems)
    error_log("Failed to send webhook email to: $email");
}

// Set the Content-Type header for the HTTP response to plain text
header('Content-Type: text/plain; charset=UTF-8');

// Return a simple acknowledgement to the webhook sender
// This confirms the webhook was received and processed
echo "Webhook received and forwarded to: $email\n\n";

// Include a sanitised copy of the REQUEST data in the response for debugging
echo "Request data:\n";
print_r($_REQUEST);
