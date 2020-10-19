#!/usr/bin/env sh

# let AstLinux system-notify handle notifications

astlinux_send() {
  _subject="$1"
  _content="$2"
  _statusCode="$3" #0: success, 1: error 2($RENEW_SKIP): skipped
  _debug "_subject" "$_subject"
  _debug "_content" "$_content"
  _debug "_statusCode" "$_statusCode"

  system-notify "$_subject" "$_content"
  return $?
}
