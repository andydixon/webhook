package main

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"strings"
	"testing"
)

type captured struct{ to, subject, html string }

func capture(t *testing.T) *captured {
	t.Helper()
	c := &captured{}
	old := sendMail
	sendMail = func(to, subject, html string) error { *c = captured{to, subject, html}; return nil }
	t.Cleanup(func() { sendMail = old })
	return c
}

func do(t *testing.T, method, path, body string, hdr map[string]string) (*httptest.ResponseRecorder, map[string]any) {
	t.Helper()
	req := httptest.NewRequest(method, path, strings.NewReader(body))
	req.RemoteAddr = "172.17.0.1:5555"
	for k, v := range hdr {
		req.Header.Set(k, v)
	}
	rec := httptest.NewRecorder()
	handle(rec, req)
	var out map[string]any
	if strings.HasPrefix(rec.Header().Get("Content-Type"), "application/json") {
		if err := json.Unmarshal(rec.Body.Bytes(), &out); err != nil {
			t.Fatalf("bad json: %v\n%s", err, rec.Body.String())
		}
	}
	return rec, out
}

func TestDocsAndHealth(t *testing.T) {
	rec, _ := do(t, "GET", "/", "", nil)
	if rec.Code != 200 || !strings.Contains(rec.Body.String(), "example.com/") {
		t.Fatalf("docs page: %d", rec.Code)
	}
	rec, _ = do(t, "GET", "/healthz", "", nil)
	if rec.Code != 200 {
		t.Fatalf("healthz: %d", rec.Code)
	}
}

func TestInvalidEmail(t *testing.T) {
	rec, out := do(t, "POST", "/notanemail", "{}", nil)
	if rec.Code != 400 || out["error"] != "invalid_email" {
		t.Fatalf("got %d %v", rec.Code, out)
	}
}

func TestDefaultJSONResponse(t *testing.T) {
	c := capture(t)
	rec, out := do(t, "POST", "/t%40e.com?src=curl", `{"event":"user.created","user_id":12345}`,
		map[string]string{"Content-Type": "application/json", "X-Real-IP": "203.0.113.7"})
	if rec.Code != 200 || out["ok"] != true || out["parser"] != "default" {
		t.Fatalf("got %d %v", rec.Code, out)
	}
	if out["request"].(map[string]any)["ip"] != "203.0.113.7" {
		t.Fatalf("forwarded ip not used: %v", out["request"])
	}
	data := out["data"].(map[string]any)
	if data["query"].(map[string]any)["src"] != "curl" || data["json"].(map[string]any)["event"] != "user.created" {
		t.Fatalf("data echo wrong: %v", data)
	}
	if !strings.HasPrefix(rec.Body.String(), "{\n    \"ok\": true,") {
		t.Fatalf("not pretty-printed with ok first:\n%s", rec.Body.String())
	}
	if c.to != "t@e.com" || !strings.HasPrefix(c.subject, "‼️ Webhook Request Received") || !strings.Contains(c.html, "user.created") {
		t.Fatalf("mail wrong: %+v", c)
	}
}

func TestPublicPeerCannotSpoofIP(t *testing.T) {
	capture(t)
	req := httptest.NewRequest("POST", "/t%40e.com", nil)
	req.RemoteAddr = "198.51.100.9:1234"
	req.Header.Set("X-Real-IP", "1.2.3.4")
	rec := httptest.NewRecorder()
	handle(rec, req)
	if !strings.Contains(rec.Body.String(), `"ip": "198.51.100.9"`) {
		t.Fatalf("spoofed:\n%s", rec.Body.String())
	}
}

func TestFormFields(t *testing.T) {
	c := capture(t)
	_, out := do(t, "POST", "/t%40e.com", "a=1&b=two", map[string]string{"Content-Type": "application/x-www-form-urlencoded"})
	form := out["data"].(map[string]any)["form"].(map[string]any)
	if form["a"] != "1" || form["b"] != "two" || out["data"].(map[string]any)["json"] != nil {
		t.Fatalf("form: %v", out["data"])
	}
	if !strings.Contains(c.html, `&#34;b&#34;: &#34;two&#34;`) {
		t.Fatalf("variables block missing form field")
	}
}

func TestGitHubLowercaseHeader(t *testing.T) {
	c := capture(t)
	body := `{"ref":"refs/heads/main","repository":{"full_name":"andy/webhooks"},"sender":{"login":"andy"},
	  "commits":[{"id":"abc1234def","message":"Line one\n\n- <b>two</b>","author":{"name":"Andy"},"url":"https://github.com/andy/webhooks/commit/abc1234"}]}`
	_, out := do(t, "POST", "/t%40e.com/github", body, map[string]string{"Content-Type": "application/json", "x-github-event": "push"})
	if out["parser"] != "github" || out["subject"] != "📤 GitHub push - andy/webhooks" {
		t.Fatalf("subject: %v", out["subject"])
	}
	for _, want := range []string{`class="sha" href="https://github.com/andy/webhooks/commit/abc1234">abc1234<`, "Line one<br>\n<br>\n- &lt;b&gt;two&lt;/b&gt;"} {
		if !strings.Contains(c.html, want) {
			t.Fatalf("missing %q in\n%s", want, c.html)
		}
	}
}

func TestGitHubPullRequestAction(t *testing.T) {
	capture(t)
	body := `{"action":"opened","pull_request":{"number":42,"title":"Epic","state":"open","html_url":"https://x/pr/42","head":{"ref":"f"},"base":{"ref":"main"}},"repository":{"full_name":"a/b"},"sender":{"login":"andy"}}`
	_, out := do(t, "POST", "/t%40e.com/github", body, map[string]string{"X-GitHub-Event": "pull_request"})
	if out["subject"] != "🔀 GitHub pull_request (opened) - a/b" {
		t.Fatalf("subject: %v", out["subject"])
	}
}

func TestGrafana(t *testing.T) {
	c := capture(t)
	body := `{"receiver":"email","status":"firing","alerts":[{"status":"firing","labels":{"alertname":"HighCPU","severity":"critical"},"annotations":{"summary":"CPU over 90%"},"startsAt":"2026-09-11T17:00:00Z"},{"status":"resolved","labels":{"alertname":"Disk"}}],"groupLabels":{"alertname":"HighCPU"},"externalURL":"https://g.example.com","truncatedAlerts":0}`
	_, out := do(t, "POST", "/t%40e.com/grafana", body, nil)
	if out["subject"] != "🚨 Grafana Alert: firing - 2 alerts" {
		t.Fatalf("subject: %v", out["subject"])
	}
	if !strings.Contains(c.html, "Alert 2") || strings.Contains(c.html, "Truncated") || !strings.Contains(c.html, `href="https://g.example.com"`) {
		t.Fatalf("grafana html wrong")
	}
}

func TestJSONParserBreadcrumbsAndScalars(t *testing.T) {
	c := capture(t)
	body := `{"order":{"id":"ORD-789","items":[{"sku":"A1","tags":["red","xl"]}],"notes":"l1\nl2"},"colours":["cyan"],"n":99.99,"ok":true,"none":null}`
	do(t, "POST", "/t%40e.com/json", body, map[string]string{"Content-Type": "application/json"})
	for _, want := range []string{
		`path-title">order<`, `path-title">order-&gt;items-&gt;[0]<`, `path-title">order-&gt;items-&gt;[0]-&gt;tags<`, `path-title">colours<`,
		"l1<br>\nl2", `>99.99<`, `>true<`, `<td class="metadata-value"></td>`,
	} {
		if !strings.Contains(c.html, want) {
			t.Fatalf("missing %q", want)
		}
	}
	// key order preserved: "order" table before "colours"
	if strings.Index(c.html, `path-title">order<`) > strings.Index(c.html, `path-title">colours<`) {
		t.Fatal("key order not preserved")
	}

	_, out := do(t, "POST", "/t%40e.com/json", `"just a string"`, map[string]string{"Content-Type": "application/json"})
	if out["parser"] != "json" || !strings.Contains(c.html, `metadata-label">value</td><td class="metadata-value">just a string<`) {
		t.Fatalf("scalar body not handled: %v", out["subject"])
	}

	_, out = do(t, "POST", "/t%40e.com/json", `not json`, nil)
	if !strings.HasPrefix(out["subject"].(string), "‼️") {
		t.Fatalf("invalid json should fall back: %v", out["subject"])
	}
}

func TestWebexSamples(t *testing.T) {
	raw, err := os.ReadFile("wxinteract_test_samples.json")
	if err != nil {
		t.Skip("no samples")
	}
	var samples map[string]json.RawMessage
	if err := json.Unmarshal(raw, &samples); err != nil {
		t.Fatal(err)
	}
	c := capture(t)
	for name, s := range samples {
		_, out := do(t, "POST", "/t%40e.com/wxinteract", string(s), nil)
		if out["parser"] != "wxinteract" || !strings.Contains(out["subject"].(string), "Webex Interact:") {
			t.Fatalf("%s: %v", name, out["subject"])
		}
		if !strings.Contains(c.html, "Event Information") {
			t.Fatalf("%s: html missing", name)
		}
	}
}

func TestMailFailureIsReported(t *testing.T) {
	old := sendMail
	sendMail = func(string, string, string) error { return os.ErrClosed }
	t.Cleanup(func() { sendMail = old })
	rec, out := do(t, "POST", "/t%40e.com", "", nil)
	if rec.Code != 502 || out["error"] != "mail_failed" {
		t.Fatalf("got %d %v", rec.Code, out)
	}
}

func TestMessageEncoding(t *testing.T) {
	msg := string(buildMessage("t@e.com", "‼️ Subject", "<p>héllo</p>"))
	if !strings.Contains(msg, "Subject: =?utf-8?q?") || !strings.Contains(msg, "Content-Transfer-Encoding: quoted-printable") || !strings.Contains(msg, "h=C3=A9llo") {
		t.Fatalf("message:\n%s", msg)
	}
}

var _ = http.StatusOK
