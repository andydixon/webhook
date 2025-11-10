# Webhook Email Forwarder

A simple, elegant PHP solution for debugging webhooks by forwarding them directly to your email.

## 🌟 Features

- **Email Delivery**: Receive complete webhook data directly in your inbox
- **Secure**: Built-in email validation and XSS protection
- **Instant**: Real-time webhook processing and forwarding
- **Simple**: No database or complex setup required
- **Universal**: Supports all HTTP methods and content types

## 📋 Overview

This webhook service captures incoming HTTP requests and forwards complete request details (headers, body, metadata) to your specified email address. Perfect for debugging webhooks, testing integrations, and monitoring API callbacks.

## 🚀 Quick Start

### 1. Encode Your Email
Replace `@` with `%40` in your email address:
- `test@example.com` becomes `test%40example.com`

### 2. Configure Webhook
Use the following URL format as your webhook endpoint:
```
https://your-domain.com/{encoded-email}
```

### 3. Check Your Inbox
Receive detailed webhook data instantly in your email with the subject:
```
‼️ Webhook Request Received - [timestamp]
```

## 💡 Usage Examples

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
- **Integration Testing**: Test webhook integrations before implementing full handlers
- **Monitoring**: Monitor webhook activity and payload changes over time
- **Documentation**: Capture real examples for API documentation
- **Development**: Quickly test webhook flows during development
- **Troubleshooting**: Diagnose issues with webhook payloads and headers

## 💡 Tips & Best Practices

- **Email Filtering**: Create an email filter for the subject line "‼️ Webhook Request Received" to organize incoming webhooks
- **Plus Addressing**: Use email plus addressing (e.g., `yourname+github%40gmail.com`) to track which services are sending webhooks
- **Timestamp in Emails**: Check the "Date Received" field to verify webhook timing and debug delivery delays
- **Parse the Body**: The raw body section contains the exact payload - perfect for copying into your code for testing

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

- **1.0.0** - Initial release
  - Basic webhook forwarding functionality
  - HTML email formatting
  - Email validation and XSS protection
  - Documentation page

---

Built with PHP and ADHD medication 💊
