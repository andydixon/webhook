# Webhook Email Forwarder

A single-binary Go service for debugging webhooks by forwarding them directly to your email.

## 🌟 Features

- **Email Delivery**: Receive complete webhook data directly in your inbox
- **Custom Parsers**: Beautifully formatted emails for GitHub, Grafana, Webex Interact, and any JSON payload
- **JSON Response**: Every request gets a structured JSON acknowledgement, not a wall of `print_r`
- **Secure**: Built-in email validation and XSS protection
- **Instant**: Real-time webhook processing and forwarding
- **Simple**: No database or complex setup required
- **Universal**: Supports all HTTP methods and content types
- **Extensible**: Easy to add new parsers for any webhook service

## 📋 Overview

This webhook service captures incoming HTTP requests and forwards complete request details (headers, body, metadata) to your specified email address. Perfect for debugging webhooks, testing integrations, and monitoring API callbacks.

## 🚀 Quick Start

### 1. Encode Your Email
Replace `@` with `%40` in your email address:
- `test@example.com` becomes `test%40example.com`

The landing page has a URL builder that does this for you.

### 2. Configure Webhook
Use one of the following URL formats as your webhook endpoint:

**Standard format** (raw webhook data):
```
https://your-domain.com/{encoded-email}
```

**With parser** (formatted email for specific services):
```
https://your-domain.com/{encoded-email}/{parser-name}
```

### 3. Check Your Inbox
Receive webhook data instantly in your email!

**Standard format:**
```
‼️ Webhook Request Received - [timestamp]
```

**With parser:**
```
📤 GitHub push - username/repo
🚨 Grafana Alert: firing - 2 alerts
📊 JSON Webhook - [timestamp]
📥 Webex Interact: Inbound SMS Received
```

## 📬 The Response

Every request is answered with `application/json`, pretty-printed. JSON bodies are decoded and echoed back under `data.json` so you can see exactly what the server understood.

```json
{
    "ok": true,
    "message": "Webhook received and forwarded to test@example.com",
    "forwarded_to": "test@example.com",
    "parser": "default",
    "subject": "‼️ Webhook Request Received - 2026-09-12 17:18:02",
    "received_at": "2026-09-12T17:18:02+00:00",
    "request": {
        "method": "POST",
        "content_type": "application/json",
        "ip": "203.0.113.7",
        "headers": 9,
        "body_bytes": 40
    },
    "data": {
        "query": { "src": "curl" },
        "form": {},
        "json": { "event": "user.created", "user_id": 12345 },
        "files": []
    }
}
```

| Field | Meaning |
|-------|---------|
| `ok` | `true` on success, `false` on error |
| `parser` | The parser used, or `default` |
| `subject` | The subject line of the email that was sent |
| `request.headers` | Number of headers received |
| `request.body_bytes` | Raw body length |
| `data.query` / `data.form` | Query string and form fields (always objects, never `[]`) |
| `data.json` | Decoded body when the Content-Type is JSON, otherwise `null` |
| `data.files` | Names of uploaded file fields |

Any 2xx is enough for most services to mark the delivery as succeeded and stop retrying.

## 🎨 Webhook Parsers

Parsers format webhooks from specific services into beautiful, readable emails. Add the parser name to the end of the URL. If a parser can't understand the payload, the email falls back to the standard raw format so nothing is lost.

### Available Parsers

#### GitHub (`/github`)
Formats GitHub webhook events with full event details.

**Supported events:**
- Push events (each commit as a card with a linked sha, author, and full multi-line message)
- Pull requests
- Issues
- Releases
- Everything else in a clean generic layout

**Example URL:**
```
https://your-domain.com/test%40example.com/github
```

Header names are matched case-insensitively, so deliveries over HTTP/2 (where headers arrive lowercase) work correctly.

#### Grafana / Prometheus Alertmanager (`/grafana`)
Formats Alertmanager webhook payloads from Grafana or Prometheus.

**What you get:**
- Firing or resolved status in the subject and header
- Group labels, common labels, and common annotations
- Each alert as its own card with labels, annotations, start and end times, and a link to the generator

**Example URL:**
```
https://your-domain.com/test%40example.com/grafana
```

#### JSON (`/json`)
Renders any JSON body as tables. Nested objects are split into separate tables with path-based breadcrumbs (for example `order->items->[0]->tags`), so you can find a deeply nested value without squinting at raw JSON.

**Handles:**
- Objects and arrays of objects
- Arrays of plain values, as one table under their breadcrumb
- Scalar bodies (a bare string, number, boolean, or `null`)
- Multi-line string values, with line breaks preserved

**Example URL:**
```
https://your-domain.com/test%40example.com/json
```

#### Webex Interact (`/wxinteract`)
Formats Webex Interact SMS API webhook events into clean, readable emails.

**Supported events:**
- 📤 Outbound SMS - Submitted
- ✅ Outbound SMS - Delivered
- ❌ Outbound SMS - Failed
- 🔗 Shortlink Clicked
- 📥 Inbound SMS Received
- 🚫 SMS Opt Out Received
- 👤 Contacts Callback

**Example URL:**
```
https://your-domain.com/test%40example.com/wxinteract
```

**What you get:**
- Event-specific formatting with relevant emojis
- Message content displayed in a message box, line breaks intact
- Contact and campaign information
- Error details for failed messages
- Click tracking details for shortlinks
- Custom fields support for contact callbacks

**Test samples available in:** `wxinteract_test_samples.json`

See also the [Webex Interact Guide](WEBEX_INTERACT_GUIDE.md).

### Creating Your Own Parser

A parser is one Go function of type `Parser` in `parser_{name}.go`, registered in the `parsers` map in `main.go`. Build the body with `section`, `table`, and `row`, escape free text with `esc`, and wrap it with `emailShell` so it picks up the shared design. See the [Parser Development Guide](PARSER_PROMPT.md) for details.

---

## 💡 Usage Examples

### cURL - POST Request with JSON
```bash
curl -X POST https://your-domain.com/test%40example.com \
  -H "Content-Type: application/json" \
  -d '{"event": "user.created", "user_id": 12345}'
```

### cURL - GET Request with Parameters
```bash
curl "https://your-domain.com/test%40example.com?event=test&id=123"
```

### JavaScript - Fetch API
```javascript
fetch('https://your-domain.com/' + encodeURIComponent('test@example.com'), {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ event: 'order.completed', order_id: 'ORD-789', total: 99.99 })
});
```

### Python - Requests Library
```python
import requests
from urllib.parse import quote

response = requests.post(
    f'https://your-domain.com/{quote("test@example.com")}',
    json={'event': 'payment.received', 'amount': 250.00},
    headers={'X-Custom-Header': 'MyValue'},
)
print(response.json())
```

### With GitHub Parser

Configure your GitHub webhook with:
```
https://your-domain.com/yourname%40company.com/github
```

When events occur, you'll receive formatted emails like:

**Push Event:**
```
📤 GitHub push - yourname/your-repo

Repository: yourname/your-repo
Branch: refs/heads/main
Commits: 3
Pusher: developerUsername

Commits:
abc1234  John Doe
  Fix bug in authentication

def5678  Jane Smith
  Update dependencies
```

**Pull Request:**
```
🔀 GitHub pull_request (opened) - yourname/your-repo

Number: #42
Title: Add new authentication method
State: open
Branch: feature-auth → main
```

### With Grafana Parser

Point a Grafana contact point or Alertmanager webhook receiver at:
```
https://your-domain.com/yourname%40company.com/grafana
```

**Firing alert:**
```
🚨 Grafana Alert: firing - 1 alert

Status: 🔥 firing
Receiver: email
Alert Count: 1

Alert 1 - firing
  alertname: HighCPU
  instance: web-01
  severity: critical
  summary: CPU over 90% for 5m
  Started At: 2026-09-11T17:00:00Z
```

### With Webex Interact Parser

Configure your Webex Interact API callback URL with:
```
https://your-domain.com/yourname%40company.com/wxinteract
```

**Outbound SMS - Submitted:**
```
📤 Webex Interact: Outbound SMS - Submitted

Message ID: msg_123456789
To: +61412345678
From: +61387654321
Campaign ID: camp_987654321
Submitted At: 2026-01-22T16:45:00Z

Message Content:
Hello! Your appointment is confirmed for tomorrow at 2 PM.
```

## 📦 Installation

### Run the binary

```bash
git clone https://github.com/andydixon/webhook.git
cd webhook
go build -o webhooks .
LISTEN=127.0.0.1:8080 SMTP_ADDR=127.0.0.1:25 MAIL_FROM=no-reply@example.com ./webhooks
```

Put a reverse proxy (nginx, Caddy) in front for TLS. The service reads `X-Real-IP` / `X-Forwarded-For` from a private-address proxy to report the real client IP.

### Run the container

```bash
docker build -t webhooks.dixon.cx:latest .
docker run --rm -p 127.0.0.1:29593:8080 webhooks.dixon.cx:latest
```

The image is a static binary on `distroless`, runs as non-root, listens on `:8080`, and relays mail to `172.17.0.1:25` (the Docker host's SMTP server) by default. `deploy/webhooks.dixon.cx.service` is the systemd unit used in production.

### Configuration

| Variable | Default | Meaning |
|----------|---------|---------|
| `LISTEN` | `127.0.0.1:8080` | Address to listen on (`:8080` in the container) |
| `SMTP_ADDR` | `127.0.0.1:25` | SMTP relay, no auth or TLS (`172.17.0.1:25` in the container) |
| `MAIL_FROM` | `no-reply@dixon.cx` | Envelope and From address |
| `PUBLIC_HOST` | `webhooks.dixon.cx` | Host shown in email footers |

`/healthz` returns `ok`, and `webhooks -check` exits non-zero if a running instance is unhealthy (used by the image's `HEALTHCHECK`).

## 🔧 Requirements

- Go 1.24 or newer to build (no third-party dependencies), or Docker
- An SMTP server that accepts mail from the service's address

## 📧 What You Receive

Every email shares one design: a gradient header with the event title and a timestamp pill, then a white card of sections. Metadata is rendered as real HTML tables so it lays out correctly in Gmail, Outlook, and Apple Mail. Code and raw payloads sit in dark code blocks.

The standard (no parser) email contains:

- **Request Timestamp**: Exact date and time when the webhook was received
- **Request Information**: IP address, HTTP method, and Content-Type
- **Request Headers**: All HTTP headers sent with the request
- **Request Body**: Complete raw body content (JSON, XML, form data, etc.)
- **Parsed Variables**: GET, POST, REQUEST, and FILES superglobals as pretty-printed JSON

## 🔒 Security Features

- **Email Validation**: Only valid email addresses are accepted
- **XSS Protection**: All output is HTML-escaped; free-text values go through `esc`, which also preserves line breaks
- **No Data Storage**: Webhook data is forwarded immediately and not stored
- **Error Logging**: Failed email attempts are logged for monitoring

> **Privacy Note**: Anyone with your webhook URL can send data to your email address. Only use this service for testing and debugging purposes. Do not use for sensitive production data without additional authentication.

## ✅ Supported Features

### HTTP Methods
- GET, POST, PUT, PATCH, DELETE
- Any custom HTTP method

### Content Types
- application/json
- application/x-www-form-urlencoded
- multipart/form-data
- text/plain
- text/xml
- Any custom content type

## 🎯 Common Use Cases

- **Debugging**: See exactly what data third-party services are sending
- **GitHub Notifications**: Get beautifully formatted emails for pushes, PRs, and issues
- **Alerting**: Grafana and Prometheus alerts in your inbox without a whole notification stack
- **Integration Testing**: Test webhook integrations before implementing full handlers
- **Monitoring**: Monitor webhook activity and payload changes over time
- **Documentation**: Capture real examples for API documentation
- **Development**: Quickly test webhook flows during development
- **Troubleshooting**: Diagnose issues with webhook payloads and headers
- **Custom Parsers**: Add your own parsers for Stripe, GitLab, Slack, etc.

## 💡 Tips & Best Practices

- **Use Parsers**: Use `/github`, `/grafana`, `/json`, or `/wxinteract` for prettier emails
- **Email Filtering**: Create email filters based on subject patterns ("📤 GitHub", "🚨 Grafana") to organize webhooks
- **Plus Addressing**: Use email plus addressing (e.g., `yourname+github%40gmail.com`) to track which services are sending webhooks
- **Timestamp in Emails**: Check the timestamp in the header to verify webhook timing and debug delivery delays
- **Standard Format**: Use the standard format (no parser) when you need complete raw data for debugging
- **Create Parsers**: Build custom parsers for your favorite services - see [Parser Guide](PARSER_PROMPT.md)

## 📝 Error Handling

### Invalid Email Address
If the email address in the URL is invalid or missing, you get a `400 Bad Request` with a JSON body:
```json
{
    "ok": false,
    "error": "invalid_email",
    "message": "Invalid email address provided in URL path.",
    "hint": "Encode @ as %40, e.g. /you%40example.com"
}
```

### Unknown Parser or Unparseable Payload
The request still succeeds. The email uses the standard raw format and the response reports the parser name you asked for.

### Mail Delivery Failure
If the SMTP relay rejects the message you get a `502` with `"error": "mail_failed"`, so the sending service will retry rather than believe the webhook was delivered.

### Body Too Large
Bodies over 10 MB get a `413` with `"error": "body_too_large"`.

## 📄 License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

## 👤 Author

**Andy Dixon**
- Website: [andydixon.com](https://andydixon.com)
- GitHub: [@andydixon](https://github.com/andydixon)

## 🤝 Contributing

Contributions, issues, and feature requests are welcome! Feel free to check the [issues page](https://github.com/andydixon/webhook/issues).

## ⭐ Show Your Support

Give a ⭐️ if this project helped you!

## 📜 Version History

- **3.0.0** - Go Rewrite
  - ♻️ The whole service is now a single Go binary with no dependencies; PHP, Apache, and `.htaccess` are gone
  - 🐳 Static `distroless` image, non-root, built-in health check
  - 📧 Mail goes over SMTP with a quoted-printable body and an RFC 2047 subject, so emoji subjects survive every relay
  - 🔁 A failed send returns `502 mail_failed` instead of a false `200`
  - 🧪 `go test ./...` covers routing, the JSON response, IP forwarding, and every parser
  - Email output and the JSON response are byte-for-byte compatible with 2.1.0, with JSON object keys kept in sender order

- **2.1.0** - JSON Response and Email Redesign
  - ✨ **NEW**: Structured JSON response for every request, including errors
  - ✨ **NEW**: Grafana / Prometheus Alertmanager parser (`/grafana`)
  - ✨ **NEW**: Generic JSON parser (`/json`) with path breadcrumbs, scalar bodies, and arrays of plain values
  - ✨ **NEW**: Redesigned landing page with a URL builder
  - 🎨 One shared email design (`emailShell()`) across every template, built on real tables
  - 🐛 Line breaks preserved in commit messages, SMS text, and JSON values
  - 🐛 GitHub event headers matched case-insensitively (fixes "Unknown" events over HTTP/2)

- **2.0.0** - Parser System Release
  - ✨ **NEW**: Custom parser support for formatted webhooks
  - ✨ **NEW**: GitHub webhook parser with full event support
  - ✨ **NEW**: Webex Interact SMS parser
  - ✨ **NEW**: Parser development guide (PARSER_PROMPT.md)
  - Backward compatible with existing webhook URLs

- **1.0.0** - Initial release
  - Basic webhook forwarding functionality
  - HTML email formatting
  - Email validation and XSS protection
  - Documentation page

---

Built with Go and ADHD medication 💊
