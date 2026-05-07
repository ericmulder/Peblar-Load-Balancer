#!/usr/bin/env bash
#
# Voorbeeld-deploy script voor self-host op een eigen Linux server (Pi, NUC, VPS).
# Configureer via .env.deploy (kopieer .env.deploy.example).
#
# Usage:
#   cp .env.deploy.example .env.deploy
#   $EDITOR .env.deploy
#   ./deploy.sh           # deploy vanaf main, vereist git tag + CHANGELOG entry
#   ./deploy.sh --force   # skip guards (niet aanbevolen)
#
set -euo pipefail

FORCE=0
for arg in "$@"; do
    [[ "$arg" == "--force" ]] && FORCE=1
done

# Laad deploy-config
if [[ ! -f .env.deploy ]]; then
    echo "ERROR: .env.deploy ontbreekt. Kopieer .env.deploy.example en pas aan." >&2
    exit 1
fi
# shellcheck disable=SC1091
set -a; source .env.deploy; set +a

: "${DEPLOY_SSH:?DEPLOY_SSH niet gezet in .env.deploy}"
: "${DEPLOY_DIR:?DEPLOY_DIR niet gezet in .env.deploy}"
: "${DEPLOY_URL:?DEPLOY_URL niet gezet in .env.deploy}"
DOCKER_COMPOSE_FILE="${DOCKER_COMPOSE_FILE:-docker-compose.yml}"

# Guard: alleen vanaf main
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [[ "$CURRENT_BRANCH" != "main" ]]; then
    echo "ERROR: huidige branch is '$CURRENT_BRANCH', niet 'main'." >&2
    [[ "$FORCE" -eq 1 ]] || exit 1
    echo "WARNING: deploy van non-main branch via --force." >&2
fi

# Guard: laatste tag moet in CHANGELOG staan
LATEST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "")
if [[ -z "$LATEST_TAG" ]]; then
    echo "ERROR: geen git tag gevonden. Tag eerst: git tag -a vX.Y.Z -m 'Release vX.Y.Z'" >&2
    [[ "$FORCE" -eq 1 ]] || exit 1
else
    VERSION="${LATEST_TAG#v}"
    if ! grep -q "## \[${VERSION}\]" CHANGELOG.md 2>/dev/null; then
        echo "ERROR: CHANGELOG.md heeft geen entry voor versie ${VERSION} (tag ${LATEST_TAG})." >&2
        [[ "$FORCE" -eq 1 ]] || exit 1
    fi
fi

echo "==> Sanity check op server (data dir + port-conflict)..."
ssh "$DEPLOY_SSH" bash <<EOF
set -e
cd $DEPLOY_DIR

# Waarschuw als data-dir leeg is maar oude named volumes nog bestaan (data niet kwijt, alleen niet gemount)
if [[ -d data && -z "\$(ls -A data 2>/dev/null)" ]]; then
    if docker volume ls --format '{{.Name}}' | grep -qE '^peblar_(peblar_)?db$'; then
        echo "WARNING: data/ is leeg maar oude named volumes (peblar_db) bestaan — data niet kwijt."
        echo "         Restore via: docker run --rm -v peblar_peblar_db:/o -v \$PWD/data:/n alpine cp /o/database.sqlite /n/"
        exit 1
    fi
fi

# Check of een ander compose project port vasthoudt (orphan van eerdere setup)
PORT=\$(echo "$DEPLOY_URL" | sed -E 's|.*:([0-9]+).*|\1|')
CONFLICT=\$(docker ps --format '{{.Names}} {{.Ports}}' | grep -v '^peblar ' | grep -E ":\${PORT}->" || true)
if [[ -n "\$CONFLICT" ]]; then
    echo "ERROR: andere container houdt port \${PORT} vast: \$CONFLICT" >&2
    echo "       Stop handmatig (docker stop <naam>) voor je opnieuw deployt." >&2
    exit 1
fi
EOF

echo "==> Code synchroniseren naar ${DEPLOY_SSH}:${DEPLOY_DIR}..."
rsync -az \
  --exclude='.git' \
  --exclude='.claude' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='bootstrap/cache/*' \
  --exclude='public/build' \
  --exclude='*.tar.gz' \
  --exclude='.env' \
  --exclude='.env.deploy' \
  . "${DEPLOY_SSH}:${DEPLOY_DIR}/"

# .env aanmaken als hij nog niet bestaat op de server
if ssh "$DEPLOY_SSH" "[ ! -f ${DEPLOY_DIR}/.env ]"; then
    if [[ -f .env ]]; then
        echo "==> .env naar server kopieren..."
        scp .env "${DEPLOY_SSH}:${DEPLOY_DIR}/.env"
    else
        echo "==> Geen lokale .env — .env.example wordt gekopieerd. Edit op server!"
        scp .env.example "${DEPLOY_SSH}:${DEPLOY_DIR}/.env"
    fi
else
    echo "==> .env bestaat al op server, overslaan."
fi

echo "==> Building image + recreate op server..."
ssh "$DEPLOY_SSH" "cd ${DEPLOY_DIR} && APP_VERSION=${LATEST_TAG} docker compose -f ${DOCKER_COMPOSE_FILE} build && docker compose -f ${DOCKER_COMPOSE_FILE} down --remove-orphans && APP_VERSION=${LATEST_TAG} docker compose -f ${DOCKER_COMPOSE_FILE} up -d --force-recreate --remove-orphans"

echo "==> Wachten op startup..."
sleep 5
HTTP_CODE=$(curl -s -o /dev/null -w '%{http_code}' "${DEPLOY_URL}")
echo "HTTP status: $HTTP_CODE"
if [[ "$HTTP_CODE" == "200" || "$HTTP_CODE" == "302" ]]; then
    echo "==> App is UP op ${DEPLOY_URL}"
else
    echo "ERROR: onverwachte HTTP status $HTTP_CODE — check serverlogs." >&2
    exit 1
fi

# Verifieer dat juiste versie draait
DEPLOYED_VERSION=$(ssh "$DEPLOY_SSH" "curl -s http://localhost:8000/api/version | jq -r .version" 2>/dev/null || echo "")
if [[ -n "$LATEST_TAG" && -n "$DEPLOYED_VERSION" && "$DEPLOYED_VERSION" != "$LATEST_TAG" ]]; then
    echo "ERROR: verwacht ${LATEST_TAG} maar server draait ${DEPLOYED_VERSION}" >&2
    exit 1
fi
echo "OK: versie ${DEPLOYED_VERSION:-$LATEST_TAG} live"
