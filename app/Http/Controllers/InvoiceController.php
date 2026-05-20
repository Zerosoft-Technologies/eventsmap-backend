<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * GET /api/invoices
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $perPage = (int) $request->input('per_page', 15);

        $invoices = Invoice::query()
            ->forUser($request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => InvoiceResource::collection($invoices->items()),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'total_pages' => $invoices->lastPage(),
                'has_more' => $invoices->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /api/invoices/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->forUser($request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * GET /api/invoices/{id}/download
     */
    public function download(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $invoice = Invoice::query()
            ->forUser($request->user()->id)
            ->findOrFail($id);

        $disk = config('invoice.storage_disk', 'public');

        if (! $invoice->invoice_pdf_path || ! Storage::disk($disk)->exists($invoice->invoice_pdf_path)) {
            try {
                $path = $this->invoiceService->generatePdf($invoice);
                $invoice->update(['invoice_pdf_path' => $path]);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice PDF could not be generated.',
                ], 500);
            }
        }

        return Storage::disk($disk)->download(
            $invoice->invoice_pdf_path,
            $invoice->invoice_number.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
