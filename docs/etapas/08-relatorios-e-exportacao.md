# Etapa 8 — Relatórios e exportação

> **Status (13/11/2026):** implementada. Relatórios: despesas e receitas por categoria (com subcategorias e variação vs. período anterior), evolução mensal, histórico de saldo e faturas por cartão; períodos prontos ou personalizado (até 5 anos); regime por data do lançamento ou do pagamento. Exportação: CSV (Excel pt-BR), PDF (dompdf, fonte com subsetting ~30 KB) e backup completo em JSON (Perfil). Exportações são geradas na hora por streaming; a fila para exportações grandes não foi necessária. Extrato de fatura em PDF ficou para depois.

## 8.1 Relatórios (Mais → Relatórios)
Filtro comum: período (mês, trimestre, ano ou personalizado), contas, cartões, categorias e regime (**caixa**, pela data do pagamento, ou **competência**, pela data da compra/lançamento).

1. **Despesas por categoria**: rosca + lista com valor, % e variação em relação ao período anterior; toque → subcategorias → lançamentos.
2. **Receitas por categoria**: idem.
3. **Receitas × Despesas**: barras mês a mês, com o saldo do período e a média mensal.
4. **Evolução do saldo**: linha do saldo geral ao fim de cada mês.
5. **Faturas de cartão**: total por mês por cartão.
6. **Investimentos**: evolução e distribuição (dados da etapa 6, sempre separados).

`ReportService` com consultas agregadas por `strftime('%Y-%m', date)` (SQLite) e índices da etapa 3.

## 8.2 Exportação
- **CSV** (separador `;`, BOM UTF-8 e vírgula decimal, para abrir direto no Excel em pt-BR):
  - Lançamentos do período filtrado (data, descrição, categoria, subcategoria, conta/cartão, tipo, valor, status, parcela).
  - Gerado via `response()->streamDownload()`, sem carregar tudo em memória.
- **PDF** (`barryvdh/laravel-dompdf`, PHP puro, compatível com hospedagem compartilhada):
  - Relatório mensal: resumo, despesas por categoria (tabela + barras simples em HTML/CSS), lista de lançamentos.
  - Extrato de fatura do cartão.
  - Os gráficos no PDF são tabelas com barras em CSS (dompdf não executa JS).
- **Backup pessoal**: "Exportar todos os meus dados" (JSON zipado), útil também por causa da LGPD.
- Exportações grandes (> ~5 mil linhas) vão para a fila e ficam disponíveis para download quando concluídas (job processado pelo cron, com aviso por push).

## Testes
- Totais dos relatórios conferidos contra o extrato.
- CSV abre corretamente no Excel (encoding e separadores); PDF gerado sem erro.

## Critério de pronto
Gerar o PDF do mês e o CSV do ano pelo celular, com os valores batendo com o app.
