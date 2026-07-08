<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\Concerns\InteractsWithTirtaAreaScope;
use App\Http\Controllers\Controller;
use App\Models\Tirta\BillingInvoice;
use App\Models\Tirta\BillingPeriod;
use App\Models\Tirta\MeterReading;
use App\Models\Tirta\MeterReadingPeriod;
use App\Models\Tirta\ServiceConnection;
use App\Models\User;
use App\Services\Tirta\TirtaBillingCalculator;
use App\Services\Tirta\TirtaBillingPeriodPlanner;
use App\Services\Tirta\TirtaConnectionLifecycleService;
use App\Services\Tirta\TirtaInvoicePaymentService;
use App\Services\Tirta\TirtaPenaltyCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BaseFeature\Models\TenantSetting;

class TirtaBillingController extends Controller
{
    use InteractsWithTirtaAreaScope;

    public function __construct(
        protected TirtaBillingCalculator $billingCalculator,
        protected TirtaBillingPeriodPlanner $billingPeriodPlanner,
        protected TirtaConnectionLifecycleService $connectionLifecycleService,
        protected TirtaInvoicePaymentService $invoicePaymentService,
        protected TirtaPenaltyCalculator $penaltyCalculator,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();

        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();
        $canManageBilling = $user instanceof User && $user->canManageTirtaBilling();
        $canRecordPayment = $user instanceof User && $user->canRecordTirtaBillingPayment();

        $tenantSetting = $this->tenantSetting();
        $disconnectionReport = $this->connectionLifecycleService->auditAndDisconnectOverdueConnections($tenantSetting, now());
        $autoDraftedPeriods = $this->billingPeriodPlanner->syncDraftPeriods($tenantSetting);

        $billingPeriods = BillingPeriod::query()
            ->with(['meterReadingPeriod', 'invoices'])
            ->withCount('invoices')
            ->orderByDesc('period_start')
            ->orderByDesc('created_at')
            ->get();

        $meterReadingPeriods = MeterReadingPeriod::query()
            ->with('billingPeriod')
            ->withCount('readings')
            ->orderByDesc('period_start')
            ->orderByDesc('created_at')
            ->get();

        $selectedPeriod = $this->selectedBillingPeriod($request, $billingPeriods);
        $allInvoices = $selectedPeriod instanceof BillingPeriod
            ? BillingInvoice::query()
                ->with(['customer', 'connection.serviceArea', 'meterReading', 'lines', 'payments', 'tariffScheme'])
                ->where('billing_period_id', $selectedPeriod->id)
                ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaInvoiceScope($query))
                ->orderBy('invoice_number')
                ->get()
            : collect();
        $paymentSummaries = $allInvoices->mapWithKeys(fn (BillingInvoice $invoice): array => [
            $invoice->id => $this->invoicePaymentService->summary($invoice),
        ])->all();
        $penaltySummaries = $allInvoices->mapWithKeys(fn (BillingInvoice $invoice): array => [
            $invoice->id => $this->penaltyCalculator->calculate(
                $invoice,
                $paymentSummaries[$invoice->id] ?? [],
                $tenantSetting,
                now()
            ),
        ])->all();

        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'bucket' => (string) $request->query('bucket', 'all'),
        ];
        $today = now()->startOfDay();
        $invoices = $allInvoices
            ->when(
                $filters['status'] !== '' && $filters['status'] !== 'all',
                fn (Collection $collection) => $collection->where('status', $filters['status'])
            )
            ->filter(fn (BillingInvoice $invoice) => $this->matchesReceivableBucket($invoice, $filters['bucket'], $today))
            ->values();

        $invoiceStats = [
            'periods' => $billingPeriods->count(),
            'issued' => $allInvoices->where('status', 'issued')->count(),
            'paid' => $allInvoices->where('status', 'paid')->count(),
            'cancelled' => $allInvoices->where('status', 'cancelled')->count(),
            'invoice_total' => (int) $allInvoices->sum('invoice_total'),
            'collected_total' => (int) collect($paymentSummaries)->sum('paid_total'),
            'outstanding_total' => (int) collect($paymentSummaries)->sum('outstanding_total'),
            'estimated_penalty_total' => (int) collect($penaltySummaries)->sum('penalty_amount'),
        ];

        $receivableStats = [
            'open_count' => $allInvoices->where('status', 'issued')->count(),
            'open_total' => (int) $allInvoices
                ->filter(fn (BillingInvoice $invoice) => $invoice->status === 'issued')
                ->sum(fn (BillingInvoice $invoice) => (int) data_get($paymentSummaries, $invoice->id . '.outstanding_total', $invoice->invoice_total)),
            'overdue_count' => $allInvoices
                ->filter(fn (BillingInvoice $invoice) => $this->matchesReceivableBucket($invoice, 'overdue', $today))
                ->count(),
            'overdue_total' => (int) $allInvoices
                ->filter(fn (BillingInvoice $invoice) => $this->matchesReceivableBucket($invoice, 'overdue', $today))
                ->sum(fn (BillingInvoice $invoice) => (int) data_get($paymentSummaries, $invoice->id . '.outstanding_total', $invoice->invoice_total)),
            'due_today_count' => $allInvoices->filter(fn (BillingInvoice $invoice) => $this->matchesReceivableBucket($invoice, 'due_today', $today))->count(),
            'upcoming_count' => $allInvoices->filter(fn (BillingInvoice $invoice) => $this->matchesReceivableBucket($invoice, 'upcoming', $today))->count(),
            'filtered_count' => $invoices->count(),
            'penalty_enabled' => (bool) ($tenantSetting->getAttribute('billing_penalty_enabled') ?? false),
            'penalty_total' => (int) collect($penaltySummaries)->sum('penalty_amount'),
        ];
        $bulkPenaltySummary = $selectedPeriod instanceof BillingPeriod
            ? $this->bulkPenaltySummary($allInvoices, $paymentSummaries, $penaltySummaries)
            : [
                'eligible_count' => 0,
                'eligible_total' => 0,
                'already_posted_count' => 0,
                'blocked_by_payment_count' => 0,
            ];

        $readingSummary = $selectedPeriod?->meterReadingPeriod instanceof MeterReadingPeriod
            ? $this->readingSummaryForBillingPeriod($selectedPeriod->meterReadingPeriod)
            : null;

        return view('basefeature::tirta.billing', [
            'billingPeriods' => $billingPeriods,
            'meterReadingPeriods' => $meterReadingPeriods,
            'selectedPeriod' => $selectedPeriod,
            'invoices' => $invoices,
            'invoiceStats' => $invoiceStats,
            'paymentSummaries' => $paymentSummaries,
            'penaltySummaries' => $penaltySummaries,
            'receivableStats' => $receivableStats,
            'filters' => $filters,
            'readingSummary' => $readingSummary,
            'tenantSetting' => $tenantSetting,
            'autoDraftedPeriods' => $autoDraftedPeriods,
            'bulkPenaltySummary' => $bulkPenaltySummary,
            'disconnectionReport' => $disconnectionReport,
            'areaScopeLabel' => $this->tirtaAreaScopeLabel(),
            'canManageBilling' => $canManageBilling,
            'canRecordPayment' => $canRecordPayment,
        ]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanManageBilling();

        $validated = $this->validatedBillingPeriod($request);
        $period = BillingPeriod::query()->create($validated);

        return redirect()
            ->route('tenant.tirta.billing', ['period' => $period->id])
            ->with('status', 'Periode billing berhasil ditambahkan.');
    }

    public function updatePeriod(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanManageBilling();

        $period = BillingPeriod::query()->findOrFail($id);
        $validated = $this->validatedBillingPeriod($request, $period);
        $this->ensureBillingPeriodEditable($period, $validated);
        $period->fill($validated)->save();

        return $this->redirectToPeriod($period->id, 'Periode billing berhasil diperbarui.');
    }

    public function generateInvoices(string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanManageBilling();

        $period = BillingPeriod::query()->with('meterReadingPeriod')->findOrFail($id);

        if (! $period->meterReadingPeriod instanceof MeterReadingPeriod) {
            throw ValidationException::withMessages([
                'billing_period' => 'Periode billing belum dikaitkan ke periode baca meter.',
            ]);
        }

        if ($period->status === 'closed') {
            throw ValidationException::withMessages([
                'billing_period' => 'Periode billing sudah ditutup dan tidak bisa generate ulang.',
            ]);
        }

        $readingPeriod = $period->meterReadingPeriod;
        $readings = MeterReading::query()
            ->with(['connection.customer', 'connection.tariffScheme.tiers'])
            ->where('meter_reading_period_id', $readingPeriod->id)
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $query->whereHas('connection', fn ($connectionQuery) => $this->applyTirtaConnectionScope($connectionQuery)))
            ->orderBy('service_connection_id')
            ->get();

        if ($readings->isEmpty()) {
            throw ValidationException::withMessages([
                'billing_period' => 'Belum ada pembacaan meter pada periode yang dipilih.',
            ]);
        }

        $generatedCount = 0;
        $skipped = [];

        DB::connection('tenant')->transaction(function () use ($period, $readings, &$generatedCount, &$skipped): void {
            /** @var MeterReading $reading */
            foreach ($readings as $reading) {
                $connection = $reading->connection;

                if (! $connection instanceof ServiceConnection) {
                    $skipped[] = 'Ada pembacaan tanpa sambungan valid.';
                    continue;
                }

                if ($connection->status !== 'active') {
                    $skipped[] = sprintf('Sambungan %s dilewati karena status %s.', $connection->service_number, strtoupper((string) $connection->status));
                    continue;
                }

                if ($reading->reading_status === 'invalid') {
                    $skipped[] = sprintf('Sambungan %s dilewati karena pembacaan meter invalid.', $connection->service_number);
                    continue;
                }

                if ($reading->visit_status !== 'read') {
                    $skipped[] = sprintf(
                        'Sambungan %s dilewati karena kunjungan lapangan berstatus %s dan belum punya bacaan final.',
                        $connection->service_number,
                        strtoupper((string) $reading->visit_status)
                    );
                    continue;
                }

                $tariffScheme = $connection->tariffScheme;

                if ($tariffScheme === null) {
                    $skipped[] = sprintf('Sambungan %s dilewati karena belum punya skema tarif.', $connection->service_number);
                    continue;
                }

                $calculation = $this->billingCalculator->calculate($reading, $tariffScheme);
                $invoiceNumber = $this->makeInvoiceNumber($period, $connection);

                $invoice = BillingInvoice::query()->updateOrCreate(
                    [
                        'billing_period_id' => $period->id,
                        'service_connection_id' => $connection->id,
                    ],
                    [
                        'meter_reading_id' => $reading->id,
                        'customer_id' => $connection->customer_id,
                        'tariff_scheme_id' => $tariffScheme->id,
                        'invoice_number' => $invoiceNumber,
                        'status' => 'issued',
                        'usage_volume' => $calculation['usage_volume'],
                        'water_charge_total' => $calculation['water_charge_total'],
                        'minimum_charge_applied' => $calculation['minimum_charge_applied'],
                        'admin_fee_total' => $calculation['admin_fee_total'],
                        'penalty_total' => 0,
                        'invoice_total' => $calculation['invoice_total'],
                        'due_date' => $period->due_date,
                        'issued_at' => now(),
                        'paid_at' => null,
                        'calculation_snapshot' => $calculation['snapshot'],
                    ]
                );

                $invoice->lines()->delete();

                foreach ($calculation['lines'] as $index => $line) {
                    $invoice->lines()->create([
                        'line_type' => $line['line_type'],
                        'label' => $line['label'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                        'meta' => $line['meta'] ?? [],
                        'sort_order' => $index,
                    ]);
                }

                $generatedCount++;
            }

            $period->forceFill([
                'status' => $generatedCount > 0 ? 'generated' : $period->status,
                'generated_at' => $generatedCount > 0 ? now() : $period->generated_at,
            ])->save();
        });

        if ($generatedCount === 0) {
            return $this->redirectToPeriod($period->id, 'Tidak ada invoice yang bisa digenerate. Cek tarif atau validitas pembacaan meter.');
        }

        $status = sprintf(
            'Generate billing selesai. %d invoice diproses, %d dilewati.',
            $generatedCount,
            count($skipped)
        );

        if ($skipped !== []) {
            $status .= ' Contoh: ' . implode(' ', array_slice($skipped, 0, 2));
        }

        return $this->redirectToPeriod($period->id, $status);
    }

    public function updateInvoice(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanManageBilling();

        $invoice = BillingInvoice::query()
            ->with(['period', 'payments'])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaInvoiceScope($query))
            ->findOrFail($id);
        $period = $invoice->period;

        if (! $period instanceof BillingPeriod) {
            abort(404);
        }

        if ($period->status === 'closed') {
            throw ValidationException::withMessages([
                'billing_invoice' => 'Periode billing sudah ditutup. Status invoice tidak bisa diubah.',
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['issued', 'paid', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validated['status'] === 'cancelled' && $invoice->payments->isNotEmpty()) {
            throw ValidationException::withMessages([
                'billing_invoice' => 'Invoice yang sudah punya histori pembayaran tidak bisa dibatalkan.',
            ]);
        }

        $invoice->forceFill([
            'status' => $validated['status'],
            'paid_at' => $validated['status'] === 'paid' ? ($invoice->paid_at ?? now()) : null,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        if ($validated['status'] !== 'cancelled') {
            $this->invoicePaymentService->syncInvoiceState($invoice);
        }

        $invoice->refresh();

        return $this->redirectToPeriod(
            $period->id,
            sprintf('Status invoice %s berhasil diperbarui menjadi %s.', $invoice->invoice_number, strtoupper($invoice->status))
        );
    }

    public function storePayment(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanRecordPayment();

        $invoice = BillingInvoice::query()
            ->with(['period', 'payments'])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaInvoiceScope($query))
            ->findOrFail($id);
        $period = $invoice->period;

        if (! $period instanceof BillingPeriod) {
            abort(404);
        }

        if ($period->status === 'closed') {
            throw ValidationException::withMessages([
                'billing_payment' => 'Periode billing sudah ditutup. Pembayaran baru tidak bisa dicatat.',
            ]);
        }

        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'billing_payment' => 'Invoice yang dibatalkan tidak bisa menerima pembayaran.',
            ]);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(['cash', 'transfer', 'qris', 'loket', 'adjustment'])],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'received_by' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $penaltyPosted = 0;
        $tenantSetting = $this->tenantSetting();
        $paidAt = Carbon::parse($validated['paid_at']);

        $reactivated = false;
        $installed = false;

        DB::connection('tenant')->transaction(function () use ($id, $validated, $tenantSetting, $paidAt, &$penaltyPosted, &$reactivated, &$installed): void {
            $invoiceQuery = BillingInvoice::query()
                ->with(['period', 'lines', 'payments', 'connection'])
                ->lockForUpdate();
            if ($this->tirtaAreaIsRestricted()) {
                $this->applyTirtaInvoiceScope($invoiceQuery);
            }
            $invoice = $invoiceQuery->findOrFail($id);
            $period = $invoice->period;

            if (! $period instanceof BillingPeriod) {
                abort(404);
            }

            if ($period->status === 'closed') {
                throw ValidationException::withMessages([
                    'billing_payment' => 'Periode billing sudah ditutup. Pembayaran baru tidak bisa dicatat.',
                ]);
            }

            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'billing_payment' => 'Invoice yang dibatalkan tidak bisa menerima pembayaran.',
                ]);
            }

            $summary = $this->invoicePaymentService->summary($invoice);

            if ((int) ($summary['outstanding_total'] ?? 0) < 1) {
                throw ValidationException::withMessages([
                    'billing_payment' => 'Invoice ini sudah lunas penuh.',
                ]);
            }

            $autoPostOnPayment = (bool) ($tenantSetting->getAttribute('billing_penalty_auto_post_on_payment') ?? true);
            $penaltyEnabled = (bool) ($tenantSetting->getAttribute('billing_penalty_enabled') ?? false);

            if ($autoPostOnPayment && $penaltyEnabled && (int) ($summary['payments_count'] ?? 0) === 0 && $invoice->status === 'issued') {
                $penaltySummary = $this->penaltyCalculator->calculate($invoice, $summary, $tenantSetting, $paidAt);
                $penaltyAmount = (int) ($penaltySummary['penalty_amount'] ?? 0);

                if ($penaltyAmount > 0 && (int) ($invoice->penalty_total ?? 0) !== $penaltyAmount) {
                    $this->applyPenaltyPosting($invoice, $penaltySummary, $penaltyAmount, $paidAt);
                    $penaltyPosted = $penaltyAmount;
                    $invoice->refresh()->load(['payments']);
                    $summary = $this->invoicePaymentService->summary($invoice);
                }
            }

            if ((int) $validated['amount'] > (int) ($summary['outstanding_total'] ?? 0)) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Nominal pembayaran melebihi sisa tagihan Rp %s.',
                        number_format((int) ($summary['outstanding_total'] ?? 0), 0, ',', '.')
                    ),
                ]);
            }

            $this->invoicePaymentService->recordPayment($invoice, $validated);

            $invoice->refresh();

            if ($invoice->connection instanceof \App\Models\Tirta\ServiceConnection) {
                $reactivated = $this->connectionLifecycleService->finalizeReactivationIfEligible($invoice->connection, $paidAt);
                $installed = $this->connectionLifecycleService->finalizeInstallationIfEligible($invoice->connection, $invoice, $paidAt);
            }
        });

        $invoice->refresh()->load('payments');
        $updatedSummary = $this->invoicePaymentService->summary($invoice);

        $statusPrefix = $penaltyPosted > 0
            ? sprintf('Denda otomatis diposting Rp %s. ', number_format($penaltyPosted, 0, ',', '.'))
            : '';
        $reactivationPrefix = $reactivated ? 'Sambungan berhasil diaktifkan kembali. ' : '';
        $installationPrefix = $installed ? 'Sambungan pasang baru aktif (setelah pembayaran pertama). ' : '';

        return $this->redirectToPeriod(
            $period->id,
            sprintf(
                '%s%s%sPembayaran untuk invoice %s berhasil dicatat. Terbayar Rp %s, sisa Rp %s.',
                $reactivationPrefix,
                $installationPrefix,
                $statusPrefix,
                $invoice->invoice_number,
                number_format((int) $updatedSummary['paid_total'], 0, ',', '.'),
                number_format((int) $updatedSummary['outstanding_total'], 0, ',', '.')
            )
        );
    }

    public function requestReactivation(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanRecordPayment();

        $request->validate([
            'allow_installment' => ['nullable', 'boolean'],
        ]);

        $connection = \App\Models\Tirta\ServiceConnection::query()
            ->with('customer')
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaConnectionScope($query))
            ->findOrFail($id);
        $tenantSetting = $this->tenantSetting();
        $allowInstallment = $request->boolean('allow_installment', (bool) ($tenantSetting->getAttribute('billing_reactivation_default_allow_installment') ?? true));

        try {
            $invoice = DB::connection('tenant')->transaction(function () use ($connection, $tenantSetting, $allowInstallment): BillingInvoice {
                $connection->refresh();

                return $this->connectionLifecycleService->requestReactivation($connection, $tenantSetting, $allowInstallment, now());
            });
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'reactivation' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('tenant.tirta.billing', ['period' => $invoice->billing_period_id])
            ->with('status', sprintf('Invoice aktivasi %s berhasil dibuat.', $invoice->invoice_number));
    }

    public function postPenalty(string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanManageBilling();

        $invoice = BillingInvoice::query()
            ->with(['period', 'lines', 'payments'])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaInvoiceScope($query))
            ->findOrFail($id);
        $period = $invoice->period;

        if (! $period instanceof BillingPeriod) {
            abort(404);
        }

        if ($period->status === 'closed') {
            throw ValidationException::withMessages([
                'billing_penalty' => 'Periode billing sudah ditutup. Posting denda tidak bisa dilakukan.',
            ]);
        }

        if ($invoice->status !== 'issued') {
            throw ValidationException::withMessages([
                'billing_penalty' => 'Denda hanya bisa diposting untuk invoice yang masih issued.',
            ]);
        }

        $setting = $this->tenantSetting();
        [$penaltySummary, $penaltyAmount] = $this->validatedPenaltyPostingPayload($invoice, $setting);

        DB::connection('tenant')->transaction(function () use ($invoice, $penaltySummary, $penaltyAmount): void {
            $this->applyPenaltyPosting($invoice, $penaltySummary, $penaltyAmount, now());
        });

        $invoice->refresh();

        return $this->redirectToPeriod(
            $period->id,
            sprintf(
                'Denda berhasil diposting ke invoice %s sebesar Rp %s.',
                $invoice->invoice_number,
                number_format($penaltyAmount, 0, ',', '.')
            )
        );
    }

    public function postPeriodPenalties(string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanManageBilling();

        $period = BillingPeriod::query()->findOrFail($id);

        if ($period->status === 'closed') {
            throw ValidationException::withMessages([
                'billing_penalty' => 'Periode billing sudah ditutup. Bulk posting denda tidak bisa dilakukan.',
            ]);
        }

        $setting = $this->tenantSetting();
        $invoices = BillingInvoice::query()
            ->with(['period', 'lines', 'payments'])
            ->where('billing_period_id', $period->id)
            ->where('status', 'issued')
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaInvoiceScope($query))
            ->orderBy('invoice_number')
            ->get();

        if ($invoices->isEmpty()) {
            throw ValidationException::withMessages([
                'billing_penalty' => 'Belum ada invoice issued di periode ini.',
            ]);
        }

        $postedCount = 0;
        $postedTotal = 0;
        $blockedByPaymentCount = 0;
        $alreadyPostedCount = 0;
        $notEligibleCount = 0;

        DB::connection('tenant')->transaction(function () use (
            $invoices,
            $setting,
            &$postedCount,
            &$postedTotal,
            &$blockedByPaymentCount,
            &$alreadyPostedCount,
            &$notEligibleCount
        ): void {
            /** @var BillingInvoice $invoice */
            foreach ($invoices as $invoice) {
                $summary = $this->invoicePaymentService->summary($invoice);

                if ((int) ($summary['payments_count'] ?? 0) > 0) {
                    $blockedByPaymentCount++;
                    continue;
                }

                $penaltySummary = $this->penaltyCalculator->calculate($invoice, $summary, $setting, now());
                $penaltyAmount = (int) ($penaltySummary['penalty_amount'] ?? 0);

                if ($penaltyAmount < 1) {
                    $notEligibleCount++;
                    continue;
                }

                if ((int) ($invoice->penalty_total ?? 0) === $penaltyAmount) {
                    $alreadyPostedCount++;
                    continue;
                }

                $this->applyPenaltyPosting($invoice, $penaltySummary, $penaltyAmount, now());
                $postedCount++;
                $postedTotal += $penaltyAmount;
            }
        });

        if ($postedCount < 1) {
            throw ValidationException::withMessages([
                'billing_penalty' => 'Tidak ada denda baru yang bisa diposting. Cek apakah invoice sudah dibayar, belum overdue, atau nominal dendanya sudah sama dengan posting hari ini.',
            ]);
        }

        $status = sprintf(
            'Bulk posting denda selesai. %d invoice diposting dengan total Rp %s.',
            $postedCount,
            number_format($postedTotal, 0, ',', '.')
        );

        if ($blockedByPaymentCount > 0 || $alreadyPostedCount > 0 || $notEligibleCount > 0) {
            $status .= sprintf(
                ' Dilewati: %d sudah ada pembayaran, %d sudah ter-post hari ini, %d belum eligible.',
                $blockedByPaymentCount,
                $alreadyPostedCount,
                $notEligibleCount
            );
        }

        return $this->redirectToPeriod($period->id, $status);
    }

    public function updatePenaltySettings(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanManageBilling();

        $validated = $request->validate([
            'billing_penalty_enabled' => ['nullable', 'boolean'],
            'billing_penalty_auto_post_on_payment' => ['nullable', 'boolean'],
            'billing_penalty_type' => ['required', 'string', Rule::in(['fixed', 'percentage'])],
            'billing_penalty_base' => ['required', 'string', Rule::in(['outstanding_total', 'invoice_total'])],
            'billing_penalty_start_basis' => ['required', 'string', Rule::in(['due_date', 'issued_at'])],
            'billing_penalty_value' => ['required', 'numeric', 'min:0'],
            'billing_penalty_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'billing_penalty_max_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $setting = $this->tenantSetting();
        $setting->forceFill([
            'billing_penalty_enabled' => $request->boolean('billing_penalty_enabled'),
            'billing_penalty_auto_post_on_payment' => $request->boolean('billing_penalty_auto_post_on_payment'),
            'billing_penalty_type' => $validated['billing_penalty_type'],
            'billing_penalty_frequency' => 'daily',
            'billing_penalty_base' => $validated['billing_penalty_base'],
            'billing_penalty_start_basis' => $validated['billing_penalty_start_basis'],
            'billing_penalty_value' => $validated['billing_penalty_value'],
            'billing_penalty_grace_days' => $validated['billing_penalty_grace_days'],
            'billing_penalty_max_amount' => $validated['billing_penalty_max_amount'] !== null && (int) $validated['billing_penalty_max_amount'] > 0
                ? (int) $validated['billing_penalty_max_amount']
                : null,
        ])->save();

        return redirect()
            ->route('tenant.tirta.billing')
            ->with('status', 'Pengaturan denda Tirta berhasil diperbarui.');
    }

    public function updateLifecycleSettings(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanManageBilling();

        $validated = $request->validate([
            'billing_disconnect_after_months' => ['required', 'integer', 'min:1', 'max:24'],
            'billing_reactivation_fee_amount' => ['required', 'integer', 'min:0'],
            'billing_reactivation_default_allow_installment' => ['nullable', 'boolean'],
        ]);

        $setting = $this->tenantSetting();
        $setting->forceFill([
            'billing_disconnect_after_months' => (int) $validated['billing_disconnect_after_months'],
            'billing_reactivation_fee_amount' => (int) $validated['billing_reactivation_fee_amount'],
            'billing_reactivation_default_allow_installment' => $request->boolean('billing_reactivation_default_allow_installment'),
        ])->save();

        return redirect()
            ->route('tenant.tirta.billing')
            ->with('status', 'Pengaturan cabut & reaktivasi berhasil diperbarui.');
    }

    public function updateInstallationSettings(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessBilling();
        $this->ensureCanManageBilling();

        $validated = $request->validate([
            'billing_installation_fee_amount' => ['required', 'integer', 'min:0'],
            'billing_installation_allow_installment' => ['nullable', 'boolean'],
            'billing_installation_default_installment_months' => ['required', 'integer', 'min:2', 'max:24'],
            'billing_installation_promo_enabled' => ['nullable', 'boolean'],
            'billing_installation_promo_discount_amount' => ['required', 'integer', 'min:0'],
            'billing_installation_promo_start_date' => ['nullable', 'date'],
            'billing_installation_promo_end_date' => ['nullable', 'date', 'after_or_equal:billing_installation_promo_start_date'],
        ]);

        $setting = $this->tenantSetting();
        $setting->forceFill([
            'billing_installation_fee_amount' => (int) $validated['billing_installation_fee_amount'],
            'billing_installation_allow_installment' => $request->boolean('billing_installation_allow_installment'),
            'billing_installation_default_installment_months' => (int) $validated['billing_installation_default_installment_months'],
            'billing_installation_promo_enabled' => $request->boolean('billing_installation_promo_enabled'),
            'billing_installation_promo_discount_amount' => (int) $validated['billing_installation_promo_discount_amount'],
            'billing_installation_promo_start_date' => $validated['billing_installation_promo_start_date'] ?: null,
            'billing_installation_promo_end_date' => $validated['billing_installation_promo_end_date'] ?: null,
        ])->save();

        return redirect()
            ->route('tenant.tirta.billing')
            ->with('status', 'Pengaturan pasang baru berhasil diperbarui.');
    }

    protected function validatedBillingPeriod(Request $request, ?BillingPeriod $period = null): array
    {
        $validated = $request->validate([
            'meter_reading_period_id' => ['required', 'string', Rule::exists('meter_reading_periods', 'id')],
            'name' => ['required', 'string', 'max:120'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_date' => ['nullable', 'date', 'after_or_equal:period_end'],
            'status' => ['required', 'string', Rule::in(['draft', 'generated', 'closed'])],
            'notes' => ['nullable', 'string'],
        ]);

        $duplicate = BillingPeriod::query()
            ->where('meter_reading_period_id', $validated['meter_reading_period_id'])
            ->when($period instanceof BillingPeriod, fn ($query) => $query->whereKeyNot($period->getKey()))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'meter_reading_period_id' => 'Periode baca meter ini sudah dipakai di billing period lain.',
            ]);
        }

        if (blank($validated['due_date'])) {
            $setting = $this->tenantSetting();
            $readingPeriod = MeterReadingPeriod::query()->find($validated['meter_reading_period_id']);
            $periodEnd = $readingPeriod?->period_end;

            if ($periodEnd !== null) {
                $publishOffset = max((int) ($setting->getAttribute('billing_publish_offset_days') ?? 1), 0);
                $dueOffset = max((int) ($setting->getAttribute('billing_due_offset_days') ?? 10), 1);
                $validated['due_date'] = $periodEnd
                    ->copy()
                    ->addDays($publishOffset + $dueOffset)
                    ->toDateString();
            }
        }

        return $validated;
    }

    protected function ensureBillingPeriodEditable(BillingPeriod $period, array $validated): void
    {
        if ($period->status === 'closed') {
            throw ValidationException::withMessages([
                'billing_period' => 'Periode billing yang sudah closed tidak bisa diubah lagi.',
            ]);
        }

        if ($period->status !== 'generated') {
            return;
        }

        $structureChanged = (string) $period->meter_reading_period_id !== (string) $validated['meter_reading_period_id']
            || $period->period_start?->toDateString() !== (string) $validated['period_start']
            || $period->period_end?->toDateString() !== (string) $validated['period_end'];

        if ($structureChanged) {
            throw ValidationException::withMessages([
                'billing_period' => 'Periode billing yang sudah generated tidak boleh ganti periode meter atau tanggal rentang. Tutup periodenya atau buat period baru.',
            ]);
        }

        if ($validated['status'] === 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Periode billing yang sudah generated tidak boleh diturunkan kembali ke draft.',
            ]);
        }
    }

    protected function selectedBillingPeriod(Request $request, Collection $periods): ?BillingPeriod
    {
        if ($periods->isEmpty()) {
            return null;
        }

        $selectedId = (string) $request->query('period');
        $selected = $selectedId !== '' ? $periods->firstWhere('id', $selectedId) : null;

        if ($selected instanceof BillingPeriod) {
            return $selected;
        }

        return $periods->firstWhere('status', 'generated')
            ?? $periods->firstWhere('status', 'draft')
            ?? $periods->first();
    }

    protected function readingSummaryForBillingPeriod(MeterReadingPeriod $period): array
    {
        $readings = MeterReading::query()
            ->where('meter_reading_period_id', $period->id)
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $query->whereHas('connection', fn ($connectionQuery) => $this->applyTirtaConnectionScope($connectionQuery)))
            ->get();

        return [
            'total_readings' => $readings->count(),
            'valid_readings' => $readings->whereNotIn('reading_status', ['invalid'])->count(),
            'warning_readings' => $readings->where('reading_status', 'warning')->count(),
            'invalid_readings' => $readings->where('reading_status', 'invalid')->count(),
            'usage_volume' => (int) $readings->sum('usage_volume'),
        ];
    }

    protected function makeInvoiceNumber(BillingPeriod $period, ServiceConnection $connection): string
    {
        return sprintf(
            'TRB-%s-%s',
            $period->period_start?->format('Ym') ?? now()->format('Ym'),
            $connection->service_number
        );
    }

    protected function bulkPenaltySummary(Collection $invoices, array $paymentSummaries, array $penaltySummaries): array
    {
        $eligibleCount = 0;
        $eligibleTotal = 0;
        $alreadyPostedCount = 0;
        $blockedByPaymentCount = 0;

        /** @var BillingInvoice $invoice */
        foreach ($invoices as $invoice) {
            if ($invoice->status !== 'issued') {
                continue;
            }

            $paymentSummary = $paymentSummaries[$invoice->id] ?? [];
            $penaltySummary = $penaltySummaries[$invoice->id] ?? [];
            $penaltyAmount = (int) ($penaltySummary['penalty_amount'] ?? 0);

            if ((int) ($paymentSummary['payments_count'] ?? 0) > 0) {
                $blockedByPaymentCount++;
                continue;
            }

            if ($penaltyAmount < 1) {
                continue;
            }

            if ((int) ($invoice->penalty_total ?? 0) === $penaltyAmount) {
                $alreadyPostedCount++;
                continue;
            }

            $eligibleCount++;
            $eligibleTotal += $penaltyAmount;
        }

        return [
            'eligible_count' => $eligibleCount,
            'eligible_total' => $eligibleTotal,
            'already_posted_count' => $alreadyPostedCount,
            'blocked_by_payment_count' => $blockedByPaymentCount,
        ];
    }

    protected function matchesReceivableBucket(BillingInvoice $invoice, string $bucket, Carbon $today): bool
    {
        if ($bucket === '' || $bucket === 'all') {
            return true;
        }

        if ($invoice->status !== 'issued') {
            return $bucket === 'closed_only' && in_array($invoice->status, ['paid', 'cancelled'], true);
        }

        $dueDate = $invoice->due_date?->copy()->startOfDay();

        return match ($bucket) {
            'open' => true,
            'overdue' => $dueDate !== null && $dueDate->lt($today),
            'due_today' => $dueDate !== null && $dueDate->isSameDay($today),
            'upcoming' => $dueDate !== null && $dueDate->gt($today),
            'undated' => $dueDate === null,
            default => true,
        };
    }

    protected function validatedPenaltyPostingPayload(BillingInvoice $invoice, TenantSetting $setting): array
    {
        $summary = $this->invoicePaymentService->summary($invoice);

        if ((int) ($summary['payments_count'] ?? 0) > 0) {
            throw ValidationException::withMessages([
                'billing_penalty' => 'Invoice yang sudah punya pembayaran belum bisa diposting dendanya. Supaya alokasi pembayaran tidak rancu.',
            ]);
        }

        $penaltySummary = $this->penaltyCalculator->calculate($invoice, $summary, $setting, now());
        $penaltyAmount = (int) ($penaltySummary['penalty_amount'] ?? 0);

        if ($penaltyAmount < 1) {
            throw ValidationException::withMessages([
                'billing_penalty' => 'Tidak ada denda yang bisa diposting untuk invoice ini pada hari ini.',
            ]);
        }

        if ((int) ($invoice->penalty_total ?? 0) === $penaltyAmount) {
            throw ValidationException::withMessages([
                'billing_penalty' => 'Denda untuk hari ini sudah diposting ke invoice ini.',
            ]);
        }

        return [$penaltySummary, $penaltyAmount];
    }

    protected function applyPenaltyPosting(BillingInvoice $invoice, array $penaltySummary, int $penaltyAmount, Carbon $asOf): void
    {
        $invoice->loadMissing('lines');

        $invoice->lines()
            ->where('line_type', 'penalty')
            ->delete();

        $existingSortMax = (int) ($invoice->lines->max('sort_order') ?? 0);
        $sortOrder = $existingSortMax + 1;

        $principalTotal = max((int) $invoice->invoice_total - max((int) ($invoice->penalty_total ?? 0), 0), 0);

        $invoice->forceFill([
            'penalty_total' => $penaltyAmount,
            'invoice_total' => $principalTotal + $penaltyAmount,
        ])->save();

        $invoice->lines()->create([
            'line_type' => 'penalty',
            'label' => sprintf('Denda Keterlambatan (%d hari)', (int) ($penaltySummary['effective_late_days'] ?? 0)),
            'quantity' => 1,
            'unit_price' => $penaltyAmount,
            'line_total' => $penaltyAmount,
            'meta' => [
                'posted_at' => now()->toISOString(),
                'as_of_date' => $asOf->copy()->startOfDay()->toDateString(),
                'late_days' => (int) ($penaltySummary['late_days'] ?? 0),
                'effective_late_days' => (int) ($penaltySummary['effective_late_days'] ?? 0),
                'daily_penalty_amount' => (int) ($penaltySummary['daily_penalty_amount'] ?? 0),
                'start_basis' => (string) ($penaltySummary['start_basis'] ?? ''),
                'base' => (string) ($penaltySummary['base'] ?? ''),
                'base_amount' => (int) ($penaltySummary['base_amount'] ?? 0),
                'value' => (float) ($penaltySummary['value'] ?? 0),
                'frequency' => (string) ($penaltySummary['frequency'] ?? 'daily'),
                'label' => (string) ($penaltySummary['label'] ?? ''),
            ],
            'sort_order' => $sortOrder,
        ]);
    }

    protected function ensureTirtaTenant(): void
    {
        if ((string) (tenant('saas_type') ?? '') !== 'tirta') {
            abort(404);
        }
    }

    protected function ensureCanAccessBilling(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canAccessTirtaBilling()) {
            abort(403, 'Akun ini tidak punya akses ke Billing.');
        }
    }

    protected function ensureCanManageBilling(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canManageTirtaBilling()) {
            abort(403, 'Akun ini tidak punya akses untuk mengelola Billing (periode, generate invoice, denda).');
        }
    }

    protected function ensureCanRecordPayment(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canRecordTirtaBillingPayment()) {
            abort(403, 'Akun ini tidak punya akses untuk mencatat pembayaran.');
        }
    }

    protected function ensureSchemaReady(): void
    {
        $requiredTables = [
            'tenant_settings',
            'service_connections',
            'meter_reading_periods',
            'meter_readings',
            'billing_periods',
            'billing_invoices',
            'billing_invoice_lines',
            'billing_payments',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                throw ValidationException::withMessages([
                    'schema' => 'Schema TirtaBilling belum siap. Jalankan migrasi tenant terbaru dulu.',
                ]);
            }
        }
    }

    protected function redirectToPeriod(string $periodId, string $status): RedirectResponse
    {
        return redirect()
            ->route('tenant.tirta.billing', ['period' => $periodId])
            ->with('status', $status);
    }

    protected function tenantSetting(): TenantSetting
    {
        return TenantSetting::query()->firstOrCreate(
            [],
            [
                'brand_name' => (string) (tenant('name') ?? tenant('id') ?? config('app.name')),
                'description' => 'Workspace Tirta belum dikustomisasi.',
                'theme_color' => '#0891b2',
            ]
        );
    }
}
