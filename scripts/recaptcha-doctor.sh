#!/usr/bin/env bash
#
# reCAPTCHA deployment doctor.
#
# Run from the project root on the server:
#   bash scripts/recaptcha-doctor.sh
#   bash scripts/recaptcha-doctor.sh https://www.accumenai.com/login
#
# Checks: deployed code, .env keys, caches, runtime config, live page markup,
# and whether Google accepts the secret key. Never prints the secret itself.

set -u

RED=$'\033[31m'; GRN=$'\033[32m'; YEL=$'\033[33m'; RST=$'\033[0m'
FAILS=0
ok()   { printf '%s  [OK]%s   %s\n'   "$GRN" "$RST" "$*"; }
bad()  { printf '%s  [FAIL]%s %s\n'   "$RED" "$RST" "$*"; FAILS=$((FAILS + 1)); }
warn() { printf '%s  [WARN]%s %s\n'   "$YEL" "$RST" "$*"; }
has()  { command -v "$1" >/dev/null 2>&1; }

[ -f artisan ] || { echo "Run me from the project root (artisan not found)."; exit 1; }

echo "== 1. Deployed code =="
if git rev-parse --git-dir >/dev/null 2>&1; then
  echo "   HEAD: $(git log --oneline -1)"
  TREE=$(git ls-tree -r HEAD --name-only 2>/dev/null || true)
  case "$TREE" in
    *app/Services/Auth/RecaptchaService.php*) ok "captcha code is committed" ;;
    *) bad "captcha code not in HEAD - git pull first" ;;
  esac
  DIRTY=$(git status --porcelain -- app/Http/Controllers/Auth resources/views/auth config/services.php app/Http/Middleware/SecurityHeaders.php)
  [ -z "$DIRTY" ] && ok "no local edits over captcha files" || warn "local edits present: $DIRTY"
else
  warn "not a git checkout (uploaded by FTP?) - skipping commit check"
fi

echo
echo "== 2. Code markers =="
for f in \
  app/Services/Auth/RecaptchaService.php \
  resources/views/auth/partials/recaptcha.blade.php \
  resources/views/auth/partials/recaptcha-script.blade.php
do
  [ -f "$f" ] && ok "$f" || bad "$f missing"
done

grep -q 'RecaptchaService::class)->assertValid' app/Http/Controllers/Auth/UserLoginController.php \
  && ok "login controller enforces captcha" \
  || bad "UserLoginController not patched (captcha not enforced)"

grep -q "auth.partials.recaptcha'" resources/views/auth/login.blade.php \
  && ok "login view includes captcha partials" \
  || bad "login.blade.php missing @include('auth.partials.recaptcha')"

grep -q 'www.google.com' app/Http/Middleware/SecurityHeaders.php \
  && ok "CSP allows google domains" \
  || bad "CSP not updated - widget script will be blocked"

grep -q "'recaptcha'" config/services.php \
  && ok "config/services.php has recaptcha block" \
  || bad "recaptcha config missing"

echo
echo "== 3. .env keys =="
if [ -f .env ]; then
  ok ".env found"
  SITE=$(grep -E '^RECAPTCHA_SITE_KEY=' .env | head -1 | cut -d= -f2- | tr -d '"')
  SEC=$(grep -E '^RECAPTCHA_SECRET_KEY=' .env | head -1 | cut -d= -f2- | tr -d '"')
  VER=$(grep -E '^RECAPTCHA_VERSION=' .env | head -1 | cut -d= -f2- | tr -d '"')
  [ -n "$SITE" ] && ok "RECAPTCHA_SITE_KEY set (${SITE:0:8}…)" || bad "RECAPTCHA_SITE_KEY empty - captcha is disabled"
  [ -n "$SEC" ]  && ok "RECAPTCHA_SECRET_KEY set (${SEC:0:8}…)"  || bad "RECAPTCHA_SECRET_KEY empty - captcha is disabled"
  [ -n "$VER" ]  && ok "RECAPTCHA_VERSION=$VER" || warn "RECAPTCHA_VERSION unset (defaults to 3, invisible)"
else
  bad ".env missing - create it (it is never committed)"
  SITE=""; SEC=""; VER=""
fi

echo
echo "== 4. Caches =="
if has php; then
  php artisan config:clear >/dev/null 2>&1 && ok "config cache cleared" || warn "config:clear failed"
  php artisan view:clear    >/dev/null 2>&1 && ok "view cache cleared"    || warn "view:clear failed"
else
  bad "php not found in PATH"
fi

echo
echo "== 5. Runtime state =="
if has php; then
  OUT=$(php artisan tinker --execute='echo \App\Services\Auth\RecaptchaService::enabled() ? "enabled" : "disabled";' 2>/dev/null | tail -1)
  [ "$OUT" = "enabled" ] && ok "captcha enabled at runtime" \
                         || bad "captcha disabled at runtime (keys not loaded / config cached)"
else
  warn "skipping - php not available"
fi

echo
echo "== 6. Live page markup =="
URL=${1:-https://www.accumenai.com/login}
if has curl; then
  HTML=$(curl -sk --max-time 20 -A "Mozilla/5.0" "$URL")
  case "$HTML" in
    *[Oo]ne\ moment*)
      warn "$URL is serving a maintenance page - nothing to verify yet" ;;
    *)
      case "$HTML" in
        *g-recaptcha-response*)   ok "page has the hidden token field" ;;
        *)                        bad "page missing captcha markup - deploy/caches stale" ;;
      esac
      case "$HTML" in
        *recaptcha/api.js?render=*) ok "v3 script tag present" ;;
        *)                          bad "v3 script tag missing" ;;
      esac
      ;;
  esac
else
  warn "curl not available - open $URL and view-source, search for g-recaptcha-response"
fi

echo
echo "== 7. Google accepts the secret =="
if has curl && [ -n "${SEC:-}" ]; then
  RESP=$(curl -sk --max-time 15 \
    --data-urlencode "secret=$SEC" \
    --data-urlencode "response=dummy-token" \
    https://www.google.com/recaptcha/api/siteverify)
  case "$RESP" in
    *invalid-input-secret*)  bad "Google rejects this secret key - regenerate keys" ;;
    *invalid-input-response*) ok "secret key valid (token rejected as expected)" ;;
    *invalid-input-key*)     bad "Google rejects this key - wrong product/type" ;;
    *) warn "unexpected response: $RESP" ;;
  esac
else
  warn "skipping - curl unavailable or secret empty"
fi

echo
if [ "$FAILS" -eq 0 ]; then
  printf '%sAll checks passed.%s Captcha is active (invisible on v3 - users see nothing).\n' "$GRN" "$RST"
else
  printf '%s%d check(s) failed.%s Fix the [FAIL] lines above, then re-run.\n' "$RED" "$FAILS" "$RST"
  exit 1
fi
