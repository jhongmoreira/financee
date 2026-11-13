<?php

namespace App\Http\Controllers;

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
            fputcsv($out, ['Data', 'Descrição', 'Categoria', 'Subcategoria', 'Conta/Cartão', 'Tipo', 'Valor', 'Situação', 'Parcela', 'Observação'], ';');

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
                        $t->type === 'income' ? 'Receita' : 'Despesa',
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

    /** Relatório do período em PDF. */
    public function pdf(Request $request, MonthSummary $summary): Response
    {
        [$start, $end, $regime] = $this->range($request);

        $transactions = $this->reports->transactionsQuery($start, $end, $regime)->limit(2000)->get();
        $expenses = $this->reports->byCategory('expense', $start, $end, $regime);
        $incomes = $this->reports->byCategory('income', $start, $end, $regime);

        $pdf = Pdf::loadView('exports.report', [
            'user' => Auth::user(),
            'start' => $start,
            'end' => $end,
            'regime' => $regime,
            'transactions' => $transactions,
            'expenses' => $expenses,
            'incomes' => $incomes,
            'totalIncome' => (int) array_sum(array_column($incomes, 'total')),
            'totalExpense' => (int) array_sum(array_column($expenses, 'total')),
            'balance' => $summary->currentTotal(),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true);

        return $pdf->download('financee-relatorio-'.$start->format('Ymd').'-'.$end->format('Ymd').'.pdf');
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
