@php
    $fmt = fn (int $c) => \App\Support\Money::format($c);
    $has = fn (string $s) => in_array($s, $sections, true);
    $monthLabel = fn ($m) => ucfirst($m->locale('pt_BR')->translatedFormat('M/y'));
@endphp
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
    table.list tr.total td { font-weight: bold; border-top: 1px solid #e5e7eb; }
    /* mesma especificidade de "table.list th" para vencer o alinhamento à esquerda */
    .right, table.list th.right, table.list td.right { text-align: right; }
    .income { color: #059669; } .expense { color: #e11d48; }
    .bar { height: 7px; background: #e5e7eb; border-radius: 4px; }
    .bar span { display: block; height: 7px; border-radius: 4px; }
    .dot { display: inline-block; width: 7px; height: 7px; border-radius: 4px; margin-right: 3px; }
    .footer { position: fixed; bottom: -14px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
    <h1>Financee · Relatório financeiro</h1>
    <p class="muted">{{ $user->name }} · {{ $start->format('d/m/Y') }} a {{ $end->format('d/m/Y') }} · {{ $regime === 'caixa' ? 'por data de pagamento' : 'por data do lançamento' }} · cartão pelo vencimento da fatura</p>

    @if ($has('resumo'))
        <table class="grid">
            <tr>
                <td><div class="label">Receitas</div><div class="value income">{{ $fmt($totalIncome) }}</div></td>
                <td><div class="label">Despesas</div><div class="value expense">{{ $fmt($totalExpense) }}</div></td>
                <td><div class="label">Balanço</div><div class="value">{{ $fmt($totalIncome - $totalExpense) }}</div></td>
                <td><div class="label">Saldo geral atual</div><div class="value">{{ $fmt($balance) }}</div></td>
            </tr>
        </table>
    @endif

    @if ($has('saldo') && $history)
        <h2>{{ $titles['saldo'] }}</h2>
        <table class="list">
            <tr>
                <th>Mês</th>
                <th class="right">Total</th>
                @foreach ($history['accounts'] as $acc)
                    <th class="right"><span class="dot" style="background: {{ $acc['color'] }}"></span>{{ $acc['name'] }}</th>
                @endforeach
            </tr>
            @foreach ($history['months'] as $m)
                <tr>
                    <td>{{ $m['label'] }}</td>
                    <td class="right {{ $m['balance'] < 0 ? 'expense' : '' }}"><strong>{{ $fmt($m['balance']) }}</strong></td>
                    @foreach ($history['accounts'] as $acc)
                        @php($v = $m['accounts'][$acc['id']] ?? 0)
                        <td class="right {{ $v < 0 ? 'expense' : '' }}">{{ $fmt($v) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
        <p class="muted">Saldo efetivado no fim de cada mês (o mês atual mostra o saldo de hoje). O total soma as contas incluídas no saldo geral.</p>
    @endif

    @foreach (['despesas' => $expenses, 'receitas' => $incomes] as $key => $rows)
        @if ($has($key))
            <h2>{{ $titles[$key] }}</h2>
            @if ($rows)
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
            @else
                <p class="muted">Nada no período.</p>
            @endif
        @endif
    @endforeach

    @if ($has('cartoes') && $cards)
        <h2>{{ $titles['cartoes'] }}</h2>
        @if ($cards['cards'])
            <table class="list">
                <tr>
                    <th>Fatura (vencimento)</th>
                    @foreach ($cards['cards'] as $card)
                        <th class="right"><span class="dot" style="background: {{ $card['color'] }}"></span>{{ $card['name'] }}</th>
                    @endforeach
                    <th class="right">Total</th>
                </tr>
                @foreach ($cards['months'] as $i => $key)
                    <tr>
                        <td>{{ $monthLabel(\App\Support\Month::parse($key)) }}</td>
                        @foreach ($cards['cards'] as $card)
                            <td class="right">{{ $fmt($card['totals'][$i]) }}</td>
                        @endforeach
                        <td class="right"><strong>{{ $fmt(array_sum(array_map(fn ($c) => $c['totals'][$i], $cards['cards']))) }}</strong></td>
                    </tr>
                @endforeach
            </table>
        @else
            <p class="muted">Nenhuma fatura no período.</p>
        @endif
    @endif

    @if ($has('investimentos') && $investments)
        <h2>{{ $titles['investimentos'] }} (posição atual)</h2>
        @if ($investments['assets']->isNotEmpty())
            <table class="list">
                <tr><th>Ativo</th><th>Carteira</th><th>Tipo</th><th class="right">Aplicado</th><th class="right">Valor atual</th><th class="right">Rentabilidade</th></tr>
                @foreach ($investments['assets'] as $asset)
                    @php($st = $investments['stats'][$asset->id])
                    <tr>
                        <td>{{ $asset->name }}</td>
                        <td class="muted">{{ $asset->portfolio?->name }}</td>
                        <td class="muted">{{ $asset->classLabel() }}</td>
                        <td class="right">{{ $fmt($st['invested']) }}</td>
                        <td class="right">{{ $fmt($asset->current_value) }}</td>
                        <td class="right {{ $st['gain'] >= 0 ? 'income' : 'expense' }}">{{ $fmt($st['gain']) }}{{ $st['pct'] !== null ? ' ('.number_format($st['pct'], 2, ',', '.').'%)' : '' }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="3">Total</td>
                    <td class="right">{{ $fmt($investments['total']['invested']) }}</td>
                    <td class="right">{{ $fmt($investments['total']['current']) }}</td>
                    <td class="right">{{ $fmt($investments['total']['gain']) }}{{ $investments['total']['pct'] !== null ? ' ('.number_format($investments['total']['pct'], 2, ',', '.').'%)' : '' }}</td>
                </tr>
            </table>
        @else
            <p class="muted">Nenhum investimento cadastrado.</p>
        @endif
    @endif

    @if ($has('lancamentos'))
        <h2>{{ $titles['lancamentos'] }} ({{ $transactions->count() }})</h2>
        <table class="list">
            <tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Conta/Cartão</th><th class="right">Valor</th></tr>
            @foreach ($transactions as $t)
                <tr>
                    <td>{{ $t->date->format('d/m/Y') }}</td>
                    <td>{{ $t->description }}{{ $t->installmentLabel() ? ' ('.$t->installmentLabel().')' : '' }}{{ $t->isPaid() ? '' : ' · pendente' }}</td>
                    <td class="muted">{{ $t->category->name ?? ($t->is_adjustment ? 'Ajuste' : '—') }}</td>
                    <td class="muted">{{ $t->account->name ?? ($t->creditCard ? 'Cartão '.$t->creditCard->name.($t->invoice ? ' · fatura '.$t->invoice->shortLabel() : '') : '') }}</td>
                    <td class="right {{ $t->type === 'income' ? 'income' : 'expense' }}">{{ $fmt($t->signedAmount()) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="footer">Gerado pelo Financee em {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
