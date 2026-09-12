# Webhook Parser Development Guide

This guide explains how to create custom webhook parsers for the Webhook Email Forwarder system.

## 📋 Overview

Parsers allow you to format webhook data from specific services (like GitHub, Stripe, GitLab, etc.) into beautifully formatted HTML emails, rather than receiving raw webhook data.

## 🚀 Quick Start

### URL Format

Parsers are triggered by adding the parser name to the URL:

```
https://your-domain.com/{email}/{parser-name}
```

**Examples:**
- `https://webhooks.dixon.cx/test%40example.com/github`
- `https://webhooks.dixon.cx/user%40company.com/grafana`
- `https://webhooks.dixon.cx/dev%40startup.io/json`

### How It Works

1. User configures webhook URL with their email and parser name
2. Service sends webhook to your URL
3. System looks for `parsers/{parser-name}.php` and a `{parser-name}Parse()` function inside it
4. Parser function processes the webhook data
5. Formatted email is sent to the user
6. The caller gets a JSON acknowledgement that includes the parser name and the email subject

If no parser is specified, the file doesn't exist, or the parser returns `false`, the system falls back to the default raw webhook format.

---

## 🛠️ Creating a Parser

### File and Function

Create `parsers/{servicename}.php`. The file is loaded on demand with `require_once`, and `parsers/helpers.php` is already loaded before it, so `emailShell()`, `esc()`, and `renderDataAsTable()` are available.

Every parser must follow this exact signature:

```php
<?php
/**
 * {Service} Webhook Parser
 *
 * @param string $rawBody The raw request body
 * @param array $headers The request headers (associative array)
 * @param array $metadata Request metadata
 * @return array|false Array with 'html' and 'subject' keys, or false on failure
 */
function {servicename}Parse($rawBody, $headers, $metadata) {
    // Your parser logic here
}
```

### Parameters Explained

#### `$rawBody` (string)
The complete raw body of the webhook request as received from the service.

```php
// For JSON webhooks:
$payload = json_decode($rawBody, true);

// For XML webhooks:
$xml = simplexml_load_string($rawBody);

// For form data:
parse_str($rawBody, $formData);
```

#### `$headers` (array)
Associative array of all HTTP headers. Keys are header names, values are header values.

**Header names arrive in whatever case the client and proxy used.** Over HTTP/2 they are all lowercase. Normalise before looking anything up:

```php
$headers = array_change_key_case($headers, CASE_LOWER);
$eventType = $headers['x-event-type'] ?? 'unknown';
$signature = $headers['x-hub-signature'] ?? null;
```

#### `$metadata` (array)
Additional request information:

```php
[
    'date' => '2026-01-22 15:30:45',  // When webhook was received (Y-m-d H:i:s)
    'ip' => '203.0.113.7',             // Client IP (the address nginx forwards, not the proxy)
    'method' => 'POST',                 // HTTP method
    'contentType' => 'application/json' // Content-Type header
]
```

### Return Value

**Success:** Return an array with two keys:

```php
return [
    'html' => $htmlEmailBody,    // Complete HTML email (string)
    'subject' => $emailSubject   // Email subject line (string)
];
```

**Failure:** Return `false` to fall back to default formatting:

```php
if (!$payload || !isset($payload['required_field'])) {
    return false;  // Will use default webhook email format
}
```

---

## 🎨 Building the Email

You do not write a full HTML document. Build the **body** out of the shared building blocks, then hand it to `emailShell()`, which adds the stylesheet, the gradient header, and the footer. Every parser looks the same this way and design changes happen in one place.

```php
$html = emailShell($title, $timestamp, $body, $footerSentence);
```

| Argument | What it is |
|----------|------------|
| `$title` | Header text, already HTML-safe. Start with an emoji. |
| `$timestamp` | Usually `$metadata['date']`, escaped. Shown as a pill under the title. |
| `$body` | Your sections (see below). |
| `$footerSentence` | Optional. One line, e.g. "This Stripe webhook was automatically forwarded to your email address." |

### Building Blocks

**Section** with a title:

```html
<div class="section">
    <div class="section-title">💳 Payment Details</div>
    ...
</div>
```

**Metadata table.** Always a real `<table>`; that is what keeps the value column full-width in every mail client:

```html
<table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
    <tr>
        <td class="metadata-label">Amount</td>
        <td class="metadata-value">$amountSafe</td>
    </tr>
</table>
```

**Other classes you can use:**

| Class | Use for |
|-------|---------|
| `.subsection-title` | A bold sub-heading inside a section |
| `.data-box` | A `<pre>` for raw JSON, headers, or code (dark block) |
| `.message-box` | Quoted free text such as an SMS body |
| `.error-box` | Error details |
| `.array-item` / `.array-item-title` | A card for one item of a list, e.g. one alert or one commit |
| `.path-title` | A breadcrumb pill above a nested table, e.g. `order->items->[0]` |
| `.commit-item` with `.sha`, `.who`, `.msg` | The GitHub commit card layout, reusable for any "id + author + message" list |

**Dump an arbitrary array as tables** with the helper, which handles nesting for you:

```php
$body .= renderDataAsTable($payload['details']);
```

### Escaping

Two helpers, use the right one:

- `htmlspecialchars($s, ENT_QUOTES, 'UTF-8')` for anything that goes into an attribute, a subject line, or a single-line value.
- `esc($s)` for free text that may contain line breaks (commit messages, descriptions, SMS bodies). It escapes **and** converts newlines to `<br>`.

---

## 📝 Example: Simple Parser

A minimal `parsers/simplepay.php` for a fictional "SimplePay" service:

```php
<?php
/**
 * SimplePay Webhook Parser
 */
function simplepayParse($rawBody, $headers, $metadata) {
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        return false;  // Invalid JSON, use default format
    }

    // Extract data with fallbacks
    $eventType = $payload['event'] ?? 'Unknown';
    $amount = $payload['amount'] ?? 'N/A';
    $currency = $payload['currency'] ?? 'USD';
    $customer = $payload['customer_email'] ?? 'Unknown';
    $note = $payload['note'] ?? '';

    // Sanitise for HTML (IMPORTANT!)
    $eventSafe = htmlspecialchars($eventType, ENT_QUOTES, 'UTF-8');
    $amountSafe = htmlspecialchars($amount, ENT_QUOTES, 'UTF-8');
    $currencySafe = htmlspecialchars($currency, ENT_QUOTES, 'UTF-8');
    $customerSafe = htmlspecialchars($customer, ENT_QUOTES, 'UTF-8');
    $noteSafe = esc($note);  // may be multi-line
    $dateSafe = htmlspecialchars($metadata['date'], ENT_QUOTES, 'UTF-8');
    $ipSafe = htmlspecialchars($metadata['ip'], ENT_QUOTES, 'UTF-8');

    $body = <<<HTML
        <div class="section">
            <div class="section-title">💳 Payment Details</div>
            <table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">
                <tr><td class="metadata-label">Event</td><td class="metadata-value">$eventSafe</td></tr>
                <tr><td class="metadata-label">Amount</td><td class="metadata-value">$amountSafe $currencySafe</td></tr>
                <tr><td class="metadata-label">Customer</td><td class="metadata-value">$customerSafe</td></tr>
                <tr><td class="metadata-label">IP Address</td><td class="metadata-value">$ipSafe</td></tr>
            </table>
        </div>
HTML;

    if ($noteSafe !== '') {
        $body .= <<<HTML
        <div class="section">
            <div class="section-title">📝 Note</div>
            <div class="message-box">$noteSafe</div>
        </div>
HTML;
    }

    $html = emailShell("💳 SimplePay: $eventSafe", $dateSafe, $body,
        'This SimplePay webhook was automatically forwarded to your email address.');

    $subject = "💳 SimplePay $eventType - $amount $currency";

    return [
        'html' => $html,
        'subject' => $subject
    ];
}
```

---

## 🎨 Style Guidelines

The shell owns colours, fonts, and spacing. Parsers only choose structure and words.

1. **Sections**: one `.section` per logical group, each with a `.section-title`
2. **Tables**: `table.metadata` for label/value pairs, never bare divs
3. **Lists**: one `.array-item` card per item (alerts, commits, line items)
4. **Raw data**: `.data-box` for anything the reader might want to copy
5. **Free text**: `.message-box`, escaped with `esc()`
6. **Links**: plain `<a href>`; the shell styles them

### Emojis

Use relevant emojis to make emails scannable:
- 💳 Payment events
- 🚀 Deployments/releases
- 📤 Push/upload events
- 🔀 Merge/pull request events
- ⚠️ Errors/warnings
- ✅ Success events
- 🔔 Notifications

Put the same emoji at the start of the subject line so inbox filters can key on it.

---

## 🔒 Security Best Practices

### 1. Always Sanitise HTML Output

**CRITICAL:** Escape ALL dynamic content:

```php
// ❌ DANGEROUS - XSS vulnerability
$html = "<div>{$payload['user_input']}</div>";

// ✅ SAFE (single line)
$safe = htmlspecialchars($payload['user_input'], ENT_QUOTES, 'UTF-8');

// ✅ SAFE (multi-line free text)
$safe = esc($payload['user_message']);
```

### 2. Validate Input

Check that required data exists before using it:

```php
if (!is_array($payload) || !isset($payload['event_type'])) {
    return false;  // Fail safely
}
```

### 3. Handle Errors Gracefully

Return `false` if parsing fails. The system falls back to the default format and the sender still gets a `200`.

### 4. Verify Signatures (Optional)

If the service provides webhook signatures, validate them:

```php
$headers = array_change_key_case($headers, CASE_LOWER);
$signature = $headers['x-service-signature'] ?? null;
if ($signature) {
    $expectedSignature = hash_hmac('sha256', $rawBody, $secret);
    if (!hash_equals($expectedSignature, $signature)) {
        return false;  // Invalid signature
    }
}
```

---

## 📦 Parser Examples by Service Type

### JSON API Webhooks (Most Common)

```php
$payload = json_decode($rawBody, true);
if (!is_array($payload)) return false;
```

### XML Webhooks

```php
$xml = simplexml_load_string($rawBody);
if (!$xml) return false;
$event = (string)$xml->event;
```

### Form Data Webhooks

```php
parse_str($rawBody, $formData);
if (empty($formData)) return false;
$event = $formData['event'] ?? 'Unknown';
```

---

## 🧪 Testing Your Parser

### 1. Lint it

```bash
php -l parsers/yourservice.php
```

### 2. Run locally without sending mail

PHP's built-in server plus a `sendmail_path` override captures every email as a file instead of sending it:

```bash
mkdir -p /tmp/mail
php -S 127.0.0.1:8099 -d sendmail_path="sh -c 'cat > /tmp/mail/\$\$.eml'" index.php
```

Then post to it:

```bash
curl -X POST http://127.0.0.1:8099/test%40example.com/yourservice \
  -H "Content-Type: application/json" \
  -H "X-Service-Event: payment.completed" \
  -d '{"amount": 99.99, "currency": "USD"}'
```

The JSON response tells you which parser ran and what subject was used. Open the `.eml` file in `/tmp/mail` (strip the headers above the first blank line) in a browser to check the layout.

### 3. Test the Failure Case

Send invalid data to ensure your parser returns `false` correctly:

```bash
curl -X POST http://127.0.0.1:8099/test%40example.com/yourservice \
  -H "Content-Type: application/json" \
  -d 'invalid json{'
```

You should get the default webhook format email.

### 4. Test a lowercase header

Real deliveries over HTTP/2 arrive with lowercase header names. Send one that way and make sure your parser still identifies the event:

```bash
curl -X POST http://127.0.0.1:8099/test%40example.com/yourservice \
  -H "x-service-event: payment.completed" -d '{}'
```

### 5. Check the Email

- ✅ Subject line is descriptive and includes an emoji
- ✅ Value cells fill the table width
- ✅ Multi-line text keeps its line breaks
- ✅ No raw HTML from the payload is rendered
- ✅ Links (if any) are clickable

---

## 📋 Parser Checklist

Before submitting a parser, verify:

- [ ] File is `parsers/{servicename}.php` and the function is `{servicename}Parse` (lowercase)
- [ ] Function signature matches exactly: `($rawBody, $headers, $metadata)`
- [ ] Returns `array` with 'html' and 'subject' keys on success
- [ ] Returns `false` on failure/invalid data
- [ ] Header lookups are case-insensitive
- [ ] All dynamic content is escaped (`htmlspecialchars()` or `esc()`)
- [ ] Body uses the shared classes and is wrapped with `emailShell()`
- [ ] Metadata is rendered with `table.metadata`, not divs
- [ ] Subject line includes an emoji and key information
- [ ] Tested with valid, invalid, and lowercase-header requests
- [ ] Documented any special requirements (API keys, signature validation, etc.)

---

## 🤝 Contributing Parsers

To contribute a parser:

1. **Add `parsers/{servicename}.php`**
2. **Test Thoroughly** with real webhook data
3. **Document Usage** - Add a section to README.md with:
   - Service name
   - URL format
   - Supported events
   - Example webhook configuration
4. **Submit Pull Request** with:
   - Parser file
   - Documentation updates
   - Example webhook payload (in PR description)

---

## 💡 Tips & Tricks

### Handle Multiple Event Types

Use a `switch` statement for different event types:

```php
switch ($eventType) {
    case 'payment.completed':
        $icon = '💳';
        break;
    case 'refund.created':
        $icon = '↩️';
        break;
    default:
        $icon = '🔔';
}
```

### Pretty Print JSON

For a debugging section:

```php
$jsonSafe = htmlspecialchars(
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ENT_QUOTES,
    'UTF-8'
);
$body .= '<div class="section"><div class="section-title">📦 Raw Payload</div><pre class="data-box">' . $jsonSafe . '</pre></div>';
```

### Truncate Long Content

```php
$message = mb_substr($payload['message'], 0, 500);
if (mb_strlen($payload['message']) > 500) {
    $message .= '…';
}
```

### Include Links

```php
$url = htmlspecialchars($payload['url'], ENT_QUOTES, 'UTF-8');
$body .= "<a href=\"$url\">$url</a>";
```

---

## 📚 Reference

### Existing Parsers

Study these in `parsers/`:
- `github.php` - Multiple event types, commit cards, case-insensitive headers
- `grafana.php` - Nested alert cards with label and annotation tables
- `json.php` - Recursive rendering with path breadcrumbs
- `wxinteract.php` - Many event types split into helper functions
- `helpers.php` - `emailShell()`, `esc()`, `renderDataAsTable()`

### Useful PHP Functions

- `json_decode($string, true)` - Parse JSON to array
- `simplexml_load_string($string)` - Parse XML
- `parse_str($string, $output)` - Parse URL-encoded data
- `array_change_key_case($headers, CASE_LOWER)` - Normalise header names
- `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')` - Escape HTML
- `esc($string)` - Escape HTML and keep line breaks
- `hash_hmac()` / `hash_equals()` - Verify signatures

---

## ❓ FAQ

**Q: Can I use external libraries?**
A: No, parsers should use only PHP built-in functions to avoid dependencies.

**Q: Can I make API calls in a parser?**
A: Not recommended - parsers should be fast and not depend on external services.

**Q: What if my service sends different content types?**
A: Check `$metadata['contentType']` and handle accordingly.

**Q: Can I store data in a database?**
A: No, parsers should only format and return email content.

**Q: How do I handle webhook signatures?**
A: Validate in your parser and return `false` if invalid.

**Q: Can I create multiple parsers for one service?**
A: Yes! Use names like `githubissues`, `githubpush`, etc. Each is its own file.

**Q: Can I change the colours or fonts?**
A: Change them in `emailShell()` in `parsers/helpers.php` and every email picks them up.

---

## 📞 Support

For questions or help developing parsers:
- GitHub Issues: https://github.com/andydixon/webhook/issues
- Review existing parsers in `parsers/`
- Check this guide for examples

---

**Happy Parser Building! 🚀**
