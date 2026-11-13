# Financee — Plano Geral

> **Status (13/11/2026):** etapas 0–10 concluídas e em produção. Operação e pendências: [go-live.md](go-live.md).

PWA de finanças pessoais, mobile-first, inspirado em Organizze/Mobills.

## 1. Decisões fechadas

| Tema | Decisão |
|---|---|
| Backend | Laravel 12 (PHP 8.4) |
| Frontend | Blade + Livewire 3 + Alpine.js + Tailwind CSS 4 (Vite, build local) |
| Banco | SQLite (WAL), arquivo **fora** da pasta do deploy |
| Auth | E-mail e senha (Laravel Fortify headless + telas próprias) |
| Usuários | Multiusuário, dados isolados por `user_id`; cadastro pode ser fechado via `.env` |
| Online | Requer internet; service worker só cacheia o "app shell" e mostra tela offline |
| Moeda/idioma | Somente BRL e pt-BR |
| Layout | Mobile-first (bottom nav, FAB, bottom sheets); adaptado para desktop (sidebar) |
| Investimentos | Controle manual; saldo totalmente separado do patrimônio "corrente" |
| Funcionalidades extras | Categorias, Orçamentos, Gráficos, Exportação (CSV/PDF), Push |
| Fora do escopo | Docker, offline-first, multi-moeda, tags, metas, importação OFX, anexos, backup externo |
| Hospedagem | Hostoo compartilhada: deploy via integração Git, cron disponível, docroot → `public/` |

## 2. Arquitetura

```
Navegador / PWA instalado
  ├─ Service Worker (cache do shell, assets versionados, página offline, push)
  └─ Livewire (wire:navigate → navegação tipo SPA, sem reload)
        │ HTTPS
Hostoo (Apache + PHP 8.4)
  └─ Laravel 12
       ├─ Livewire components (telas)
       ├─ Services de domínio (Lançamentos, Faturas, Recorrências, Saldos, Investimentos)
       ├─ Queue "database" + Scheduler → executados pelo cron (1x/min)
       └─ SQLite  (~/financee-data/database.sqlite)
```

Princípios:
- **Valores em centavos (integer)** em todas as tabelas. Nunca float.
- **Toda regra financeira em Services testados (Pest)**, componentes Livewire ficam finos.
- **Escopo por usuário** em todos os models (global scope `BelongsToUser` + Policies).
- **Performance**: consultas agregadas com índices, `php artisan optimize`, OPcache, assets com hash, sem N+1 (`preventLazyLoading` em dev).

## 3. Modelo de dados (visão geral)

| Tabela | Função |
|---|---|
| `users` | perfil, avatar, preferência de tema, configurações de notificação |
| `institutions` | lista fixa de bancos/corretoras (nome, código, cor, logo) — seed |
| `accounts` | contas (corrente, poupança, carteira, outros), saldo inicial, "incluir no total" |
| `credit_cards` | limite, dia de fechamento, dia de vencimento, conta padrão de pagamento |
| `invoices` | faturas por cartão/mês: fechamento, vencimento, status |
| `invoice_payments` | pagamentos (total/parcial) de fatura a partir de uma conta |
| `categories` | categorias e subcategorias de receita/despesa (padrões criados no cadastro) |
| `transactions` | lançamentos: receita/despesa, conta **ou** cartão, efetivado/previsto, vínculo com parcelamento/recorrência |
| `transfers` | transferência entre contas (e conta ↔ investimento) |
| `installment_plans` | agrupador de parcelamentos (cartão ou conta) |
| `recurrences` | modelo de lançamento fixo (mensal, semanal, anual...) |
| `budgets` | orçamento mensal por categoria |
| `investment_portfolios` | corretoras/carteiras de investimento |
| `investment_assets` | ativos (ação, FII, CDB, Tesouro, renda fixa, fundo, cripto, outro) |
| `investment_movements` | aporte, resgate, rendimento, atualização de valor |
| `push_subscriptions` | assinaturas Web Push por dispositivo |
| `jobs`, `cache`, `sessions` | infraestrutura Laravel |

Detalhes de colunas ficam no plano de cada etapa.

## 4. Navegação (mobile)

```
┌──────────────────────────────┐
│  ☰  Novembro 2026 ◀ ▶     🔔 │  ← app bar (mês corrente navegável)
│                              │
│        conteúdo da tela      │
│                              │
├──────────────────────────────┤
│ Início  Lançam.  (+)  Plan.  Mais │  ← bottom nav + FAB central
└──────────────────────────────┘
```
- **Início**: saldo geral, contas, cartões, pendências, gráfico rápido.
- **Lançamentos**: extrato do mês com filtros.
- **(+)**: bottom sheet → Despesa / Receita / Despesa no cartão / Transferência.
- **Planejamento**: orçamentos e previsão de saldo.
- **Mais**: Contas, Cartões, Investimentos, Categorias, Relatórios, Perfil, Tema, Sair.

No desktop (≥ `lg`), a bottom nav vira sidebar fixa e o conteúdo usa largura máxima.

## 5. Estratégia de deploy (Hostoo)

A integração Git da Hostoo copia os arquivos a cada commit, mas não sabemos se ela roda `composer`/`npm`. Para não depender de SSH:

1. Branch `main` = código-fonte limpo.
2. Script `scripts/release.sh` (rodado no WSL) gera um build de produção (`composer install --no-dev`, `npm run build`) e publica na branch **`production`**, que inclui `vendor/` e `public/build/`.
3. A Hostoo acompanha a branch `production`.
4. O cron (`php artisan schedule:run` a cada minuto) executa o comando `app:post-deploy`, que detecta uma nova versão (arquivo `RELEASE`) e roda `migrate --force` + `optimize`.
5. `.env` e o SQLite ficam **fora** do diretório sincronizado, então um deploy não sobrescreve nada.

## 6. Etapas

| # | Etapa | Plano | Entrega |
|---|---|---|---|
| 0 | Ambiente local e pipeline de deploy | [etapas/00-ambiente-e-deploy.md](etapas/00-ambiente-e-deploy.md) | "Hello Financee" rodando na Hostoo com HTTPS |
| 1 | Fundação: auth, perfil, app shell, tema, PWA | [etapas/01-fundacao.md](etapas/01-fundacao.md) | Login, cadastro, perfil, tema escuro, app instalável |
| 2 | Cadastros: instituições, contas, cartões, categorias | [etapas/02-cadastros.md](etapas/02-cadastros.md) | CRUDs completos |
| 3 | Lançamentos e transferências | [etapas/03-lancamentos.md](etapas/03-lancamentos.md) | Extrato, saldos por conta |
| 4 | Cartão de crédito e faturas | [etapas/04-cartoes-e-faturas.md](etapas/04-cartoes-e-faturas.md) | Compras, parcelas, faturas, pagamentos, estornos |
| 5 | Parcelamentos em conta, fixas e previsões | [etapas/05-recorrencias-e-previsoes.md](etapas/05-recorrencias-e-previsoes.md) | Saldo projetado |
| 6 | Investimentos | [etapas/06-investimentos.md](etapas/06-investimentos.md) | Carteiras e ativos com saldo separado |
| 7 | Orçamentos, dashboard e gráficos | [etapas/07-orcamentos-e-dashboard.md](etapas/07-orcamentos-e-dashboard.md) | Tela Início completa e planejamento |
| 8 | Relatórios e exportação | [etapas/08-relatorios-e-exportacao.md](etapas/08-relatorios-e-exportacao.md) | Relatórios, CSV e PDF |
| 9 | Notificações push | [etapas/09-notificacoes-push.md](etapas/09-notificacoes-push.md) | Lembretes de vencimento |
| 10 | Polimento, performance e go-live | [etapas/10-polimento-e-go-live.md](etapas/10-polimento-e-go-live.md) | Versão 1.0 |

Cada etapa termina com testes passando, deploy em produção e validação no celular.

## 7. Pendências a confirmar com a Hostoo (Etapa 0)

- [x] PHP **8.4.27** no CLI (`/usr/local/bin/ea-php84`), com `gmp`, `bcmath`, `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `gd`, `dom`, `fileinfo`, `intl`, `zip`, `curl`. Servidor cPanel com LiteSpeed (`lsphp`).
- [x] Acesso SSH disponível.
- [x] PHP do site também é 8.4.27 (LiteSpeed LSAPI, CloudLinux).
- [x] A integração Git permite escolher a branch: a Hostoo acompanha `production`.
- [x] Extensões ativas sem `-c` (o `~/php.ini` não existe). O cron usa só `/usr/local/bin/ea-php84`.
- [x] Composer 2.10.2 disponível no servidor (usado como alternativa via SSH; o deploy normal leva o `vendor/` pronto na branch `production`).
- [ ] A integração Git apaga arquivos que não estão no repositório? Isso define onde `.env` e o SQLite ficam.
