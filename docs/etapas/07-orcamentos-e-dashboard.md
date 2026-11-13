# Etapa 7 — Orçamentos, dashboard e gráficos

## 7.1 Orçamentos (`budgets`)
`id, user_id, category_id, amount, month (YYYY-MM)?` (null = padrão de todos os meses; um registro com mês sobrescreve o padrão).
- Orçamento em categoria pai inclui as subcategorias.
- Gasto = Σ despesas da categoria no mês (conta + cartão pela **data da compra**, previsto + efetivado, com indicação separada).
- Tela **Planejamento → Orçamentos**: total orçado × gasto, lista por categoria com barra (verde < 80%, amarelo 80–100%, vermelho > 100%), quanto resta por dia até o fim do mês.
- "Copiar orçamento do mês anterior" e "Sugerir com base na média dos últimos 3 meses".
- Gatilho de notificação ao passar de 80% e de 100% (enviada na etapa 9).

## 7.2 Tela Início (dashboard)
Ordem dos cards (o usuário pode reordenar e ocultar cards; preferências salvas em `settings`):
1. **Saldo geral** (contas com `include_in_total`) + previsto para o fim do mês; ícone de olho para ocultar valores.
2. **Receitas × Despesas do mês** (efetivado e previsto).
3. **Contas a pagar/receber** (etapa 5).
4. **Cartões**: fatura atual de cada cartão e vencimento.
5. **Orçamentos**: as 3 categorias mais próximas do limite.
6. **Despesas por categoria** (rosca).
7. **Contas**: lista compacta de saldos.
8. **Investimentos** (separado, opcional).

## 7.3 Gráficos
- Biblioteca: **Chart.js 4** (leve, tree-shakeable), importando apenas os controladores usados e carregada sob demanda (dynamic import) para não pesar no carregamento inicial.
- Componente Blade `<x-chart type="doughnut" :data="..." />` + Alpine, com as cores lidas das CSS variables (funciona no tema escuro e é atualizado ao trocar o tema).
- Gráficos: rosca por categoria, barras de receitas × despesas (6–12 meses), linha do saldo projetado (etapa 5), evolução dos investimentos (etapa 6).

## 7.4 Performance da tela Início
- Todas as consultas do dashboard em um `DashboardService` com no máximo ~8 queries agregadas.
- Cache por usuário/mês (`Cache::remember`, 10 minutos), invalidado por eventos de model (lançamento salvo, excluído etc.).
- Cards pesados com `#[Lazy]` do Livewire + skeleton.

## Testes
- Cálculo de orçamento (pai/filho, padrão × mensal, cartão pela data da compra).
- Invalidação de cache do dashboard.

## Critério de pronto
Ao abrir o app, em menos de 1 segundo dá para ver quanto há disponível, o que vence e como estão os gastos do mês.
