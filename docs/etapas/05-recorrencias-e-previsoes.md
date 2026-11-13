# Etapa 5 — Parcelamentos em conta, lançamentos fixos e previsões

> **Status (13/11/2026):** implementada. Fixos geram 12 meses à frente (no cartão, só até o mês seguinte, para não consumir limite com compras que ainda não aconteceram); rotina diária às 03:10 estende e efetiva débitos automáticos. Edição de fixo vale "deste em diante" (os já pagos não mudam). Parcelado em conta: 2x a 120x, valor total ou da parcela. Previsão de 12 meses na aba Planejamento (gráfico em barras simples; gráficos completos na Etapa 7).

## 5.1 Parcelamento em conta (boleto/carnê/financiamento)
- Reaproveita `installment_plans` com `account_id` preenchido.
- Gera N lançamentos **previstos** (`paid_at = null`) mensais a partir da data do 1º vencimento.
- Cada parcela é marcada como paga individualmente (deslizar → pago); a edição em série funciona como no cartão.

## 5.2 Lançamentos fixos (`recurrences`)
```
id, user_id, type (income|expense), description, amount, category_id,
account_id? | credit_card_id?,
frequency (weekly|monthly|bimonthly|quarterly|semiannual|yearly),
interval (int, padrão 1), day_of_month?, start_date, end_date?,
auto_mark_paid (bool), active (bool), last_generated_until (date)
```
- **Materialização antecipada**: o comando agendado `recurrences:generate` (diário) cria os lançamentos previstos até **12 meses à frente**. Também roda ao criar/editar uma recorrência.
- Assim o extrato, o saldo projetado, os orçamentos e os relatórios tratam fixos e avulsos da mesma forma (consultas simples e rápidas).
- Editar o valor: "só este mês", "deste mês em diante" (atualiza a recorrência e regenera os previstos futuros não pagos) ou "todos os pendentes".
- Encerrar recorrência: apaga os previstos futuros não pagos.
- Fixos em cartão (assinaturas): geram compras na fatura correspondente.
- `auto_mark_paid`: no dia, marca como efetivado automaticamente (ex.: débito automático).
- Tela **Mais → Lançamentos fixos**: lista com o próximo vencimento, valor e o total mensal comprometido.

## 5.3 Previsões futuras
- Lançamento avulso futuro = lançamento com data futura e não pago (já suportado na etapa 3).
- `ForecastService`:
  - Saldo previsto ao fim de cada mês para os próximos 12 meses = saldo atual + Σ previstos de contas + Σ faturas de cartão a vencer (pelo `due_date`) − pagamentos já feitos.
  - Opção de considerar apenas os lançamentos confirmados ou incluir os previstos.
- Tela **Planejamento → Previsão**: gráfico de linha do saldo projetado, com alerta visual quando o saldo fica negativo. Lista mês a mês com entradas, saídas e saldo final.
- Na tela Início: "Previsto para o fim do mês: R$ X".

## 5.4 Pendências
- Card "Contas a pagar/receber" na tela Início: vencidos (vermelho), hoje e próximos 7 dias.
- Ação rápida "Pagar" com confirmação de valor e data (o valor pode diferir do previsto, como em contas de energia).

## Testes
- Geração de recorrências (fim de mês, anual em 29/02, intervalo, data final).
- Edição "deste em diante" sem afetar os já pagos.
- Previsão combinando contas, cartões, parcelas e recorrências.

## Critério de pronto
Ao cadastrar salário, aluguel, assinaturas e parcelamentos, o app mostra corretamente quanto sobra no fim deste mês e dos próximos.
