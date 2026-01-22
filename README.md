# Webhook Email Forwarder

A simple, elegant PHP solution for debugging webhooks by forwarding them directly to your email.

## 🌟 Features

- **Email Delivery**: Receive complete webhook data directly in your inbox
- **Custom Parsers**: Beautifully formatted emails for GitHub and other services
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
💳 Stripe payment.succeeded - $99.99
```

## 🎨 Webhook Parsers

Parsers format webhooks from specific services into beautiful, readable emails.

### Available Parsers

#### GitHub (`/github`)
Formats GitHub webhook events with full event details.

**Supported events:**
- Push events (with commit details)
- Pull requests
- Issues
- Releases
- And more...

**Example URL:**
```
https://your-domain.com/test%40example.com/github
```

**What you get:**
- Event-specific formatting
- Commit details with author info
- Pull request/issue metadata
- Clean, scannable layout
- Relevant emojis for quick identification

### Creating Your Own Parser

Want to add support for Stripe, GitLab, or other services? Check out the [**Parser Development Guide**](../PARSER_PROMPT.md) for detailed instructions on creating custom parsers.

---

## 💡 Usage Examples

### Standard Webhook (Raw Data)

### cURL - POST Request with JSON
```bash
curl -X POST https://your-domain.com/test%40example.com \
  -H "Content-Type: application/json" \
  -d '{"event": "user.created", "user_id": 12345}'
```

### cURL - GET Request with Parameters
```bash
curl https://your-domain.com/test%40example.com?event=test&id=123
```

### JavaScript - Fetch API
```javascript
fetch('https://your-domain.com/test%40example.com', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    event: 'order.completed',
    order_id: 'ORD-789',
    total: 99.99
  })
});
```

### Python - Requests Library
```python
import requests

response = requests.post(
    'https://your-domain.com/test%40example.com',
    json={'event': 'payment.received', 'amount': 250.00},
    headers={'X-Custom-Header': 'MyValue'}
)
print(response.text)
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
abc1234 Fix bug in authentication by John Doe
def5678 Update dependencies by Jane Smith
ghi9012 Add new feature by Bob Johnson
```

**Pull Request:**
```
🔀 GitHub pull_request (opened) - yourname/your-repo

Number: #42
Title: Add new authentication method
State: open
Branch: feature-auth → main
```

## 📦 Installation

1. Clone the repository:
```bash
git clone https://github.com/andydixon/webhook.git
```

2. Upload the files to your web server (ensure PHP is installed and configured)

3. Ensure the web server has permission to send emails (PHP `mail()` function)

4. Access the root URL to view the documentation page

## 🔧 Requirements

- PHP 7.0 or higher
- Web server (Apache, Nginx, etc.)
- PHP `mail()` function enabled
- Outbound SMTP/email capability

## 📧 What You Receive

Each webhook email contains:

- **Request Timestamp**: Exact date and time when the webhook was received
- **Request Information**: IP address, HTTP method, and Content-Type
- **Request Headers**: All HTTP headers sent with the request
- **Request Body**: Complete raw body content (JSON, XML, form data, etc.)
- **PHP Variables**: Parsed GET, POST, REQUEST, and FILES superglobals

## 🔒 Security Features

- **Email Validation**: Only valid email addresses are accepted
- **XSS Protection**: All output is sanitized using `htmlspecialchars()`
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
- **Integration Testing**: Test webhook integrations before implementing full handlers
- **Monitoring**: Monitor webhook activity and payload changes over time
- **Documentation**: Capture real examples for API documentation
- **Development**: Quickly test webhook flows during development
- **Troubleshooting**: Diagnose issues with webhook payloads and headers
- **Custom Parsers**: Add your own parsers for Stripe, GitLab, Slack, etc.

## 💡 Tips & Best Practices

- **Use Parsers**: Use `/github`, `/stripe`, etc. for prettier emails from supported services
- **Email Filtering**: Create email filters based on subject patterns ("📤 GitHub", "💳 Stripe") to organize webhooks
- **Plus Addressing**: Use email plus addressing (e.g., `yourname+github%40gmail.com`) to track which services are sending webhooks
- **Timestamp in Emails**: Check the "Date Received" field to verify webhook timing and debug delivery delays
- **Standard Format**: Use the standard format (no parser) when you need complete raw data for debugging
- **Create Parsers**: Build custom parsers for your favorite services - see [Parser Guide](../PARSER_PROMPT.md)

## 📝 Error Handling

### Invalid Email Address
If the email address in the URL is invalid or missing:
```
HTTP 400 Bad Request
Invalid email address provided in URL path.
```

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

- **2.0.0** - Parser System Release
  - ✨ **NEW**: Custom parser support for formatted webhooks
  - ✨ **NEW**: GitHub webhook parser with full event support
  - ✨ **NEW**: Parser development guide (PARSER_PROMPT.md)
  - Backward compatible with existing webhook URLs
  - Same security and styling standards

- **1.0.0** - Initial release
  - Basic webhook forwarding functionality
  - HTML email formatting
  - Email validation and XSS protection
  - Documentation page

---

Built with PHP and ADHD medication 💊
