package main

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"html"
	"os"
	"strings"
	"text/template"
)

// ---------- escaping ----------

// h escapes a single-line value for HTML.
func h(s string) string { return html.EscapeString(s) }

// esc escapes free text and keeps its line breaks (PHP's nl2br(htmlspecialchars())).
func esc(s string) string {
	s = html.EscapeString(s)
	s = strings.ReplaceAll(s, "\r\n", "\n")
	return strings.ReplaceAll(s, "\n", "<br>\n")
}

// ---------- ordered JSON ----------

// OMap is a JSON object that remembers key order, so emails list fields
// in the order the sender wrote them.
type OMap struct {
	Keys []string
	M    map[string]any
}

func (m *OMap) Get(k string) any {
	if m == nil {
		return nil
	}
	return m.M[k]
}

func (m *OMap) Len() int {
	if m == nil {
		return 0
	}
	return len(m.Keys)
}

// decodeJSON parses JSON into OMap / []any / string / json.Number / bool / nil.
func decodeJSON(b []byte) (any, error) {
	dec := json.NewDecoder(bytes.NewReader(b))
	dec.UseNumber()
	v, err := decodeValue(dec)
	if err != nil {
		return nil, err
	}
	if _, err := dec.Token(); err == nil {
		return nil, errors.New("trailing data after JSON value")
	}
	return v, nil
}

func decodeValue(dec *json.Decoder) (any, error) {
	t, err := dec.Token()
	if err != nil {
		return nil, err
	}
	d, isDelim := t.(json.Delim)
	if !isDelim {
		return t, nil
	}
	switch d {
	case '{':
		om := &OMap{M: map[string]any{}}
		for dec.More() {
			kt, err := dec.Token()
			if err != nil {
				return nil, err
			}
			k, _ := kt.(string)
			v, err := decodeValue(dec)
			if err != nil {
				return nil, err
			}
			if _, dup := om.M[k]; !dup {
				om.Keys = append(om.Keys, k)
			}
			om.M[k] = v
		}
		_, err = dec.Token() // '}'
		return om, err
	case '[':
		arr := []any{}
		for dec.More() {
			v, err := decodeValue(dec)
			if err != nil {
				return nil, err
			}
			arr = append(arr, v)
		}
		_, err = dec.Token() // ']'
		return arr, err
	}
	return nil, fmt.Errorf("unexpected delimiter %v", d)
}

// asObject returns the value as an object, or nil.
func asObject(v any) *OMap {
	m, _ := v.(*OMap)
	return m
}

// isContainer reports whether v is an object or array (PHP's is_array on decoded JSON).
func isContainer(v any) bool {
	switch v.(type) {
	case *OMap, []any:
		return true
	}
	return false
}

func containerLen(v any) int {
	switch x := v.(type) {
	case *OMap:
		return x.Len()
	case []any:
		return len(x)
	}
	return 0
}

// str renders a scalar the way it should read in an email.
func str(v any) string {
	switch x := v.(type) {
	case nil:
		return ""
	case string:
		return x
	case json.Number:
		return x.String()
	case bool:
		if x {
			return "true"
		}
		return "false"
	default:
		b, _ := json.Marshal(x)
		return string(b)
	}
}

// strOr is PHP's `$x ?? $default`: only a missing/null value takes the default.
func strOr(v any, def string) string {
	if v == nil {
		return def
	}
	return str(v)
}

// get walks nested objects: get(payload, "repository", "full_name").
func get(v any, keys ...string) any {
	for _, k := range keys {
		m := asObject(v)
		if m == nil {
			return nil
		}
		v = m.M[k]
	}
	return v
}

func num(v any) float64 {
	if n, ok := v.(json.Number); ok {
		f, _ := n.Float64()
		return f
	}
	return 0
}

// ---------- HTML building blocks ----------

const tableOpen = `<table class="metadata" role="presentation" cellspacing="0" cellpadding="0" width="100%">`

func row(label, valueHTML string) string {
	return `<tr><td class="metadata-label">` + label + `</td><td class="metadata-value">` + valueHTML + `</td></tr>`
}

func table(rows ...string) string {
	return tableOpen + strings.Join(rows, "") + `</table>`
}

func section(title, inner string) string {
	return `<div class="section"><div class="section-title">` + title + `</div>` + inner + `</div>`
}

func link(url string) string {
	u := h(url)
	return `<a href="` + u + `">` + u + `</a>`
}

// renderDataAsTable renders arbitrary decoded JSON as nested tables (generic fallback).
func renderDataAsTable(data any, depth int) string {
	if depth > 10 {
		return `<div class="data-box">Max depth reached</div>`
	}
	var b strings.Builder
	switch d := data.(type) {
	case []any:
		if len(d) == 0 {
			return table()
		}
		for i, item := range d {
			if m := asObject(item); m != nil {
				b.WriteString(`<div class="array-item"><div class="array-item-title">Item ` + fmt.Sprint(i+1) + `</div>` + tableOpen)
				for _, k := range m.Keys {
					v := m.M[k]
					if isContainer(v) {
						b.WriteString(row(h(k), renderDataAsTable(v, depth+1)))
					} else {
						b.WriteString(row(h(k), esc(str(v))))
					}
				}
				b.WriteString(`</table></div>`)
			} else if isContainer(item) {
				b.WriteString(renderDataAsTable(item, depth+1))
			} else {
				b.WriteString(`<div class="array-item-simple">• ` + esc(str(item)) + `</div>`)
			}
		}
	case *OMap:
		var rows []string
		flush := func() {
			if len(rows) > 0 {
				b.WriteString(table(rows...))
				rows = nil
			}
		}
		for _, k := range d.Keys {
			v := d.M[k]
			switch {
			case isContainer(v) && containerLen(v) > 0:
				flush()
				b.WriteString(`<div class="subsection"><div class="subsection-title">` + h(k) + `</div>` + renderDataAsTable(v, depth+1) + `</div>`)
			case isContainer(v):
				rows = append(rows, row(h(k), `<em>empty</em>`))
			default:
				rows = append(rows, row(h(k), esc(str(v))))
			}
		}
		flush()
	default:
		return `<div class="simple-value">` + esc(str(data)) + `</div>`
	}
	return b.String()
}

// ---------- email shell ----------

var shellTmpl = template.Must(template.New("shell").Parse(shellSrc))

type shellData struct {
	Host, Title, PlainTitle, Stamp, Body, Footer, Mono, Sans string
}

var hostName = func() string {
	if v := os.Getenv("PUBLIC_HOST"); v != "" {
		return v
	}
	return "webhooks.dixon.cx"
}()

// emailShell wraps a body of sections in the shared design: stylesheet, gradient header, footer.
// title and stamp must already be HTML-safe.
func emailShell(title, stamp, body, footer string) string {
	if footer == "" {
		footer = "Automatically forwarded to your inbox."
	}
	var out strings.Builder
	_ = shellTmpl.Execute(&out, shellData{
		Host:       h(hostName),
		Title:      title,
		PlainTitle: stripTags(title),
		Stamp:      stamp,
		Body:       body,
		Footer:     footer,
		Mono:       "ui-monospace,SFMono-Regular,Menlo,Consolas,'Liberation Mono',monospace",
		Sans:       "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif",
	})
	return out.String()
}

func stripTags(s string) string {
	var b strings.Builder
	in := false
	for _, r := range s {
		switch {
		case r == '<':
			in = true
		case r == '>':
			in = false
		case !in:
			b.WriteRune(r)
		}
	}
	return b.String()
}

const shellSrc = `<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{.PlainTitle}}</title>
<style>
  body { margin:0; padding:0; background:#0f0f17; color:#15151f; -webkit-font-smoothing:antialiased; font-family:{{.Sans}}; font-size:15px; line-height:1.6; }
  a { color:#7c3aed; }
  table { border-collapse:separate; border-spacing:0; }
  .frame { width:100%; max-width:660px; }
  .hero { background:#7c3aed; background-image:linear-gradient(120deg,#8b5cf6 0%,#ec4899 55%,#f97316 100%); padding:32px 34px 28px; border-radius:20px 20px 0 0; }
  .kicker { font-family:{{.Mono}}; font-size:11px; letter-spacing:.18em; text-transform:uppercase; color:rgba(255,255,255,.75); margin:0 0 10px; }
  .hero h1 { margin:0 0 14px; font-size:26px; font-weight:800; letter-spacing:-.02em; line-height:1.2; color:#ffffff; }
  .stamp { display:inline-block; font-family:{{.Mono}}; font-size:12px; letter-spacing:.06em; color:#ffffff; background:rgba(15,15,23,.28); padding:6px 12px; border-radius:999px; }
  .card { background:#ffffff; padding:8px 0 14px; border-radius:0 0 20px 20px; }
  .section { padding:24px 34px 0; }
  .section-title { font-family:{{.Mono}}; font-size:11px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:#7c3aed; margin:0 0 12px; padding-left:10px; border-left:3px solid #ec4899; line-height:1.4; }
  .subsection { margin:14px 0 0; }
  .subsection-title { font-size:13px; font-weight:700; color:#15151f; margin:16px 0 8px; }
  table.metadata { width:100%; border:1px solid #e6e3f2; border-radius:12px; overflow:hidden; background:#ffffff; }
  td.metadata-label, td.metadata-value { padding:11px 14px; border-bottom:1px solid #efedf7; vertical-align:top; text-align:left; }
  tr:last-child > td.metadata-label, tr:last-child > td.metadata-value { border-bottom:0; }
  td.metadata-label { width:150px; max-width:170px; font-family:{{.Mono}}; font-size:12px; line-height:1.5; color:#6b6b80; background:#f8f7fd; word-break:break-word; }
  td.metadata-value { font-size:14px; color:#15151f; word-break:break-word; }
  td.metadata-value table.metadata { margin:2px 0; }
  .data-box { margin:0; padding:16px 18px; background:#15151f; color:#e6e6f0; border-radius:12px; border:1px solid #2a2a3a; font-family:{{.Mono}}; font-size:12.5px; line-height:1.65; white-space:pre-wrap; word-break:break-word; overflow-x:auto; }
  .path-title { display:inline-block; margin:18px 0 8px; padding:5px 12px; font-family:{{.Mono}}; font-size:12px; font-weight:700; color:#be185d; background:#fdf2f8; border:1px solid #fbcfe8; border-radius:999px; }
  .array-item { margin:0 0 12px; padding:16px; background:#f8f7fd; border:1px solid #e6e3f2; border-radius:14px; }
  .array-item-title { font-size:14px; font-weight:800; margin:0 0 10px; color:#15151f; letter-spacing:-.01em; }
  .array-item-simple { margin:0 0 6px; padding:9px 14px; background:#f8f7fd; border:1px solid #e6e3f2; border-radius:10px; font-size:14px; }
  .simple-value { font-size:14px; padding:4px 0; }
  .commit-item { margin:0 0 10px; padding:12px 14px; background:#f8f7fd; border:1px solid #e6e3f2; border-radius:12px; }
  .commit-item .sha { display:inline-block; font-family:{{.Mono}}; font-size:12px; font-weight:700; color:#be185d; background:#fdf2f8; border:1px solid #fbcfe8; border-radius:999px; padding:2px 9px; text-decoration:none; }
  .commit-item .who { font-size:12px; color:#6b6b80; margin-left:6px; }
  .commit-item .msg { margin-top:6px; font-size:14px; line-height:1.5; color:#15151f; }
  .message-box { margin:12px 0; padding:16px 18px 16px 20px; background:#f8f7fd; border-left:4px solid #8b5cf6; border-radius:0 12px 12px 0; font-size:16px; line-height:1.55; color:#15151f; }
  .error-box { margin:12px 0; padding:12px 14px; background:#fef2f2; border-left:4px solid #ef4444; border-radius:0 10px 10px 0; }
  .footer { padding:22px 34px 8px; text-align:center; color:#6a6a86; font-family:{{.Mono}}; font-size:11px; letter-spacing:.06em; line-height:1.8; }
  .footer a { color:#a78bfa; text-decoration:none; }
  .footer .bolt { color:#f97316; }
</style>
</head>
<body style="margin:0;padding:0;background:#0f0f17;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f17;">
  <tr><td align="center" style="padding:36px 12px 28px;">
    <table role="presentation" class="frame" cellpadding="0" cellspacing="0">
      <tr><td class="hero">
        <p class="kicker">Webhook &rsaquo; {{.Host}}</p>
        <h1>{{.Title}}</h1>
        <span class="stamp">{{.Stamp}}</span>
      </td></tr>
      <tr><td class="card">
{{.Body}}
      </td></tr>
      <tr><td class="footer">
        {{.Footer}}<br>
        <span class="bolt">&#9889;</span> <a href="https://{{.Host}}/">{{.Host}}</a>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
`
