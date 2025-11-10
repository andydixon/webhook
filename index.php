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
// Example: '/email%40domain.com/' becomes 'email%40domain.com'
$emailEncoded = trim($path, '/');

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
        /* Main body styling with dark blue background */
        body {
            background-color: #004080;
            color: white;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }
        /* Section heading styling */
        h2 {
            color: white;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
            margin-top: 20px;
        }
        /* Preformatted text block styling for code/data display */
        pre {
            background-color: #002f5f;
            padding: 15px;
            border-radius: 5px;
            color: #f0f0f0;
            font-family: monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <!-- Display the timestamp of when the webhook was received -->
    <h2>Request Received</h2>
    <pre>Date: $dateReceivedSafe</pre>

    <!-- Display metadata about the request origin and method -->
    <h2>Request Information</h2>
    <pre>IP Address: $ipAddressSafe
Request Method: $requestMethodSafe
Content-Type: $contentTypeSafe</pre>

    <!-- Display all HTTP headers sent with the request -->
    <h2>Request Headers</h2>
    <pre>$headersText</pre>

    <!-- Display the raw request body exactly as received -->
    <h2>Request Body</h2>
    <pre>$rawBodySafe</pre>

    <!-- Display all PHP superglobal variables for complete request context -->
    <h2>PHP Variables</h2>
    <pre>$variablesTextSafe</pre>
</body>
</html>
HTML;

// Construct the email subject line with an attention-grabbing emoji and timestamp
$subject = "‼️ Webhook Request Received - $dateReceived";

// Build email headers to ensure proper HTML rendering and sender information
// MIME-Version declares email format capabilities
$emailHeaders = "MIME-Version: 1.0\r\n";
// Content-type specifies HTML email with UTF-8 character encoding
$emailHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
// From header sets the sender address to a no-reply address on the same domain
$emailHeaders .= "From: no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";

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
