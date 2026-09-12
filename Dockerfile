FROM golang:1.26-alpine AS build
WORKDIR /src
COPY go.mod ./
COPY *.go docs.html ./
RUN CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /webhooks .

FROM gcr.io/distroless/static-debian12:nonroot
COPY --from=build /webhooks /webhooks
ENV LISTEN=:8080 SMTP_ADDR=172.17.0.1:25 MAIL_FROM=no-reply@dixon.cx PUBLIC_HOST=webhooks.dixon.cx
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s CMD ["/webhooks", "-check"]
ENTRYPOINT ["/webhooks"]
