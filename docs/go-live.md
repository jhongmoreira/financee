# Financee — Go-live e operação

## Situação (13/11/2026)
Todas as etapas (0 a 10) implementadas e publicadas em https://financee.jgmoreira.com.br.
Suíte de testes automatizados (Pest) e análise estática (Larastan nível 6) rodam a cada release.

## O que roda sozinho no servidor (cron a cada minuto)
| Quando | Comando | O quê |
|---|---|---|
| a cada minuto | `app:post-deploy` | Após um deploy: migrations + caches (cada passo em processo separado) |
| a cada minuto | `queue:work --stop-when-empty` | Fila: envio das notificações push |
| 03:10 | `recurrences:generate` | Estende os lançamentos fixos (12 meses) e efetiva débitos automáticos |
| 09:00 | `notifications:send` | Avisos do dia (contas, faturas, saldo negativo, investimentos) |
| dom 04:00 | `app:db-maintenance` | `PRAGMA optimize` no SQLite |
| dia 1º 04:30 | `app:db-maintenance --vacuum` | Compacta o arquivo do SQLite |

Cron cadastrado no cPanel:
```
* * * * * /usr/local/bin/ea-php84 /home/mv8f1v6j/public_html/financee/artisan schedule:run >> /dev/null 2>&1
```

## Pendente: e-mail (SMTP) — necessário só para "Esqueci a senha"
1. No cPanel, crie uma conta de e-mail, por exemplo `nao-responda@jgmoreira.com.br` (em "Contas de e-mail").
2. Veja os dados de conexão em "Conectar dispositivos" (servidor SMTP, porta 465 com SSL).
3. Edite `~/financee-data/.env` por SSH e ajuste:
```
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.jgmoreira.com.br
MAIL_PORT=465
MAIL_USERNAME=nao-responda@jgmoreira.com.br
MAIL_PASSWORD="a-senha-da-conta"
MAIL_FROM_ADDRESS=nao-responda@jgmoreira.com.br
MAIL_FROM_NAME=Financee
```
4. Aplique: `/usr/local/bin/ea-php84 ~/public_html/financee/artisan optimize`
5. Teste em https://financee.jgmoreira.com.br/forgot-password.

## Opcional
- **Fechar o cadastro** depois de criar as contas que você quer: `REGISTRATION_ENABLED=false` no `.env` + `optimize`.
- **Backup**: o banco é o arquivo `~/financee-data/database.sqlite`. Cada usuário também pode baixar tudo em JSON em Perfil → "Exportar todos os meus dados".
- **Exclusões são definitivas**: o SQLite roda com `PRAGMA secure_delete = ON` (ligado em toda conexão, em `AppServiceProvider`), então o conteúdo excluído é sobrescrito com zeros no arquivo do banco na hora — vale para "Recomeçar do zero", "Excluir conta" e qualquer exclusão.
- **Chaves do push**: geradas automaticamente em `~/financee-data/storage/app/private/vapid.json`. Não apague (os dispositivos ativados deixariam de receber).

## Checklist de go-live
- [x] `.env` de produção (APP_ENV=production, APP_DEBUG=false, LOG_STACK=daily)
- [x] Cron ativo (scheduler + fila) e post-deploy validado
- [x] HTTPS forçado (`public/.htaccess`) + HSTS + cabeçalhos de segurança (CSP, X-Frame-Options...)
- [x] Cookie de sessão seguro em produção
- [x] Ícones, nome e tela offline do PWA
- [x] Páginas de erro em português (404, 419, 429, 500, 503)
- [ ] E-mail SMTP (acima)
- [ ] Uso real por 1–2 semanas conferindo saldos e faturas com os bancos

## Cuidados
- Não altere a versão do PHP pelo painel: o cPanel reescreve `public/.htaccess` e trava o deploy ("arquivos alterados manualmente"). Se acontecer: `cd ~/public_html/financee && git status`.
- Deploy: `scripts/release.sh` (testes + Larastan + build + PR para `production` com merge automático).
