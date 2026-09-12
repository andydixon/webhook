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
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => false,
        'error' => 'invalid_email',
        'message' => 'Invalid email address provided in URL path.',
        'hint' => 'Encode @ as %40, e.g. /you%40example.com',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
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
    'GET Variables' => $_GET,
    'POST Variables' => $_POST,
    'Raw Request Body' => $_REQUEST,
    'Uploaded Files' => $_FILES,
];

// Convert the variables array to a human-readable string format
// The second parameter (true) makes print_r return the output instead of printing it
$variablesText = json_encode($variables, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Sanitise the variables text for HTML output
$variablesTextSafe = htmlspecialchars($variablesText, ENT_QUOTES, 'UTF-8');

// Sanitise individual metadata fields for HTML output
$dateReceivedSafe = htmlspecialchars($dateReceived, ENT_QUOTES, 'UTF-8');
$ipAddressSafe = htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8');
$requestMethodSafe = htmlspecialchars($requestMethod, ENT_QUOTES, 'UTF-8');
$contentTypeSafe = htmlspecialchars($contentType, ENT_QUOTES, 'UTF-8');

// Load parser helpers
require_once __DIR__ . '/parsers/helpers.php';

// Check if a parser is specified and load it
$html = '';
$subject = '';
if ($parserName) {
    $parserFile = __DIR__ . '/parsers/' . $parserName . '.php';
    if (file_exists($parserFile)) {
        require_once $parserFile;
        $parserFunction = $parserName . 'Parse';
        
        if (function_exists($parserFunction)) {
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
    }
}

// If no parser or parser failed, use default format
if (empty($html)) {
    // Construct the HTML email body using a heredoc string for better readability
    // This creates a styled HTML email with all webhook details
    $emailTitle = "⚡ Webhook Request Received";
    $emailStamp = "$dateReceivedSafe";
    $body = <<<HTML

        <!-- Request Information Section -->
        <div class="section">
            <div class="section-title">📋 Request Information</div>
            <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td class="metadata-label">IP Address</td>
                    <td class="metadata-value">$ipAddressSafe</td>
                </tr>
                <tr>
                    <td class="metadata-label">Method</td>
                    <td class="metadata-value">$requestMethodSafe</td>
                </tr>
                <tr>
                    <td class="metadata-label">Content-Type</td>
                    <td class="metadata-value">$contentTypeSafe</td>
                </tr>
            </table>
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
            <div class="section-title">🔧 Parsed Variables</div>
            <pre class="data-box">$variablesTextSafe</pre>
        </div>

        <!-- Footer -->
HTML;
    $html = emailShell($emailTitle, $emailStamp, $body, "This webhook was automatically forwarded to your email address.");

    // Construct the email subject line with an attention-grabbing emoji and timestamp
    $subject = "‼️ Webhook Request Received - $dateReceived";
}

// Build email headers to ensure proper HTML rendering and sender information
// MIME-Version declares email format capabilities
$emailHeaders = "MIME-Version: 1.0\r\n";
// Content-type specifies HTML email with UTF-8 character encoding
$emailHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
// Keep both the visible sender and SMTP envelope sender on the public domain.
$envelopeSender = 'no-reply@dixon.cx';
$emailHeaders .= "From: Webhook Call <$envelopeSender>\r\n";

// Send the email using PHP's built-in mail function
// The -f option prevents sendmail from deriving www-data@<container-hostname>.
$mailSent = mail($email, $subject, $html, $emailHeaders, '-f' . $envelopeSender);

// Check if the email was sent successfully
if (!$mailSent) {
    // Log error or handle failure (note: mail() return value is unreliable on some systems)
    error_log("Failed to send webhook email to: $email");
}

// JSON acknowledgement back to the webhook sender
header('Content-Type: application/json; charset=UTF-8');

// Decode JSON bodies so the caller sees what we understood, not a raw string
$bodyJson = ($rawBody !== '' && str_contains($contentType, 'json')) ? json_decode($rawBody, true) : null;

echo json_encode([
    'ok' => true,
    'message' => "Webhook received and forwarded to $email",
    'forwarded_to' => $email,
    'parser' => $parserName ?: 'default',
    'subject' => $subject,
    'received_at' => date('c'),
    'request' => [
        'method' => $requestMethod,
        'content_type' => $contentType,
        'ip' => $ipAddress,
        'headers' => count($headersArray),
        'body_bytes' => strlen($rawBody),
    ],
    'data' => [
        'query' => (object) $_GET,
        'form' => (object) $_POST,
        'json' => $bodyJson,
        'files' => array_keys($_FILES),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
