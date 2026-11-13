#!/usr/bin/env bash
#
# Gera um build de produção a partir da branch main e publica na branch
# "production", que é a branch acompanhada pela integração Git da Hostoo.
# A branch production contém vendor/ e public/build/ prontos, porque a
# Hostoo apenas copia os arquivos (não roda composer nem npm).
#
# Uso: scripts/release.sh            (testes + build + push)
#      scripts/release.sh --no-push  (só gera o commit local, para conferir)
#
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

PUSH=1
[[ "${1:-}" == "--no-push" ]] && PUSH=0

info() { printf '\033[1;34m▶ %s\033[0m\n' "$*"; }
fail() { printf '\033[1;31m✖ %s\033[0m\n' "$*" >&2; exit 1; }

# 1. Pré-condições ---------------------------------------------------------
[[ "$(git branch --show-current)" == "main" ]] || fail "Rode a partir da branch main."
[[ -z "$(git status --porcelain)" ]] || fail "Há alterações não commitadas."

COMMIT="$(git rev-parse --short HEAD)"
RELEASE="$(date +%Y%m%d%H%M%S)-${COMMIT}"

info "Rodando testes"
php artisan test --compact || fail "Testes falharam. Release cancelado."

info "Compilando assets (Vite)"
npm ci --no-audit --no-fund --silent
npm run build >/dev/null

# 2. Worktree da branch production -----------------------------------------
BUILD_DIR="$(mktemp -d)"
trap 'git worktree remove --force "$BUILD_DIR" 2>/dev/null || rm -rf "$BUILD_DIR"' EXIT

git fetch origin production 2>/dev/null || true
if git show-ref --verify --quiet refs/heads/production; then
    git worktree add -f "$BUILD_DIR" production >/dev/null
elif git show-ref --verify --quiet refs/remotes/origin/production; then
    git worktree add -f -b production "$BUILD_DIR" origin/production >/dev/null
else
    git worktree add -f --detach "$BUILD_DIR" >/dev/null
    git -C "$BUILD_DIR" checkout --orphan production >/dev/null
fi

# Limpa tudo (menos .git) e extrai o código commitado de main
find "$BUILD_DIR" -mindepth 1 -maxdepth 1 ! -name .git -exec rm -rf {} +
git archive HEAD | tar -x -C "$BUILD_DIR"

# 3. Monta o build ------------------------------------------------------------
info "Instalando dependências PHP de produção"
composer install --working-dir="$BUILD_DIR" --no-dev --optimize-autoloader \
    --no-interaction --no-progress --quiet

cp -r public/build "$BUILD_DIR/public/build"
rm -rf "$BUILD_DIR/tests" "$BUILD_DIR/docs" "$BUILD_DIR/scripts" \
       "$BUILD_DIR/node_modules" "$BUILD_DIR/public/hot"
echo "$RELEASE" > "$BUILD_DIR/RELEASE"

# Na branch production, vendor/ e public/build/ PRECISAM ser versionados.
cat > "$BUILD_DIR/.gitignore" <<'EOF'
.env
.env.*
!.env.example
/node_modules
/public/hot
/public/storage
/storage/*.key
/bootstrap/cache/*.php
EOF

# 4. Commit e push ------------------------------------------------------------
git -C "$BUILD_DIR" add -A
if git -C "$BUILD_DIR" diff --cached --quiet; then
    info "Nada mudou desde o último release."
    exit 0
fi
git -C "$BUILD_DIR" commit -q -m "Release ${RELEASE} (main@${COMMIT})"

if [[ $PUSH -eq 1 ]]; then
    info "Enviando branch production"
    git -C "$BUILD_DIR" push -q origin production
    info "Release ${RELEASE} publicado. O cron aplica as migrations em até 1 minuto."
else
    info "Commit local criado na branch production (sem push)."
fi
