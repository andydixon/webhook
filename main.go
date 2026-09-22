// Webhook Email Forwarder
//
// Captures incoming webhook requests and forwards the complete request
// (headers, body, metadata) as an HTML email to the address given in the
// URL path:  /you%40example.com[/parser]
package main

import (
	"bytes"
	"context"
	"embed"
	"encoding/json"
	"flag"
	"fmt"
	"html/template"
	"io"
	"io/fs"
	"log"
	"net"
	"net/http"
	"net/mail"
	"os"
	"os/signal"
	"regexp"
	"sort"
	"strings"
	"syscall"
	"time"
)

//go:embed docs.html
var docsSrc string

var docsTmpl = template.Must(template.New("docs").Parse(docsSrc))

//go:embed static
var staticFS embed.FS

// assets serves favicons and the share image at the site root.
var assets = func() http.Handler {
	sub, _ := fs.Sub(staticFS, "static")
	return http.FileServerFS(sub)
}()

const maxBody = 10 << 20 // 10 MB, like PHP's post_max_size

// Meta is the request metadata handed to every parser.
type Meta struct {
	Date        string // Y-m-d H:i:s
	IP          string
	Method      string
	ContentType string
}

// Parser turns a raw webhook into an email. ok=false means "fall back to the default email".
type Parser func(raw []byte, headers http.Header, meta Meta) (html, subject string, ok bool)

var parsers = map[string]Parser{
	"github":     githubParse,
	"grafana":    grafanaParse,
	"json":       jsonParse,
	"wxinteract": wxinteractParse,
}

func main() {
	listen := flag.String("listen", envOr("LISTEN", "127.0.0.1:8080"), "address to listen on")
	check := flag.Bool("check", false, "health-check a running instance and exit")
	flag.Parse()

	if *check {
		addr := *listen
		if strings.HasPrefix(addr, ":") {
			addr = "127.0.0.1" + addr
		}
		resp, err := http.Get("http://" + addr + "/healthz")
		if err != nil || resp.StatusCode != 200 {
			os.Exit(1)
		}
		return
	}

	srv := &http.Server{
		Addr:              *listen,
		Handler:           http.HandlerFunc(handle),
		ReadHeaderTimeout: 10 * time.Second,
		ReadTimeout:       30 * time.Second,
		WriteTimeout:      30 * time.Second,
	}
	go func() {
		log.Printf("listening on %s, smtp %s, from %s", *listen, smtpAddr, mailFrom)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatal(err)
		}
	}()
	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGTERM)
	<-stop
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	_ = srv.Shutdown(ctx)
}

func envOr(k, def string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return def
}

// ---------- HTTP ----------

var emailRe = regexp.MustCompile(`^[^\s@]+@[^\s@]+\.[^\s@]+$`)

func validEmail(s string) bool {
	if !emailRe.MatchString(s) {
		return false
	}
	a, err := mail.ParseAddress(s)
	return err == nil && a.Address == s
}

func handle(w http.ResponseWriter, r *http.Request) {
	path := strings.Trim(r.URL.Path, "/")
	if path == "" {
		w.Header().Set("Content-Type", "text/html; charset=UTF-8")
		_ = docsTmpl.Execute(w, map[string]string{"Host": r.Host, "Canonical": hostName})
		return
	}
	switch path {
	case "healthz":
		_, _ = io.WriteString(w, "ok\n")
		return
	case "favicon.ico", "favicon.svg", "apple-touch-icon.png", "og.png":
		w.Header().Set("Cache-Control", "public, max-age=604800")
		assets.ServeHTTP(w, r)
		return
	case "robots.txt":
		w.Header().Set("Content-Type", "text/plain; charset=UTF-8")
		fmt.Fprintf(w, "User-agent: *\nDisallow: /*%%40\nDisallow: /*@\nSitemap: https://%s/sitemap.xml\n", hostName)
		return
	case "sitemap.xml":
		w.Header().Set("Content-Type", "application/xml; charset=UTF-8")
		fmt.Fprintf(w, `<?xml version="1.0" encoding="UTF-8"?>`+"\n"+`<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://%s/</loc></url></urlset>`+"\n", hostName)
		return
	}

	parts := strings.SplitN(path, "/", 3)
	email := parts[0] // r.URL.Path is already percent-decoded
	parserName := ""
	if len(parts) > 1 {
		parserName = parts[1]
	}

	if !validEmail(email) {
		writeJSON(w, 400, errResp{false, "invalid_email", "Invalid email address provided in URL path.", "Encode @ as %40, e.g. /you%40example.com"})
		return
	}

	now := time.Now()
	meta := Meta{
		Date:        now.Format("2006-01-02 15:04:05"),
		IP:          clientIP(r),
		Method:      r.Method,
		ContentType: r.Header.Get("Content-Type"),
	}
	if meta.ContentType == "" {
		meta.ContentType = "N/A"
	}

	raw, err := io.ReadAll(http.MaxBytesReader(w, r.Body, maxBody))
	if err != nil {
		writeJSON(w, 413, errResp{false, "body_too_large", err.Error(), ""})
		return
	}

	// Form fields and uploaded file names, the way PHP's $_POST / $_FILES saw them.
	form := map[string]string{}
	files := []string{}
	ct := strings.ToLower(meta.ContentType)
	if strings.HasPrefix(ct, "application/x-www-form-urlencoded") || strings.HasPrefix(ct, "multipart/form-data") {
		r.Body = io.NopCloser(bytes.NewReader(raw))
		if strings.HasPrefix(ct, "multipart/form-data") {
			if err := r.ParseMultipartForm(maxBody); err == nil && r.MultipartForm != nil {
				for name := range r.MultipartForm.File {
					files = append(files, name)
				}
				sort.Strings(files)
			}
		} else {
			_ = r.ParseForm()
		}
		for k, v := range r.PostForm {
			form[k] = v[len(v)-1]
		}
	}
	query := map[string]string{}
	for k, v := range r.URL.Query() {
		query[k] = v[len(v)-1]
	}

	// Every header, Host included, as the email and parsers see them.
	headers := r.Header.Clone()
	headers.Set("Host", r.Host)

	var body, subject string
	if p, found := parsers[parserName]; found {
		body, subject, _ = p(raw, headers, meta)
	}
	if body == "" {
		body, subject = defaultEmail(raw, headers, meta, query, form, files)
	}

	if err := sendMail(email, subject, body); err != nil {
		log.Printf("mail to %s failed: %v", email, err)
		writeJSON(w, 502, errResp{false, "mail_failed", "Webhook received but the email could not be sent.", ""})
		return
	}

	var jsonEcho json.RawMessage
	if len(raw) > 0 && strings.Contains(ct, "json") && json.Valid(raw) {
		jsonEcho = raw
	}
	if parserName == "" {
		parserName = "default"
	}
	writeJSON(w, 200, response{
		OK:          true,
		Message:     "Webhook received and forwarded to " + email,
		ForwardedTo: email,
		Parser:      parserName,
		Subject:     subject,
		ReceivedAt:  now.Format("2006-01-02T15:04:05-07:00"),
		Request: reqInfo{
			Method:      meta.Method,
			ContentType: meta.ContentType,
			IP:          meta.IP,
			Headers:     len(headers),
			BodyBytes:   len(raw),
		},
		Data: reqData{Query: query, Form: form, JSON: jsonEcho, Files: files},
	})
}

type errResp struct {
	OK      bool   `json:"ok"`
	Error   string `json:"error"`
	Message string `json:"message"`
	Hint    string `json:"hint,omitempty"`
}

type response struct {
	OK          bool    `json:"ok"`
	Message     string  `json:"message"`
	ForwardedTo string  `json:"forwarded_to"`
	Parser      string  `json:"parser"`
	Subject     string  `json:"subject"`
	ReceivedAt  string  `json:"received_at"`
	Request     reqInfo `json:"request"`
	Data        reqData `json:"data"`
}

type reqInfo struct {
	Method      string `json:"method"`
	ContentType string `json:"content_type"`
	IP          string `json:"ip"`
	Headers     int    `json:"headers"`
	BodyBytes   int    `json:"body_bytes"`
}

type reqData struct {
	Query map[string]string `json:"query"`
	Form  map[string]string `json:"form"`
	JSON  json.RawMessage   `json:"json"`
	Files []string          `json:"files"`
}

// writeJSON emits pretty-printed JSON with 4-space indent and no HTML escaping,
// preserving key order inside any RawMessage.
func writeJSON(w http.ResponseWriter, status int, v any) {
	var buf bytes.Buffer
	enc := json.NewEncoder(&buf)
	enc.SetEscapeHTML(false)
	if err := enc.Encode(v); err != nil {
		http.Error(w, err.Error(), 500)
		return
	}
	var out bytes.Buffer
	_ = json.Indent(&out, bytes.TrimSpace(buf.Bytes()), "", "    ")
	out.WriteByte('\n')
	w.Header().Set("Content-Type", "application/json; charset=UTF-8")
	w.WriteHeader(status)
	_, _ = w.Write(out.Bytes())
}

// clientIP prefers the address nginx forwards, but only when the direct peer
// is private/loopback (i.e. a proxy), so public clients cannot spoof it.
func clientIP(r *http.Request) string {
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		host = r.RemoteAddr
	}
	ip := net.ParseIP(host)
	if ip != nil && (ip.IsPrivate() || ip.IsLoopback() || ip.IsLinkLocalUnicast() || ip.IsUnspecified()) {
		fwd := r.Header.Get("X-Real-IP")
		if fwd == "" {
			fwd = strings.TrimSpace(strings.Split(r.Header.Get("X-Forwarded-For"), ",")[0])
		}
		if net.ParseIP(fwd) != nil {
			return fwd
		}
	}
	return host
}

// ---------- default email ----------

func defaultEmail(raw []byte, headers http.Header, meta Meta, query, form map[string]string, files []string) (string, string) {
	keys := make([]string, 0, len(headers))
	for k := range headers {
		keys = append(keys, k)
	}
	sort.Strings(keys)
	var hb strings.Builder
	for _, k := range keys {
		for _, v := range headers[k] {
			hb.WriteString(h(k) + ": " + h(v) + "\n")
		}
	}

	vars, _ := json.MarshalIndent(map[string]any{
		"GET Variables":  query,
		"POST Variables": form,
		"Uploaded Files": files,
	}, "", "    ")

	body := section("📋 Request Information", table(
		row("IP Address", h(meta.IP)),
		row("Method", h(meta.Method)),
		row("Content-Type", h(meta.ContentType)),
	)) +
		section("📨 Request Headers", `<pre class="data-box">`+hb.String()+`</pre>`) +
		section("📦 Request Body", `<pre class="data-box">`+h(string(raw))+`</pre>`) +
		section("🔧 Parsed Variables", `<pre class="data-box">`+h(string(vars))+`</pre>`)

	html := emailShell("⚡ Webhook Request Received", h(meta.Date), body,
		"This webhook was automatically forwarded to your email address.")
	return html, fmt.Sprintf("‼️ Webhook Request Received - %s", meta.Date)
}
