package main

import (
	"fmt"
	"mime"
	"mime/quotedprintable"
	"net/smtp"
	"os"
	"time"
)

var (
	smtpAddr = envOr("SMTP_ADDR", "127.0.0.1:25")
	mailFrom = envOr("MAIL_FROM", "no-reply@dixon.cx")
)

// sendMail is a variable so tests can capture messages instead of sending them.
var sendMail = smtpSend

func smtpSend(to, subject, htmlBody string) error {
	c, err := smtp.Dial(smtpAddr)
	if err != nil {
		return err
	}
	defer c.Close()
	if err := c.Mail(mailFrom); err != nil {
		return err
	}
	if err := c.Rcpt(to); err != nil {
		return err
	}
	w, err := c.Data()
	if err != nil {
		return err
	}
	if _, err := w.Write(buildMessage(to, subject, htmlBody)); err != nil {
		return err
	}
	if err := w.Close(); err != nil {
		return err
	}
	return c.Quit()
}

func buildMessage(to, subject, htmlBody string) []byte {
	host, _ := os.Hostname()
	var b []byte
	b = fmt.Appendf(b, "From: Webhook Call <%s>\r\n", mailFrom)
	b = fmt.Appendf(b, "To: <%s>\r\n", to)
	b = fmt.Appendf(b, "Subject: %s\r\n", mime.QEncoding.Encode("utf-8", subject))
	b = fmt.Appendf(b, "Date: %s\r\n", time.Now().Format(time.RFC1123Z))
	b = fmt.Appendf(b, "Message-ID: <%d.%d@%s>\r\n", time.Now().UnixNano(), os.Getpid(), host)
	b = append(b, "MIME-Version: 1.0\r\n"...)
	b = append(b, "Content-Type: text/html; charset=UTF-8\r\n"...)
	b = append(b, "Content-Transfer-Encoding: quoted-printable\r\n\r\n"...)
	qp := quotedprintable.NewWriter(sliceWriter{&b})
	_, _ = qp.Write([]byte(htmlBody))
	_ = qp.Close()
	return b
}

type sliceWriter struct{ b *[]byte }

func (s sliceWriter) Write(p []byte) (int, error) {
	*s.b = append(*s.b, p...)
	return len(p), nil
}
