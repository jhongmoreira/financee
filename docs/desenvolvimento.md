# Financee — Desenvolvimento em outro computador

Guia para configurar o projeto do zero (testado clonando o repositório numa pasta limpa).

## 1. O que instalar

| Ferramenta | Versão | Para quê |
|---|---|---|
| Git | qualquer recente | código |
| PHP | **8.4** com as extensões `pdo_sqlite`, `sqlite3`, `mbstring`, `intl`, `gd`, `gmp` (ou `bcmath`), `zip`, `curl`, `openssl`, `fileinfo`, `dom` | Laravel |
| Composer | 2.x | dependências PHP |
| Node.js | 24 (com npm) | Vite/Tailwind, gráficos, scripts de ícones |
| GitHub CLI (`gh`) | 2.x | `scripts/release.sh` abre e mergeia a PR do deploy |

**Linux / WSL / macOS** — PHP + Composer sem `sudo` e sem Docker:
```bash
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.4)"   # macOS: .../install/mac/8.4
source ~/.bashrc    # ou abra um terminal novo (instala em ~/.config/herd-lite/bin)
php -v && composer --version
```
**Windows** — recomendado usar o **WSL** (Ubuntu) e seguir o passo acima; o `release.sh` é um script bash.

Node 24: https://nodejs.org (ou `nvm install 24`). GitHub CLI: https://cli.github.com.

## 2. Clonar e configurar

```bash
git clone https://github.com/jhongmoreira/financee.git
cd financee
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed      # cria o usuário de demonstração
php artisan storage:link        # fotos de perfil
```

Usuário de demonstração (só local, ~6 meses de dados): **demo@financee.test / demo12345**.
Para recriar os dados de demonstração a qualquer momento: `php artisan db:seed`.

## 3. Rodar

```bash
composer run dev    # servidor (http://localhost:8000) + Vite + fila + logs, tudo junto
```
Ou separado: `php artisan serve` e `npm run dev` em dois terminais.

O service worker (PWA) só é registrado em produção, para não atrapalhar o desenvolvimento.

## 4. Qualidade (obrigatório antes de publicar)

```bash
php artisan test                                        # Pest (~185 testes)
./vendor/bin/phpstan analyse --memory-limit=1G           # Larastan nível 6
./vendor/bin/pint                                        # formatação
```

## 5. Publicar em produção

```bash
gh auth login        # uma vez por computador (conta com acesso ao repositório)
git push origin main
scripts/release.sh   # testes + Larastan + build + PR para "production" + merge automático
```
- O script recusa rodar com alterações não commitadas ou fora da branch `main`.
- A Hostoo puxa a branch `production` em ~15 s; o cron aplica migrations e caches em até 1 minuto.
- `scripts/release.sh --no-merge` deixa a PR aberta para revisar antes.

## 6. Servidor (Hostoo, cPanel + LiteSpeed)

Acesso por SSH com o usuário da conta (dados no painel da Hostoo; nada de senha no repositório).

| O quê | Onde |
|---|---|
| Código (integração Git, branch `production`) | `~/public_html/financee` — **não editar arquivos ali** |
| `.env`, banco SQLite, storage, logs | `~/financee-data/` |
| Logs da aplicação | `~/financee-data/storage/logs/laravel-AAAA-MM-DD.log` |
| PHP do terminal/cron | `/usr/local/bin/ea-php84` |

Comandos úteis por SSH:
```bash
cd ~/public_html/financee
/usr/local/bin/ea-php84 artisan app:post-deploy --force   # reaplica migrations + caches
/usr/local/bin/ea-php84 artisan optimize                  # após editar o ~/financee-data/.env
git status                                                # se o deploy falhar ("arquivos alterados")
tail -50 ~/financee-data/storage/logs/laravel-*.log
```
Mais detalhes de operação (cron, SMTP, cuidados com o cPanel): [go-live.md](go-live.md).

## 7. Scripts auxiliares

| Script | O que faz |
|---|---|
| `node scripts/icons.mjs` | gera os ícones do PWA a partir de `resources/icons/*.svg` |
| `node scripts/splash.mjs` | gera as telas de abertura do iPhone/iPad e `components/layouts/splash.blade.php` |
| `scripts/release.sh` | publica (ver seção 5) |

## 8. Mapa rápido do código

| Pasta | Conteúdo |
|---|---|
| `app/Livewire/` | telas (Início, Lançamentos, Cartões/Fatura, Planejamento, Relatórios, Investimentos...) e o formulário único `EntryForm` |
| `app/Services/` | regras de negócio: saldos, faturas, fixos, previsão, orçamentos, relatórios, notificações |
| `app/Models/` | modelos; `Concerns/BelongsToUser` isola os dados por usuário |
| `resources/views/components/ui/` | componentes visuais (bottom sheet, campo de valor, gráfico...) |
| `routes/console.php` | rotinas agendadas (post-deploy, fila, fixos, avisos, manutenção do SQLite) |
| `tests/Feature/` | testes Pest |
| `docs/` | planos por etapa, operação e este guia |
