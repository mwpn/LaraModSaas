<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\SubscriptionPackage;
use App\Models\Central\TenantSubscriptionInvoice;
use App\Models\Central\TenantSubscriptionInvoiceLine;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantSubscriptionInvoiceService
{
    public function invoiceHealthForTenant(Tenant $tenant): array
    {
        $tablesExist = $this->tablesExist();
        $relationalInvoices = $tablesExist ? $this->invoicesForTenant($tenant) : [];
        $legacyInvoices = $tenant->legacyBillingInvoices();
        $legacyMeta = $this->legacyMetaSnapshot($tenant);
        $legacyMetaKeys = collect($legacyMeta)
            ->filter(fn ($value): bool => $value !== null && $value !== '' && $value !== [] && $value !== 0)
            ->keys()
            ->values()
            ->all();

        $relationalCount = count($relationalInvoices);
        $legacyCount = count($legacyInvoices);
        $hasRelational = $relationalCount > 0;
        $hasLegacy = $legacyCount > 0;
        $signaturesMatch = $hasRelational && $hasLegacy
            ? $this->invoiceSignatures($relationalInvoices) === $this->invoiceSignatures($legacyInvoices)
            : false;

        $status = match (true) {
            ! $tablesExist => 'tables_missing',
            $hasRelational && $hasLegacy && ! $signaturesMatch => 'mismatch',
            $hasRelational && ($hasLegacy || $legacyMetaKeys !== []) => 'relational_shadow',
            $hasRelational => 'relational_only',
            $hasLegacy || $legacyMetaKeys !== [] => 'legacy_only',
            default => 'empty',
        };

        return [
            'status' => $status,
            'label' => $this->invoiceHealthLabel($status),
            'tables_exist' => $tablesExist,
            'relational_count' => $relationalCount,
            'legacy_count' => $legacyCount,
            'has_relational' => $hasRelational,
            'has_legacy' => $hasLegacy,
            'signatures_match' => $signaturesMatch,
            'cleanup_ready' => $tablesExist && $hasRelational && $status !== 'mismatch',
            'legacy_meta_keys' => $legacyMetaKeys,
            'mismatch_invoice_numbers' => $this->mismatchInvoiceNumbers($relationalInvoices, $legacyInvoices),
        ];
    }

    public function auditTenants(?string $tenantId = null): array
    {
        $query = Tenant::query()->orderBy('id');

        if (is_string($tenantId) && trim($tenantId) !== '') {
            $query->whereKey(trim($tenantId));
        }

        $rows = $query->get()
            ->map(function (Tenant $tenant): array {
                $health = $this->invoiceHealthForTenant($tenant);

                return [
                    'tenant_id' => (string) $tenant->getKey(),
                    'tenant_name' => (string) ($tenant->name ?? $tenant->getKey()),
                    'status' => (string) $health['status'],
                    'label' => (string) $health['label'],
                    'relational_count' => (int) $health['relational_count'],
                    'legacy_count' => (int) $health['legacy_count'],
                    'cleanup_ready' => (bool) $health['cleanup_ready'],
                    'legacy_meta_keys' => $health['legacy_meta_keys'],
                    'mismatch_invoice_numbers' => $health['mismatch_invoice_numbers'],
                ];
            })
            ->values()
            ->all();

        return [
            'tables_exist' => $this->tablesExist(),
            'counts' => [
                'tenants' => count($rows),
                'relational_only' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'relational_only')),
                'relational_shadow' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'relational_shadow')),
                'legacy_only' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'legacy_only')),
                'mismatch' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'mismatch')),
                'empty' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'empty')),
                'tables_missing' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'tables_missing')),
                'cleanup_ready' => count(array_filter($rows, fn (array $row): bool => (bool) $row['cleanup_ready'])),
            ],
            'rows' => $rows,
        ];
    }

    public function cleanupLegacyShadow(Tenant $tenant): array
    {
        $health = $this->invoiceHealthForTenant($tenant);

        if (! $health['cleanup_ready']) {
            return [
                'cleaned' => false,
                'status' => (string) $health['status'],
                'label' => (string) $health['label'],
            ];
        }

        $tenant->forceFill([
            'billing_invoices' => [],
            'invoice_sequence' => null,
            'first_invoice_issued_at' => null,
            'last_invoice_generated_at' => null,
            'last_invoice_status_updated_at' => null,
        ])->save();

        return [
            'cleaned' => true,
            'status' => (string) $health['status'],
            'label' => (string) $health['label'],
        ];
    }

    public function syncTenantToLegacyShadow(Tenant $tenant): void
    {
        $relationalInvoices = $this->invoicesForTenant($tenant);
        $aggregate = $this->aggregateForTenant($tenant);

        $tenant->forceFill([
            'billing_invoices' => array_values(array_map(
                fn (array $record): array => $this->normalizeLegacyRecord($record),
                $relationalInvoices
            )),
            'invoice_sequence' => (int) ($aggregate['max_sequence'] ?? 0),
            'first_invoice_issued_at' => $aggregate['first_issued_at'] instanceof CarbonImmutable
                ? $aggregate['first_issued_at']->toIso8601String()
                : null,
            'last_invoice_generated_at' => $aggregate['last_generated_at'] instanceof CarbonImmutable
                ? $aggregate['last_generated_at']->toIso8601String()
                : null,
            'last_invoice_status_updated_at' => $aggregate['last_status_updated_at'] instanceof CarbonImmutable
                ? $aggregate['last_status_updated_at']->toIso8601String()
                : null,
        ])->save();
    }

    public function repairTenantInvoices(Tenant $tenant, string $mode = 'auto', bool $cleanupShadow = false): array
    {
        $mode = in_array($mode, ['auto', 'legacy_to_relational', 'relational_to_legacy'], true)
            ? $mode
            : 'auto';

        $before = $this->invoiceHealthForTenant($tenant);

        if (! $before['tables_exist']) {
            return [
                'tenant_id' => (string) $tenant->getKey(),
                'mode' => $mode,
                'action' => 'skipped',
                'reason' => 'tables_missing',
                'before' => $before,
                'after' => $before,
            ];
        }

        $action = 'skipped';
        $reason = 'noop';

        if ($mode === 'auto') {
            if ($before['status'] === 'legacy_only' && $before['has_legacy']) {
                $this->syncTenantInvoices($tenant, $tenant->legacyBillingInvoices());
                $action = 'synced';
                $reason = 'legacy_backfilled_to_relational';

                if ($cleanupShadow) {
                    $this->cleanupLegacyShadow($tenant->fresh() ?? $tenant);
                    $action = 'synced_and_cleaned';
                }
            } elseif ($before['status'] === 'relational_shadow' && $cleanupShadow) {
                $this->cleanupLegacyShadow($tenant);
                $action = 'cleaned';
                $reason = 'legacy_shadow_removed';
            } elseif ($before['status'] === 'mismatch') {
                $reason = 'manual_review_required';
            }
        }

        if ($mode === 'legacy_to_relational') {
            if ($tenant->legacyBillingInvoices() === []) {
                $reason = 'legacy_empty';
            } else {
                $this->syncTenantInvoices($tenant, $tenant->legacyBillingInvoices());
                $action = 'synced';
                $reason = 'legacy_backfilled_to_relational';

                if ($cleanupShadow) {
                    $this->cleanupLegacyShadow($tenant->fresh());
                    $action = 'synced_and_cleaned';
                }
            }
        }

        if ($mode === 'relational_to_legacy') {
            if ($this->invoicesForTenant($tenant) === []) {
                $reason = 'relational_empty';
            } else {
                $this->syncTenantToLegacyShadow($tenant);
                $action = 'synced';
                $reason = 'relational_shadow_rebuilt';
            }
        }

        $freshTenant = $tenant->fresh() ?? $tenant;
        $after = $this->invoiceHealthForTenant($freshTenant);

        return [
            'tenant_id' => (string) $tenant->getKey(),
            'mode' => $mode,
            'action' => $action,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
        ];
    }

    public function hasRelationalInvoices(Tenant|string $tenant): bool
    {
        if (! $this->tablesExist()) {
            return false;
        }

        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : (string) $tenant;

        if ($tenantId === '') {
            return false;
        }

        return TenantSubscriptionInvoice::query()
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    public function tablesExist(): bool
    {
        $schema = Schema::connection($this->connectionName());

        return $schema->hasTable('tenant_subscription_invoices')
            && $schema->hasTable('tenant_subscription_invoice_lines');
    }

    public function invoicesForTenant(Tenant|string $tenant): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : (string) $tenant;

        if ($tenantId === '') {
            return [];
        }

        return TenantSubscriptionInvoice::query()
            ->with('lines')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TenantSubscriptionInvoice $invoice): array => $this->toRuntimeArray($invoice))
            ->values()
            ->all();
    }

    public function aggregateForTenant(Tenant|string $tenant): array
    {
        if (! $this->tablesExist()) {
            return $this->emptyAggregate();
        }

        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : (string) $tenant;

        if ($tenantId === '') {
            return $this->emptyAggregate();
        }

        $invoices = TenantSubscriptionInvoice::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get(['invoice_number', 'issued_at', 'created_at', 'updated_at']);

        if ($invoices->isEmpty()) {
            return $this->emptyAggregate();
        }

        $firstIssuedAt = $invoices
            ->map(fn (TenantSubscriptionInvoice $invoice) => $invoice->issued_at ?: $invoice->created_at)
            ->filter()
            ->sortBy(fn ($value) => $value->getTimestamp())
            ->first();
        $lastGeneratedAt = $invoices
            ->map(fn (TenantSubscriptionInvoice $invoice) => $invoice->created_at)
            ->filter()
            ->sortByDesc(fn ($value) => $value->getTimestamp())
            ->first();
        $lastStatusUpdatedAt = $invoices
            ->map(fn (TenantSubscriptionInvoice $invoice) => $invoice->updated_at)
            ->filter()
            ->sortByDesc(fn ($value) => $value->getTimestamp())
            ->first();

        return [
            'count' => $invoices->count(),
            'max_sequence' => $invoices
                ->map(fn (TenantSubscriptionInvoice $invoice): int => $this->extractInvoiceSequence((string) $invoice->invoice_number))
                ->max(),
            'first_issued_at' => $firstIssuedAt ? CarbonImmutable::instance($firstIssuedAt) : null,
            'last_generated_at' => $lastGeneratedAt ? CarbonImmutable::instance($lastGeneratedAt) : null,
            'last_status_updated_at' => $lastStatusUpdatedAt ? CarbonImmutable::instance($lastStatusUpdatedAt) : null,
        ];
    }

    public function syncTenantInvoices(Tenant $tenant, array $records): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $normalized = collect($records)
            ->filter(fn ($record): bool => is_array($record) && (string) ($record['invoice_number'] ?? '') !== '')
            ->map(fn (array $record): array => $this->normalizeRecord($tenant, $record))
            ->values();

        DB::connection($this->connectionName())->transaction(function () use ($tenant, $normalized): void {
            $invoiceNumbers = $normalized->pluck('invoice_number')->all();

            if ($invoiceNumbers !== []) {
                TenantSubscriptionInvoice::query()
                    ->where('tenant_id', (string) $tenant->getKey())
                    ->whereNotIn('invoice_number', $invoiceNumbers)
                    ->delete();
            } else {
                TenantSubscriptionInvoice::query()
                    ->where('tenant_id', (string) $tenant->getKey())
                    ->delete();

                return;
            }

            foreach ($normalized as $record) {
                $invoice = TenantSubscriptionInvoice::query()->updateOrCreate(
                    ['invoice_number' => $record['invoice_number']],
                    [
                        'tenant_id' => (string) $tenant->getKey(),
                        'period_key' => $record['period_key'],
                        'period_label' => $record['period_label'],
                        'package_id' => $record['package_id'],
                        'package_code_snapshot' => $record['package_code'],
                        'status' => $record['status'],
                        'currency' => $record['currency'],
                        'setup_fee_total' => $record['setup_fee'],
                        'monthly_total' => $record['monthly_total'],
                        'invoice_total' => $record['invoice_total'],
                        'issued_at' => $record['issued_at'],
                        'due_at' => $record['due_at'],
                        'paid_at' => $record['paid_at'],
                        'payment_meta' => $record['payment'],
                        'usage_snapshot' => $record['usage'],
                        'created_at' => $record['created_at'],
                    ]
                );

                TenantSubscriptionInvoiceLine::query()
                    ->where('invoice_id', $invoice->getKey())
                    ->delete();

                foreach ($record['lines'] as $index => $line) {
                    TenantSubscriptionInvoiceLine::query()->create([
                        'invoice_id' => $invoice->getKey(),
                        'line_code' => (string) ($line['code'] ?? ('line_' . ($index + 1))),
                        'label' => (string) ($line['label'] ?? 'Line Item'),
                        'kind' => (string) ($line['kind'] ?? 'flat'),
                        'quantity' => $this->normalizeDecimal($line['qty'] ?? 1),
                        'amount' => max((int) ($line['amount'] ?? 0), 0),
                        'rate' => isset($line['rate']) ? $this->normalizeDecimal($line['rate']) : null,
                        'line_total' => max((int) ($line['total'] ?? 0), 0),
                        'meta' => is_array($line['meta'] ?? null) ? $line['meta'] : [],
                    ]);
                }
            }
        });
    }

    public function syncAllFromLegacy(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant): void {
            $legacyInvoices = $this->legacyInvoicesFromTenant($tenant);

            if ($legacyInvoices !== []) {
                $this->syncTenantInvoices($tenant, $legacyInvoices);
            }
        });
    }

    public function mutateInvoice(Tenant $tenant, string $invoiceNumber, callable $mutator): ?array
    {
        $updated = null;
        $sourceInvoices = $this->invoicesForMutation($tenant);

        $records = collect($sourceInvoices)
            ->map(function (array $invoice) use ($invoiceNumber, $mutator, &$updated): array {
                if (($invoice['invoice_number'] ?? null) !== $invoiceNumber) {
                    return $this->normalizeRecordArray($invoice);
                }

                $updated = $this->normalizeRecordArray($mutator($invoice));

                return $updated;
            })
            ->all();

        if (! is_array($updated)) {
            return null;
        }

        if (! $this->tablesExist()) {
            $this->persistLegacyInvoices($tenant, $records);

            return collect($tenant->fresh()->legacyBillingInvoices())
                ->firstWhere('invoice_number', $invoiceNumber)
                ?? collect($records)->firstWhere('invoice_number', $invoiceNumber);
        }

        $this->syncTenantInvoices($tenant, $records);

        return collect($this->invoicesForTenant($tenant))
            ->firstWhere('invoice_number', $invoiceNumber)
            ?? collect($records)->firstWhere('invoice_number', $invoiceNumber);
    }

    protected function legacyInvoicesFromTenant(Tenant $tenant): array
    {
        $invoices = data_get($tenant, 'billing_invoices', []);

        if (! is_array($invoices)) {
            return [];
        }

        return collect($invoices)
            ->filter(fn ($invoice): bool => is_array($invoice))
            ->values()
            ->all();
    }

    protected function invoicesForMutation(Tenant $tenant): array
    {
        $relationalInvoices = $this->invoicesForTenant($tenant);

        if ($relationalInvoices !== []) {
            return $relationalInvoices;
        }

        return $tenant->legacyBillingInvoices();
    }

    protected function normalizeRecordArray(array $record): array
    {
        return [
            'invoice_number' => (string) ($record['invoice_number'] ?? ''),
            'period_key' => (string) ($record['period_key'] ?? ''),
            'period_label' => (string) ($record['period_label'] ?? ''),
            'package_code' => (string) ($record['package_code'] ?? ''),
            'status' => (string) ($record['status'] ?? 'issued'),
            'currency' => (string) ($record['currency'] ?? 'IDR'),
            'invoice_total' => max((int) ($record['invoice_total'] ?? 0), 0),
            'monthly_total' => max((int) ($record['monthly_total'] ?? 0), 0),
            'setup_fee' => max((int) ($record['setup_fee'] ?? 0), 0),
            'usage' => is_array($record['usage'] ?? null) ? $record['usage'] : [],
            'lines' => is_array($record['lines'] ?? null) ? $record['lines'] : [],
            'issued_at' => $record['issued_at'] ?? null,
            'due_at' => $record['due_at'] ?? null,
            'paid_at' => $record['paid_at'] ?? null,
            'created_at' => $record['created_at'] ?? null,
            'payment' => is_array($record['payment'] ?? null) ? $record['payment'] : [],
        ];
    }

    protected function invoiceHealthLabel(string $status): string
    {
        return match ($status) {
            'tables_missing' => 'Schema Relasional Belum Ada',
            'mismatch' => 'Mismatch Legacy vs Relasional',
            'relational_shadow' => 'Relasional Aktif + Shadow Legacy',
            'relational_only' => 'Relasional Only',
            'legacy_only' => 'Legacy Only',
            default => 'Kosong',
        };
    }

    protected function legacyMetaSnapshot(Tenant $tenant): array
    {
        return [
            'invoice_sequence' => data_get($tenant, 'invoice_sequence'),
            'first_invoice_issued_at' => data_get($tenant, 'first_invoice_issued_at'),
            'last_invoice_generated_at' => data_get($tenant, 'last_invoice_generated_at'),
            'last_invoice_status_updated_at' => data_get($tenant, 'last_invoice_status_updated_at'),
        ];
    }

    protected function invoiceSignatures(array $records): array
    {
        return collect($records)
            ->filter(fn ($record): bool => is_array($record))
            ->map(function (array $record): array {
                $normalized = $this->normalizeRecordArray($record);

                return [
                    'invoice_number' => (string) ($normalized['invoice_number'] ?? ''),
                    'status' => (string) ($normalized['status'] ?? ''),
                    'invoice_total' => (int) ($normalized['invoice_total'] ?? 0),
                    'period_key' => (string) ($normalized['period_key'] ?? ''),
                    'paid_at' => $normalized['paid_at'] instanceof CarbonImmutable
                        ? $normalized['paid_at']->toIso8601String()
                        : (string) ($normalized['paid_at'] ?? ''),
                ];
            })
            ->sortBy(fn (array $record): string => $record['invoice_number'])
            ->values()
            ->all();
    }

    protected function mismatchInvoiceNumbers(array $relationalInvoices, array $legacyInvoices): array
    {
        $relationalMap = collect($this->invoiceSignatures($relationalInvoices))
            ->mapWithKeys(fn (array $record): array => [$record['invoice_number'] => $record]);
        $legacyMap = collect($this->invoiceSignatures($legacyInvoices))
            ->mapWithKeys(fn (array $record): array => [$record['invoice_number'] => $record]);

        return $relationalMap
            ->keys()
            ->merge($legacyMap->keys())
            ->unique()
            ->filter(function (string $invoiceNumber) use ($relationalMap, $legacyMap): bool {
                return $relationalMap->get($invoiceNumber) !== $legacyMap->get($invoiceNumber);
            })
            ->values()
            ->all();
    }

    protected function persistLegacyInvoices(Tenant $tenant, array $records): void
    {
        $tenant->forceFill([
            'billing_invoices' => array_values(array_map(
                fn (array $record): array => $this->normalizeLegacyRecord($record),
                $records
            )),
            'last_invoice_status_updated_at' => CarbonImmutable::now()->toIso8601String(),
        ])->save();
    }

    protected function normalizeLegacyRecord(array $record): array
    {
        return [
            'invoice_number' => (string) ($record['invoice_number'] ?? ''),
            'period_key' => (string) ($record['period_key'] ?? ''),
            'period_label' => (string) ($record['period_label'] ?? ''),
            'package_code' => (string) ($record['package_code'] ?? ''),
            'status' => (string) ($record['status'] ?? 'issued'),
            'currency' => (string) ($record['currency'] ?? 'IDR'),
            'invoice_total' => max((int) ($record['invoice_total'] ?? 0), 0),
            'monthly_total' => max((int) ($record['monthly_total'] ?? 0), 0),
            'setup_fee' => max((int) ($record['setup_fee'] ?? 0), 0),
            'usage' => is_array($record['usage'] ?? null) ? $record['usage'] : [],
            'lines' => is_array($record['lines'] ?? null) ? $record['lines'] : [],
            'issued_at' => $record['issued_at'] instanceof CarbonImmutable ? $record['issued_at']->toIso8601String() : $record['issued_at'],
            'due_at' => $record['due_at'] instanceof CarbonImmutable ? $record['due_at']->toIso8601String() : $record['due_at'],
            'paid_at' => $record['paid_at'] instanceof CarbonImmutable ? $record['paid_at']->toIso8601String() : $record['paid_at'],
            'created_at' => $record['created_at'] instanceof CarbonImmutable ? $record['created_at']->toIso8601String() : $record['created_at'],
            'payment' => is_array($record['payment'] ?? null) ? $record['payment'] : [],
        ];
    }

    protected function normalizeRecord(Tenant $tenant, array $record): array
    {
        $packageCode = (string) ($record['package_code'] ?? $tenant->packageCode() ?? '');
        $tenantPlatform = strtolower((string) data_get($tenant, 'saas_type', 'universal'));
        $package = $packageCode !== ''
            ? SubscriptionPackage::query()
                ->where('platform_type', $tenantPlatform)
                ->where('package_code', $packageCode)
                ->first()
            : null;

        return [
            'invoice_number' => (string) ($record['invoice_number'] ?? ''),
            'period_key' => (string) ($record['period_key'] ?? ''),
            'period_label' => (string) ($record['period_label'] ?? ''),
            'package_id' => $package?->getKey(),
            'package_code' => $packageCode,
            'status' => $this->normalizedStatus((string) ($record['status'] ?? 'issued'), $record),
            'currency' => (string) ($record['currency'] ?? 'IDR'),
            'invoice_total' => max((int) ($record['invoice_total'] ?? 0), 0),
            'monthly_total' => max((int) ($record['monthly_total'] ?? 0), 0),
            'setup_fee' => max((int) ($record['setup_fee'] ?? 0), 0),
            'usage' => $this->normalizeUsage((array) ($record['usage'] ?? [])),
            'lines' => $this->normalizeLines((array) ($record['lines'] ?? [])),
            'issued_at' => $this->normalizeTimestamp($record['issued_at'] ?? null),
            'due_at' => $this->normalizeTimestamp($record['due_at'] ?? null),
            'paid_at' => $this->normalizeTimestamp($record['paid_at'] ?? null),
            'created_at' => $this->normalizeTimestamp($record['created_at'] ?? null) ?? CarbonImmutable::now(),
            'payment' => $this->normalizePayment((array) ($record['payment'] ?? [])),
        ];
    }

    protected function toRuntimeArray(TenantSubscriptionInvoice $invoice): array
    {
        $status = $this->normalizedStatus((string) $invoice->status, [
            'due_at' => $invoice->due_at,
            'paid_at' => $invoice->paid_at,
        ]);

        return [
            'invoice_number' => (string) $invoice->invoice_number,
            'period_key' => (string) $invoice->period_key,
            'period_label' => (string) $invoice->period_label,
            'package_code' => (string) $invoice->package_code_snapshot,
            'status' => $status,
            'currency' => (string) $invoice->currency,
            'invoice_total' => (int) $invoice->invoice_total,
            'monthly_total' => (int) $invoice->monthly_total,
            'setup_fee' => (int) $invoice->setup_fee_total,
            'usage' => $this->normalizeUsage((array) ($invoice->usage_snapshot ?? [])),
            'lines' => $invoice->lines
                ->map(fn (TenantSubscriptionInvoiceLine $line): array => [
                    'code' => (string) ($line->line_code ?? ''),
                    'label' => (string) $line->label,
                    'kind' => (string) $line->kind,
                    'qty' => (float) $line->quantity,
                    'amount' => (int) $line->amount,
                    'rate' => $line->rate !== null ? (float) $line->rate : null,
                    'total' => (int) $line->line_total,
                    'meta' => is_array($line->meta) ? $line->meta : [],
                ])
                ->values()
                ->all(),
            'issued_at' => $invoice->issued_at ? CarbonImmutable::instance($invoice->issued_at) : null,
            'due_at' => $invoice->due_at ? CarbonImmutable::instance($invoice->due_at) : null,
            'paid_at' => $invoice->paid_at ? CarbonImmutable::instance($invoice->paid_at) : null,
            'created_at' => $invoice->created_at ? CarbonImmutable::instance($invoice->created_at) : null,
            'payment' => $this->normalizePayment((array) ($invoice->payment_meta ?? [])),
        ];
    }

    protected function normalizeUsage(array $usage): array
    {
        return [
            'customers' => max((int) ($usage['customers'] ?? 0), 0),
            'successful_transactions' => max((int) ($usage['successful_transactions'] ?? 0), 0),
            'checkouts' => max((int) ($usage['checkouts'] ?? 0), 0),
            'transaction_amount' => max((int) ($usage['transaction_amount'] ?? 0), 0),
        ];
    }

    protected function normalizeLines(array $lines): array
    {
        return collect($lines)
            ->filter(fn ($line): bool => is_array($line))
            ->map(fn (array $line): array => [
                'code' => (string) ($line['code'] ?? ''),
                'label' => (string) ($line['label'] ?? 'Line Item'),
                'kind' => (string) ($line['kind'] ?? 'flat'),
                'qty' => $this->normalizeDecimal($line['qty'] ?? 1),
                'amount' => max((int) ($line['amount'] ?? 0), 0),
                'rate' => isset($line['rate']) ? $this->normalizeDecimal($line['rate']) : null,
                'total' => max((int) ($line['total'] ?? 0), 0),
                'meta' => is_array($line['meta'] ?? null) ? $line['meta'] : [],
            ])
            ->values()
            ->all();
    }

    protected function normalizePayment(array $payment): array
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
                'matched_at' => $this->normalizeTimestamp(data_get($payment, 'manual_transfer.matched_at')),
                'source_adapter' => (string) data_get($payment, 'manual_transfer.source_adapter', ''),
                'evidence' => is_array(data_get($payment, 'manual_transfer.evidence'))
                    ? data_get($payment, 'manual_transfer.evidence')
                    : [],
            ],
            'qris' => [
                'invoice_id' => (string) data_get($payment, 'qris.invoice_id', ''),
                'content' => (string) data_get($payment, 'qris.content', ''),
                'nmid' => (string) data_get($payment, 'qris.nmid', ''),
                'request_date' => $this->normalizeTimestamp(data_get($payment, 'qris.request_date')),
                'expires_at' => $this->normalizeTimestamp(data_get($payment, 'qris.expires_at')),
                'last_checked_at' => $this->normalizeTimestamp(data_get($payment, 'qris.last_checked_at')),
                'raw_status' => (string) data_get($payment, 'qris.raw_status', ''),
            ],
        ];
    }

    protected function normalizedStatus(string $status, array $record): string
    {
        $status = strtolower(trim($status));

        if (! in_array($status, ['draft', 'issued', 'paid', 'overdue', 'void'], true)) {
            $status = 'issued';
        }

        $dueAt = $this->normalizeTimestamp($record['due_at'] ?? null);
        $paidAt = $this->normalizeTimestamp($record['paid_at'] ?? null);

        if (in_array($status, ['draft', 'issued'], true) && ! $paidAt && $dueAt && $dueAt->isPast()) {
            return 'overdue';
        }

        return $status;
    }

    protected function extractInvoiceSequence(string $invoiceNumber): int
    {
        if (preg_match('/-(\d{1,10})$/', $invoiceNumber, $matches) === 1) {
            return max((int) ($matches[1] ?? 0), 0);
        }

        return 0;
    }

    protected function emptyAggregate(): array
    {
        return [
            'count' => 0,
            'max_sequence' => 0,
            'first_issued_at' => null,
            'last_generated_at' => null,
            'last_status_updated_at' => null,
        ];
    }

    protected function normalizeTimestamp(mixed $value): ?CarbonImmutable
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

    protected function normalizeDecimal(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    protected function connectionName(): string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }
}
