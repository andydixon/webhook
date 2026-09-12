package main

import (
	"fmt"
	"net/http"
	"strings"
)

// githubParse formats GitHub webhook events.
func githubParse(raw []byte, headers http.Header, meta Meta) (string, string, bool) {
	v, err := decodeJSON(raw)
	payload := asObject(v)
	if err != nil || payload.Len() == 0 {
		return "", "", false
	}

	// http.Header lookups are case-insensitive, which covers lowercase HTTP/2 names.
	event := headers.Get("X-GitHub-Event")
	if event == "" {
		event = "Unknown"
	}

	action := strOr(payload.Get("action"), "")
	repo := strOr(get(payload, "repository", "full_name"), "Unknown")
	sender := strOr(get(payload, "sender", "login"), "Unknown")

	eventSafe := h(event)
	repoSafe := h(repo)
	senderSafe := h(sender)

	icon := "🔔"
	var content string

	switch event {
	case "push":
		icon = "📤"
		ref := h(strOr(payload.Get("ref"), "Unknown"))
		commits, _ := payload.Get("commits").([]any)
		content = section("Push Details", table(
			row("Branch", ref),
			row("Commits", fmt.Sprint(len(commits))),
			row("Pusher", senderSafe),
		))
		if len(commits) > 0 {
			var b strings.Builder
			for i, c := range commits {
				if i == 10 {
					break
				}
				msg := esc(strOr(get(c, "message"), ""))
				author := h(strOr(get(c, "author", "name"), "Unknown"))
				id := strOr(get(c, "id"), "")
				if len(id) > 7 {
					id = id[:7]
				}
				sha := h(id)
				url := strOr(get(c, "url"), "")
				shaHTML := `<span class="sha">` + sha + `</span>`
				if url != "" {
					shaHTML = `<a class="sha" href="` + h(url) + `">` + sha + `</a>`
				}
				b.WriteString(`<div class="commit-item">` + shaHTML + ` <span class="who">` + author + `</span><div class="msg">` + msg + `</div></div>`)
			}
			content += section("Commits", b.String())
		}

	case "pull_request":
		icon = "🔀"
		pr := payload.Get("pull_request")
		content = section("Pull Request Details", table(
			row("Number", "#"+h(strOr(get(pr, "number"), "N/A"))),
			row("Title", h(strOr(get(pr, "title"), "N/A"))),
			row("State", h(strOr(get(pr, "state"), "N/A"))),
			row("Branch", h(strOr(get(pr, "head", "ref"), "N/A"))+" → "+h(strOr(get(pr, "base", "ref"), "N/A"))),
			row("URL", link(strOr(get(pr, "html_url"), "#"))),
		))

	case "issues":
		icon = "📝"
		is := payload.Get("issue")
		content = section("Issue Details", table(
			row("Number", "#"+h(strOr(get(is, "number"), "N/A"))),
			row("Title", h(strOr(get(is, "title"), "N/A"))),
			row("State", h(strOr(get(is, "state"), "N/A"))),
			row("URL", link(strOr(get(is, "html_url"), "#"))),
		))

	case "release":
		icon = "🚀"
		rel := payload.Get("release")
		content = section("Release Details", table(
			row("Tag", h(strOr(get(rel, "tag_name"), "N/A"))),
			row("Name", h(strOr(get(rel, "name"), "N/A"))),
			row("URL", link(strOr(get(rel, "html_url"), "#"))),
		))

	default:
		content = section("Event Details", renderDataAsTable(payload, 0))
	}

	actionDisplay := ""
	if action != "" {
		actionDisplay = " (" + h(action) + ")"
	}

	body := section("📋 Repository Information", table(
		row("Repository", repoSafe),
		row("Event", eventSafe),
		row("Sender", senderSafe),
		row("IP Address", h(meta.IP)),
	)) + content

	html := emailShell(icon+" GitHub: "+eventSafe+actionDisplay, h(meta.Date), body,
		"This GitHub webhook was automatically forwarded to your email address.")
	subject := fmt.Sprintf("%s GitHub %s%s - %s", icon, event, strings.ReplaceAll(actionDisplay, "&#39;", "'"), repo)
	return html, subject, true
}
