# Webex Interact Webhook Parser Guide

## Overview

The Webex Interact parser (`/wxinteract`) formats SMS API webhook events from Webex Interact into clean, readable HTML emails. Each event type gets custom formatting with relevant emojis for easy identification.

## Configuration

### URL Format

```
https://your-domain.com/{your-email}/wxinteract
```

### Example

```
https://webhooks.dixon.cx/sms-notifications%40mycompany.com/wxinteract
```

## Supported Events

The parser automatically detects the event type based on the `data.status` field in the webhook payload.

### 📤 Outbound SMS - Submitted
**Status:** `submitted`

Triggered when an SMS message is submitted to the carrier for delivery.

**Fields displayed:**
- Message ID
- To (recipient phone number)
- From (sender phone number)
- Campaign ID
- Submitted At (timestamp)
- Message Content (formatted in a clean message box)

**Example payload:**
```json
{
  "data": {
    "status": "submitted",
    "message_id": "msg_123456789",
    "to": "+61412345678",
    "from": "+61387654321",
    "message": "Hello! Your appointment is confirmed for tomorrow at 2 PM.",
    "submitted_at": "2026-01-22T16:45:00Z",
    "campaign_id": "camp_987654321"
  }
}
```

---

### ✅ Outbound SMS - Delivered
**Status:** `delivered`

Triggered when an SMS message is successfully delivered to the recipient.

**Fields displayed:**
- Message ID
- To (recipient phone number)
- From (sender phone number)
- Campaign ID
- Delivered At (timestamp)

**Example payload:**
```json
{
  "data": {
    "status": "delivered",
    "message_id": "msg_123456789",
    "to": "+61412345678",
    "from": "+61387654321",
    "delivered_at": "2026-01-22T16:46:15Z",
    "campaign_id": "camp_987654321"
  }
}
```

---

### ❌ Outbound SMS - Failed
**Status:** `failed`

Triggered when an SMS message fails to deliver.

**Fields displayed:**
- Message ID
- To (recipient phone number)
- From (sender phone number)
- Campaign ID
- Failed At (timestamp)
- Error Code (highlighted in error box)
- Error Message (highlighted in error box)

**Example payload:**
```json
{
  "data": {
    "status": "failed",
    "message_id": "msg_987654321",
    "to": "+61499999999",
    "from": "+61387654321",
    "failed_at": "2026-01-22T16:47:00Z",
    "error_code": "ERR_INVALID_NUMBER",
    "error_message": "The destination number is invalid or not reachable",
    "campaign_id": "camp_987654321"
  }
}
```

---

### 🔗 Shortlink Clicked
**Status:** `shortlink_clicked`

Triggered when a recipient clicks a tracked shortlink in an SMS message.

**Fields displayed:**
- Shortlink (clickable)
- Original URL (clickable)
- Phone Number (recipient who clicked)
- Message ID
- Campaign ID
- Clicked At (timestamp)
- IP Address (of the click)
- User Agent (browser/device information)

**Example payload:**
```json
{
  "data": {
    "status": "shortlink_clicked",
    "shortlink": "https://wxint.co/abc123",
    "original_url": "https://example.com/special-offer",
    "clicked_at": "2026-01-22T17:30:45Z",
    "phone_number": "+61412345678",
    "message_id": "msg_123456789",
    "campaign_id": "camp_987654321",
    "user_agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15",
    "ip_address": "203.0.113.45"
  }
}
```

---

### 📥 Inbound SMS Received
**Status:** `received`

Triggered when an inbound SMS is received from a recipient.

**Fields displayed:**
- Message ID
- From (sender phone number)
- To (your phone number)
- Keyword (detected keyword)
- Received At (timestamp)
- Message Content (formatted in a clean message box)

**Example payload:**
```json
{
  "data": {
    "status": "received",
    "message_id": "msg_inbound_001",
    "from": "+61412345678",
    "to": "+61387654321",
    "message": "YES I would like to receive updates",
    "received_at": "2026-01-22T18:00:00Z",
    "keyword": "YES"
  }
}
```

---

### 🚫 SMS Opt Out Received
**Status:** `opt_out`

Triggered when a recipient opts out of receiving SMS messages (typically by replying "STOP").

**Fields displayed:**
- Phone Number (who opted out)
- From Number (your SMS number)
- Keyword (e.g., "STOP")
- Message ID
- Opt Out At (timestamp)

**Example payload:**
```json
{
  "data": {
    "status": "opt_out",
    "phone_number": "+61412345678",
    "opt_out_at": "2026-01-22T18:15:00Z",
    "keyword": "STOP",
    "message_id": "msg_optout_001",
    "from": "+61387654321"
  }
}
```

---

### 👤 Contacts Callback
**Status:** `contact_created` or `contact_updated`

Triggered when a contact is created or updated in your Webex Interact account.

**Fields displayed:**
- Contact ID
- Phone Number
- First Name
- Last Name
- Email
- Status
- Created At (timestamp)
- Updated At (timestamp)
- Custom Fields (if present, displayed in a separate section)

**Example payload:**
```json
{
  "data": {
    "status": "contact_created",
    "contact_id": "contact_abc123",
    "phone_number": "+61412345678",
    "first_name": "John",
    "last_name": "Smith",
    "email": "john.smith@example.com",
    "created_at": "2026-01-22T19:00:00Z",
    "updated_at": "2026-01-22T19:00:00Z",
    "custom_fields": {
      "company": "Acme Corporation",
      "department": "Sales",
      "customer_id": "CUST_12345"
    }
  }
}
```

---

## Testing

### Using cURL

Test individual event types using the sample payloads:

```bash
# Test Outbound SMS Submitted
curl -X POST https://your-domain.com/test%40example.com/wxinteract \
  -H "Content-Type: application/json" \
  -d '{"data": {"status": "submitted", "message_id": "msg_123", "to": "+61412345678", "from": "+61387654321", "message": "Test message", "submitted_at": "2026-01-22T16:45:00Z", "campaign_id": "camp_001"}}'

# Test SMS Opt Out
curl -X POST https://your-domain.com/test%40example.com/wxinteract \
  -H "Content-Type: application/json" \
  -d '{"data": {"status": "opt_out", "phone_number": "+61412345678", "opt_out_at": "2026-01-22T18:15:00Z", "keyword": "STOP", "message_id": "msg_005", "from": "+61387654321"}}'
```

### Using Test Samples

A complete set of test samples for all event types is available in `wxinteract_test_samples.json`.

```bash
# Test with submitted event
cat wxinteract_test_samples.json | jq '.outbound_sms_submitted' | \
  curl -X POST https://your-domain.com/test%40example.com/wxinteract \
    -H "Content-Type: application/json" \
    -d @-
```

---

## Configuring in Webex Interact

### Step 1: Log in to Webex Interact

Go to your Webex Interact dashboard at https://interact.webex.com/

### Step 2: Navigate to API Settings

1. Click on **Settings** or **API** in the navigation menu
2. Look for **Webhooks** or **API Callbacks** section

### Step 3: Configure Callback URL

1. Enter your webhook URL: `https://your-domain.com/{your-email}/wxinteract`
2. Select the events you want to receive:
   - ☑️ Outbound SMS - Submitted
   - ☑️ Outbound SMS - Delivered
   - ☑️ Outbound SMS - Failed
   - ☑️ Shortlink Clicked
   - ☑️ Inbound SMS Received
   - ☑️ SMS Opt Out Received
   - ☑️ Contacts Callback
3. Save your configuration

### Step 4: Test the Integration

1. Send a test SMS from Webex Interact
2. Check your email inbox for the formatted webhook notification
3. Verify the email contains the correct information

---

## Email Organization Tips

### Create Email Filters

Set up filters based on the subject line patterns:

- **All Webex Interact webhooks:** Subject contains "Webex Interact"
- **Failed messages only:** Subject contains "❌ Webex Interact: Outbound SMS - Failed"
- **Opt-outs only:** Subject contains "🚫 Webex Interact: SMS Opt Out"
- **Click tracking:** Subject contains "🔗 Webex Interact: Shortlink Clicked"

### Use Plus Addressing

Track different campaigns or environments:

- Production: `sms-prod%40mycompany.com`
- Staging: `sms-staging%40mycompany.com`
- Campaign A: `sms+campaignA%40mycompany.com`

---

## Troubleshooting

### Parser Not Working

If you're receiving raw webhook data instead of formatted emails:

1. **Check URL:** Ensure `/wxinteract` is at the end of the URL
2. **Check payload structure:** Parser expects `{"data": {"status": "...", ...}}`
3. **Check PHP logs:** Look for any parser errors in your server logs

### Missing Fields

If some fields are showing as "N/A":

- The webhook payload may not include those fields for that event type
- Check the Webex Interact documentation for the specific event
- Use the raw webhook format (without `/wxinteract`) to see the complete payload

### Wrong Event Type

If the event is being parsed as a generic event:

- Check that `data.status` matches one of the supported values
- Supported values: `submitted`, `delivered`, `failed`, `shortlink_clicked`, `received`, `opt_out`, `contact_created`, `contact_updated`

---

## API Documentation References

Official Webex Interact webhook documentation:

- [Outbound SMS - Submitted](https://docs.webexinteract.com/reference/sms-api-request-accepted)
- [Outbound SMS - Delivered](https://docs.webexinteract.com/reference/outbound-sms-delivered)
- [Outbound SMS - Failed](https://docs.webexinteract.com/reference/outbound-sms-failed)
- [Shortlink Clicked](https://docs.webexinteract.com/reference/shortlink-clicked)
- [Inbound SMS Events](https://docs.webexinteract.com/reference/inbound-sms-events)
- [SMS Opt Out Received](https://docs.webexinteract.com/reference/sms-opt-out-received)
- [Contacts Callback](https://docs.webexinteract.com/reference/contacts-callback)

---

## Support

For issues with:
- **This parser:** Open an issue on the GitHub repository
- **Webex Interact API:** Contact Webex Interact support

---

Built with ❤️ for better webhook debugging
