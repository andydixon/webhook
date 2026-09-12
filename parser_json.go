package main

import (
	"fmt"
	"net/http"
	"strings"
)

// jsonParse renders any JSON body as tables, splitting nested objects into
// their own tables under path breadcrumbs like order->items->[0]->tags.
func jsonParse(raw []byte, headers http.Header, meta Meta) (string, string, bool) {
	v, err := decodeJSON(raw)
	if err != nil {
		return "", "", false
	}

	body := section("📋 Request Information", table(
		row("IP Address", h(meta.IP)),
		row("Method", h(meta.Method)),
		row("Content-Type", h(meta.ContentType)),
	)) + section("📦 JSON Data", renderJSON(v, ""))

	html := emailShell("📊 JSON Webhook Data", h(meta.Date), body, "JSON data automatically parsed and formatted.")
	return html, "📊 JSON Webhook - " + meta.Date, true
}

func pathTitle(path string) string {
	if path == "" {
		return ""
	}
	return `<div class="path-title">` + h(path) + `</div>`
}

func renderJSON(data any, path string) string {
	var b strings.Builder

	switch d := data.(type) {
	case []any:
		if len(d) == 0 {
			return ""
		}
		allScalar := true
		for _, it := range d {
			if isContainer(it) {
				allScalar = false
				break
			}
		}
		if allScalar {
			// Array of plain values: one table under a single breadcrumb
			rows := make([]string, 0, len(d))
			for i, it := range d {
				rows = append(rows, row("Item "+fmt.Sprint(i+1), esc(str(it))))
			}
			return pathTitle(path) + table(rows...)
		}
		for i, item := range d {
			itemPath := fmt.Sprintf("[%d]", i)
			if path != "" {
				itemPath = path + "->" + itemPath
			}
			if m := asObject(item); m != nil {
				var simple []string
				var sub []string
				for _, k := range m.Keys {
					if isContainer(m.M[k]) {
						sub = append(sub, k)
					} else {
						simple = append(simple, row(h(k), esc(str(m.M[k]))))
					}
				}
				if len(simple) > 0 {
					b.WriteString(pathTitle(path2(path, itemPath)))
					b.WriteString(`<div class="array-item"><div class="array-item-title">Item ` + fmt.Sprint(i+1) + `</div>` + table(simple...) + `</div>`)
				}
				for _, k := range sub {
					b.WriteString(renderJSON(m.M[k], itemPath+"->"+k))
				}
			} else if isContainer(item) {
				b.WriteString(renderJSON(item, itemPath))
			} else {
				b.WriteString(table(row("Item "+fmt.Sprint(i+1), esc(str(item)))))
			}
		}

	case *OMap:
		var simple []string
		var sub []string
		for _, k := range d.Keys {
			if isContainer(d.M[k]) {
				sub = append(sub, k)
			} else {
				simple = append(simple, row(h(k), esc(str(d.M[k]))))
			}
		}
		if len(simple) > 0 {
			b.WriteString(pathTitle(path) + table(simple...))
		}
		for _, k := range sub {
			subPath := k
			if path != "" {
				subPath = path + "->" + k
			}
			b.WriteString(renderJSON(d.M[k], subPath))
		}

	default:
		// Scalar (or null): one-row table so it lines up with everything else
		label := path
		if label == "" {
			label = "value"
		}
		val := str(data)
		if data == nil {
			val = "null"
		}
		return table(row(h(label), esc(val)))
	}
	return b.String()
}

// path2 mirrors the PHP behaviour: an item breadcrumb is only shown when nested under a path.
func path2(parent, itemPath string) string {
	if parent == "" {
		return ""
	}
	return itemPath
}
