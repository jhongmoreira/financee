# Etapa 4 — Cartão de crédito e faturas

> **Status (13/11/2026):** implementada. Fatura identificada pelo mês de vencimento; compra no dia do fechamento já cai na próxima. Pagamento parcial: em fatura fechada o padrão é levar o restante para a próxima (pode desligar); em fatura aberta é adiantamento. Antecipação move as parcelas futuras para a fatura atual (sem desconto automático — ajuste o valor editando a parcela). Limitação conhecida: mudar os dias de fechamento/vencimento de um cartão não reorganiza faturas já criadas.

## 4.1 Modelo
`invoices`: `id, user_id, credit_card_id, reference_month (YYYY-MM), closing_date, due_date, carried_balance (int, ± saldo vindo da fatura anterior), timestamps`. Índice único `(credit_card_id, reference_month)`.
- Total da fatura = Σ despesas − Σ estornos/créditos + `carried_balance`.
- Status **derivado**: `open` (antes do fechamento), `closed` (fechada e não paga), `partial`, `paid`, `overdue`.

`invoice_payments`: `id, user_id, invoice_id, account_id, amount, paid_at`.
- O pagamento debita a conta (entra no saldo da conta como saída), mas **não** conta como despesa por categoria, porque as despesas já foram contadas nas compras.

## 4.2 Em qual fatura cai a compra
`InvoiceResolver::forPurchase(card, purchaseDate)`:
- Se `dia da compra >= closing_day` → fatura do mês seguinte; senão → fatura do mês atual.
- `closing_day`/`due_day` maiores que o último dia do mês → usa o último dia do mês (ex.: 31 → 28/02).
- `due_date` cai no mês da referência se `due_day > closing_day`; senão, no mês seguinte.
- As faturas são criadas sob demanda (`firstOrCreate`).
- O usuário pode mover manualmente um lançamento para outra fatura.

## 4.3 Compras parceladas no cartão
`installment_plans`: `id, user_id, description, total_amount, installments, first_date, credit_card_id?, account_id?, category_id`.
- Gera N `transactions`, uma por fatura consecutiva, com `installment_number`/`installment_total`.
- Divisão em centavos: o resto vai para a 1ª parcela (ex.: 100,00/3 → 33,34 + 33,33 + 33,33).
- Opção de informar o valor da parcela em vez do total (para compras com juros): total = parcela × N.
- Editar/excluir: "só esta", "esta e as próximas" ou "todas".
- **Antecipação**: move as parcelas futuras escolhidas para a fatura atual, com desconto opcional (o valor é ajustado e o desconto fica registrado na observação).

## 4.4 Estornos e créditos
- Lançamento do tipo `income` no cartão = estorno/crédito; reduz a fatura e pode ter categoria.
- Estorno de compra parcelada: estorna as parcelas restantes ou um valor avulso.

## 4.5 Pagamento de fatura
- Tela da fatura → "Pagar fatura": valor (padrão = total), conta (padrão = `payment_account_id`) e data.
- **Parcial**: o restante vira `carried_balance` positivo na próxima fatura quando a atual fecha/vence (mostrado como "Saldo anterior").
- **A maior**: o excedente vira `carried_balance` negativo (crédito) na próxima fatura.
- Desfazer pagamento.
- Os pagamentos aparecem no extrato da conta como "Pagamento fatura Nubank (Nov/26)".

## 4.6 Limite
- Limite usado = Σ (faturas não pagas, inclusive parcelas futuras) − pagamentos.
- Disponível = limite − usado. Barra de progresso no card.

## 4.7 Telas
- **Cartões**: carrossel de cartões com fatura atual, vencimento e limite.
- **Fatura**: navegação entre meses (passadas e futuras), status colorido, lista de compras por dia, total, botões "Pagar" e "Ajustar".
- O formulário rápido no modo "Cartão" mostra em qual fatura a compra vai cair ("Entra na fatura de Dez/26").

## Testes (críticos)
- Datas de fechamento e vencimento em todos os casos de borda (dia 31, fevereiro, virada de ano, fechamento = vencimento).
- Parcelamento com arredondamento, edição em série, antecipação.
- Pagamento total, parcial, a maior e desfeito; saldo carregado entre faturas.
- Limite disponível.

## Critério de pronto
Uma fatura real do seu cartão bate centavo a centavo com o app.
