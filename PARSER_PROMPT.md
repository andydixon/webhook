# Webhook Parser Development Guide

This guide explains how to create custom webhook parsers for the Webhook Email Forwarder.

## 📋 Overview

Parsers format webhook data from specific services (GitHub, Grafana, Stripe, GitLab, etc.) into readable HTML emails instead of the raw request dump.

## 🚀 Quick Start

### URL Format

Parsers are triggered by adding the parser name to the URL:

```
https://your-domain.com/{email}/{parser-name}
```

**Examples:**
- `https://webhook.me.uk/test%40example.com/github`
- `https://webhook.me.uk/user%40company.com/grafana`
- `https://webhook.me.uk/dev%40startup.io/json`

### How It Works

1. User configures the webhook URL with their email and parser name
2. Service sends a webhook to that URL
3. The server looks the parser name up in the `parsers` map in `main.go`
4. The parser turns the request into an email body and subject
5. The email is sent and the caller gets a JSON acknowledgement naming the parser and subject

If no parser is named, the name is unknown, or the parser returns `ok == false`, the default raw-request email is used instead.

---

## 🛠️ Creating a Parser

### File and Function

Create `parser_{servicename}.go` in the package root with one function of this type:

```go
// Parser turns a raw webhook into an email. ok=false means "fall back to the default email".
type Parser func(raw []byte, headers http.Header, meta Meta) (html, subject string, ok bool)
```

Then register it in `main.go`:

```go
var parsers = map[string]Parser{
    "github":      githubParse,
    "grafana":     grafanaParse,
    "json":        jsonParse,
    "wxinteract":  wxinteractParse,
    "servicename": servicenameParse,   // <- add yours
}
```

### Parameters

#### `raw []byte`
The complete request body as received.

```go
v, err := decodeJSON(raw)          // ordered JSON: *OMap, []any, string, json.Number, bool, nil
payload := asObject(v)             // nil if the body is not an object
```

`decodeJSON` keeps object keys in the order the sender wrote them, so tables read naturally. Use it rather than `encoding/json` into a `map`, which sorts keys.

#### `headers http.Header`
All request headers, including `Host`. Lookups through `headers.Get` are case-insensitive, which covers the lowercase names HTTP/2 delivers:

```go
event := headers.Get("X-GitHub-Event")
```

#### `meta Meta`
```go
type Meta struct {
    Date        string // "2026-01-22 15:30:45"
    IP          string // the client address nginx forwards, not the proxy
    Method      string // "POST"
    ContentType string // "application/json"
}
```

### Return Value

- **Success:** the complete HTML document (from `emailShell`), the subject line, and `true`
- **Failure:** `"", "", false` to fall back to the default email

---

## 🎨 Building the Email

Never write a full HTML document by hand. Compose the body from the helpers in `render.go` and wrap it with `emailShell`, which adds the stylesheet, gradient header, and footer. Every parser looks the same this way and design changes happen in one place.

```go
html := emailShell(title, stamp, body, footerSentence)
```

| Argument | What it is |
|----------|------------|
| `title` | Header text, already HTML-safe. Start with an emoji. |
| `stamp` | Usually `h(meta.Date)`. Shown as a pill under the title. |
| `body` | Your sections. |
| `footerSentence` | One line, e.g. "This Stripe webhook was automatically forwarded to your email address." Empty string for the generic default. |

### Building Blocks

```go
section(title, innerHTML string) string   // <div class="section"> with a title
table(rows ...string) string              // a real <table class="metadata">
row(label, valueHTML string) string       // one label/value <tr>
link(url string) string                   // escaped <a href>
kvTable(m *OMap) string                   // every key/value of an object as one table
renderDataAsTable(v any, depth int) string // arbitrary nested JSON as tables (generic fallback)
```

Example:

```go
body := section("💳 Payment Details", table(
    row("Event", h(event)),
    row("Amount", h(amount)+" "+h(currency)),
    row("Customer", h(customer)),
)) + section("📝 Note", `<div class="message-box">`+esc(note)+`</div>`)
```

**Other classes you can use inside a section:**

| Class | Use for |
|-------|---------|
| `.subsection-title` | A bold sub-heading |
| `.data-box` | A `<pre>` for raw JSON, headers, or code (dark block) |
| `.message-box` | Quoted free text such as an SMS body |
| `.error-box` | Error details |
| `.array-item` / `.array-item-title` | A card for one item of a list (an alert, a commit) |
| `.path-title` | A breadcrumb pill above a nested table, e.g. `order->items->[0]` |
| `.commit-item` with `.sha`, `.who`, `.msg` | The GitHub commit card, reusable for any id + author + message list |

### Reading values

```go
str(v)                       // scalar as text ("" for null, "true"/"false" for bools)
strOr(v, "N/A")              // PHP's `?? 'N/A'`: only missing/null takes the default
get(payload, "repository", "full_name")   // nested lookup, nil if any step is missing
asObject(v)                  // *OMap or nil
isContainer(v)               // object or array?
num(v)                       // float64 for a json.Number, else 0
```

### Escaping

Two helpers, use the right one:

- `h(s)` for anything that goes into an attribute or a single-line value.
- `esc(s)` for free text that may contain line breaks (commit messages, descriptions, SMS bodies). It escapes **and** converts newlines to `<br>`.

Subjects are plain text, never escaped.

---

## 📝 Example: Simple Parser

`parser_simplepay.go` for a fictional "SimplePay" service:

```go
package main

import "net/http"

func simplepayParse(raw []byte, headers http.Header, meta Meta) (string, string, bool) {
	payload := asObject(mustDecode(raw))
	if payload == nil {
		return "", "", false // not a JSON object: use the default email
	}

	event := strOr(payload.Get("event"), "Unknown")
	amount := strOr(payload.Get("amount"), "N/A")
	currency := strOr(payload.Get("currency"), "USD")
	customer := strOr(payload.Get("customer_email"), "Unknown")
	note := strOr(payload.Get("note"), "")

	body := section("💳 Payment Details", table(
		row("Event", h(event)),
		row("Amount", h(amount)+" "+h(currency)),
		row("Customer", h(customer)),
		row("IP Address", h(meta.IP)),
	))
	if note != "" {
		body += section("📝 Note", `<div class="message-box">`+esc(note)+`</div>`)
	}

	html := emailShell("💳 SimplePay: "+h(event), h(meta.Date), body,
		"This SimplePay webhook was automatically forwarded to your email address.")
	return html, "💳 SimplePay " + event + " - " + amount + " " + currency, true
}

func mustDecode(raw []byte) any {
	v, err := decodeJSON(raw)
	if err != nil {
		return nil
	}
	return v
}
```

---

## 🎨 Style Guidelines

The shell owns colours, fonts, and spacing. Parsers only choose structure and words.

1. **Sections**: one `section` per logical group
2. **Tables**: `table(row(...))` for label/value pairs, never hand-written divs
3. **Lists**: one `.array-item` card per item (alerts, commits, line items)
4. **Raw data**: `.data-box` for anything the reader might want to copy
5. **Free text**: `.message-box`, escaped with `esc`
6. **Links**: `link(url)`

### Emojis

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

### 1. Escape everything dynamic

```go
// ❌ DANGEROUS - XSS in the email
body += "<div>" + str(payload.Get("user_input")) + "</div>"

// ✅ SAFE (single line)
body += "<div>" + h(str(payload.Get("user_input"))) + "</div>"

// ✅ SAFE (multi-line free text)
body += `<div class="message-box">` + esc(str(payload.Get("message"))) + `</div>`
```

### 2. Validate input

```go
if payload == nil || payload.Get("event_type") == nil {
    return "", "", false
}
```

### 3. Verify signatures (optional)

```go
sig := headers.Get("X-Service-Signature")
mac := hmac.New(sha256.New, []byte(secret))
mac.Write(raw)
if sig == "" || !hmac.Equal([]byte(sig), []byte(hex.EncodeToString(mac.Sum(nil)))) {
    return "", "", false
}
```

---

## 🧪 Testing Your Parser

### 1. Unit test

`main_test.go` has a `capture(t)` helper that swaps the mailer for an in-memory one and a `do(...)` helper that drives the handler. Add a test in the same style:

```go
func TestSimplePay(t *testing.T) {
	c := capture(t)
	_, out := do(t, "POST", "/t%40e.com/simplepay", `{"event":"paid","amount":"9.99"}`, nil)
	if out["subject"] != "💳 SimplePay paid - 9.99 USD" || !strings.Contains(c.html, "9.99") {
		t.Fatalf("%v", out["subject"])
	}
}
```

```bash
go test ./...
```

### 2. Run locally without sending mail

Point the server at any SMTP sink (the repo's tests don't need one, but for eyeballing the HTML):

```bash
LISTEN=127.0.0.1:8098 SMTP_ADDR=127.0.0.1:2525 go run .
curl -X POST http://127.0.0.1:8098/test%40example.com/simplepay \
  -H "Content-Type: application/json" \
  -d '{"event":"paid","amount":"9.99","currency":"USD"}'
```

The JSON response tells you which parser ran and what subject was used.

### 3. Test the failure case

Send invalid data and confirm the response shows the default subject (`‼️ Webhook Request Received`).

### 4. Test a lowercase header

Real deliveries over HTTP/2 arrive with lowercase header names. `headers.Get` handles it, but if you read `headers[...]` directly you will miss them.

---

## 📋 Parser Checklist

- [ ] File is `parser_{servicename}.go` and the function matches the `Parser` type
- [ ] Registered in the `parsers` map in `main.go`
- [ ] Returns `false` on failure/invalid data
- [ ] Header lookups use `headers.Get`
- [ ] All dynamic content goes through `h` or `esc`
- [ ] Body uses `section` / `table` / `row` and is wrapped with `emailShell`
- [ ] Subject line includes an emoji and key information
- [ ] Unit test added and `go test ./...` passes
- [ ] README section added for the parser

---

## 🤝 Contributing Parsers

1. Add `parser_{servicename}.go` and register it
2. Add a test
3. Document usage in README.md (service name, URL format, supported events)
4. Submit a pull request with an example payload in the description

---

## 📚 Reference

### Existing parsers

- `parser_github.go` - Multiple event types, commit cards, generic fallback
- `parser_grafana.go` - Nested alert cards with label and annotation tables
- `parser_json.go` - Recursive rendering with path breadcrumbs
- `parser_wxinteract.go` - Many event types in one switch
- `render.go` - `emailShell`, `esc`, `h`, `decodeJSON`, `renderDataAsTable`, table helpers
- `main.go` - Routing, JSON response, default email, parser registry
- `mail.go` - SMTP delivery

### Configuration

| Variable | Default | Meaning |
|----------|---------|---------|
| `LISTEN` | `127.0.0.1:8080` | Address to listen on (`:8080` in the container) |
| `SMTP_ADDR` | `127.0.0.1:25` | SMTP relay (`172.17.0.1:25` in the container, the host's postfix) |
| `MAIL_FROM` | `no-reply@webhook.me.uk` | Envelope and From address |
| `PUBLIC_HOST` | `webhook.me.uk` | Host shown in email footers |

---

## ❓ FAQ

**Q: Can I use external libraries?**
A: The project has no dependencies beyond the standard library. Keep it that way unless there is a very good reason.

**Q: Can I make API calls in a parser?**
A: Not recommended. Parsers should be fast and not depend on external services.

**Q: What if my service sends different content types?**
A: Check `meta.ContentType` and parse accordingly.

**Q: Can I create multiple parsers for one service?**
A: Yes. Register as many names as you like.

**Q: Can I change the colours or fonts?**
A: Change `shellSrc` in `render.go` and every email picks it up.

---

**Happy Parser Building! 🚀**
