<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminInvoiceListRequest;
use App\Http\Resources\Admin\AdminInvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Super-admin: view all premium purchase invoices (PDF receipts).
 */
class AdminInvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * GET /api/admin/invoices/stats
     */
    public function stats(): JsonResponse
    {
        $paidQuery = Invoice::query()->where('payment_status', Invoice::PAYMENT_PAID);

        $totalsByCurrency = (clone $paidQuery)
            ->select('currency', DB::raw('SUM(total_amount) as revenue_cents'), DB::raw('COUNT(*) as invoice_count'))
            ->groupBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => strtoupper((string) $row->currency),
                'revenue_cents' => (int) $row->revenue_cents,
                'invoice_count' => (int) $row->invoice_count,
            ])
            ->values()
            ->all();

        $now = now();

        return response()->json([
            'success' => true,
            'message' => 'Invoice stats fetched successfully',
            'data' => [
                'total_invoices' => Invoice::query()->count(),
                'paid_invoices' => Invoice::query()->where('payment_status', Invoice::PAYMENT_PAID)->count(),
                'pending_invoices' => Invoice::query()->where('payment_status', Invoice::PAYMENT_PENDING)->count(),
                'failed_invoices' => Invoice::query()->where('payment_status', Invoice::PAYMENT_FAILED)->count(),
                'revenue_by_currency' => $totalsByCurrency,
                'invoices_this_month' => Invoice::query()
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->count(),
                'paid_revenue_this_month_cents' => (int) (clone $paidQuery)
                    ->whereMonth('paid_at', $now->month)
                    ->whereYear('paid_at', $now->year)
                    ->sum('total_amount'),
                'invoices_last_month' => Invoice::query()
                    ->whereMonth('created_at', $now->copy()->subMonth()->month)
                    ->whereYear('created_at', $now->copy()->subMonth()->year)
                    ->count(),
                'paid_revenue_last_month_cents' => (int) (clone $paidQuery)
                    ->whereMonth('paid_at', $now->copy()->subMonth()->month)
                    ->whereYear('paid_at', $now->copy()->subMonth()->year)
                    ->sum('total_amount'),
            ],
        ]);
    }

    /**
     * GET /api/admin/invoices
     */
    public function index(AdminInvoiceListRequest $request): JsonResponse
    {
        $query = $this->filteredInvoiceQuery($request)
            ->with(['user:id,name,email,account_type']);

        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        $invoices = $query->paginate($request->perPage());

        return response()->json([
            'success' => true,
            'message' => 'Invoices fetched successfully',
            'data' => [
                'invoices' => AdminInvoiceResource::collection($invoices->items()),
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/invoices/{id}
     */
    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->with(['user:id,name,email,account_type,profile_type,country', 'subscription:id,stripe_subscription_id,status'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Invoice fetched successfully',
            'data' => new AdminInvoiceResource($invoice),
        ]);
    }

    /**
     * GET /api/admin/invoices/{id}/download
     */
    public function download(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($id);

        $disk = config('invoice.storage_disk', 'public');

        if (! $invoice->invoice_pdf_path || ! Storage::disk($disk)->exists($invoice->invoice_pdf_path)) {
            try {
                $path = $this->invoiceService->generatePdf($invoice);
                $invoice->update(['invoice_pdf_path' => $path]);
            } catch (\Throwable) {
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

    /**
     * @return Builder<Invoice>
     */
    private function filteredInvoiceQuery(AdminInvoiceListRequest $request): Builder
    {
        $query = Invoice::query();

        $query->when($request->filled('user_id'), function (Builder $q) use ($request) {
            $q->where('user_id', (int) $request->input('user_id'));
        });

        $query->when($request->filled('payment_status'), function (Builder $q) use ($request) {
            $q->where('payment_status', $request->input('payment_status'));
        });

        $query->when($request->filled('status'), function (Builder $q) use ($request) {
            $q->where('status', $request->input('status'));
        });

        $query->when($request->filled('currency'), function (Builder $q) use ($request) {
            $q->where('currency', strtolower((string) $request->input('currency')));
        });

        $query->when($request->filled('paid_from'), function (Builder $q) use ($request) {
            $q->whereDate('paid_at', '>=', $request->input('paid_from'));
        });

        $query->when($request->filled('paid_to'), function (Builder $q) use ($request) {
            $q->whereDate('paid_at', '<=', $request->input('paid_to'));
        });

        $query->when($request->filled('created_from'), function (Builder $q) use ($request) {
            $q->whereDate('created_at', '>=', $request->input('created_from'));
        });

        $query->when($request->filled('created_to'), function (Builder $q) use ($request) {
            $q->whereDate('created_at', '<=', $request->input('created_to'));
        });

        $query->when($request->filled('search'), function (Builder $q) use ($request) {
            $search = $request->input('search');
            $op = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $pattern = '%'.$search.'%';

            $q->where(function (Builder $sub) use ($pattern, $op) {
                $sub->where('invoice_number', $op, $pattern)
                    ->orWhere('order_id', $op, $pattern)
                    ->orWhere('billing_email', $op, $pattern)
                    ->orWhere('billing_name', $op, $pattern)
                    ->orWhereHas('user', function (Builder $userQ) use ($pattern, $op) {
                        $userQ->where('email', $op, $pattern)
                            ->orWhere('name', $op, $pattern);
                    });
            });
        });

        return $query;
    }
}
