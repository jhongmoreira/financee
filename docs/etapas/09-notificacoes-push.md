# Etapa 9 — Notificações push

## 9.1 Infraestrutura
- Pacote `laravel-notification-channels/webpush` (usa `minishlink/web-push`; requer as extensões `gmp` ou `bcmath` e `openssl`, conferidas na etapa 0).
- Chaves VAPID geradas uma vez (`php artisan webpush:vapid`) e guardadas no `.env` do servidor.
- Tabela `push_subscriptions` (do pacote), com uma assinatura por dispositivo.
- O service worker (etapa 1) trata `push` (exibe título, corpo, ícone e badge) e `notificationclick` (abre/foca o app na URL da notificação).
- Envio via fila `database`, processada pelo cron (`queue:work --stop-when-empty`).

## 9.2 Permissão (UX)
- Nunca pedir permissão ao abrir o app. Tela **Mais → Notificações** com explicação e botão "Ativar neste dispositivo".
- No iOS: só funciona com o app **instalado na tela inicial** (iOS 16.4+). Detectar e mostrar instruções.
- Lista de dispositivos ativos com opção de remover.

## 9.3 Tipos de notificação (cada um pode ser ligado/desligado em `settings`)
| Notificação | Quando | Agendamento |
|---|---|---|
| Contas a vencer | X dias antes (padrão 1) e no dia, às 09h | `notifications:due` diário |
| Contas vencidas | dia seguinte ao vencimento, se ainda pendente | idem |
| Fatura fechou | no dia do fechamento, com o total | idem |
| Fatura vence | 2 dias antes e no dia | idem |
| Orçamento | ao atingir 80% e 100% de uma categoria | evento ao salvar lançamento (com deduplicação por mês) |
| Saldo previsto negativo | quando a previsão do mês fica negativa | diário |
| Lembrete de investimentos | 1º dia do mês | mensal |
| Exportação pronta | ao concluir um job | evento |

- Agrupamento: várias contas no mesmo dia → uma única notificação ("3 contas vencem hoje: R$ 1.250,00").
- Registro de notificações enviadas (`notification_logs`) para evitar duplicidade se o cron rodar duas vezes.
- Também ficam disponíveis no app (sino na app bar) via canal `database`.

## Testes
- Seleção correta de pendências por data, deduplicação e respeito às preferências.
- Envio simulado (`Notification::fake()`).

## Critério de pronto
O celular (Android e iPhone com o app instalado) recebe o aviso de uma conta cadastrada para amanhã.
