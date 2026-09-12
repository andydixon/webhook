package main

import (
	"net/http"
)

// wxinteractParse formats Webex Interact SMS webhook events.
func wxinteractParse(raw []byte, headers http.Header, meta Meta) (string, string, bool) {
	v, err := decodeJSON(raw)
	payload := asObject(v)
	if err != nil || payload.Len() == 0 {
		return "", "", false
	}
	data := asObject(payload.Get("data"))
	if data == nil {
		return "", "", false
	}

	status := strOr(data.Get("status"), "Unknown")
	f := func(key string) string { return h(strOr(data.Get(key), "N/A")) }

	icon, title := "📱", "SMS Event"
	var content string

	switch status {
	case "submitted":
		icon, title = "📤", "Outbound SMS - Submitted"
		content = section("📤 SMS Details", table(
			row("Message ID", f("message_id")),
			row("To", f("to")),
			row("From", f("from")),
			row("Campaign ID", f("campaign_id")),
			row("Submitted At", f("submitted_at")),
		)) + section("💬 Message Content", `<div class="message-box">`+esc(strOr(data.Get("message"), "N/A"))+`</div>`)

	case "delivered":
		icon, title = "✅", "Outbound SMS - Delivered"
		content = section("✅ Delivery Details", table(
			row("Message ID", f("message_id")),
			row("To", f("to")),
			row("From", f("from")),
			row("Campaign ID", f("campaign_id")),
			row("Delivered At", f("delivered_at")),
		))

	case "failed":
		icon, title = "❌", "Outbound SMS - Failed"
		content = section("❌ Failure Details", table(
			row("Message ID", f("message_id")),
			row("To", f("to")),
			row("From", f("from")),
			row("Campaign ID", f("campaign_id")),
			row("Failed At", f("failed_at")),
		)) + section("⚠️  Error Information", `<div class="error-box"><strong>Error Code:</strong> `+f("error_code")+
			`<br><strong>Error Message:</strong> `+esc(strOr(data.Get("error_message"), "N/A"))+`</div>`)

	case "shortlink_clicked":
		icon, title = "🔗", "Shortlink Clicked"
		content = section("🔗 Click Details", table(
			row("Shortlink", link(strOr(data.Get("shortlink"), "N/A"))),
			row("Original URL", link(strOr(data.Get("original_url"), "N/A"))),
			row("Phone Number", f("phone_number")),
			row("Message ID", f("message_id")),
			row("Campaign ID", f("campaign_id")),
			row("Clicked At", f("clicked_at")),
			row("IP Address", f("ip_address")),
		)) + section("🌐 Browser Information", `<div class="data-box">`+f("user_agent")+`</div>`)

	case "received":
		icon, title = "📥", "Inbound SMS Received"
		content = section("📥 Inbound SMS Details", table(
			row("Message ID", f("message_id")),
			row("From", f("from")),
			row("To", f("to")),
			row("Keyword", f("keyword")),
			row("Received At", f("received_at")),
		)) + section("💬 Message Content", `<div class="message-box">`+esc(strOr(data.Get("message"), "N/A"))+`</div>`)

	case "opt_out":
		icon, title = "🚫", "SMS Opt Out Received"
		content = section("🚫 Opt Out Details", table(
			row("Phone Number", f("phone_number")),
			row("From Number", f("from")),
			row("Keyword", f("keyword")),
			row("Message ID", f("message_id")),
			row("Opt Out At", f("opt_out_at")),
		))

	case "contact_created", "contact_updated":
		icon, title = "👤", "Contacts Callback"
		content = section("👤 Contact Details", table(
			row("Contact ID", f("contact_id")),
			row("Phone Number", f("phone_number")),
			row("First Name", f("first_name")),
			row("Last Name", f("last_name")),
			row("Email", f("email")),
			row("Status", f("status")),
			row("Created At", f("created_at")),
			row("Updated At", f("updated_at")),
		))
		if cf := asObject(data.Get("custom_fields")); cf.Len() > 0 {
			content += section("🏷️  Custom Fields", kvTable(cf))
		}

	default:
		title = "SMS Event: " + status
		content = section("Event Details", renderDataAsTable(data, 0))
	}

	body := section("📋 Event Information", table(
		row("Event Type", h(status)),
		row("IP Address", h(meta.IP)),
	)) + content

	html := emailShell(icon+" Webex Interact: "+h(title), h(meta.Date), body,
		"This Webex Interact webhook was automatically forwarded to your email address.")
	return html, icon + " Webex Interact: " + title, true
}
