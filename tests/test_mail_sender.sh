#!/usr/bin/env bash

set -euo pipefail

project_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)
test_dir=$(mktemp -d)
trap 'rm -rf "$test_dir"' EXIT

sendmail_args="$test_dir/sendmail.args"
sendmail_message="$test_dir/sendmail.message"
fake_sendmail="$test_dir/sendmail"
prepend_file="$test_dir/prepend.php"

cat > "$fake_sendmail" <<'FAKE_SENDMAIL'
#!/usr/bin/env bash
printf '%s\n' "$@" > "$MAIL_TEST_ARGS"
cat > "$MAIL_TEST_MESSAGE"
FAKE_SENDMAIL
chmod +x "$fake_sendmail"

cat > "$prepend_file" <<'PHP'
<?php
if (!function_exists('getallheaders')) {
    function getallheaders(): array
    {
        return [];
    }
}
PHP

MAIL_TEST_ARGS="$sendmail_args" \
MAIL_TEST_MESSAGE="$sendmail_message" \
REQUEST_URI='/recipient%40example.com' \
REQUEST_METHOD='POST' \
REMOTE_ADDR='192.0.2.1' \
CONTENT_TYPE='application/json' \
php \
    -d "auto_prepend_file=$prepend_file" \
    -d "sendmail_path=$fake_sendmail -t -i" \
    "$project_dir/index.php" >/dev/null

if ! grep -Fxq -- '-fno-reply@dixon.cx' "$sendmail_args"; then
    echo 'Expected the envelope sender to be no-reply@dixon.cx.' >&2
    echo 'Actual sendmail arguments:' >&2
    sed 's/^/  /' "$sendmail_args" >&2
    exit 1
fi

if ! grep -Fq $'From: Webhook Call <no-reply@dixon.cx>\r' "$sendmail_message"; then
    echo 'Expected the From header to be Webhook Call <no-reply@dixon.cx>.' >&2
    exit 1
fi

echo 'Mail sender test passed.'
