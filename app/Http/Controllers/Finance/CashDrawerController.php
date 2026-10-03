<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Services\ActivityLogger;
use App\Services\Finance\CashFlowService;
use App\Support\ExportSanitizer;
use App\Support\ShopTime;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashDrawerController extends Controller
{
    public function __construct(private readonly CashFlowService $cashFlow) {}

    public function index(Request $request)
    {
        $ownerId = $this->ownerId();
        $period = $this->cashFlow->resolvePeriod(
            $ownerId,
            $request->string('preset')->toString(),
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );
        $data = $this->cashFlow->cashDrawerData(
            $ownerId,
            $period['from_date'],
            $period['to_date'],
            $request->string('method', 'cash')->toString() ?: 'cash',
        );
        $perPage = max(25, min(100, $request->integer('per_page') ?: 50));
        $page = LengthAwarePaginator::resolveCurrentPage();
        $rows = $data['rows'];
        $data['rows'] = new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('finance.cash-drawer.index', [
            'period' => $period,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:opening,in,out',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'note' => 'nullable|string|max:2000',
            'occurred_at' => 'required|date',
        ]);

        $movement = CashMovement::create([
            'user_id' => $this->ownerId(),
            'type' => $request->string('type')->toString(),
            'amount' => $request->input('amount'),
            'reason' => $request->string('reason')->toString(),
            'note' => $request->string('note')->toString() ?: null,
            'occurred_at' => \Carbon\Carbon::parse(
                $request->string('occurred_at')->toString(),
                ShopTime::timezone($this->ownerId()),
            )->utc(),
            'created_by' => auth()->id(),
        ]);

        ActivityLogger::record(
            'created',
            'cash_movement',
            $movement,
            ['type' => $movement->type, 'reason' => $movement->reason],
            (float) $movement->amount,
            $movement->reason,
            $this->ownerId(),
        );

        return back()->with('success', __('finance.cash_drawer.saved_successfully'));
    }

    public function destroy(CashMovement $cashMovement)
    {
        abort_unless((int) $cashMovement->user_id === $this->ownerId(), 403);

        ActivityLogger::record(
            'deleted',
            'cash_movement',
            $cashMovement,
            ['type' => $cashMovement->type, 'reason' => $cashMovement->reason],
            (float) $cashMovement->amount,
            $cashMovement->reason,
            $this->ownerId(),
        );

        $cashMovement->delete();

        return back()->with('success', __('finance.cash_drawer.deleted_successfully'));
    }

    public function export(Request $request)
    {
        $ownerId = $this->ownerId();
        $period = $this->cashFlow->resolvePeriod(
            $ownerId,
            $request->string('preset')->toString(),
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );
        $format = $request->string('format', 'csv')->toString();
        $data = $this->cashFlow->cashDrawerData(
            $ownerId,
            $period['from_date'],
            $period['to_date'],
            $request->string('method', 'cash')->toString() ?: 'cash',
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cash Drawer');
        $sheet->fromArray([
            [
                __('finance.common.date'),
                __('finance.cash_drawer.category'),
                __('finance.cash_drawer.method'),
                __('finance.cash_drawer.document'),
                __('finance.common.user'),
                __('finance.cash_drawer.amount_in'),
                __('finance.cash_drawer.amount_out'),
                __('finance.cash_drawer.running_balance'),
            ],
        ]);

        $rowNumber = 2;
        foreach ($data['rows'] as $row) {
            ExportSanitizer::writeString($sheet, 'A' . $rowNumber, ShopTime::local($row['occurred_at'], $ownerId)->format('Y-m-d H:i'));
            ExportSanitizer::writeString($sheet, 'B' . $rowNumber, __('finance.cash_drawer.categories.' . $row['category']));
            ExportSanitizer::writeString($sheet, 'C' . $rowNumber, __('finance.methods.' . $row['method']));
            ExportSanitizer::writeString($sheet, 'D' . $rowNumber, $row['document_label']);
            ExportSanitizer::writeString($sheet, 'E' . $rowNumber, $row['actor_name']);
            $sheet->setCellValue('F' . $rowNumber, $row['amount_in']);
            $sheet->setCellValue('G' . $rowNumber, $row['amount_out']);
            $sheet->setCellValue('H' . $rowNumber, $row['running_balance']);
            $rowNumber++;
        }

        $writer = $format === 'xlsx' ? new Xlsx($spreadsheet) : new Csv($spreadsheet);
        $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
        $contentType = $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="cash-drawer.' . $extension . '"',
        ]);
    }

    private function ownerId(): int
    {
        return (int) auth()->user()->ownerId();
    }
}
