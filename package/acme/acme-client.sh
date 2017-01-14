#!/usr/bin/env bash

. /etc/rc.conf

ACME_REPO="/stat/etc/acme"

ACME_PROG="$ACME_REPO/acme.sh"

ACME_WORKING_DIR="/mnt/kd/acme"

ACME_OPTS="--home $ACME_WORKING_DIR --useragent AstLinux"

LOCKFILE="/var/lock/acme-client.lock"

if [ ! -x "$ACME_PROG" ]; then
  echo "acme-client: executable file \"$ACME_PROG\" not found." >&2
  exit 1
fi

if [ ! -d "$ACME_WORKING_DIR" ]; then
  mkdir "$ACME_WORKING_DIR"
fi

if ! cd "$ACME_WORKING_DIR"; then
  exit 1
fi

# Robust 'bash' method of creating/testing for a lockfile
if ! ( set -o noclobber; echo "$$" > "$LOCKFILE" ) 2>/dev/null; then
  echo "acme-client: already running, lockfile \"$LOCKFILE\" exists, process id: $(cat "$LOCKFILE")." >&2
  exit 9
fi

add_account_opts()
{
  local file="$1" line opt value IFS

  IFS=$'\n'
  for line in $(cat "$file" | sed -e 's/#.*//' -e 's/ *$//' -e '/^$/d'); do
    opt="$(echo "$line" | awk -F' ' '/^[a-z]/ { print $1; }')"
    value="$(echo "$line" | awk -F' ' '/^[a-z]/ { print $2; }')"
    if [ -n "$opt" ]; then
      if [ -n "$value" ]; then
        ACME_OPTS="$ACME_OPTS --$opt $value"
      else
        ACME_OPTS="$ACME_OPTS --$opt"
      fi
    fi
  done
}

accountemail="$ACME_ACCOUNT_EMAIL"

# Extract from possible <a@b.tld> format
accountemail="${accountemail##*<}"
accountemail="${accountemail%%>*}"

if [ -n "$accountemail" ]; then
  ACME_OPTS="$ACME_OPTS --accountemail $accountemail"
fi

if [ -f "$ACME_WORKING_DIR/account.opts" ]; then
  add_account_opts "$ACME_WORKING_DIR/account.opts"
fi

trap 'rm -f "$LOCKFILE"; exit $?' INT TERM EXIT

$ACME_PROG $ACME_OPTS "$@"
rtn=$?

rm -f "$LOCKFILE"
trap - INT TERM EXIT

exit $rtn
