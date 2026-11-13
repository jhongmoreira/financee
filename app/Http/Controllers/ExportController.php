<?php

namespace App\Http\Controllers;

use App\Livewire\Reports\Index as ReportsIndex;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\InstallmentPlan;
use App\Models\InvestmentAsset;
use App\Models\InvestmentMovement;
use App\Models\InvestmentPortfolio;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\InvestmentService;
use App\Services\MonthSummary;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController
{
    public function __construct(private ReportService $reports) {}

    /** Lançamentos do período em CSV (Excel pt-BR: BOM UTF-8, ";" e vírgula decimal). */
    public function csv(Request $request): StreamedResponse
    {
        [$start, $end, $regime] = $this->range($request);
        $query = $this->reports->transactionsQuery($start, $end, $regime);
        $filename = 'financee-lancamentos-'.$start->format('Ymd').'-'.$end->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Data', 'Descrição', 'Categoria', 'Subcategoria', 'Conta/Cartão', 'Fatura', 'Tipo', 'Valor', 'Situação', 'Parcela', 'Observação'], ';');

            $query->chunk(500, function ($transactions) use ($out) {
                foreach ($transactions as $t) {
                    /** @var Transaction $t */
                    $category = $t->category;
                    $root = $category->parent ?? $category;
                    fputcsv($out, [
                        $t->date->format('d/m/Y'),
                        $t->description,
                        $root->name ?? ($t->is_adjustment ? 'Ajuste de saldo' : ''),
                        $category && $category->parent_id ? $category->name : '',
                        $t->account->name ?? ($t->creditCard ? 'Cartão '.$t->creditCard->name : ''),
                        $t->invoice ? $t->invoice->shortLabel().' (vence '.$t->invoice->due_date->format('d/m/Y').')' : '',
                        $t->isCard() && $t->type === 'income' ? 'Estorno' : ($t->type === 'income' ? 'Receita' : 'Despesa'),
                        number_format($t->signedAmount() / 100, 2, ',', ''),
                        $t->isPaid() ? 'Efetivado' : 'Pendente',
                        (string) $t->installmentLabel(),
                        (string) $t->notes,
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Relatório do período em PDF, com as seções escolhidas (padrão: todas). */
    public function pdf(Request $request): Response
    {
        $data = $this->pdfData($request);
        [$start, $end] = [$data['start'], $data['end']];

        // Muitas contas no saldo: página deitada para caber as colunas
        $landscape = $data['history'] && count($data['history']['accounts']) > 4;

        $pdf = Pdf::loadView('exports.report', $data)->setPaper('a4', $landscape ? 'landscape' : 'portrait')
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download('financee-relatorio-'.$start->format('Ymd').'-'.$end->format('Ymd').'.pdf');
    }

    /**
     * Dados do relatório em PDF (separado para poder ser testado).
     *
     * @return array<string, mixed>
     */
    public function pdfData(Request $request): array
    {
        $summary = app(MonthSummary::class);
        $investments = app(InvestmentService::class);
        [$start, $end, $regime] = $this->range($request);

        $all = array_keys(ReportsIndex::PDF_SECTIONS);
        $asked = array_filter(explode(',', $request->string('secoes')->toString()));
        $sections = $asked ? array_values(array_intersect($all, $asked)) : $all;
        $has = fn (string $s) => in_array($s, $sections, true);

        $expenses = $has('resumo') || $has('despesas') ? $this->reports->byCategory('expense', $start, $end, $regime) : [];
        $incomes = $has('resumo') || $has('receitas') ? $this->reports->byCategory('income', $start, $end, $regime) : [];

        $data = [
            'user' => Auth::user(),
            'start' => $start,
            'end' => $end,
            'regime' => $regime,
            'sections' => $sections,
            'titles' => ReportsIndex::PDF_SECTIONS,
            'expenses' => $expenses,
            'incomes' => $incomes,
            'totalIncome' => (int) array_sum(array_column($incomes, 'total')),
            'totalExpense' => (int) array_sum(array_column($expenses, 'total')),
            'balance' => $summary->currentTotal(),
            'history' => $has('saldo') ? $this->reports->balanceHistory($start, $end->min(CarbonImmutable::today())) : null,
            'cards' => $has('cartoes') ? $this->reports->invoicesByCard($start, $end) : null,
            'transactions' => $has('lancamentos') ? $this->reports->transactionsQuery($start, $end, $regime)->limit(2000)->get() : collect(),
            'investments' => null,
        ];

        if ($has('investimentos')) {
            $assets = InvestmentAsset::query()->whereNull('archived_at')->with('portfolio')
                ->whereHas('portfolio', fn ($q) => $q->whereNull('archived_at'))->orderBy('name')->get();
            $stats = $investments->stats($assets);
            $data['investments'] = ['assets' => $assets, 'stats' => $stats, 'total' => $investments->sum($stats)];
        }

        return $data;
    }

    /** Todos os dados do usuário em JSON (backup pessoal / LGPD). */
    public function backup(): StreamedResponse
    {
        $user = Auth::user();

        return response()->streamDownload(function () use ($user) {
            echo json_encode([
                'exported_at' => now()->toIso8601String(),
                'app' => 'Financee',
                'note' => 'Valores em centavos. Datas no formato AAAA-MM-DD.',
                'user' => ['name' => $user?->name, 'email' => $user?->email],
                'accounts' => Account::all(),
                'credit_cards' => CreditCard::all(),
                'categories' => Category::all(),
                'transactions' => Transaction::all(),
                'transfers' => Transfer::all(),
                'invoices' => Invoice::all(),
                'invoice_payments' => InvoicePayment::all(),
                'installment_plans' => InstallmentPlan::all(),
                'recurrences' => Recurrence::all(),
                'budgets' => Budget::all(),
                'investment_portfolios' => InvestmentPortfolio::all(),
                'investment_assets' => InvestmentAsset::all(),
                'investment_movements' => InvestmentMovement::all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, 'financee-backup-'.now()->format('Ymd-His').'.json', ['Content-Type' => 'application/json']);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    private function range(Request $request): array
    {
        [$start, $end] = ReportService::presetRange('custom', $request->string('inicio')->toString(), $request->string('fim')->toString());
        if ($start->diffInYears($end) > 5) {
            $start = $end->subYears(5);
        }
        $regime = $request->string('regime')->toString() === 'caixa' ? 'caixa' : 'competencia';

        return [$start, $end, $regime];
    }
}
