<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Central\TenantModuleState;
use App\Models\Central\TenantSubscription;
use App\Services\Central\TenantSubscriptionInvoiceService;
use App\Services\Central\TenantSubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    protected static function booted(): void
    {
        static::creating(function (self $tenant): void {
            if (! $tenant->getInternal('db_connection')) {
                $tenant->setInternal('db_connection', 'tenant_template');
            }
        });
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'saas_type',
        ];
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class, 'tenant_id', 'id');
    }

    public function moduleStates(): HasMany
    {
        return $this->hasMany(TenantModuleState::class, 'tenant_id', 'id');
    }

    public function normalizedStatus(): string
    {
        $status = strtolower((string) data_get($this, 'status', 'active'));

        return in_array($status, ['active', 'suspended'], true)
            ? $status
            : 'active';
    }

    public function isSuspended(): bool
    {
        return $this->normalizedStatus() === 'suspended';
    }

    public function suspendedAt(): ?CarbonImmutable
    {
        $suspendedAt = data_get($this, 'suspended_at');

        if (! is_string($suspendedAt) || $suspendedAt === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($suspendedAt);
        } catch (\Throwable) {
            return null;
        }
    }

    public function packageCode(): ?string
    {
        $subscription = $this->subscriptionRecord();

        if ($subscription) {
            $packageCode = trim((string) ($subscription->package_code_snapshot ?: $subscription->package?->package_code));

            if ($packageCode !== '') {
                return $packageCode;
            }
        }

        $packageCode = trim((string) data_get($this, 'package_code', ''));

        return $packageCode !== '' ? $packageCode : null;
    }

    public function subscriptionStatus(): string
    {
        if ($this->isSuspended()) {
            return 'suspended';
        }

        $subscription = $this->subscriptionRecord();
        $status = strtolower((string) ($subscription?->status ?: data_get($this, 'subscription_status', 'active')));

        if (! in_array($status, ['trial', 'active', 'grace', 'expired', 'suspended'], true)) {
            $status = 'active';
        }

        $expiresAt = $this->subscriptionExpiresAt();
        $graceUntil = $this->subscriptionGraceUntil();
        if ($expiresAt && $expiresAt->isPast()) {
            if ($graceUntil && $graceUntil->isFuture()) {
                return 'grace';
            }

            return 'expired';
        }

        return $status;
    }

    public function subscriptionStartsAt(): ?CarbonImmutable
    {
        $subscription = $this->subscriptionRecord();

        if ($subscription?->starts_at) {
            return CarbonImmutable::instance($subscription->starts_at);
        }

        return $this->parseTenantTimestamp(data_get($this, 'subscription_starts_at'));
    }

    public function subscriptionExpiresAt(): ?CarbonImmutable
    {
        $subscription = $this->subscriptionRecord();

        if ($subscription?->expires_at) {
            return CarbonImmutable::instance($subscription->expires_at);
        }

        return $this->parseTenantTimestamp(data_get($this, 'subscription_expires_at'));
    }

    public function subscriptionGraceUntil(): ?CarbonImmutable
    {
        $subscription = $this->subscriptionRecord();

        if ($subscription?->grace_until) {
            return CarbonImmutable::instance($subscription->grace_until);
        }

        return $this->parseTenantTimestamp(data_get($this, 'subscription_grace_until'));
    }

    public function billingUsageSnapshot(): array
    {
        $subscription = $this->subscriptionRecord();
        $usage = $subscription?->billing_usage_snapshot ?? data_get($this, 'billing_usage', []);

        return [
            'customers' => max((int) data_get($usage, 'customers', 0), 0),
            'successful_transactions' => max((int) data_get($usage, 'successful_transactions', 0), 0),
            'checkouts' => max((int) data_get($usage, 'checkouts', 0), 0),
            'transaction_amount' => max((int) data_get($usage, 'transaction_amount', 0), 0),
        ];
    }

    public function invoiceSequence(): int
    {
        $invoiceService = app(TenantSubscriptionInvoiceService::class);
        $aggregate = $invoiceService->aggregateForTenant($this);
        $derivedSequence = collect($this->billingInvoices())
            ->map(function (array $invoice): int {
                $invoiceNumber = (string) ($invoice['invoice_number'] ?? '');

                if (preg_match('/-(\d{1,10})$/', $invoiceNumber, $matches) === 1) {
                    return max((int) ($matches[1] ?? 0), 0);
                }

                return 0;
            })
            ->max() ?? 0;

        return max(
            (int) ($aggregate['max_sequence'] ?? 0),
            $derivedSequence,
            (int) data_get($this, 'invoice_sequence', 0),
        );
    }

    public function firstInvoiceIssuedAt(): ?CarbonImmutable
    {
        $aggregate = app(TenantSubscriptionInvoiceService::class)->aggregateForTenant($this);

        if ($aggregate['first_issued_at'] instanceof CarbonImmutable) {
            return $aggregate['first_issued_at'];
        }

        $legacyTimestamp = $this->parseTenantTimestamp(data_get($this, 'first_invoice_issued_at'));

        if ($legacyTimestamp instanceof CarbonImmutable) {
            return $legacyTimestamp;
        }

        return collect($this->billingInvoices())
            ->map(fn (array $invoice): ?CarbonImmutable => $invoice['issued_at'] ?? $invoice['created_at'] ?? null)
            ->filter(fn ($value): bool => $value instanceof CarbonImmutable)
            ->sortBy(fn (CarbonImmutable $value): int => $value->getTimestamp())
            ->first();
    }

    public function lastInvoiceGeneratedAt(): ?CarbonImmutable
    {
        $aggregate = app(TenantSubscriptionInvoiceService::class)->aggregateForTenant($this);

        if ($aggregate['last_generated_at'] instanceof CarbonImmutable) {
            return $aggregate['last_generated_at'];
        }

        return $this->parseTenantTimestamp(data_get($this, 'last_invoice_generated_at'));
    }

    public function lastInvoiceStatusUpdatedAt(): ?CarbonImmutable
    {
        $aggregate = app(TenantSubscriptionInvoiceService::class)->aggregateForTenant($this);

        if ($aggregate['last_status_updated_at'] instanceof CarbonImmutable) {
            return $aggregate['last_status_updated_at'];
        }

        return $this->parseTenantTimestamp(data_get($this, 'last_invoice_status_updated_at'));
    }

    public function billingInvoices(): array
    {
        $relationalInvoices = app(TenantSubscriptionInvoiceService::class)->invoicesForTenant($this);

        if ($relationalInvoices !== []) {
            return $relationalInvoices;
        }

        return $this->legacyBillingInvoices();
    }

    public function legacyBillingInvoices(): array
    {
        $invoices = data_get($this, 'billing_invoices', []);

        if (! is_array($invoices)) {
            return [];
        }

        return collect($invoices)
            ->filter(fn ($invoice): bool => is_array($invoice))
            ->map(function (array $invoice): array {
                $status = strtolower((string) data_get($invoice, 'status', 'issued'));

                if (! in_array($status, ['draft', 'issued', 'paid', 'overdue', 'void'], true)) {
                    $status = 'issued';
                }

                $dueAt = $this->parseTenantTimestamp(data_get($invoice, 'due_at'));
                $paidAt = $this->parseTenantTimestamp(data_get($invoice, 'paid_at'));
                $payment = $this->normalizeInvoicePayment(data_get($invoice, 'payment', []));

                if (in_array($status, ['draft', 'issued'], true) && ! $paidAt && $dueAt && $dueAt->isPast()) {
                    $status = 'overdue';
                }

                return [
                    'invoice_number' => (string) data_get($invoice, 'invoice_number', ''),
                    'period_key' => (string) data_get($invoice, 'period_key', ''),
                    'period_label' => (string) data_get($invoice, 'period_label', ''),
                    'package_code' => (string) data_get($invoice, 'package_code', ''),
                    'status' => $status,
                    'currency' => (string) data_get($invoice, 'currency', 'IDR'),
                    'invoice_total' => max((int) data_get($invoice, 'invoice_total', 0), 0),
                    'monthly_total' => max((int) data_get($invoice, 'monthly_total', 0), 0),
                    'setup_fee' => max((int) data_get($invoice, 'setup_fee', 0), 0),
                    'usage' => [
                        'customers' => max((int) data_get($invoice, 'usage.customers', 0), 0),
                        'successful_transactions' => max((int) data_get($invoice, 'usage.successful_transactions', 0), 0),
                        'checkouts' => max((int) data_get($invoice, 'usage.checkouts', 0), 0),
                        'transaction_amount' => max((int) data_get($invoice, 'usage.transaction_amount', 0), 0),
                    ],
                    'lines' => is_array(data_get($invoice, 'lines')) ? data_get($invoice, 'lines') : [],
                    'issued_at' => $this->parseTenantTimestamp(data_get($invoice, 'issued_at')),
                    'due_at' => $dueAt,
                    'paid_at' => $paidAt,
                    'created_at' => $this->parseTenantTimestamp(data_get($invoice, 'created_at')),
                    'payment' => $payment,
                ];
            })
            ->sortByDesc(fn (array $invoice) => $invoice['created_at']?->getTimestamp() ?? 0)
            ->values()
            ->all();
    }

    public function latestBillingInvoice(): ?array
    {
        return $this->billingInvoices()[0] ?? null;
    }

    public function billingGraceDays(): int
    {
        $subscription = $this->subscriptionRecord();

        return max((int) ($subscription?->billing_grace_days ?? data_get($this, 'billing_grace_days', 3)), 0);
    }

    public function oldestCollectibleInvoice(): ?array
    {
        return collect($this->billingInvoices())
            ->filter(function (array $invoice): bool {
                return in_array($invoice['status'], ['issued', 'overdue'], true)
                    && ! $invoice['paid_at']
                    && ! in_array($invoice['status'], ['void'], true);
            })
            ->sortBy(fn (array $invoice) => $invoice['due_at']?->getTimestamp() ?? PHP_INT_MAX)
            ->first();
    }

    public function billingGraceEndsAt(): ?CarbonImmutable
    {
        $invoice = $this->oldestCollectibleInvoice();
        $dueAt = $invoice['due_at'] ?? null;

        if (! $dueAt instanceof CarbonImmutable) {
            return null;
        }

        return $dueAt->addDays($this->billingGraceDays());
    }

    public function hasInvoiceOverdueBlock(): bool
    {
        $graceEndsAt = $this->billingGraceEndsAt();

        return $graceEndsAt instanceof CarbonImmutable && $graceEndsAt->isPast();
    }

    public function hasSubscriptionBlock(): bool
    {
        return $this->subscriptionStatus() === 'expired';
    }

    public function hasAccessBlock(): bool
    {
        return $this->isSuspended() || $this->hasSubscriptionBlock() || $this->hasInvoiceOverdueBlock();
    }

    public function accessBlockReason(): ?string
    {
        if ($this->isSuspended()) {
            return 'manual_suspend';
        }

        if ($this->hasSubscriptionBlock()) {
            return 'subscription_expired';
        }

        if ($this->hasInvoiceOverdueBlock()) {
            return 'invoice_overdue';
        }

        return null;
    }

    public function accessBlockMeta(): array
    {
        $reason = $this->accessBlockReason();
        $invoice = $this->oldestCollectibleInvoice();
        $graceEndsAt = $this->billingGraceEndsAt();

        return [
            'reason' => $reason,
            'label' => match ($reason) {
                'manual_suspend' => 'Suspended Manual',
                'subscription_expired' => 'Subscription Expired',
                'invoice_overdue' => 'Invoice Overdue',
                default => 'Active',
            },
            'message' => match ($reason) {
                'manual_suspend' => 'Akses tenant sedang ditahan manual dari panel pusat.',
                'subscription_expired' => 'Masa aktif subscription tenant sudah lewat dan perlu diperpanjang.',
                'invoice_overdue' => 'Invoice tenant melewati jatuh tempo dan grace period billing.',
                default => 'Tenant masih aktif dan tidak sedang diblokir.',
            },
            'invoice_number' => is_array($invoice) ? ($invoice['invoice_number'] ?? null) : null,
            'due_at' => is_array($invoice) ? ($invoice['due_at'] ?? null) : null,
            'grace_ends_at' => $graceEndsAt,
        ];
    }

    protected function parseTenantTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeInvoicePayment(mixed $payment): array
    {
        if (! is_array($payment)) {
            $payment = [];
        }

        return [
            'method' => (string) data_get($payment, 'method', ''),
            'status' => (string) data_get($payment, 'status', ''),
            'reference' => (string) data_get($payment, 'reference', ''),
            'notes' => (string) data_get($payment, 'notes', ''),
            'paid_via' => (string) data_get($payment, 'paid_via', ''),
            'customer_name' => (string) data_get($payment, 'customer_name', ''),
            'manual_transfer' => [
                'bank_name' => (string) data_get($payment, 'manual_transfer.bank_name', ''),
                'account_name' => (string) data_get($payment, 'manual_transfer.account_name', ''),
                'account_number' => (string) data_get($payment, 'manual_transfer.account_number', ''),
                'base_amount' => max((int) data_get($payment, 'manual_transfer.base_amount', 0), 0),
                'unique_code' => max((int) data_get($payment, 'manual_transfer.unique_code', 0), 0),
                'expected_amount' => max((int) data_get($payment, 'manual_transfer.expected_amount', 0), 0),
                'matched_by' => (string) data_get($payment, 'manual_transfer.matched_by', ''),
                'matched_at' => $this->parseTenantTimestamp(data_get($payment, 'manual_transfer.matched_at')),
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
                'request_date' => $this->parseTenantTimestamp(data_get($payment, 'qris.request_date')),
                'expires_at' => $this->parseTenantTimestamp(data_get($payment, 'qris.expires_at')),
                'last_checked_at' => $this->parseTenantTimestamp(data_get($payment, 'qris.last_checked_at')),
                'raw_status' => (string) data_get($payment, 'qris.raw_status', ''),
            ],
        ];
    }

    protected function subscriptionRecord(): ?TenantSubscription
    {
        if ($this->relationLoaded('subscription')) {
            $loaded = $this->getRelation('subscription');

            return $loaded instanceof TenantSubscription ? $loaded : null;
        }

        $subscription = app(TenantSubscriptionService::class)->findForTenant($this);

        if ($subscription) {
            $this->setRelation('subscription', $subscription);
        }

        return $subscription;
    }
}
