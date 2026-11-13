# Etapa 2 — Cadastros: instituições, contas, cartões e categorias

> **Status (13/11/2026):** implementada. Instituições aparecem com sigla + cor (sem logotipos de marcas). Ficam para a Etapa 3: saldo calculado com lançamentos, e ao excluir conta/categoria com lançamentos pedir destino (hoje a exclusão é direta, com confirmação). Reordenar por arrastar ficou para a Etapa 10.

## 2.1 Instituições (seed, global)
`institutions`: `id, name, code (COMPE), type (bank|broker|other), color, logo_path`.
- Seed com os principais bancos e corretoras: Nubank, Itaú, Bradesco, BB, Caixa, Santander, Inter, C6, BTG, XP, Rico, Clear, NuInvest, PicPay, Mercado Pago, Sicoob, Sicredi, PagBank, "Outro".
- Logos em SVG e WebP pequenos em `public/img/institutions` (com fallback para as iniciais em um círculo colorido).

## 2.2 Contas (`accounts`)
Colunas: `id, user_id, institution_id?, name, type (checking|savings|wallet|other), initial_balance (int), color, icon, include_in_total (bool), archived_at, sort_order, timestamps`.
- O saldo atual é **calculado**: `initial_balance + Σ efetivados (receitas − despesas ± transferências)`. Fica em `AccountBalanceService`, com cache por request.
- Telas: lista com o saldo de cada conta e o total; formulário em bottom sheet; arquivar (em vez de excluir quando há lançamentos); reordenar (drag com Alpine Sort).
- "Ajustar saldo": cria um lançamento de ajuste para bater com o saldo real do banco.

## 2.3 Cartões de crédito (`credit_cards`)
Colunas: `id, user_id, institution_id?, name, brand (visa|master|elo|amex|hipercard|other), limit (int), closing_day (1-31), due_day (1-31), payment_account_id, color, archived_at, sort_order`.
- Vários cartões por instituição.
- Validação: o dia de vencimento pode ser menor que o de fechamento (vencimento no mês seguinte). O cálculo das datas fica na etapa 4.
- Tela: card visual estilo cartão físico (cor/bandeira), limite usado e disponível, fatura atual (preenchidos na etapa 4).

## 2.4 Categorias (`categories`)
Colunas: `id, user_id, parent_id?, type (expense|income), name, icon, color, archived_at, sort_order`.
- Dois níveis (categoria → subcategoria).
- Padrões criados no cadastro do usuário:
  - Despesas: Alimentação (Mercado, Restaurante, Delivery), Moradia (Aluguel, Condomínio, Energia, Água, Internet), Transporte (Combustível, App, Manutenção), Saúde, Educação, Lazer, Compras, Assinaturas, Impostos e Taxas, Outros.
  - Receitas: Salário, Freelance, Rendimentos, Reembolso, Outros.
- Seletor de ícones (lista curada de cerca de 60 ícones) e paleta de cores.
- Excluir categoria com lançamentos → pede uma categoria de destino para mover os lançamentos.

## 2.5 Componentes reutilizáveis desta etapa
- `account-picker`, `card-picker`, `category-picker` (grid de ícones em bottom sheet, com busca).
- `color-picker`, `icon-picker`.

## Testes
- CRUD de cada entidade, isolamento por usuário, arquivamento, regras de exclusão/migração de categoria.

## Critério de pronto
O usuário cadastra as contas reais, os cartões e ajusta as categorias pelo celular, cada operação em poucos toques.
