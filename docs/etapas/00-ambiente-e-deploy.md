# Etapa 0 — Ambiente local e pipeline de deploy

**Objetivo:** validar a hospedagem logo no início, antes de existir código de negócio.

## 0.1 Ambiente local (WSL, sem Docker)
- Instalar PHP 8.4 + Composer + Laravel installer via `php.new` (sem sudo):
  `/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.4)"`
- Extensões necessárias: `pdo_sqlite`, `mbstring`, `intl`, `gd`, `gmp`, `zip`.
- Node 24 (já instalado) para Vite/Tailwind.
- `sqlite3` CLI (opcional, para inspeção): `sudo apt install sqlite3`.

## 0.2 Projeto
- `laravel new financee` (sem starter kit) → Laravel 12, Pest, SQLite.
- Instalar: `livewire/livewire`, `laravel/fortify`, Tailwind 4 via `@tailwindcss/vite`, Alpine (vem com Livewire).
- Dev: `barryvdh/laravel-debugbar`, `larastan/larastan`, `laravel/pint`.
- `config/database.php` (sqlite): `journal_mode=WAL`, `busy_timeout=5000`, `synchronous=NORMAL`, `foreign_key_constraints=true`.
- `AppServiceProvider`: `Model::shouldBeStrict(!app()->isProduction())`, `Date::use(CarbonImmutable::class)`, locale `pt_BR`, timezone `America/Sao_Paulo`.
- Git: `main` + `.gitignore` padrão; criar repositório no GitHub.

## 0.3 Estrutura de pastas no servidor
```
/home/mv8f1v6j/                (home da conta Hostoo)
├─ financee-data/              ← FORA do deploy e fora da web
│   ├─ .env                   (criado uma vez via SSH)
│   ├─ database.sqlite
│   └─ storage/               (logs, cache, sessões, uploads)
└─ public_html/
    └─ financee/               ← integração Git (branch production); .htaccess bloqueia tudo
        └─ public/             ← document root de financee.jgmoreira.com.br
```
- O repositório fica em `~/public_html/financee` (a integração Git da Hostoo clona dentro do public_html). `bootstrap/app.php` usa `~/financee-data` (home do usuário) quando existe `~/financee-data/.env`; um `.htaccess` na raiz do repositório bloqueia o acesso web a tudo fora de `public/`.
- `DB_DATABASE` com caminho absoluto para `financee-data/database.sqlite`.
- Lições: o cPanel altera `public/.htaccess` e cria `public/.user.ini`/`php.ini`; o bloco dele está espelhado no repositório e os .ini são ignorados. Se o painel voltar a mexer, o deploy da Hostoo falha com "arquivos alterados manualmente" (rodar `git status` no servidor).

## 0.4 Build de release
`scripts/release.sh`:
1. Verifica se a árvore de trabalho está limpa e se os testes passam (`php artisan test`).
2. Cria um worktree temporário da branch `production`.
3. Copia o código de `main` (via `git archive`), roda `composer install --no-dev --optimize-autoloader` e `npm ci && npm run build`.
4. Grava o arquivo `RELEASE` com o hash do commit e a data.
5. Faz commit numa branch `release/<versão>` e abre uma **Pull Request para `production`** (via `gh`). O deploy só acontece quando a PR é aprovada e mergeada.

## 0.5 Pós-deploy sem SSH
- Comando `app:post-deploy`: compara `RELEASE` com o valor salvo em `storage/app/deployed_release`. Se forem diferentes, executa `migrate --force`, `optimize:clear`, `optimize` e `storage:link`, e grava a nova versão.
- Agendado em `routes/console.php` com `everyMinute()->withoutOverlapping()`.
- Cron na Hostoo: `* * * * * /usr/local/bin/ea-php84 ~/public_html/financee/artisan schedule:run >> /dev/null 2>&1`. O alias `php` do shell não vale no cron, por isso o caminho é completo.
- Como há SSH e Composer 2.10 no servidor, os mesmos comandos (`composer install --no-dev`, `migrate --force`, `optimize`) também podem ser rodados manualmente em caso de problema.
- Fila: `queue:work --stop-when-empty --max-time=50` agendado a cada minuto, porque não há supervisor no compartilhado.

## 0.6 Validação
- Rota `/up` (health check do Laravel) e uma página "Hello Financee".
- HTTPS ativo (Let's Encrypt pelo painel).
- Checklist (13/11/2026): [x] PHP 8.4 web e CLI  [x] extensões  [x] deploy por commit  [x] cron cadastrado  [x] migrations aplicadas  [x] dados fora do deploy e da web (403 confirmado)  [x] deploy automático (commit → Hostoo → cron aplica release)

## Critério de pronto
Um commit em `main` → `scripts/release.sh` → em até ~2 minutos a nova versão está no ar, com as migrations aplicadas, sem nenhuma ação manual.
