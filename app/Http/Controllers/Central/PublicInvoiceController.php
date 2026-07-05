<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralSetting;
use App\Models\Tenant;
use App\Services\Central\BillingNotificationService;
use App\Services\Central\CentralAuditLogger;
use App\Services\Central\ManualTransferInboxService;
use App\Services\Central\ManualTransferService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class PublicInvoiceController extends Controller
{
    public function __construct(
        protected CentralAuditLogger $auditLogger,
        protected ManualTransferService $manualTransferService,
        protected ManualTransferInboxService $manualTransferInboxService,
        protected BillingNotificationService $billingNotificationService,
    ) {
    }

    public function show(string $tenantId, string $invoiceNumber): View
    {
        [$tenant, $invoice] = $this->tenantAndInvoice($tenantId, $invoiceNumber);
        $paymentSettings = CentralSetting::paymentMethodSettings();
        $notificationSettings = CentralSetting::notificationChannelSettings();

        return view('central.public-invoice', [
            'tenant' => $tenant,
            'invoice' => $invoice,
            'paymentSettings' => $paymentSettings,
            'publicPaymentNote' => (string) data_get($notificationSettings, 'templates.public_payment_note', ''),
        ]);
    }

    public function createQris(string $tenantId, string $invoiceNumber): RedirectResponse
    {
        [$tenant, $invoice] = $this->tenantAndInvoice($tenantId, $invoiceNumber);
        $qrisConfig = $this->qrisConfig();

        if (! $qrisConfig['enabled'] || ! $qrisConfig['ready']) {
            return back()->withErrors([
                'invoice' => 'QRIS belum siap dipakai saat ini.',
            ]);
        }

        if (in_array((string) ($invoice['status'] ?? 'issued'), ['paid', 'void'], true)) {
            return back()->withErrors([
                'invoice' => 'Invoice ini tidak bisa dibuatkan QRIS lagi.',
            ]);
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get($qrisConfig['base_url'] . '/show_qris.php', [
                    'do' => 'create-invoice',
                    'apikey' => $qrisConfig['apikey'],
                    'mID' => $qrisConfig['merchant_id'],
                    'cliTrxNumber' => $invoice['invoice_number'],
                    'cliTrxAmount' => (int) ($invoice['invoice_total'] ?? 0),
                    'useTip' => $qrisConfig['use_tip'],
                ]);
        } catch (\Throwable $throwable) {
            $this->auditLogger->error('public_invoice.qris_failed', 'Create QRIS publik gagal.', [
                'target_type' => 'tenant',
                'target_id' => (string) $tenant->id,
                'meta' => [
                    'invoice_number' => $invoice['invoice_number'],
                    'error' => $throwable->getMessage(),
                ],
            ]);

            return back()->withErrors([
                'invoice' => 'Provider QRIS tidak bisa dihubungi sekarang.',
            ]);
        }

        if (! $response->ok()) {
            $this->auditLogger->warning('public_invoice.qris_rejected', 'Provider QRIS menolak create request publik.', [
                'target_type' => 'tenant',
                'target_id' => (string) $tenant->id,
                'meta' => ['invoice_number' => $invoice['invoice_number']],
            ]);

            return back()->withErrors([
                'invoice' => 'Provider QRIS menolak request pembayaran.',
            ]);
        }

        $payload = $response->json();
        $requestDate = CarbonImmutable::now();
        $expiresAt = $requestDate->addMinutes(30);

        $this->updateInvoice($tenant, $invoice['invoice_number'], function (array $record) use ($payload, $requestDate, $expiresAt): array {
            $record['payment'] = array_merge(
                $this->normalizeInvoicePayment((array) data_get($record, 'payment', [])),
                [
                    'method' => 'qris',
                    'status' => 'pending',
                    'reference' => (string) data_get($payload, 'data.qris_invoiceid', $record['invoice_number'] ?? ''),
                    'qris' => [
                        'invoice_id' => (string) data_get($payload, 'data.qris_invoiceid', ''),
                        'content' => (string) data_get($payload, 'data.qris_string', data_get($payload, 'data.qris_content', '')),
                        'nmid' => (string) data_get($payload, 'data.qris_nmid', ''),
                        'request_date' => $requestDate->toIso8601String(),
                        'expires_at' => $expiresAt->toIso8601String(),
                        'last_checked_at' => null,
                        'raw_status' => 'pending',
                    ],
                ]
            );

            return $record;
        });

        $this->auditLogger->info('public_invoice.qris_created', 'QRIS publik berhasil dibuat.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
            'meta' => ['invoice_number' => $invoice['invoice_number']],
        ]);

        return redirect()
            ->route('central.public-invoice.show', [$tenant->id, $invoice['invoice_number']])
            ->with('status', 'QRIS berhasil dibuat. Silakan lanjut scan kode atau string QRIS yang tampil.');
    }

    public function checkQrisStatus(string $tenantId, string $invoiceNumber): RedirectResponse
    {
        [$tenant, $invoice] = $this->tenantAndInvoice($tenantId, $invoiceNumber);
        $qrisConfig = $this->qrisConfig();
        $qrisInvoiceId = (string) data_get($invoice, 'payment.qris.invoice_id', '');

        if (! $qrisConfig['enabled'] || ! $qrisConfig['ready'] || $qrisInvoiceId === '') {
            return back()->withErrors([
                'invoice' => 'QRIS invoice ini belum siap dicek statusnya.',
            ]);
        }

        $requestDate = data_get($invoice, 'payment.qris.request_date');
        $requestDate = is_string($requestDate) && $requestDate !== ''
            ? CarbonImmutable::parse($requestDate)
            : CarbonImmutable::now();

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get($qrisConfig['base_url'] . '/checkpaid_qris.php', [
                    'do' => 'checkStatus',
                    'apikey' => $qrisConfig['apikey'],
                    'mID' => $qrisConfig['merchant_id'],
                    'invid' => $qrisInvoiceId,
                    'trxvalue' => (int) ($invoice['invoice_total'] ?? 0),
                    'trxdate' => $requestDate->format('Y-m-d'),
                ]);
        } catch (\Throwable $throwable) {
            $this->auditLogger->error('public_invoice.qris_check_failed', 'Cek status QRIS publik gagal.', [
                'target_type' => 'tenant',
                'target_id' => (string) $tenant->id,
                'meta' => [
                    'invoice_number' => $invoice['invoice_number'],
                    'error' => $throwable->getMessage(),
                ],
            ]);

            return back()->withErrors([
                'invoice' => 'Gagal cek status QRIS ke provider.',
            ]);
        }

        if (! $response->ok()) {
            return back()->withErrors([
                'invoice' => 'Provider QRIS menolak cek status pembayaran.',
            ]);
        }

        $payload = $response->json();
        $qrisStatus = strtolower((string) data_get($payload, 'data.qris_status', 'unpaid'));
        $checkedAt = CarbonImmutable::now();
        $expiresAt = data_get($invoice, 'payment.qris.expires_at');
        $expiresAt = is_string($expiresAt) && $expiresAt !== '' ? CarbonImmutable::parse($expiresAt) : null;
        $paymentStatus = $qrisStatus === 'paid'
            ? 'paid'
            : (($expiresAt instanceof CarbonImmutable && $expiresAt->isPast()) ? 'expired' : 'pending');

        $this->updateInvoice($tenant, $invoice['invoice_number'], function (array $record) use ($payload, $checkedAt, $paymentStatus, $qrisStatus): array {
            $record['payment'] = array_merge(
                $this->normalizeInvoicePayment((array) data_get($record, 'payment', [])),
                [
                    'method' => 'qris',
                    'status' => $paymentStatus,
                    'paid_via' => (string) data_get($payload, 'data.qris_payment_methodby', data_get($record, 'payment.paid_via', '')),
                    'customer_name' => (string) data_get($payload, 'data.qris_payment_customername', data_get($record, 'payment.customer_name', '')),
                    'qris' => array_merge(
                        (array) data_get($record, 'payment.qris', []),
                        [
                            'last_checked_at' => $checkedAt->toIso8601String(),
                            'raw_status' => $qrisStatus,
                        ]
                    ),
                ]
            );

            if ($qrisStatus === 'paid') {
                $record['status'] = 'paid';
                $record['paid_at'] = $checkedAt->toIso8601String();
            }

            return $record;
        });

        return redirect()
            ->route('central.public-invoice.show', [$tenant->id, $invoice['invoice_number']])
            ->with('status', $qrisStatus === 'paid'
                ? 'Pembayaran QRIS sudah terkonfirmasi.'
                : 'Status QRIS terbaru berhasil di-refresh.');
    }

    public function receiveBcaEvidence(Request $request): JsonResponse
    {
        $result = $this->manualTransferService->reconcileBcaEvidence($request->all());
        $tenant = $result['tenant'] ?? null;
        $invoice = $result['invoice'] ?? null;

        if (($result['status'] ?? '') === 'matched_auto') {
            if ($tenant instanceof Tenant && is_array($invoice)) {
                $this->billingNotificationService->dispatchPaymentSuccess($tenant, $invoice);
            }
        }

        $statusCode = match ((string) ($result['status'] ?? 'invalid')) {
            'matched_auto', 'duplicate' => 200,
            'no_match' => 202,
            'ambiguous' => 409,
            default => 422,
        };

        return response()->json([
            'status' => (string) ($result['status'] ?? 'invalid'),
            'message' => (string) ($result['message'] ?? 'Payload evidence transfer manual tidak valid.'),
            'message_id' => (string) ($result['message_id'] ?? ''),
            'tenant_id' => $tenant instanceof Tenant ? (string) $tenant->id : null,
            'invoice_number' => is_array($invoice)
                ? (string) data_get($invoice, 'invoice_number', '')
                : null,
            'expected_amount' => isset($result['expected_amount']) ? (int) $result['expected_amount'] : null,
        ], $statusCode);
    }

    public function confirmManualTransfer(string $tenantId, string $invoiceNumber): RedirectResponse
    {
        [$tenant, $invoice] = $this->tenantAndInvoice($tenantId, $invoiceNumber);

        if (in_array((string) ($invoice['status'] ?? 'issued'), ['paid', 'void'], true)) {
            return back()->withErrors([
                'invoice' => 'Invoice ini sudah tidak perlu konfirmasi transfer lagi.',
            ]);
        }

        $result = $this->manualTransferInboxService->fetchAndReconcileInvoice($tenant, $invoice);
        $status = (string) ($result['status'] ?? 'invalid');

        if ($status === 'matched_auto') {
            $matchedTenant = $result['tenant'] ?? null;
            $matchedInvoice = $result['invoice'] ?? null;

            if ($matchedTenant instanceof Tenant && is_array($matchedInvoice)) {
                $this->billingNotificationService->dispatchPaymentSuccess($matchedTenant, $matchedInvoice);
            }

            return redirect()
                ->route('central.public-invoice.show', [$tenant->id, $invoice['invoice_number']])
                ->with('status', 'Transfer berhasil ditemukan di email BCA dan invoice otomatis ditandai paid.');
        }

        if ($status === 'duplicate') {
            return back()->with('status', 'Email transfer ini sudah pernah diproses sebelumnya. Kalau invoice belum paid, cek kembali inbox atau hubungi admin.');
        }

        return back()->withErrors([
            'invoice' => (string) ($result['message'] ?? 'Auto fetch email transfer manual belum menemukan bukti yang cocok.'),
        ]);
    }

    protected function tenantAndInvoice(string $tenantId, string $invoiceNumber): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $invoice = collect($tenant->billingInvoices())
            ->firstWhere('invoice_number', $invoiceNumber);

        abort_if(! is_array($invoice), 404);

        return [$tenant, $invoice];
    }

    protected function updateInvoice(Tenant $tenant, string $invoiceNumber, callable $mutator): void
    {
        $billingInvoices = collect($tenant->billingInvoices())
            ->map(function (array $invoice) use ($invoiceNumber, $mutator): array {
                if ($invoice['invoice_number'] !== $invoiceNumber) {
                    return $invoice;
                }

                return $mutator($invoice);
            })
            ->values()
            ->all();

        $tenant->forceFill([
            'billing_invoices' => $billingInvoices,
            'last_invoice_status_updated_at' => CarbonImmutable::now()->toIso8601String(),
        ])->save();
    }

    protected function normalizeInvoicePayment(array $payment): array
    {
        return [
            'method' => (string) ($payment['method'] ?? ''),
            'status' => (string) ($payment['status'] ?? ''),
            'reference' => (string) ($payment['reference'] ?? ''),
            'notes' => (string) ($payment['notes'] ?? ''),
            'paid_via' => (string) ($payment['paid_via'] ?? ''),
            'customer_name' => (string) ($payment['customer_name'] ?? ''),
            'manual_transfer' => [
                'bank_name' => (string) data_get($payment, 'manual_transfer.bank_name', ''),
                'account_name' => (string) data_get($payment, 'manual_transfer.account_name', ''),
                'account_number' => (string) data_get($payment, 'manual_transfer.account_number', ''),
                'base_amount' => max((int) data_get($payment, 'manual_transfer.base_amount', 0), 0),
                'unique_code' => max((int) data_get($payment, 'manual_transfer.unique_code', 0), 0),
                'expected_amount' => max((int) data_get($payment, 'manual_transfer.expected_amount', 0), 0),
                'matched_by' => (string) data_get($payment, 'manual_transfer.matched_by', ''),
                'matched_at' => data_get($payment, 'manual_transfer.matched_at'),
                'source_adapter' => (string) data_get($payment, 'manual_transfer.source_adapter', ''),
                'evidence' => [
                    'message_id' => (string) data_get($payment, 'manual_transfer.evidence.message_id', ''),
                    'ws_ref' => (string) data_get($payment, 'manual_transfer.evidence.ws_ref', ''),
                    'sender_name' => (string) data_get($payment, 'manual_transfer.evidence.sender_name', ''),
                    'account_number' => (string) data_get($payment, 'manual_transfer.evidence.account_number', ''),
                    'credit_amount' => max((int) data_get($payment, 'manual_transfer.evidence.credit_amount', 0), 0),
                    'transaction_at' => (string) data_get($payment, 'manual_transfer.evidence.transaction_at', ''),
                    'from_address' => (string) data_get($payment, 'manual_transfer.evidence.from_address', ''),
                    'raw_payload' => is_array(data_get($payment, 'manual_transfer.evidence.raw_payload'))
                        ? data_get($payment, 'manual_transfer.evidence.raw_payload')
                        : [],
                ],
            ],
            'qris' => [
                'invoice_id' => (string) data_get($payment, 'qris.invoice_id', ''),
                'content' => (string) data_get($payment, 'qris.content', ''),
                'nmid' => (string) data_get($payment, 'qris.nmid', ''),
                'request_date' => data_get($payment, 'qris.request_date'),
                'expires_at' => data_get($payment, 'qris.expires_at'),
                'last_checked_at' => data_get($payment, 'qris.last_checked_at'),
                'raw_status' => (string) data_get($payment, 'qris.raw_status', ''),
            ],
        ];
    }

    protected function qrisConfig(): array
    {
        $settings = CentralSetting::paymentMethodSettings();
        $enabled = (bool) data_get($settings, 'qris.enabled', false);
        $apikey = trim((string) data_get($settings, 'qris.api_key', config('services.interactive_qris.apikey', '')));
        $merchantId = trim((string) data_get($settings, 'qris.merchant_id', config('services.interactive_qris.merchant_id', '')));
        $baseUrl = rtrim((string) data_get($settings, 'qris.base_url', config('services.interactive_qris.base_url', '')), '/');

        return [
            'enabled' => $enabled,
            'ready' => $apikey !== '' && $merchantId !== '' && $baseUrl !== '',
            'apikey' => $apikey,
            'merchant_id' => $merchantId,
            'base_url' => $baseUrl,
            'use_tip' => (string) data_get($settings, 'qris.use_tip', config('services.interactive_qris.use_tip', 'no')),
        ];
    }
}
