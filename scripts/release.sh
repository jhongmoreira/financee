#!/usr/bin/env bash
#
# Gera um build de produção a partir da branch main e abre uma PULL REQUEST
# para a branch "production" (acompanhada pela integração Git da Hostoo).
# Nada é enviado direto para production: o deploy só acontece quando a PR
# é aprovada e mergeada no GitHub.
# A branch production contém vendor/ e public/build/ prontos, porque a
# Hostoo apenas copia os arquivos (não roda composer nem npm).
#
# Uso: scripts/release.sh            (testes + build + PR para production)
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

# 2. Worktree a partir da production publicada ------------------------------
BUILD_DIR="$(mktemp -d)"
BRANCH="release/${RELEASE}"
trap 'git worktree remove --force "$BUILD_DIR" 2>/dev/null || rm -rf "$BUILD_DIR"; git branch -D "$BRANCH" >/dev/null 2>&1 || true' EXIT

git fetch -q origin production main
git worktree add -q --detach "$BUILD_DIR" origin/production
git -C "$BUILD_DIR" checkout -q -b "$BRANCH"

# Commit de main incluído no último release (para o resumo da PR)
PREV_COMMIT="$(git log -1 --grep='main@' --format=%B origin/production | grep -o 'main@[0-9a-f]*' | head -1 | cut -d@ -f2 || true)"

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
/public/.user.ini
/public/php.ini
EOF

# 4. Commit e push ------------------------------------------------------------
git -C "$BUILD_DIR" add -A
if git -C "$BUILD_DIR" diff --cached --quiet; then
    info "Nada mudou desde o último release."
    exit 0
fi
git -C "$BUILD_DIR" commit -q -m "Release ${RELEASE} (main@${COMMIT})"

if [[ $PUSH -eq 0 ]]; then
    info "Commit local criado em ${BRANCH} (sem push)."
    trap 'git worktree remove --force "$BUILD_DIR" 2>/dev/null || true' EXIT
    exit 0
fi

info "Enviando ${BRANCH} e abrindo PR para production"
git -C "$BUILD_DIR" push -q -u origin "$BRANCH"

REPO_URL="$(gh repo view --json url -q .url)"
if [[ -n "$PREV_COMMIT" ]]; then
    CHANGES="$(git log --format='- %h %s' "${PREV_COMMIT}..${COMMIT}")"
    COMPARE="${REPO_URL}/compare/${PREV_COMMIT}...${COMMIT}"
else
    CHANGES="$(git log -10 --format='- %h %s' "$COMMIT")"
    COMPARE="${REPO_URL}/commits/${COMMIT}"
fi

BODY="$(cat <<MD
## Release ${RELEASE}

Build de produção gerado a partir de \`main@${COMMIT}\` (testes passando).

### Alterações desde o último release
${CHANGES:-"- (sem novos commits de código)"}

**Diff do código-fonte (legível):** ${COMPARE}

> O diff desta PR inclui \`vendor/\` e \`public/build/\`; revise o código pelo link acima.

### Ao mergear
A Hostoo atualiza o servidor e o cron aplica migrations e caches em até 1 minuto.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
MD
)"

PR_URL="$(gh pr create --base production --head "$BRANCH" --title "Release ${RELEASE} (main@${COMMIT})" --body "$BODY")"
info "PR aberta: ${PR_URL}"
info "O deploy acontece quando a PR for aprovada e mergeada."
