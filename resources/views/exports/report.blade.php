@php($fmt = fn (int $c) => \App\Support\Money::format($c))
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Relatório Financee</title>
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
    h1 { font-size: 18px; margin: 0; color: #047857; }
    h2 { font-size: 12px; margin: 18px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; }
    .muted { color: #6b7280; }
    .grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .grid td { width: 25%; padding: 8px; background: #f3f4f6; border: 3px solid #fff; }
    .grid .label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
    .grid .value { font-size: 13px; font-weight: bold; }
    table.list { width: 100%; border-collapse: collapse; }
    table.list th { text-align: left; font-size: 8px; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; padding: 4px; }
    table.list td { padding: 4px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    .right { text-align: right; }
    .income { color: #059669; } .expense { color: #e11d48; }
    .bar { height: 7px; background: #e5e7eb; border-radius: 4px; }
    .bar span { display: block; height: 7px; border-radius: 4px; }
    .footer { position: fixed; bottom: -14px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
    <h1>Financee · Relatório financeiro</h1>
    <p class="muted">{{ $user->name }} · {{ $start->format('d/m/Y') }} a {{ $end->format('d/m/Y') }} · {{ $regime === 'caixa' ? 'por data de pagamento' : 'por data do lançamento' }}</p>

    <table class="grid">
        <tr>
            <td><div class="label">Receitas</div><div class="value income">{{ $fmt($totalIncome) }}</div></td>
            <td><div class="label">Despesas</div><div class="value expense">{{ $fmt($totalExpense) }}</div></td>
            <td><div class="label">Balanço</div><div class="value">{{ $fmt($totalIncome - $totalExpense) }}</div></td>
            <td><div class="label">Saldo geral atual</div><div class="value">{{ $fmt($balance) }}</div></td>
        </tr>
    </table>

    @foreach (['Despesas por categoria' => $expenses, 'Receitas por categoria' => $incomes] as $title => $rows)
        @if ($rows)
            <h2>{{ $title }}</h2>
            <table class="list">
                <tr><th>Categoria</th><th style="width:45%"></th><th class="right">%</th><th class="right">Valor</th></tr>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td><div class="bar"><span style="width: {{ max(1, $row['pct']) }}%; background: {{ $row['color'] }}"></span></div></td>
                        <td class="right">{{ number_format($row['pct'], 1, ',', '.') }}%</td>
                        <td class="right">{{ $fmt($row['total']) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach

    <h2>Lançamentos ({{ $transactions->count() }})</h2>
    <table class="list">
        <tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Conta/Cartão</th><th class="right">Valor</th></tr>
        @foreach ($transactions as $t)
            <tr>
                <td>{{ $t->date->format('d/m/Y') }}</td>
                <td>{{ $t->description }}{{ $t->installmentLabel() ? ' ('.$t->installmentLabel().')' : '' }}{{ $t->isPaid() ? '' : ' · pendente' }}</td>
                <td class="muted">{{ $t->category->name ?? ($t->is_adjustment ? 'Ajuste' : '—') }}</td>
                <td class="muted">{{ $t->account->name ?? ($t->creditCard ? 'Cartão '.$t->creditCard->name : '') }}</td>
                <td class="right {{ $t->type === 'income' ? 'income' : 'expense' }}">{{ $fmt($t->signedAmount()) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="footer">Gerado pelo Financee em {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
