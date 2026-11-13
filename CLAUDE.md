# Financee — contexto para o Claude Code

PWA de finanças pessoais em produção em https://financee.jgmoreira.com.br (Hostoo, hospedagem compartilhada).
Laravel 12 · PHP 8.4 · Livewire 3 + Alpine · Tailwind 4 · SQLite · Pest · Larastan. Sem Docker.
Idioma do app, commits e conversa: **português do Brasil**.

## Fluxo de trabalho
- Antes de publicar: `php artisan test`, `./vendor/bin/phpstan analyse --memory-limit=1G` e `./vendor/bin/pint` sem erros.
- Conferir mudanças visuais no navegador (há Chromium do Playwright em `~/.cache/ms-playwright`).
- Commit na `main` → `git push` → `scripts/release.sh` (abre PR para `production` e faz o merge sozinho — o usuário pediu assim). O deploy leva ~15 s; migrations em até 1 min (cron).
- Nunca editar arquivos direto no servidor; `.env`/banco de produção ficam em `~/financee-data`.
- PHP local instalado via php.new: se `php` não for encontrado, `export PATH="$HOME/.config/herd-lite/bin:$PATH"`.

## Regras do domínio (não quebrar)
- Valores sempre em **centavos (int)**. Datas gravadas como `AAAA-MM-DD` (cast `App\Casts\DateOnly`).
- Todo model do usuário usa `BelongsToUser` (escopo por `Auth::check`). Em console/cron, usar `Auth::setUser` por usuário.
- Compra no cartão conta no mês do **vencimento da fatura** (totais, orçamentos, relatórios); em Lançamentos aparece como uma linha "Fatura X". Saldo da conta só muda com o **pagamento** da fatura.
- Fatura identificada pelo mês de vencimento; compra no dia do fechamento vai para a próxima.
- Fixos são gerados até 31/12 do ano seguinte (rotina diária estende); fixo no cartão só ocupa limite na data da cobrança.
- Investimentos têm saldo separado: nunca entram no saldo geral, previsão ou relatórios de despesa.
- Em views Livewire, não reutilizar nomes de propriedades do componente como variáveis de `@foreach` (já causou bug).

## Documentação
- `docs/desenvolvimento.md` — configurar em outro computador · `docs/go-live.md` — operação/servidor · `docs/etapas/` — histórico das decisões.
