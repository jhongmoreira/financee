# Etapa 3 — Lançamentos e transferências

> **Status (13/11/2026):** implementada. Diferenças em relação ao plano: exclusão é definitiva (sem soft delete); o formulário abre direto pelo "+" (sem menu intermediário), com tipo Despesa já selecionado; "Despesa no cartão" entra na Etapa 4. Pendências da Etapa 2 resolvidas: saldo calculado, conta com lançamentos só pode ser arquivada, categoria usada pede destino ao excluir. Deslizar item para pagar/excluir ficou como botão "Pagar/Receber" na própria linha.

## 3.1 Tabela `transactions`
```
id, user_id,
type            (income|expense)
description, notes?
amount          int (centavos, sempre positivo)
date            date (competência/vencimento)
paid_at         date? (null = previsto/pendente)
account_id?     (lançamento em conta)
credit_card_id? + invoice_id?   (lançamento em cartão — etapa 4)
category_id
installment_plan_id?, installment_number?, installment_total?   (etapas 4/5)
recurrence_id?                                                    (etapa 5)
is_adjustment   bool
timestamps, softDeletes
```
Regra: exatamente um entre `account_id` e `credit_card_id` é preenchido.
Índices: `(user_id, date)`, `(account_id, paid_at)`, `(invoice_id)`, `(category_id, date)`.

## 3.2 Tabela `transfers`
`id, user_id, from_account_id, to_account_id, amount, date, paid_at?, description?`
- Transferência **não** entra em receitas/despesas nem em relatórios por categoria; altera apenas os saldos.
- Na etapa 6 ganha os destinos/origens de investimento (`to_portfolio_id` / `from_portfolio_id`).

## 3.3 Formulário rápido (o mais importante do app)
Bottom sheet aberto pelo FAB:
1. Segmented control: **Despesa | Receita | Cartão | Transferência**.
2. Valor em destaque com teclado numérico (`inputmode="decimal"`), campo já focado.
3. Descrição (com autocomplete das últimas descrições, que também sugere a categoria e a conta).
4. Categoria (grid de ícones), Conta, Data (chips "Hoje", "Ontem" e "Outra").
5. Toggle "Pago/Recebido" (padrão: pago se a data ≤ hoje).
6. "Mais opções": observação, repetir (etapa 5), parcelar (etapas 4/5).
- Botão "Salvar" e "Salvar e criar outro".
- Meta: lançar uma despesa comum em **menos de 5 segundos**.

## 3.4 Extrato (aba Lançamentos)
- Navegação por mês (swipe lateral + setas na app bar).
- Agrupado por dia, com o saldo do fim do dia opcional.
- Ícone/cor da categoria, conta/cartão, badge "pendente", parcela "3/10".
- Resumo no topo: receitas, despesas e saldo do mês (efetivado vs. previsto).
- Filtros (bottom sheet): tipo, conta, cartão, categoria, status e busca por texto.
- Ações por item (toque → detalhe; deslizar para a esquerda → excluir; para a direita → marcar como pago).
- Paginação/carregamento progressivo com `wire:intersect` se o mês tiver muitos itens.

## 3.5 Saldos
- `AccountBalanceService`: saldo atual, saldo em uma data e saldo previsto no fim do mês, todos via SQL agregado (`SUM(CASE ...)`).
- Saldo geral = Σ contas com `include_in_total`. **Nunca** inclui investimentos.

## Testes
- Cálculo de saldo com receitas, despesas, pendentes, transferências e ajustes.
- Validações (valor > 0, conta do próprio usuário, categoria compatível com o tipo).
- Exclusão (soft delete) recalculando o saldo.

## Critério de pronto
Uso diário possível: lançar, editar, excluir, transferir e ver o extrato e o saldo corretos.
