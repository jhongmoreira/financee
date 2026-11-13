# Etapa 1 — Fundação: autenticação, perfil, app shell, tema e PWA

> **Status (13/11/2026):** implementada. Pendências movidas: categorias/conta padrão no cadastro → Etapa 2; SMTP de produção (e-mail de "Esqueci a senha") → Etapa 10.

## 1.1 Autenticação (Fortify headless)
- Funcionalidades: cadastro, login ("lembrar de mim"), logout, recuperação de senha por e-mail, verificação de e-mail (opcional via config) e troca de senha.
- Telas Blade próprias, no visual do app (sem layout de site).
- `REGISTRATION_ENABLED=true|false` no `.env` para fechar o cadastro.
- Rate limit no login (5/min por e-mail+IP).
- E-mail: SMTP da própria Hostoo (configurar no `.env`).
- Ao cadastrar (listener `Registered`): criar as categorias padrão e uma conta "Carteira".

## 1.2 Perfil
- Tela **Mais → Perfil**: nome, e-mail, foto (upload redimensionado para 256px com `intervention/image`, salvo em `storage/app/public/avatars`), troca de senha e exclusão de conta (com confirmação de senha, apagando todos os dados).
- Preferências (coluna JSON `settings` em `users`): tema (`system|light|dark`), dia de início do mês financeiro (padrão 1), ocultar valores ao abrir o app (ícone de "olho").

## 1.3 App shell (layout)
- `layouts/app.blade.php`:
  - App bar fixa no topo (título, seletor de mês quando aplicável, ações).
  - Bottom nav fixa com 4 itens + FAB central; respeita `env(safe-area-inset-bottom)` (iPhone).
  - `lg:` → sidebar à esquerda e conteúdo com `max-w-3xl`.
- Componentes Blade reutilizáveis (`resources/views/components/ui/`):
  - `bottom-sheet` (Alpine, arrastar para fechar), `modal`, `list-item`, `money` (formatação BRL e modo oculto), `amount-input` (teclado numérico, máscara de centavos), `fab`, `empty-state`, `toast`, `segmented-control`, `icon` (Heroicons/Lucide inline em SVG), `skeleton`.
- Navegação com `wire:navigate` + `@persist` para a bottom nav, sem recarregar a página.
- Feedback tátil: estados `active:scale-95`, `navigator.vibrate` leve em ações de confirmação.
- Fonte do sistema (sem fonte externa) para carregar rápido.

## 1.4 Tema escuro
- Tailwind 4 com `@custom-variant dark (&:where(.dark, .dark *))`.
- Tokens de cor em CSS variables (`--surface`, `--on-surface`, `--primary`, `--income`, `--expense`...).
- Script inline no `<head>` aplica a classe antes da pintura (evita "flash") lendo a preferência do usuário (renderizada no HTML) com fallback para `prefers-color-scheme`.
- Alternância rápida em **Mais**; `meta theme-color` atualizada dinamicamente.

## 1.5 PWA
- `public/manifest.webmanifest`: nome, `display: standalone`, `start_url: /`, ícones 192/512 e maskable, cores, `lang: pt-BR`, atalhos ("Nova despesa", "Nova receita").
- `public/sw.js` (escrito à mão, sem Workbox, para ficar enxuto):
  - Pré-cache do shell: CSS/JS de `public/build` (lista gerada a partir do `manifest.json` do Vite no build), ícones e página `/offline`.
  - Estratégias: assets → cache-first; navegação → network-first com fallback para `/offline`; nada de cache de dados financeiros.
  - Versão do cache = hash do release; limpeza de caches antigos no `activate`.
  - Handler de `push` e `notificationclick` (usado na etapa 9).
- Banner "Instalar app" (evento `beforeinstallprompt` no Android; instruções para iOS).
- Splash/ícones iOS (`apple-touch-icon`, `apple-mobile-web-app-*`).

## 1.6 Infraestrutura de domínio
- Trait `BelongsToUser` (global scope + preenchimento automático de `user_id`).
- Cast `MoneyCast` / value object `Money` (centavos ↔ "R$ 1.234,56").
- Helper de mês de referência (`YearMonth`) usado em toda a navegação mensal.

## Testes
- Fluxos de auth (cadastro fechado/aberto, login, reset), perfil, exclusão de conta, isolamento entre usuários.
- Lighthouse PWA: instalável, nota de performance ≥ 90 no mobile.

## Critério de pronto
App instalável no Android e no iOS, login funcionando, tema escuro sem flash, perfil editável e navegação instantânea entre telas vazias.
