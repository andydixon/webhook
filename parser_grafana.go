package main

import (
	"fmt"
	"net/http"
	"strings"
)

// grafanaParse formats Grafana / Prometheus Alertmanager webhook payloads.
func grafanaParse(raw []byte, headers http.Header, meta Meta) (string, string, bool) {
	v, err := decodeJSON(raw)
	payload := asObject(v)
	if err != nil || payload.Len() == 0 {
		return "", "", false
	}

	status := strOr(payload.Get("status"), "unknown")
	receiver := strOr(payload.Get("receiver"), "Unknown")
	externalURL := strOr(payload.Get("externalURL"), "")
	alerts, _ := payload.Get("alerts").([]any)
	truncated := num(payload.Get("truncatedAlerts"))

	icon, color := statusStyle(status, "🚨")

	// Summary
	rows := []string{
		row("Status", `<strong style="color: `+color+`;">`+icon+" "+h(status)+`</strong>`),
		row("Receiver", h(receiver)),
		row("Alert Count", fmt.Sprint(len(alerts))),
	}
	if truncated > 0 {
		rows = append(rows, row("Truncated Alerts", fmt.Sprint(int(truncated))))
	}
	if externalURL != "" {
		rows = append(rows, row("Alertmanager URL", link(externalURL)))
	}
	body := section("Alert Summary", table(rows...))

	// Labels
	groupLabels := asObject(payload.Get("groupLabels"))
	commonLabels := asObject(payload.Get("commonLabels"))
	if groupLabels.Len() > 0 || commonLabels.Len() > 0 {
		var b strings.Builder
		if groupLabels.Len() > 0 {
			b.WriteString(`<div class="subsection-title">Group Labels</div>` + kvTable(groupLabels))
		}
		if commonLabels.Len() > 0 {
			b.WriteString(`<div class="subsection-title">Common Labels</div>` + kvTable(commonLabels))
		}
		body += section("Labels", b.String())
	}

	// Common annotations
	if ca := asObject(payload.Get("commonAnnotations")); ca.Len() > 0 {
		body += section("Common Annotations", kvTable(ca))
	}

	// Individual alerts
	if len(alerts) > 0 {
		var b strings.Builder
		for i, a := range alerts {
			al := asObject(a)
			aStatus := strOr(al.Get("status"), "unknown")
			aIcon, aColor := statusStyle(aStatus, "🔥")
			b.WriteString(`<div class="array-item"><div class="array-item-title">` + aIcon + " Alert " + fmt.Sprint(i+1) +
				` - <span style="color: ` + aColor + `;">` + h(aStatus) + `</span></div>`)
			if l := asObject(al.Get("labels")); l.Len() > 0 {
				b.WriteString(`<div class="subsection-title">Labels</div>` + kvTable(l))
			}
			if an := asObject(al.Get("annotations")); an.Len() > 0 {
				b.WriteString(`<div class="subsection-title">Annotations</div>` + kvTable(an))
			}
			var extra []string
			if s := strOr(al.Get("startsAt"), ""); s != "" {
				extra = append(extra, row("Started At", h(s)))
			}
			if s := strOr(al.Get("endsAt"), ""); s != "" {
				extra = append(extra, row("Ended At", h(s)))
			}
			if s := strOr(al.Get("generatorURL"), ""); s != "" {
				extra = append(extra, row("Generator URL", link(s)))
			}
			if s := strOr(al.Get("fingerprint"), ""); s != "" {
				extra = append(extra, row("Fingerprint", h(s)))
			}
			if len(extra) > 0 {
				b.WriteString(table(extra...))
			}
			b.WriteString(`</div>`)
		}
		body += section("Alerts", b.String())
	}

	body += section("📋 Request Information", table(
		row("Receiver", h(receiver)),
		row("IP Address", h(meta.IP)),
	))

	html := emailShell(icon+" Grafana Alert: "+h(status), h(meta.Date), body,
		"This Grafana/Prometheus alert was automatically forwarded to your email address.")
	count := "1 alert"
	if len(alerts) != 1 {
		count = fmt.Sprintf("%d alerts", len(alerts))
	}
	return html, fmt.Sprintf("%s Grafana Alert: %s - %s", icon, status, count), true
}

func statusStyle(status, firingIcon string) (icon, color string) {
	switch status {
	case "firing":
		return firingIcon, "#dc3545"
	case "resolved":
		return "✅", "#28a745"
	}
	return "🔔", "#666666"
}

// kvTable renders an object of scalar values as a label/value table.
func kvTable(m *OMap) string {
	rows := make([]string, 0, m.Len())
	for _, k := range m.Keys {
		rows = append(rows, row(h(k), esc(str(m.M[k]))))
	}
	return table(rows...)
}
