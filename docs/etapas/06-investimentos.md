# Etapa 6 — Investimentos (controle manual, saldo separado)

## 6.1 Princípio
O patrimônio investido aparece em um **módulo próprio**. Ele **nunca** soma no saldo geral, no saldo previsto, nos orçamentos nem nos relatórios de receita/despesa. A tela Início mostra apenas um card separado, "Investimentos: R$ X", que pode ser ocultado.

## 6.2 Modelo
`investment_portfolios` (corretora/banco): `id, user_id, institution_id?, name, color, archived_at`.

`investment_assets`:
```
id, user_id, portfolio_id, name, ticker?,
class (stock|fii|etf|fixed_income|cdb|lci_lca|treasury|fund|crypto|pension|other),
rate_description? (ex.: "110% CDI", "IPCA + 6%"),  maturity_date?, liquidity? (texto),
quantity? (decimal, para ações/FIIs), current_value (int), current_value_at (date),
archived_at
```

`investment_movements`:
```
id, user_id, asset_id,
type (contribution|withdrawal|income|valuation),
amount (int), quantity?, unit_price?, date, notes?,
transfer_id?  (vínculo com movimentação de conta corrente)
```
- **contribution (aporte)**: aumenta o investido e o valor atual.
- **withdrawal (resgate)**: reduz.
- **income**: dividendos/JCP/juros (opcionalmente "creditado em conta" → gera receita na conta, categoria Rendimentos).
- **valuation**: atualização manual do valor de mercado (ex.: "hoje o CDB vale R$ 10.532,10").

## 6.3 Integração com as contas
- Aporte com "origem: conta X" → cria um `transfer` (conta → portfólio): o saldo da conta cai, mas isso **não** é despesa.
- Resgate com "destino: conta X" → `transfer` portfólio → conta: o saldo da conta sobe, mas **não** é receita.
- Aporte/resgate sem conta de origem/destino também é permitido (ex.: posição antiga).

## 6.4 Cálculos (`InvestmentService`)
- Por ativo: total aportado líquido (aportes − resgates), valor atual, rentabilidade (R$ e %) = valor atual − aportado líquido + rendimentos recebidos.
- Por carteira e total geral; distribuição por classe de ativo.

## 6.5 Telas
- **Mais → Investimentos**: valor total, rentabilidade, gráfico de rosca por classe, lista de carteiras.
- Carteira → ativos → detalhe do ativo (histórico de movimentos e gráfico de evolução a partir das valorizações).
- Ações rápidas: "Aportar", "Resgatar", "Rendimento" e "Atualizar valor" (bottom sheet com o mesmo teclado numérico).
- Lembrete opcional "atualize o valor dos seus investimentos" uma vez por mês (push, etapa 9).

## Testes
- Separação: o saldo geral e os relatórios não mudam com movimentos de investimento, exceto pelas transferências de/para conta.
- Rentabilidade com aportes, resgates, rendimentos e valorizações.

## Futuro (fora do escopo atual)
Cotação automática (brapi.dev) e cálculo de CDB/Tesouro por CDI/IPCA (API do Banco Central).
