# Etapa 10 — Polimento, performance, segurança e go-live

> **Status (13/11/2026):** implementada. Segurança: cabeçalhos (CSP, X-Frame-Options, nosniff, Referrer/Permissions-Policy, HSTS em produção), cookie seguro, HTTPS forçado; Livewire trata 419 recarregando e erros com aviso em português; páginas de erro próprias. Desempenho: cache longo de assets com hash, compressão, N+1 eliminados (testados com modo estrito e dados de demonstração), manutenção semanal/mensal do SQLite. Qualidade: Larastan no release.sh, DemoSeeder (demo@financee.test / demo12345, só local), teste de fumaça de todas as telas. Reordenar contas/cartões (setas). Pendente com o usuário: SMTP (ver docs/go-live.md). Não feitos: pull-to-refresh (o app já atualiza sozinho após lançamentos) e Lighthouse (sem ferramenta no ambiente).

## 10.1 Performance
- Produção: `php artisan optimize` (config, rotas, views, eventos), OPcache, `composer --classmap-authoritative`.
- SQLite: revisar o `EXPLAIN QUERY PLAN` das consultas principais, `PRAGMA optimize` agendado semanalmente e `VACUUM` mensal.
- Assets: CSS/JS com hash e `Cache-Control: immutable` (via `.htaccess`), compressão gzip/brotli se o Apache da Hostoo permitir.
- Metas: TTFB < 300 ms nas telas principais; Lighthouse mobile ≥ 90 em Performance, Acessibilidade e PWA.

## 10.2 UX mobile
- Revisão de todos os toques: áreas ≥ 44px, estados de carregamento (`wire:loading`), feedback de sucesso (toast), confirmação em ações destrutivas.
- Pull-to-refresh nas listas; gestos de swipe consistentes.
- Teclado: `inputmode` correto, `enterkeyhint`, sem zoom em inputs (font-size ≥ 16px).
- Acessibilidade: contraste nos dois temas, `aria-label` nos ícones, foco visível.
- Testar em: Android Chrome (instalado), iPhone Safari (instalado) e desktop Chrome/Firefox.

## 10.3 Segurança
- Policies em todas as rotas e ações Livewire (testes automáticos de acesso cruzado entre usuários).
- `#[Locked]` nas propriedades Livewire sensíveis (IDs).
- Headers: CSP básica, `X-Frame-Options`, `Referrer-Policy`, HSTS.
- Sessão: `SESSION_SECURE_COOKIE=true`, `same_site=lax`, tempo de vida longo para a experiência de app (com "lembrar de mim").
- `APP_DEBUG=false`, logs com rotação diária (`LOG_STACK=daily`, 14 dias).
- O arquivo SQLite fora do docroot (já garantido na etapa 0).

## 10.4 Qualidade
- Larastan nível 6+, Pint e suíte Pest completa no `scripts/release.sh` (o release é bloqueado se algo falhar).
- Seeder de demonstração (`DemoSeeder`) com 6 meses de dados realistas para testes manuais.

## 10.5 Go-live
- [ ] `.env` de produção revisado (VAPID, `REGISTRATION_ENABLED`).
- [ ] **E-mail (SMTP da Hostoo)** configurado e testado: recuperação de senha e demais avisos por e-mail (adiado da Etapa 1).
- [ ] Cron ativo (scheduler + fila) e post-deploy validado.
- [ ] HTTPS forçado e redirecionamento www/não-www.
- [ ] Ícones, nome e splash do PWA finais.
- [ ] Teste de ponta a ponta com dados reais por 1–2 semanas.
- [ ] Tag `v1.0.0`.

## Backlog pós-1.0 (ideias)
Cotações automáticas, cálculo CDI/IPCA, metas, tags, importação OFX/CSV, anexos de comprovantes, contas compartilhadas entre usuários (família), modo offline com sincronização.
