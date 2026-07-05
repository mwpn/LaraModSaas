<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\CentralSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

class ManualTransferService
{
    public function __construct(
        protected CentralAuditLogger $auditLogger,
    ) {
    }

    public function allocateUniqueCode(int $baseAmount, array $reservedExpectedAmounts = []): array
    {
        $baseAmount = max($baseAmount, 0);

        if ($baseAmount === 0) {
            return [
                'base_amount' => 0,
                'unique_code' => 0,
                'expected_amount' => 0,
            ];
        }

        $usedExpectedAmounts = $this->activeExpectedAmounts();

        foreach ($reservedExpectedAmounts as $expectedAmount) {
            $expectedAmount = (int) $expectedAmount;

            if ($expectedAmount > 0) {
                $usedExpectedAmounts[$expectedAmount] = true;
            }
        }

        $startCode = random_int(101, 999);

        for ($offset = 0; $offset < 899; $offset++) {
            $candidateCode = 101 + (($startCode - 101 + $offset) % 899);
            $candidateAmount = $baseAmount + $candidateCode;

            if (! isset($usedExpectedAmounts[$candidateAmount])) {
                return [
                    'base_amount' => $baseAmount,
                    'unique_code' => $candidateCode,
                    'expected_amount' => $candidateAmount,
                ];
            }
        }

        throw new RuntimeException('Kode unik transfer manual habis untuk nominal invoice ini. Coba generate ulang beberapa saat lagi.');
    }

    public function reconcileBcaEvidence(array $payload): array
    {
        $messageId = trim((string) data_get($payload, 'message_id', data_get($payload, 'parsed_payload.message_id', '')));
        $parsedPayload = (array) data_get($payload, 'parsed_payload', []);
        $accountNumber = $this->normalizeAccountNumber((string) data_get($parsedPayload, 'account_number', ''));
        $creditAmount = $this->normalizeCurrencyAmount(data_get($parsedPayload, 'credit_amount'));
        $configuredAccountNumber = $this->normalizeAccountNumber((string) data_get(CentralSetting::paymentMethodSettings(), 'manual_transfer.account_number', ''));

        if ($messageId !== '' && $this->messageIdAlreadyProcessed($messageId)) {
            $this->auditLogger->info('manual_transfer.bca_duplicate', 'Evidence transfer manual BCA duplikat diabaikan.', [
                'target_type' => 'billing',
                'target_id' => $messageId,
                'meta' => ['source_adapter' => (string) data_get($payload, 'source_adapter', 'bca_email')],
            ]);

            return [
                'status' => 'duplicate',
                'message' => 'Message ID sudah pernah diproses.',
                'message_id' => $messageId,
            ];
        }

        if ($configuredAccountNumber !== '' && $accountNumber !== '' && $configuredAccountNumber !== $accountNumber) {
            $this->auditLogger->warning('manual_transfer.bca_account_mismatch', 'Evidence transfer manual ditolak karena rekening tujuan tidak cocok.', [
                'target_type' => 'billing',
                'target_id' => $messageId !== '' ? $messageId : (string) $creditAmount,
                'meta' => [
                    'configured_account_number' => $configuredAccountNumber,
                    'incoming_account_number' => $accountNumber,
                ],
            ]);

            return [
                'status' => 'rejected',
                'message' => 'Nomor rekening tujuan tidak cocok dengan rekening manual transfer aktif.',
                'message_id' => $messageId,
            ];
        }

        if ($creditAmount <= 0) {
            return [
                'status' => 'invalid',
                'message' => 'Nominal transfer tidak valid.',
                'message_id' => $messageId,
            ];
        }

        $matches = $this->matchingInvoicesByExpectedAmount($creditAmount);

        if ($matches->isEmpty()) {
            $this->auditLogger->warning('manual_transfer.bca_no_match', 'Evidence transfer manual belum menemukan invoice yang cocok.', [
                'target_type' => 'billing',
                'target_id' => $messageId !== '' ? $messageId : (string) $creditAmount,
                'meta' => [
                    'expected_amount' => $creditAmount,
                    'account_number' => $accountNumber,
                ],
            ]);

            return [
                'status' => 'no_match',
                'message' => 'Belum ada invoice unpaid dengan nominal transfer unik ini.',
                'message_id' => $messageId,
                'expected_amount' => $creditAmount,
            ];
        }

        if ($matches->count() > 1) {
            $this->auditLogger->warning('manual_transfer.bca_ambiguous', 'Evidence transfer manual menemukan lebih dari satu kandidat invoice.', [
                'target_type' => 'billing',
                'target_id' => $messageId !== '' ? $messageId : (string) $creditAmount,
                'meta' => [
                    'expected_amount' => $creditAmount,
                    'candidates' => $matches->map(fn (array $match): array => [
                        'tenant_id' => (string) $match['tenant']->id,
                        'invoice_number' => (string) data_get($match, 'invoice.invoice_number', ''),
                    ])->values()->all(),
                ],
            ]);

            return [
                'status' => 'ambiguous',
                'message' => 'Ditemukan lebih dari satu invoice dengan nominal transfer yang sama.',
                'message_id' => $messageId,
                'expected_amount' => $creditAmount,
            ];
        }

        $match = $matches->first();
        $tenant = $match['tenant'];
        $invoice = $match['invoice'];
        $matchedAt = CarbonImmutable::now();
        $transferConfig = $this->manualTransferConfig();
        $updatedInvoice = $this->markInvoicePaidFromEvidence($tenant, $invoice, $payload, $transferConfig, $matchedAt);

        $this->auditLogger->info('manual_transfer.bca_matched', 'Transfer manual BCA berhasil di-auto-match ke invoice tenant.', [
            'target_type' => 'tenant',
            'target_id' => (string) $tenant->id,
            'meta' => [
                'invoice_number' => (string) ($updatedInvoice['invoice_number'] ?? ''),
                'message_id' => $messageId,
                'expected_amount' => $creditAmount,
            ],
        ]);

        return [
            'status' => 'matched_auto',
            'message' => 'Transfer berhasil di-auto-match ke invoice tenant.',
            'message_id' => $messageId,
            'tenant' => $tenant,
            'invoice' => $updatedInvoice,
        ];
    }

    protected function activeExpectedAmounts(): array
    {
        $used = [];

        Tenant::query()
            ->get()
            ->each(function (Tenant $tenant) use (&$used): void {
                foreach ($tenant->billingInvoices() as $invoice) {
                    if (! $this->isCollectibleInvoice($invoice)) {
                        continue;
                    }

                    $expectedAmount = (int) data_get($invoice, 'payment.manual_transfer.expected_amount', 0);

                    if ($expectedAmount > 0) {
                        $used[$expectedAmount] = true;
                    }
                }
            });

        return $used;
    }

    protected function matchingInvoicesByExpectedAmount(int $expectedAmount): Collection
    {
        return Tenant::query()
            ->get()
            ->flatMap(function (Tenant $tenant) use ($expectedAmount): array {
                return collect($tenant->billingInvoices())
                    ->filter(function (array $invoice) use ($expectedAmount): bool {
                        return $this->isCollectibleInvoice($invoice)
                            && (int) data_get($invoice, 'payment.manual_transfer.expected_amount', 0) === $expectedAmount;
                    })
                    ->map(fn (array $invoice): array => [
                        'tenant' => $tenant,
                        'invoice' => $invoice,
                    ])
                    ->values()
                    ->all();
            })
            ->values();
    }

    protected function isCollectibleInvoice(array $invoice): bool
    {
        return in_array((string) ($invoice['status'] ?? 'issued'), ['issued', 'overdue'], true)
            && ! data_get($invoice, 'paid_at');
    }

    protected function markInvoicePaidFromEvidence(
        Tenant $tenant,
        array $invoice,
        array $payload,
        array $transferConfig,
        CarbonImmutable $matchedAt
    ): array {
        $messageId = trim((string) data_get($payload, 'message_id', data_get($payload, 'parsed_payload.message_id', '')));
        $parsedPayload = (array) data_get($payload, 'parsed_payload', []);
        $billingInvoices = collect($tenant->billingInvoices())
            ->map(function (array $candidate) use ($invoice, $payload, $transferConfig, $matchedAt, $messageId, $parsedPayload): array {
                if (($candidate['invoice_number'] ?? null) !== ($invoice['invoice_number'] ?? null)) {
                    return $candidate;
                }

                $manualTransfer = array_merge(
                    [
                        'bank_name' => (string) ($transferConfig['bank_name'] ?? ''),
                        'account_name' => (string) ($transferConfig['account_name'] ?? ''),
                        'account_number' => (string) ($transferConfig['account_number'] ?? ''),
                        'unique_code' => (int) data_get($candidate, 'payment.manual_transfer.unique_code', 0),
                        'expected_amount' => (int) data_get($candidate, 'payment.manual_transfer.expected_amount', 0),
                        'base_amount' => (int) data_get($candidate, 'payment.manual_transfer.base_amount', data_get($candidate, 'invoice_total', 0)),
                    ],
                    (array) data_get($candidate, 'payment.manual_transfer', [])
                );

                $manualTransfer['matched_by'] = 'bca_email_unique_code';
                $manualTransfer['matched_at'] = $matchedAt->toIso8601String();
                $manualTransfer['source_adapter'] = (string) data_get($payload, 'source_adapter', 'bca_email');
                $manualTransfer['evidence'] = [
                    'message_id' => $messageId,
                    'ws_ref' => (string) data_get($payload, 'ws_ref', data_get($parsedPayload, 'ws_ref', '')),
                    'sender_name' => (string) data_get($payload, 'sender_name', data_get($parsedPayload, 'sender_name', '')),
                    'account_number' => (string) data_get($parsedPayload, 'account_number', ''),
                    'credit_amount' => $this->normalizeCurrencyAmount(data_get($parsedPayload, 'credit_amount')),
                    'transaction_at' => (string) data_get($parsedPayload, 'transaction_at', ''),
                    'from_address' => (string) data_get($parsedPayload, 'from_address', data_get($payload, 'from_address', '')),
                    'raw_payload' => $payload,
                ];

                $candidate['status'] = 'paid';
                $candidate['paid_at'] = $matchedAt;
                $candidate['payment'] = [
                    'method' => 'manual_transfer',
                    'status' => 'paid',
                    'reference' => (string) data_get($payload, 'ws_ref', $messageId !== '' ? $messageId : ($candidate['invoice_number'] ?? '')),
                    'notes' => 'Pembayaran transfer manual di-auto-match dari evidence email BCA berdasarkan nominal unik.',
                    'paid_via' => trim((string) ($transferConfig['bank_name'] ?? 'BCA')),
                    'customer_name' => (string) data_get($payload, 'sender_name', data_get($parsedPayload, 'sender_name', '')),
                    'manual_transfer' => $manualTransfer,
                    'qris' => (array) data_get($candidate, 'payment.qris', []),
                ];

                return $candidate;
            })
            ->values()
            ->all();

        $tenant->forceFill([
            'billing_invoices' => $billingInvoices,
            'last_invoice_status_updated_at' => $matchedAt->toIso8601String(),
        ])->save();

        return collect($tenant->fresh()->billingInvoices())
            ->firstWhere('invoice_number', (string) ($invoice['invoice_number'] ?? '')) ?? $invoice;
    }

    protected function messageIdAlreadyProcessed(string $messageId): bool
    {
        return Tenant::query()
            ->get()
            ->contains(function (Tenant $tenant) use ($messageId): bool {
                return collect($tenant->billingInvoices())
                    ->contains(fn (array $invoice): bool => (string) data_get($invoice, 'payment.manual_transfer.evidence.message_id', '') === $messageId);
            });
    }

    protected function normalizeCurrencyAmount(mixed $amount): int
    {
        if (is_int($amount)) {
            return max($amount, 0);
        }

        if (is_float($amount)) {
            return max((int) round($amount), 0);
        }

        $normalized = trim((string) $amount);

        if ($normalized === '') {
            return 0;
        }

        $normalized = str_replace(',', '.', $normalized);

        if (is_numeric($normalized)) {
            return max((int) round((float) $normalized), 0);
        }

        $digitsOnly = preg_replace('/\D+/', '', $normalized) ?? '';

        return $digitsOnly !== '' ? max((int) $digitsOnly, 0) : 0;
    }

    protected function normalizeAccountNumber(string $accountNumber): string
    {
        return preg_replace('/\D+/', '', $accountNumber) ?? '';
    }

    protected function manualTransferConfig(): array
    {
        $settings = CentralSetting::paymentMethodSettings();

        return [
            'bank_name' => trim((string) data_get($settings, 'manual_transfer.bank_name', '')),
            'account_name' => trim((string) data_get($settings, 'manual_transfer.account_name', '')),
            'account_number' => trim((string) data_get($settings, 'manual_transfer.account_number', '')),
        ];
    }
}
