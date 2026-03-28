<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\LogbookEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Tracking report: list of documents with current status, current holder, last movement.
     * Params: date_from, date_to (filter by document created_at or last movement), document_type_id, status.
     * Optional: format=csv for CSV download.
     */
    public function tracking(Request $request): JsonResponse|StreamedResponse|Response
    {
        $query = Document::with([
            'documentType:id,name,code',
            'currentHolder:id,name,email,section_unit,designation_position',
            'logbookEntries' => fn ($q) => $q->with('user:id,name,email,section_unit,designation_position')
                ->orderByDesc('moved_at')
                ->limit(1),
        ]);

        if ($request->filled('document_type_id')) {
            $query->where('document_type_id', $request->document_type_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $documents = $query->orderByDesc('created_at')->get();

        if ($request->get('format') === 'csv') {
            return $this->trackingCsv($documents);
        }
        if ($request->get('format') === 'xlsx') {
            return $this->trackingXlsx($documents);
        }
        if ($request->get('format') === 'pdf') {
            return $this->trackingPdf($documents);
        }

        $data = $documents->map(function (Document $doc) {
            $lastEntry = $doc->logbookEntries->first();

            return [
                'id' => $doc->id,
                'control_number' => $doc->control_number,
                'document_type' => $doc->documentType ? $doc->documentType->only(['id', 'name', 'code']) : null,
                'document_type_other' => $doc->document_type_other,
                'status' => $doc->status,
                'current_holder' => $doc->currentHolder ? $doc->currentHolder->only(['id', 'name', 'email', 'section_unit', 'designation_position']) : null,
                'last_movement' => $lastEntry ? [
                    'action' => $lastEntry->action,
                    'moved_at' => $lastEntry->moved_at?->toIso8601String(),
                    'user' => $lastEntry->user ? $lastEntry->user->only(['id', 'name', 'email']) : null,
                ] : null,
                'created_at' => $doc->created_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Document history / logbook report: In/Out entries with who, when, details.
     * Params (all optional): document_id, control_number, date_from, date_to.
     * When none provided, returns all logbook entries. Filters are applied when provided.
     * Optional: format=csv for CSV download.
     */
    public function documentHistory(Request $request): JsonResponse|StreamedResponse|Response
    {
        $query = LogbookEntry::with(['document.documentType', 'user']);

        if ($request->filled('document_id')) {
            $query->where('document_id', $request->document_id);
        }
        if ($request->filled('control_number')) {
            $doc = Document::where('control_number', $request->control_number)->first();
            if ($doc) {
                $query->where('document_id', $doc->id);
            } else {
                $query->whereRaw('1 = 0'); // no match
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('moved_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('moved_at', '<=', $request->date_to);
        }

        $entries = $query->orderBy('moved_at', 'asc')->get();

        if ($request->get('format') === 'csv') {
            return $this->documentHistoryCsv($entries);
        }
        if ($request->get('format') === 'xlsx') {
            return $this->documentHistoryXlsx($entries);
        }
        if ($request->get('format') === 'pdf') {
            return $this->documentHistoryPdf($entries);
        }

        $data = $entries->map(fn (LogbookEntry $e) => [
            'id' => $e->id,
            'document_id' => $e->document_id,
            'control_number' => $e->document?->control_number,
            'document_type' => $e->document ? $e->document->documentTypeLabel() : null,
            'action' => $e->action,
            'user' => $e->user ? $e->user->only(['id', 'name', 'email', 'section_unit', 'designation_position']) : null,
            'moved_at' => $e->moved_at?->toIso8601String(),
            'remarks' => $e->remarks,
            'registration_details' => $e->registration_details,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Personnel-only view: the signed-in user's In/Out logbook rows with each document's
     * current holder (custody), plus documents currently in this user's hands.
     * Query: optional date_from, date_to (applied to transaction moved_at only).
     */
    public function personnelHistory(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $userId = (int) $request->user()->id;

        $txQuery = LogbookEntry::query()
            ->with([
                'document.documentType',
                'document.currentHolder:id,name,email,section_unit,designation_position',
            ])
            ->where('user_id', $userId);

        if ($request->filled('date_from')) {
            $txQuery->whereDate('moved_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $txQuery->whereDate('moved_at', '<=', $request->date_to);
        }

        $entries = $txQuery->orderByDesc('moved_at')->get();

        $transactions = $entries->map(function (LogbookEntry $e) use ($userId) {
            $doc = $e->document;
            $holder = $doc?->currentHolder;

            return [
                'id' => $e->id,
                'document_id' => $e->document_id,
                'control_number' => $doc?->control_number,
                'document_type' => $doc?->documentType ? $doc->documentType->only(['id', 'name', 'code']) : null,
                'document_type_other' => $doc?->document_type_other,
                'document_status' => $doc?->status,
                'action' => $e->action,
                'moved_at' => $e->moved_at?->toIso8601String(),
                'remarks' => $e->remarks,
                'registration_details' => $e->registration_details,
                'current_holder' => $holder ? $holder->only(['id', 'name', 'email', 'section_unit', 'designation_position']) : null,
                'you_are_current_holder' => $doc !== null && (int) ($doc->current_holder_user_id ?? 0) === $userId,
            ];
        });

        $holdingDocs = Document::query()
            ->with([
                'documentType:id,name,code',
                'createdBy:id,name,email,section_unit,designation_position',
                'currentHolder:id,name,email,section_unit,designation_position',
            ])
            ->where('current_holder_user_id', $userId)
            ->orderByDesc('updated_at')
            ->get();

        $currently_holding = $holdingDocs->map(function (Document $d) {
            return [
                'id' => $d->id,
                'control_number' => $d->control_number,
                'description' => $d->description,
                'status' => $d->status,
                'document_type' => $d->documentType ? $d->documentType->only(['id', 'name', 'code']) : null,
                'document_type_other' => $d->document_type_other,
                'created_by' => $d->createdBy ? $d->createdBy->only(['id', 'name', 'email', 'section_unit', 'designation_position']) : null,
                'updated_at' => $d->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'transactions' => $transactions,
            'currently_holding' => $currently_holding,
        ]);
    }

    /**
     * Accountability report: who handled what and when (for a user and date range).
     * Params (all optional): user_id, date_from, date_to. When none provided, returns all logbook entries.
     * Optional: format=csv.
     */
    public function accountability(Request $request): JsonResponse|StreamedResponse|Response
    {
        $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $query = LogbookEntry::with(['document.documentType', 'user']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('moved_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('moved_at', '<=', $request->date_to);
        }

        $entries = $query->orderBy('moved_at', 'asc')->get();

        if ($request->get('format') === 'csv') {
            return $this->accountabilityCsv($entries);
        }
        if ($request->get('format') === 'xlsx') {
            return $this->accountabilityXlsx($entries);
        }
        if ($request->get('format') === 'pdf') {
            return $this->accountabilityPdf($entries);
        }

        $data = $entries->map(fn (LogbookEntry $e) => [
            'id' => $e->id,
            'document_id' => $e->document_id,
            'control_number' => $e->document?->control_number,
            'document_type' => $e->document ? $e->document->documentTypeLabel() : null,
            'action' => $e->action,
            'user' => $e->user ? $e->user->only(['id', 'name', 'email', 'section_unit', 'designation_position']) : null,
            'moved_at' => $e->moved_at?->toIso8601String(),
            'remarks' => $e->remarks,
            'registration_details' => $e->registration_details,
        ]);

        return response()->json(['data' => $data]);
    }

    private function trackingCsv($documents): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="tracking-report-'.date('Y-m-d-His').'.csv"',
        ];

        return response()->streamDownload(function () use ($documents) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Control Number', 'Document Type', 'Status', 'Current Holder', 'Last Action', 'Last Moved At', 'Created At']);
            foreach ($documents as $doc) {
                $last = $doc->logbookEntries->first();
                fputcsv($out, [
                    $doc->control_number,
                    $doc->documentTypeLabel(),
                    $doc->status,
                    $doc->currentHolder?->name ?? '',
                    $last?->action ?? '',
                    $last?->moved_at?->format('Y-m-d H:i:s') ?? '',
                    $doc->created_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }
            fclose($out);
        }, 'tracking-report-'.date('Y-m-d-His').'.csv', $headers);
    }

    private function documentHistoryCsv($entries): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="document-history-'.date('Y-m-d-His').'.csv"',
        ];

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Control Number', 'Document Type', 'Action', 'User', 'Moved At', 'Remarks']);
            foreach ($entries as $e) {
                fputcsv($out, [
                    $e->document?->control_number ?? '',
                    $e->document ? $e->document->documentTypeLabel() : '',
                    $e->action,
                    $e->user?->name ?? '',
                    $e->moved_at?->format('Y-m-d H:i:s') ?? '',
                    $e->remarks ?? '',
                ]);
            }
            fclose($out);
        }, 'document-history-'.date('Y-m-d-His').'.csv', $headers);
    }

    private function accountabilityCsv($entries): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="accountability-report-'.date('Y-m-d-His').'.csv"',
        ];

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Control Number', 'Document Type', 'Action', 'User', 'Moved At', 'Remarks']);
            foreach ($entries as $e) {
                fputcsv($out, [
                    $e->document?->control_number ?? '',
                    $e->document ? $e->document->documentTypeLabel() : '',
                    $e->action,
                    $e->user?->name ?? '',
                    $e->moved_at?->format('Y-m-d H:i:s') ?? '',
                    $e->remarks ?? '',
                ]);
            }
            fclose($out);
        }, 'accountability-report-'.date('Y-m-d-His').'.csv', $headers);
    }

    private function trackingXlsx($documents): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tracking');
        $headers = ['Control Number', 'Document Type', 'Status', 'Current Holder', 'Last Action', 'Last Moved At', 'Created At'];
        $sheet->fromArray($headers, null, 'A1');
        $row = 2;
        foreach ($documents as $doc) {
            $last = $doc->logbookEntries->first();
            $sheet->fromArray([
                $doc->control_number,
                $doc->documentTypeLabel(),
                $doc->status,
                $doc->currentHolder?->name ?? '',
                $last?->action ?? '',
                $last?->moved_at?->format('Y-m-d H:i:s') ?? '',
                $doc->created_at?->format('Y-m-d H:i:s') ?? '',
            ], null, 'A'.$row);
            $row++;
        }

        $filename = 'tracking-report-'.date('Y-m-d-His').'.xlsx';

        return $this->streamXlsx($spreadsheet, $filename);
    }

    private function documentHistoryXlsx($entries): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Document History');
        $sheet->fromArray(['Control Number', 'Document Type', 'Action', 'User', 'Moved At', 'Remarks'], null, 'A1');
        $row = 2;
        foreach ($entries as $e) {
            $sheet->fromArray([
                $e->document?->control_number ?? '',
                $e->document ? $e->document->documentTypeLabel() : '',
                $e->action,
                $e->user?->name ?? '',
                $e->moved_at?->format('Y-m-d H:i:s') ?? '',
                $e->remarks ?? '',
            ], null, 'A'.$row);
            $row++;
        }

        $filename = 'document-history-'.date('Y-m-d-His').'.xlsx';

        return $this->streamXlsx($spreadsheet, $filename);
    }

    private function accountabilityXlsx($entries): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Accountability');
        $sheet->fromArray(['Control Number', 'Document Type', 'Action', 'User', 'Moved At', 'Remarks'], null, 'A1');
        $row = 2;
        foreach ($entries as $e) {
            $sheet->fromArray([
                $e->document?->control_number ?? '',
                $e->document ? $e->document->documentTypeLabel() : '',
                $e->action,
                $e->user?->name ?? '',
                $e->moved_at?->format('Y-m-d H:i:s') ?? '',
                $e->remarks ?? '',
            ], null, 'A'.$row);
            $row++;
        }

        $filename = 'accountability-report-'.date('Y-m-d-His').'.xlsx';

        return $this->streamXlsx($spreadsheet, $filename);
    }

    private function trackingPdf($documents): Response|JsonResponse
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response()->json(['message' => 'PDF export is not available. Install: composer require barryvdh/laravel-dompdf'], 503);
        }
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.tracking', [
            'documents' => $documents,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]);

        return $pdf->download('tracking-report-'.date('Y-m-d-His').'.pdf');
    }

    private function documentHistoryPdf($entries): Response|JsonResponse
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response()->json(['message' => 'PDF export is not available. Install: composer require barryvdh/laravel-dompdf'], 503);
        }
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.document-history', [
            'entries' => $entries,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]);

        return $pdf->download('document-history-'.date('Y-m-d-His').'.pdf');
    }

    private function accountabilityPdf($entries): Response|JsonResponse
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response()->json(['message' => 'PDF export is not available. Install: composer require barryvdh/laravel-dompdf'], 503);
        }
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.accountability', [
            'entries' => $entries,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]);

        return $pdf->download('accountability-report-'.date('Y-m-d-His').'.pdf');
    }

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
