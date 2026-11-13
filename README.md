# Financee

PWA de finanças pessoais (Laravel 12 + Livewire + Tailwind + SQLite), mobile-first.

- Planejamento: [`docs/plano-geral.md`](docs/plano-geral.md) e [`docs/etapas/`](docs/etapas)

## Desenvolvimento

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
composer run dev        # servidor + vite
php artisan test        # testes (Pest)
./vendor/bin/phpstan    # análise estática
```

## Deploy (Hostoo)

```bash
scripts/release.sh      # testes + build + push na branch production
```

A Hostoo acompanha a branch `production` (que já contém `vendor/` e `public/build/`).
O cron `* * * * * /usr/local/bin/ea-php84 ~/financee/artisan schedule:run` aplica
migrations e caches automaticamente quando um novo `RELEASE` chega.
`.env`, banco e storage de produção ficam em `~/financee-data/` (fora do deploy).
