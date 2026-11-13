# Financee

PWA de finanças pessoais (Laravel 12 + Livewire + Tailwind + SQLite), mobile-first.

**Recursos:** contas e saldos, lançamentos (despesa, receita, transferência), cartões de crédito com faturas,
parcelamento, estornos e pagamentos parciais, lançamentos fixos e parcelados em conta, previsão de saldo,
orçamentos por categoria, investimentos (saldo separado), relatórios com gráficos, exportação CSV/PDF/JSON,
notificações push, tema escuro e app instalável.

- **Configurar em outro computador:** [`docs/desenvolvimento.md`](docs/desenvolvimento.md)
- Operação em produção: [`docs/go-live.md`](docs/go-live.md)
- Planejamento e histórico: [`docs/plano-geral.md`](docs/plano-geral.md) e [`docs/etapas/`](docs/etapas)
- Contexto para o Claude Code: [`CLAUDE.md`](CLAUDE.md)

## Desenvolvimento

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed   # usuário demo@financee.test / demo12345
php artisan storage:link
composer run dev        # servidor + vite
php artisan test        # testes (Pest)
./vendor/bin/phpstan    # análise estática
```

## Deploy (Hostoo)

```bash
scripts/release.sh      # testes + build + PR para production + merge automático
```

A Hostoo acompanha a branch `production` (que já contém `vendor/` e `public/build/`).
Cada deploy vira uma PR (histórico) que o script já mergeia; use `--no-merge` para revisar antes.
O cron `* * * * * /usr/local/bin/ea-php84 ~/public_html/financee/artisan schedule:run` aplica
migrations e caches automaticamente quando um novo `RELEASE` chega.
`.env`, banco e storage de produção ficam em `~/financee-data/` (fora do deploy).
