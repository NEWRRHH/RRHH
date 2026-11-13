#!/bin/sh
# Production .env check (service "env-check" in docker-compose.prod.yml).
#
# - Secrets that can be generated (APP_KEY, DB passwords, Reverb key/secret):
#   if missing, a fresh secure value is printed ready to paste into .env.
# - Values only the owner knows (tunnel token, public URL): explained.
# Exits 1 while anything is missing so no other service starts.
# Existing secrets are never printed.

missing=0
generated=""
manual=""
warnings=""

value_of() { eval "printf '%s' \"\${$1:-}\""; }
is_unset() { v=$(value_of "$1"); [ -z "$v" ] || [ "$v" = "CHANGE_ME" ]; }
rand_hex() { head -c "$1" /dev/urandom | od -An -tx1 | tr -d ' \n'; }
rand_b64() { head -c "$1" /dev/urandom | base64 | tr -d '\n'; }

gen() { # gen NAME VALUE
  if is_unset "$1"; then
    missing=1
    generated="${generated}$1=$2
"
  fi
}

ask() { # ask NAME "how to get it"
  if is_unset "$1"; then
    missing=1
    manual="${manual}  - $1
      $2
"
  fi
}

warn() { warnings="${warnings}  ! $1
"; }

# --- generated secrets ---------------------------------------------------------
gen APP_KEY            "base64:$(rand_b64 32)"
gen DB_PASSWORD        "$(rand_hex 24)"
gen DB_ROOT_PASSWORD   "$(rand_hex 24)"
gen REVERB_APP_KEY     "$(rand_hex 16)"
gen REVERB_APP_SECRET  "$(rand_hex 32)"

# --- values only you know --------------------------------------------------------
case ",${COMPOSE_PROFILES:-}," in
  *,tunnel-domain,*)
    ask CLOUDFLARE_TUNNEL_TOKEN "Cloudflare Zero Trust > Networks > Tunnels > tu tunel > copiar el token (eyJ...). Public hostname -> http://gateway:80"
    ask PUBLIC_HOSTS "Tu subdominio, p.ej. rrhh.nanodev.com"
    ask APP_URL "URL publica, p.ej. https://rrhh.nanodev.com"
    ;;
  *,tunnel,*) ;;
  *) warn "COMPOSE_PROFILES no incluye 'tunnel' ni 'tunnel-domain': la app no sera accesible desde internet." ;;
esac

# --- sanity warnings (do not block) ----------------------------------------------
[ "${APP_ENV:-}" = "production" ] || warn "APP_ENV='${APP_ENV:-}' (deberia ser production)"
[ "${APP_DEBUG:-false}" = "false" ] || warn "APP_DEBUG=${APP_DEBUG} expone trazas de error: ponlo en false"
[ "${NUXT_COOKIE_SECURE:-}" = "true" ] || warn "NUXT_COOKIE_SECURE deberia ser true (se sirve por HTTPS)"

line="=================================================================="

if [ "$missing" -eq 1 ]; then
  echo "$line"
  echo " RRHH - el .env de produccion esta incompleto"
  echo "$line"
  if [ -n "$generated" ]; then
    echo ""
    echo "Copia estas lineas en el .env (valores aleatorios generados ahora):"
    echo ""
    printf '%s' "$generated"
    echo ""
    echo "IMPORTANTE: DB_PASSWORD y DB_ROOT_PASSWORD deben estar puestas antes"
    echo "del primer arranque: MySQL las guarda en el volumen al crearlo."
  fi
  if [ -n "$manual" ]; then
    echo ""
    echo "Completa a mano:"
    printf '%s' "$manual"
  fi
  if [ -n "$warnings" ]; then
    echo ""
    echo "Avisos:"
    printf '%s' "$warnings"
  fi
  echo ""
  echo "Despues vuelve a ejecutar: docker compose up -d --build"
  echo "$line"
  exit 1
fi

echo "$line"
echo " RRHH - .env de produccion OK"
echo "$line"
for v in APP_KEY DB_PASSWORD DB_ROOT_PASSWORD REVERB_APP_KEY REVERB_APP_SECRET; do
  echo "  ok  $v"
done
[ -n "${APP_URL:-}" ] && echo "  url $APP_URL"
if [ -n "$warnings" ]; then
  echo ""
  echo "Avisos:"
  printf '%s' "$warnings"
fi
exit 0
