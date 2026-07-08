<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\Concerns\InteractsWithTirtaAreaScope;
use App\Http\Controllers\Controller;
use App\Models\Tirta\MeterReaderAssignment;
use App\Models\Tirta\MeterReading;
use App\Models\Tirta\MeterReadingPeriod;
use App\Models\Tirta\ServiceArea;
use App\Models\Tirta\ServiceConnection;
use App\Models\User;
use App\Services\Tirta\TirtaBillingPeriodPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BaseFeature\Models\TenantSetting;

class TirtaMeterReadingController extends Controller
{
    use InteractsWithTirtaAreaScope;

    public function __construct(
        protected TirtaBillingPeriodPlanner $billingPeriodPlanner,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMeterReading();

        return view('basefeature::tirta.meter-readings', $this->buildMeterReadingWorkspaceData($request));
    }

    public function verifierDashboard(Request $request): View
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMeterReading();

        /** @var User|null $currentUser */
        $currentUser = Auth::guard('tenant')->user();

        if ($currentUser instanceof User && $currentUser->isMeterReader()) {
            abort(403, 'Dashboard verifikator hanya bisa diakses oleh admin atau operator verifikator.');
        }

        return view('basefeature::tirta.meter-verification', $this->buildMeterReadingWorkspaceData($request, true));
    }

    protected function buildMeterReadingWorkspaceData(Request $request, bool $verifierMode = false): array
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::guard('tenant')->user();
        $isMeterReader = $currentUser instanceof User && $currentUser->isMeterReader();

        $tenantSetting = $this->tenantSetting();
        $cycleSettings = $this->cycleSettings($tenantSetting);
        $periods = MeterReadingPeriod::query()
            ->withCount('readings')
            ->orderByDesc('period_start')
            ->orderByDesc('created_at')
            ->get();

        $selectedPeriod = $this->selectedPeriod($request, $periods);
        $meterReaders = $this->meterReaders();
        $defaultMeterReader = $this->resolveDefaultMeterReader($tenantSetting, $meterReaders);
        $meterAssignmentMode = $this->meterAssignmentMode($tenantSetting);
        $serviceAreas = ServiceArea::query()
            ->with('parent')
            ->withCount('connections')
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $query->whereIn('id', $this->tirtaAllowedAreaIds()->all()))
            ->orderByRaw("case area_type when 'branch' then 1 when 'unit' then 2 when 'rayon' then 3 else 4 end")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $serviceAreaMap = $serviceAreas->keyBy(fn (ServiceArea $area): string => (string) $area->getKey());
        $serviceAreaOptions = $this->serviceAreaOptions($serviceAreas, $serviceAreaMap);
        $serviceAreaDescendantMap = $this->serviceAreaDescendantMap($serviceAreas);
        $usesServiceAreas = $serviceAreas->isNotEmpty();
        $effectiveAssignmentMode = $meterAssignmentMode === 'per_area' && $usesServiceAreas
            ? 'per_area'
            : 'global';
        $readerAssignments = MeterReaderAssignment::query()
            ->with(['serviceArea.parent', 'user.role'])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $query->whereIn('service_area_id', $this->tirtaAllowedAreaIds()->all()))
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->get();
        $activeAssignments = $readerAssignments
            ->where('is_active', true)
            ->keyBy('service_area_id');
        $filters = [
            'service_area_id' => (string) $request->query('service_area_id', ''),
            'user_id' => (string) $request->query('user_id', ''),
            'status_bucket' => (string) $request->query('status_bucket', $verifierMode ? 'warning' : ''),
            'review_bucket' => (string) $request->query('review_bucket', $verifierMode ? 'need_review' : ''),
        ];

        if (
            $this->tirtaAreaIsRestricted()
            && $filters['service_area_id'] !== ''
            && $filters['service_area_id'] !== '__global__'
            && ! $this->tirtaAllowedAreaIds()->contains($filters['service_area_id'])
        ) {
            $filters['service_area_id'] = '';
        }

        if ($isMeterReader && $currentUser instanceof User) {
            $filters['user_id'] = (string) $currentUser->id;
            $filters['status_bucket'] = (string) $request->query('status_bucket', 'pending');
            $filters['review_bucket'] = '';
        }

        $connections = ServiceConnection::query()
            ->with(['customer', 'serviceArea', 'serviceCategory'])
            ->whereIn('status', ['active', 'inactive', 'blocked'])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaConnectionScope($query))
            ->orderBy('service_number')
            ->get();

        $currentReadings = $selectedPeriod instanceof MeterReadingPeriod
            ? MeterReading::query()
                ->with(['connection.customer', 'connection.serviceArea', 'connection.serviceCategory'])
                ->where('meter_reading_period_id', $selectedPeriod->id)
                ->orderByDesc('recorded_at')
                ->orderByDesc('created_at')
                ->get()
                ->keyBy('service_connection_id')
            : collect();

        $previousReadings = $this->previousReadingsMap($selectedPeriod, $connections->pluck('id')->all());

        $assignedConnectionRows = $connections->map(function (ServiceConnection $connection) use ($activeAssignments, $currentReadings, $previousReadings, $defaultMeterReader, $usesServiceAreas, $effectiveAssignmentMode, $serviceAreaMap): array {
            /** @var MeterReading|null $currentReading */
            $currentReading = $currentReadings->get($connection->id);
            /** @var MeterReading|null $previousReading */
            $previousReading = $previousReadings->get($connection->id);
            $assignmentResolution = $this->resolveAreaAssignment($connection->serviceArea, $activeAssignments, $serviceAreaMap);
            /** @var MeterReaderAssignment|null $assignment */
            $assignment = $assignmentResolution['assignment'];
            $assignedReader = $assignment?->user;
            $serviceAreaLabel = $connection->serviceArea instanceof ServiceArea
                ? $this->serviceAreaHierarchyLabel($connection->serviceArea, $serviceAreaMap)
                : null;
            $assignmentScope = $assignmentResolution['scope'];
            $assignmentAreaLabel = $assignmentResolution['area_label'];

            if ($effectiveAssignmentMode === 'global') {
                $serviceAreaLabel = 'Semua Sambungan';
                $assignmentAreaLabel = 'Semua Sambungan';

                if ($defaultMeterReader instanceof User) {
                    $assignedReader = $defaultMeterReader;
                    $assignmentScope = 'global';
                }
            } elseif ($connection->service_area_id === null) {
                $serviceAreaLabel = $usesServiceAreas ? 'Tanpa area' : 'Semua Sambungan';
                $assignmentAreaLabel = 'Global / tanpa area';

                if ($defaultMeterReader instanceof User) {
                    $assignedReader = $defaultMeterReader;
                    $assignmentScope = 'global';
                }
            } elseif ($serviceAreaLabel === null) {
                $serviceAreaLabel = 'Area terhapus';
            }

            return [
                'connection' => $connection,
                'assignment' => $assignment,
                'assigned_reader' => $assignedReader,
                'service_area_label' => $serviceAreaLabel,
                'assignment_scope' => $assignmentScope,
                'assignment_area_label' => $assignmentAreaLabel,
                'current_reading' => $currentReading,
                'previous_reading' => $previousReading,
                'baseline_reading' => $previousReading?->current_reading ?? 0,
                'usage_volume' => $currentReading?->usage_volume,
                'reading_status' => $currentReading?->reading_status ?? 'pending',
                'visit_status' => $currentReading?->visit_status ?? 'pending',
                'review_status' => $currentReading?->review_status ?? 'pending',
                'follow_up_action' => $currentReading?->follow_up_action,
                'customer_notification_status' => $currentReading?->customer_notification_status ?? 'not_applicable',
                'customer_notification_channels' => $currentReading?->customer_notification_channels ?? [],
                'requires_review' => $currentReading instanceof MeterReading
                    ? $this->meterReadingRequiresReview($currentReading)
                    : false,
            ];
        })
            ->filter(function (array $row) use ($filters, $serviceAreaDescendantMap): bool {
                $connection = $row['connection'];

                if ($filters['service_area_id'] === '__global__' && $row['assignment_scope'] !== 'global' && $connection->service_area_id !== null) {
                    return false;
                }

                if ($filters['service_area_id'] !== '' && $filters['service_area_id'] !== '__global__') {
                    if ($connection->service_area_id === null) {
                        return false;
                    }

                    $allowedAreaIds = $serviceAreaDescendantMap->get($filters['service_area_id'], collect());

                    if (! $allowedAreaIds->contains((string) $connection->service_area_id)) {
                        return false;
                    }
                }

                if ($filters['user_id'] !== '' && (string) ($row['assigned_reader']?->id ?? '') !== $filters['user_id']) {
                    return false;
                }

                return true;
            })
            ->values();

        $connectionRows = $assignedConnectionRows
            ->filter(function (array $row) use ($filters): bool {
                $statusMatches = match ($filters['status_bucket']) {
                    'pending' => ! $row['current_reading'] instanceof MeterReading,
                    'recorded' => $row['current_reading'] instanceof MeterReading,
                    'warning' => (bool) ($row['requires_review'] ?? false),
                    '', 'all' => true,
                    default => true,
                };

                if (! $statusMatches) {
                    return false;
                }

                return match ($filters['review_bucket']) {
                    'need_review' => (bool) ($row['requires_review'] ?? false),
                    'need_verification' => (string) ($row['review_status'] ?? '') === 'need_verification',
                    'revisit_required' => (string) ($row['review_status'] ?? '') === 'revisit_required',
                    'inspection_required' => (string) ($row['review_status'] ?? '') === 'inspection_required',
                    'customer_contact_required' => (string) ($row['review_status'] ?? '') === 'customer_contact_required',
                    'notification_pending' => in_array((string) ($row['customer_notification_status'] ?? ''), ['pending', 'failed'], true),
                    'verified' => in_array((string) ($row['review_status'] ?? ''), ['verified', 'corrected'], true),
                    '', 'all' => true,
                    default => true,
                };
            })
            ->sortBy(function (array $row): array {
                $currentReading = $row['current_reading'];
                $requiresReview = (bool) ($row['requires_review'] ?? false);
                $reviewStatus = (string) ($row['review_status'] ?? 'pending');
                $notificationPending = in_array((string) ($row['customer_notification_status'] ?? ''), ['pending', 'failed'], true);
                $reviewPriority = match ($reviewStatus) {
                    'inspection_required' => 0,
                    'revisit_required' => 1,
                    'customer_contact_required' => 2,
                    'need_verification' => 3,
                    'verified', 'corrected' => 5,
                    default => 4,
                };

                return [
                    ! $currentReading instanceof MeterReading ? 0 : ($requiresReview ? 1 : 2),
                    $reviewPriority,
                    $notificationPending ? 0 : 1,
                    (string) data_get($row, 'connection.service_number', ''),
                ];
            })
            ->values();

        $readingStats = [
            'periods' => $periods->count(),
            'connections' => $connections->count(),
            'filtered_connections' => $assignedConnectionRows->count(),
            'recorded' => $assignedConnectionRows->filter(fn (array $row): bool => $row['current_reading'] instanceof MeterReading)->count(),
            'warnings' => $assignedConnectionRows->filter(fn (array $row): bool => (bool) ($row['requires_review'] ?? false))->count(),
            'pending' => $assignedConnectionRows->filter(fn (array $row): bool => ! $row['current_reading'] instanceof MeterReading)->count(),
        ];
        $assignmentStats = [
            'areas' => $serviceAreas->count(),
            'assigned_areas' => $serviceAreas->filter(fn (ServiceArea $area): bool => $activeAssignments->has($area->id))->count(),
            'unassigned_areas' => $serviceAreas->filter(fn (ServiceArea $area): bool => ! $activeAssignments->has($area->id))->count(),
            'active_readers' => $meterReaders->count(),
            'connections_without_area' => $connections->whereNull('service_area_id')->count(),
            'global_assignment_active' => $defaultMeterReader instanceof User,
            'effective_mode' => $effectiveAssignmentMode,
        ];
        $workflowStats = [
            'billing_locked_periods' => $periods->filter(fn (MeterReadingPeriod $period): bool => in_array((string) ($period->billingPeriod?->status ?? ''), ['generated', 'closed'], true))->count(),
            'draft_billing_periods' => $periods->filter(fn (MeterReadingPeriod $period): bool => (string) ($period->billingPeriod?->status ?? '') === 'draft')->count(),
        ];
        $verifierStats = [
            'need_review' => $assignedConnectionRows->filter(fn (array $row): bool => (bool) ($row['requires_review'] ?? false))->count(),
            'need_verification' => $assignedConnectionRows->filter(fn (array $row): bool => (string) ($row['review_status'] ?? '') === 'need_verification')->count(),
            'revisit_required' => $assignedConnectionRows->filter(fn (array $row): bool => (string) ($row['review_status'] ?? '') === 'revisit_required')->count(),
            'inspection_required' => $assignedConnectionRows->filter(fn (array $row): bool => (string) ($row['review_status'] ?? '') === 'inspection_required')->count(),
            'customer_contact_required' => $assignedConnectionRows->filter(fn (array $row): bool => (string) ($row['review_status'] ?? '') === 'customer_contact_required')->count(),
            'notification_pending' => $assignedConnectionRows->filter(fn (array $row): bool => in_array((string) ($row['customer_notification_status'] ?? ''), ['pending', 'failed'], true))->count(),
            'verified' => $assignedConnectionRows->filter(fn (array $row): bool => in_array((string) ($row['review_status'] ?? ''), ['verified', 'corrected'], true))->count(),
        ];

        return [
            'tenantSetting' => $tenantSetting,
            'cycleSettings' => $cycleSettings,
            'cycleTimeline' => $this->cycleTimeline($selectedPeriod, $cycleSettings),
            'isMeterReader' => $isMeterReader,
            'defaultMeterReader' => $defaultMeterReader,
            'meterAssignmentMode' => $meterAssignmentMode,
            'effectiveAssignmentMode' => $effectiveAssignmentMode,
            'usesServiceAreas' => $usesServiceAreas,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'connections' => $connections,
            'assignedConnectionRows' => $assignedConnectionRows,
            'connectionRows' => $connectionRows,
            'readingStats' => $readingStats,
            'assignmentStats' => $assignmentStats,
            'serviceAreas' => $serviceAreas,
            'serviceAreaOptions' => $serviceAreaOptions,
            'meterReaders' => $meterReaders,
            'readerAssignments' => $readerAssignments,
            'filters' => $filters,
            'workflowStats' => $workflowStats,
            'verifierStats' => $verifierStats,
            'areaScopeLabel' => $this->tirtaAreaScopeLabel(),
            'visitStatusOptions' => $this->visitStatusOptions(),
            'visitStatusHelp' => $this->visitStatusHelp(),
            'followUpActionOptions' => $this->followUpActionOptions(),
            'reviewStatusOptions' => $this->reviewStatusOptions(),
            'verifierMode' => $verifierMode,
        ];
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanManageMeterReadingConfig();

        MeterReadingPeriod::query()->create($this->validatedPeriod($request));

        return redirect()
            ->route('tenant.tirta.meter-readings')
            ->with('status', 'Periode baca meter berhasil ditambahkan.');
    }

    public function updatePeriod(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanManageMeterReadingConfig();

        $period = MeterReadingPeriod::query()->findOrFail($id);
        $period->loadMissing('billingPeriod');
        $this->ensurePeriodConfigEditable($period);

        $validated = $this->validatedPeriod($request, $period);
        $closingNow = $period->status !== 'closed' && $validated['status'] === 'closed';

        $period->fill($validated)->save();

        $status = 'Periode baca meter berhasil diperbarui.';

        if ($closingNow) {
            $billingPeriod = $this->billingPeriodPlanner->ensureDraftPeriod($period, $this->tenantSetting());
            $status = sprintf(
                'Periode baca meter ditutup dan draft billing %s otomatis disiapkan.',
                $billingPeriod->name
            );
        }

        return $this->redirectToPeriod($period->id, $status);
    }

    public function updateCycleSettings(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanManageMeterReadingConfig();

        $validated = $request->validate([
            'meter_reading_window_start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'meter_reading_window_end_day' => ['required', 'integer', 'min:1', 'max:31'],
            'billing_publish_offset_days' => ['required', 'integer', 'min:0', 'max:60'],
            'billing_due_offset_days' => ['required', 'integer', 'min:1', 'max:90'],
            'billing_penalty_start_basis' => ['required', 'string', Rule::in(['due_date', 'issued_at'])],
            'billing_penalty_grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'meter_assignment_mode' => ['required', 'string', Rule::in(['global', 'per_area'])],
            'default_meter_reader_user_id' => ['nullable', 'string', Rule::exists('users', 'id')],
        ]);

        if ((int) $validated['meter_reading_window_end_day'] < (int) $validated['meter_reading_window_start_day']) {
            throw ValidationException::withMessages([
                'meter_reading_window_end_day' => 'Akhir jendela baca meter harus sama atau setelah hari mulai.',
            ]);
        }

        if (filled($validated['default_meter_reader_user_id'])) {
            $defaultReader = $this->meterReaders()->firstWhere('id', $validated['default_meter_reader_user_id']);

            if (! $defaultReader instanceof User) {
                throw ValidationException::withMessages([
                    'default_meter_reader_user_id' => 'Petugas global tanpa rayon harus user aktif yang memang boleh masuk workflow catat meter.',
                ]);
            }
        }

        $validated['default_meter_reader_user_id'] = filled($validated['default_meter_reader_user_id'])
            ? (string) $validated['default_meter_reader_user_id']
            : null;

        $this->tenantSetting()->forceFill($validated)->save();

        return $this->redirectToWorkspace(
            $request,
            'Siklus operasional Tirta berhasil diperbarui.'
        );
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanManageMeterReadingConfig();

        $payload = $this->validatedAssignmentPayload($request);
        $assignment = MeterReaderAssignment::query()->updateOrCreate(
            [
                'service_area_id' => $payload['service_area_id'],
            ],
            [
                'user_id' => $payload['user_id'],
                'is_active' => $payload['is_active'],
                'notes' => $payload['notes'],
            ]
        );

        $assignment->loadMissing(['serviceArea', 'user']);

        return $this->redirectToWorkspace(
            $request,
            sprintf(
                'Petugas baca meter untuk rayon %s berhasil disimpan.',
                $assignment->serviceArea?->name ?? 'terpilih'
            )
        );
    }

    public function updateAssignment(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanManageMeterReadingConfig();

        $assignment = MeterReaderAssignment::query()->findOrFail($id);
        $this->abortIfOutsideTirtaArea($assignment->service_area_id, 'Assignment ini berada di luar cakupan area kerja Anda.');
        $payload = $this->validatedAssignmentPayload($request, $assignment);

        $duplicate = MeterReaderAssignment::query()
            ->where('service_area_id', $payload['service_area_id'])
            ->whereKeyNot($assignment->getKey())
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'service_area_id' => 'Rayon ini sudah punya assignment petugas lain. Edit assignment yang sudah ada atau nonaktifkan dulu.',
            ]);
        }

        $assignment->fill($payload)->save();
        $assignment->loadMissing(['serviceArea', 'user']);

        return $this->redirectToWorkspace(
            $request,
            sprintf(
                'Assignment petugas untuk rayon %s berhasil diperbarui.',
                $assignment->serviceArea?->name ?? 'terpilih'
            )
        );
    }

    public function storeReading(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();

        $request->validate([
            'meter_reading_period_id' => ['required', 'string', Rule::exists('meter_reading_periods', 'id')],
            'service_connection_id' => ['required', 'string', Rule::exists('service_connections', 'id')],
        ]);

        $period = MeterReadingPeriod::query()->findOrFail((string) $request->input('meter_reading_period_id'));
        $this->ensurePeriodWritable($period);

        $connection = ServiceConnection::query()->findOrFail((string) $request->input('service_connection_id'));
        $connection->loadMissing('customer');
        $this->ensureMeterReaderAssignedToConnection($connection);
        $payload = $this->validatedReadingPayload($request, $period, $connection);

        $reading = MeterReading::query()->updateOrCreate(
            [
                'meter_reading_period_id' => $period->id,
                'service_connection_id' => $connection->id,
            ],
            $payload
        );
        $this->dispatchCustomerFailureNotification($reading, $connection);

        return $this->redirectToPeriod($period->id, sprintf(
            'Pembacaan meter untuk sambungan %s berhasil disimpan.',
            $connection->service_number
        ));
    }

    public function updateReading(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();

        $reading = MeterReading::query()->with(['period', 'connection'])->findOrFail($id);
        $period = $reading->period;
        $connection = $reading->connection;

        if (! $period instanceof MeterReadingPeriod || ! $connection instanceof ServiceConnection) {
            abort(404);
        }

        $connection->loadMissing('customer');
        $this->ensureMeterReaderAssignedToConnection($connection);
        $this->ensurePeriodWritable($period);

        $reading->fill($this->validatedReadingPayload($request, $period, $connection, $reading))->save();
        $this->dispatchCustomerFailureNotification($reading, $connection);

        return $this->redirectToPeriod($period->id, sprintf(
            'Pembacaan meter untuk sambungan %s berhasil diperbarui.',
            $connection->service_number
        ));
    }

    public function reviewReading(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanManageMeterReadingConfig();

        $reading = MeterReading::query()->with(['period', 'connection.customer'])->findOrFail($id);
        $period = $reading->period;
        $connection = $reading->connection;

        if (! $period instanceof MeterReadingPeriod || ! $connection instanceof ServiceConnection) {
            abort(404);
        }

        $this->ensurePeriodWritable($period);
        $this->abortIfOutsideTirtaArea(
            $this->tirtaConnectionAreaId($connection),
            'Pembacaan ini berada di luar cakupan area kerja Anda.'
        );

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['approve', 'revisit', 'inspection', 'contact_customer', 'send_notification'])],
        ]);

        $action = (string) $validated['action'];

        switch ($action) {
            case 'approve':
                if ($reading->visit_status !== 'read') {
                    throw ValidationException::withMessages([
                        'action' => 'Approve hanya bisa dipakai setelah bacaan final berhasil dibaca.',
                    ]);
                }

                if ($reading->reading_status === 'invalid') {
                    throw ValidationException::withMessages([
                        'action' => 'Bacaan invalid harus dikoreksi dulu sebelum di-approve.',
                    ]);
                }

                $reading->forceFill([
                    'review_status' => 'verified',
                    'follow_up_action' => null,
                ])->save();

                return $this->redirectToPeriod($period->id, sprintf(
                    'Pembacaan sambungan %s sudah di-approve verifikator.',
                    $connection->service_number
                ));

            case 'revisit':
                $reading->forceFill([
                    'review_status' => 'revisit_required',
                    'follow_up_action' => 'revisit_required',
                ])->save();

                return $this->redirectToPeriod($period->id, sprintf(
                    'Sambungan %s masuk daftar kunjungan ulang.',
                    $connection->service_number
                ));

            case 'inspection':
                $reading->forceFill([
                    'review_status' => 'inspection_required',
                    'follow_up_action' => 'inspection_required',
                ])->save();

                return $this->redirectToPeriod($period->id, sprintf(
                    'Sambungan %s ditandai untuk inspeksi teknis.',
                    $connection->service_number
                ));

            case 'contact_customer':
                $reading->forceFill([
                    'review_status' => 'customer_contact_required',
                    'follow_up_action' => 'customer_contact_required',
                ])->save();
                $this->dispatchCustomerFailureNotification($reading, $connection, true);

                return $this->redirectToPeriod($period->id, sprintf(
                    'Tindak lanjut pelanggan untuk sambungan %s sudah dicatat.',
                    $connection->service_number
                ));

            case 'send_notification':
                $this->dispatchCustomerFailureNotification($reading, $connection, true);

                return $this->redirectToPeriod($period->id, sprintf(
                    'Email notifikasi untuk sambungan %s sudah diproses ulang.',
                    $connection->service_number
                ));
        }

        throw ValidationException::withMessages([
            'action' => 'Aksi review tidak dikenali.',
        ]);
    }

    protected function selectedPeriod(Request $request, Collection $periods): ?MeterReadingPeriod
    {
        if ($periods->isEmpty()) {
            return null;
        }

        $selectedId = (string) $request->query('period');
        $selected = $selectedId !== '' ? $periods->firstWhere('id', $selectedId) : null;

        if ($selected instanceof MeterReadingPeriod) {
            return $selected;
        }

        return $periods->firstWhere('status', 'open')
            ?? $periods->firstWhere('status', 'draft')
            ?? $periods->first();
    }

    protected function previousReadingsMap(?MeterReadingPeriod $selectedPeriod, array $connectionIds): Collection
    {
        if (! $selectedPeriod instanceof MeterReadingPeriod || $connectionIds === []) {
            return collect();
        }

        return MeterReading::query()
            ->with('period')
            ->whereIn('service_connection_id', $connectionIds)
            ->whereHas('period', function ($query) use ($selectedPeriod): void {
                $query->where('period_start', '<', $selectedPeriod->period_start);
            })
            ->orderByDesc('recorded_at')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('service_connection_id')
            ->map(fn (Collection $items): ?MeterReading => $items->first());
    }

    protected function validatedPeriod(Request $request, ?MeterReadingPeriod $period = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'status' => ['required', 'string', Rule::in(['draft', 'open', 'closed'])],
            'notes' => ['nullable', 'string'],
        ]);

        $duplicate = MeterReadingPeriod::query()
            ->whereDate('period_start', $validated['period_start'])
            ->whereDate('period_end', $validated['period_end'])
            ->when($period instanceof MeterReadingPeriod, fn ($query) => $query->whereKeyNot($period->getKey()))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'period_start' => 'Periode baca meter dengan rentang tanggal yang sama sudah ada.',
            ]);
        }

        return $validated;
    }

    protected function validatedReadingPayload(
        Request $request,
        MeterReadingPeriod $period,
        ServiceConnection $connection,
        ?MeterReading $existing = null
    ): array {
        if ($connection->status !== 'active') {
            throw ValidationException::withMessages([
                'service_connection' => sprintf('Sambungan %s sedang %s dan tidak bisa diinput baca meter.', $connection->service_number, strtoupper((string) $connection->status)),
            ]);
        }

        $validated = $request->validate([
            'visit_status' => ['required', 'string', Rule::in(array_keys($this->visitStatusOptions()))],
            'current_reading' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'reader_name' => ['nullable', 'string', 'max:100'],
            'recorded_at' => ['nullable', 'date'],
            'evidence_photo' => ['nullable', 'image', 'max:5120'],
            'recorded_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'recorded_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'recorded_accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string'],
        ]);

        $previousReading = $this->latestReadingBeforePeriod($period, $connection, $existing);
        $previousValue = (int) ($previousReading?->current_reading ?? 0);
        $visitStatus = (string) $validated['visit_status'];
        $isFailedVisit = $visitStatus !== 'read';
        $notes = trim((string) ($validated['notes'] ?? ''));

        if (! $isFailedVisit && ! array_key_exists('current_reading', $validated)) {
            throw ValidationException::withMessages([
                'current_reading' => 'Angka meter sekarang wajib diisi kalau pembacaan berhasil.',
            ]);
        }

        if (! $isFailedVisit && ($validated['current_reading'] ?? null) === null) {
            throw ValidationException::withMessages([
                'current_reading' => 'Angka meter sekarang wajib diisi kalau pembacaan berhasil.',
            ]);
        }

        if ($isFailedVisit && $notes === '') {
            throw ValidationException::withMessages([
                'notes' => 'Catatan lapangan wajib diisi untuk status gagal baca atau kendala akses.',
            ]);
        }

        $currentValue = $isFailedVisit
            ? $previousValue
            : (int) $validated['current_reading'];
        $usageVolume = max($currentValue - $previousValue, 0);
        [$readingStatus, $anomalyNotes] = $this->readingStatusPayload($visitStatus, $currentValue, $previousValue, $previousReading);
        $readerName = trim((string) ($validated['reader_name'] ?? ''));

        if ($readerName === '') {
            $readerName = $this->currentReaderName();
        }

        $evidencePhotoPath = $existing?->evidence_photo_path;

        /** @var User|null $currentUser */
        $currentUser = Auth::guard('tenant')->user();
        $isMeterReader = $currentUser instanceof User && $currentUser->isMeterReader();
        $hasExistingEvidence = is_string($evidencePhotoPath) && $evidencePhotoPath !== '';

        if ($isMeterReader && ! $hasExistingEvidence && ! $request->hasFile('evidence_photo')) {
            throw ValidationException::withMessages([
                'evidence_photo' => 'Foto evidence meter wajib untuk petugas catat meter.',
            ]);
        }

        if ($request->hasFile('evidence_photo')) {
            $evidencePhotoPath = $this->storeCompressedEvidencePhoto($request->file('evidence_photo'));

            if (is_string($existing?->evidence_photo_path) && $existing->evidence_photo_path !== '') {
                Storage::disk('public')->delete($existing->evidence_photo_path);
            }
        }

        $reviewFlags = $this->buildReviewFlags(
            $period,
            $visitStatus,
            $readingStatus,
            $currentValue,
            $previousValue,
            $previousReading,
            $validated
        );
        $reviewStatus = $reviewFlags !== [] ? 'need_verification' : 'auto_pass';
        $followUpAction = $this->resolveFollowUpAction($visitStatus, $reviewFlags);
        $notificationChannels = $this->notificationChannelsForConnection($connection, $visitStatus);
        $notificationStatus = $this->notificationStatusForVisit($visitStatus, $notificationChannels);
        $notificationMessage = $notificationStatus === 'pending'
            ? $this->buildFailedReadingNotificationMessage($connection, $validated, $visitStatus, $followUpAction)
            : null;

        return [
            'previous_reading' => $previousValue,
            'current_reading' => $currentValue,
            'usage_volume' => $usageVolume,
            'reading_status' => $readingStatus,
            'visit_status' => $visitStatus,
            'follow_up_action' => $followUpAction,
            'review_status' => $reviewStatus,
            'review_flags' => $reviewFlags,
            'reader_name' => $readerName !== '' ? $readerName : null,
            'recorded_at' => $validated['recorded_at'] ?? now(),
            'evidence_photo_path' => $evidencePhotoPath,
            'recorded_latitude' => filled($validated['recorded_latitude'] ?? null) ? (float) $validated['recorded_latitude'] : null,
            'recorded_longitude' => filled($validated['recorded_longitude'] ?? null) ? (float) $validated['recorded_longitude'] : null,
            'recorded_accuracy_meters' => filled($validated['recorded_accuracy_meters'] ?? null) ? (float) $validated['recorded_accuracy_meters'] : null,
            'customer_notification_status' => $notificationStatus,
            'customer_notification_channels' => $notificationChannels,
            'customer_notification_message' => $notificationMessage,
            'customer_notified_at' => null,
            'anomaly_notes' => $anomalyNotes,
            'notes' => $notes !== '' ? $notes : null,
        ];
    }

    protected function storeCompressedEvidencePhoto(UploadedFile $file): string
    {
        $path = $file->store('tirta/meter-readings', 'public');

        if (! function_exists('imagecreatefromstring')) {
            return $path;
        }

        $contents = @file_get_contents((string) $file->getRealPath());

        if (! is_string($contents) || $contents === '') {
            return $path;
        }

        $image = @imagecreatefromstring($contents);

        if (! is_resource($image) && ! ($image instanceof \GdImage)) {
            return $path;
        }

        $width = (int) imagesx($image);
        $height = (int) imagesy($image);

        if ($width <= 0 || $height <= 0) {
            return $path;
        }

        $square = min($width, $height);
        $sourceX = (int) floor(($width - $square) / 2);
        $sourceY = (int) floor(($height - $square) / 2);

        $size = (int) min(768, $square);
        $canvas = imagecreatetruecolor($size, $size);

        if (! is_resource($canvas) && ! ($canvas instanceof \GdImage)) {
            return $path;
        }

        imagecopyresampled($canvas, $image, 0, 0, $sourceX, $sourceY, $size, $size, $square, $square);

        ob_start();
        imagejpeg($canvas, null, 75);
        $jpegBytes = (string) ob_get_clean();

        imagedestroy($image);
        imagedestroy($canvas);

        if ($jpegBytes === '') {
            return $path;
        }

        $newPath = sprintf('tirta/meter-readings/%s/%s.jpg', now()->format('Ymd'), (string) Str::uuid());
        Storage::disk('public')->put($newPath, $jpegBytes);
        Storage::disk('public')->delete($path);

        return $newPath;
    }

    protected function validatedAssignmentPayload(Request $request, ?MeterReaderAssignment $assignment = null): array
    {
        $validated = $request->validate([
            'service_area_id' => ['required', 'string', Rule::exists('service_areas', 'id')],
            'user_id' => ['required', 'string', Rule::exists('users', 'id')],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = $this->meterReaders()->firstWhere('id', $validated['user_id']);

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'user_id' => 'Petugas baca meter harus user aktif yang memang boleh masuk workflow catat meter.',
            ]);
        }

        if ($assignment instanceof MeterReaderAssignment && $assignment->service_area_id === $validated['service_area_id']) {
            return [
                'service_area_id' => $validated['service_area_id'],
                'user_id' => $validated['user_id'],
                'is_active' => $request->boolean('is_active', true),
                'notes' => $validated['notes'] ?? null,
            ];
        }

        $area = ServiceArea::query()->findOrFail((string) $validated['service_area_id']);

        if (! (bool) $area->is_active) {
            throw ValidationException::withMessages([
                'service_area_id' => 'Area nonaktif tidak bisa dipakai untuk assignment petugas baca meter.',
            ]);
        }

        $this->ensureTirtaAreaAccessible(
            (string) $area->getKey(),
            'service_area_id',
            'Assignment area harus berada di dalam cakupan area kerja Anda.'
        );

        return [
            'service_area_id' => $validated['service_area_id'],
            'user_id' => $validated['user_id'],
            'is_active' => $request->boolean('is_active', true),
            'notes' => $validated['notes'] ?? null,
        ];
    }

    protected function resolveAreaAssignment(?ServiceArea $serviceArea, Collection $activeAssignments, Collection $serviceAreaMap): array
    {
        if (! $serviceArea instanceof ServiceArea) {
            return [
                'assignment' => null,
                'scope' => 'unassigned',
                'area_label' => null,
            ];
        }

        $current = $serviceArea;
        $depth = 0;
        $visited = [];

        while ($current instanceof ServiceArea) {
            $currentKey = (string) $current->getKey();

            if (in_array($currentKey, $visited, true)) {
                break;
            }

            $visited[] = $currentKey;

            /** @var MeterReaderAssignment|null $assignment */
            $assignment = $activeAssignments->get($currentKey);

            if ($assignment instanceof MeterReaderAssignment) {
                return [
                    'assignment' => $assignment,
                    'scope' => $depth === 0 ? 'area' : 'ancestor',
                    'area_label' => $this->serviceAreaHierarchyLabel($current, $serviceAreaMap),
                ];
            }

            $parentId = $current->parent_id;
            $current = $parentId !== null && $serviceAreaMap->has((string) $parentId)
                ? $serviceAreaMap->get((string) $parentId)
                : null;
            $depth++;
        }

        return [
            'assignment' => null,
            'scope' => 'unassigned',
            'area_label' => $this->serviceAreaHierarchyLabel($serviceArea, $serviceAreaMap),
        ];
    }

    protected function serviceAreaOptions(Collection $serviceAreas, Collection $serviceAreaMap): Collection
    {
        return $serviceAreas->mapWithKeys(function (ServiceArea $serviceArea) use ($serviceAreaMap): array {
            return [
                (string) $serviceArea->getKey() => $this->serviceAreaHierarchyLabel($serviceArea, $serviceAreaMap),
            ];
        });
    }

    protected function serviceAreaHierarchyLabel(ServiceArea $serviceArea, Collection $serviceAreaMap): string
    {
        $segments = [$serviceArea->name];
        $parentId = $serviceArea->parent_id;
        $visited = [(string) $serviceArea->getKey()];

        while ($parentId !== null && $serviceAreaMap->has((string) $parentId)) {
            /** @var ServiceArea $parent */
            $parent = $serviceAreaMap->get((string) $parentId);
            $parentKey = (string) $parent->getKey();

            if (in_array($parentKey, $visited, true)) {
                break;
            }

            array_unshift($segments, $parent->name);
            $visited[] = $parentKey;
            $parentId = $parent->parent_id;
        }

        return implode(' / ', $segments);
    }

    protected function serviceAreaDescendantMap(Collection $serviceAreas): Collection
    {
        $childrenByParent = $serviceAreas->groupBy(fn (ServiceArea $serviceArea): string => (string) ($serviceArea->parent_id ?? '__root__'));

        return $serviceAreas->mapWithKeys(function (ServiceArea $serviceArea) use ($childrenByParent): array {
            return [
                (string) $serviceArea->getKey() => $this->serviceAreaDescendantIds((string) $serviceArea->getKey(), $childrenByParent),
            ];
        });
    }

    protected function serviceAreaDescendantIds(string $areaId, Collection $childrenByParent): Collection
    {
        $ids = collect([$areaId]);
        /** @var Collection<int, ServiceArea> $children */
        $children = $childrenByParent->get($areaId, collect());

        foreach ($children as $child) {
            $ids = $ids->merge($this->serviceAreaDescendantIds((string) $child->getKey(), $childrenByParent));
        }

        return $ids->unique()->values();
    }

    protected function latestReadingBeforePeriod(
        MeterReadingPeriod $period,
        ServiceConnection $connection,
        ?MeterReading $existing = null
    ): ?MeterReading {
        return MeterReading::query()
            ->with('period')
            ->where('service_connection_id', $connection->id)
            ->when($existing instanceof MeterReading, fn ($query) => $query->whereKeyNot($existing->getKey()))
            ->whereHas('period', function ($query) use ($period): void {
                $query->where('period_start', '<', $period->period_start);
            })
            ->orderByDesc('recorded_at')
            ->orderByDesc('created_at')
            ->first();
    }

    protected function readingStatusPayload(
        string $visitStatus,
        int $currentValue,
        int $previousValue,
        ?MeterReading $previousReading
    ): array
    {
        if ($visitStatus !== 'read') {
            $label = $this->visitStatusOptions()[$visitStatus] ?? 'Kendala lapangan';

            return [
                'invalid',
                sprintf('%s. Pembacaan belum final dan tidak ikut billing sampai ada tindak lanjut.', $label),
            ];
        }

        if ($currentValue < $previousValue) {
            return [
                'invalid',
                'Angka meter sekarang lebih kecil dari pembacaan periode sebelumnya. Cek kemungkinan salah input atau rollover meter.',
            ];
        }

        $usageVolume = $currentValue - $previousValue;
        $previousUsage = (int) ($previousReading?->usage_volume ?? 0);

        if ($previousReading instanceof MeterReading && $usageVolume === 0) {
            return [
                'warning',
                'Stand meter tidak berubah dari periode sebelumnya. Cek kemungkinan rumah kosong, meter macet, atau pembacaan perlu diverifikasi.',
            ];
        }

        if ($previousUsage > 0 && $usageVolume >= ($previousUsage * 2)) {
            return [
                'warning',
                sprintf('Pemakaian naik tajam menjadi %d m3, lebih dari dua kali periode sebelumnya (%d m3).', $usageVolume, $previousUsage),
            ];
        }

        if ($usageVolume >= 100) {
            return [
                'warning',
                sprintf('Pemakaian %d m3 melewati ambang review operasional 100 m3.', $usageVolume),
            ];
        }

        return ['normal', null];
    }

    protected function meterReadingRequiresReview(MeterReading $reading): bool
    {
        if (in_array((string) $reading->review_status, ['verified', 'corrected'], true)) {
            return false;
        }

        if (in_array((string) $reading->review_status, ['revisit_required', 'inspection_required', 'customer_contact_required'], true)) {
            return true;
        }

        if ($reading->visit_status !== 'read') {
            return true;
        }

        if (in_array((string) $reading->reading_status, ['warning', 'invalid'], true)) {
            return true;
        }

        return (string) $reading->review_status === 'need_verification';
    }

    protected function visitStatusOptions(): array
    {
        return [
            'read' => 'Berhasil Dibaca',
            'house_empty' => 'Rumah Kosong',
            'gate_locked' => 'Pagar Dikunci',
            'access_denied' => 'Akses Ditolak',
            'meter_inaccessible' => 'Meter Tidak Bisa Dijangkau',
            'meter_damaged' => 'Meter Rusak / Buram',
            'unsafe_location' => 'Lokasi Tidak Aman',
            'other_issue' => 'Kendala Lainnya',
        ];
    }

    protected function visitStatusHelp(): array
    {
        return [
            'read' => 'Pakai saat angka meter berhasil dibaca normal.',
            'house_empty' => 'Rumah tidak bisa dikonfirmasi penghuninya dan bacaan belum final.',
            'gate_locked' => 'Petugas datang tetapi akses ke meter tertutup pagar atau pintu.',
            'access_denied' => 'Pelanggan atau penjaga menolak akses ke meter.',
            'meter_inaccessible' => 'Meter ada, tapi tertutup barang atau posisinya tidak bisa dibaca.',
            'meter_damaged' => 'Meter rusak, buram, atau display tidak dapat dipastikan.',
            'unsafe_location' => 'Ada hewan, banjir, atau risiko keselamatan lain di lapangan.',
            'other_issue' => 'Gunakan untuk kasus khusus dengan catatan lapangan yang jelas.',
        ];
    }

    protected function followUpActionOptions(): array
    {
        return [
            'none' => 'Tidak ada tindak lanjut',
            'verification_required' => 'Verifikasi petugas verifikator',
            'revisit_required' => 'Kunjungan ulang',
            'inspection_required' => 'Inspeksi teknis meter',
            'customer_contact_required' => 'Hubungi pelanggan',
            'supervisor_review' => 'Review supervisor',
        ];
    }

    protected function reviewStatusOptions(): array
    {
        return [
            'pending' => 'Belum diproses',
            'auto_pass' => 'Lolos otomatis',
            'need_verification' => 'Masuk antrean verifikator',
            'verified' => 'Disetujui verifikator',
            'revisit_required' => 'Perlu kunjungan ulang',
            'inspection_required' => 'Perlu inspeksi teknis',
            'customer_contact_required' => 'Perlu hubungi pelanggan',
            'corrected' => 'Sudah dikoreksi',
        ];
    }

    protected function buildReviewFlags(
        MeterReadingPeriod $period,
        string $visitStatus,
        string $readingStatus,
        int $currentValue,
        int $previousValue,
        ?MeterReading $previousReading,
        array $validated
    ): array {
        $flags = [];

        if ($visitStatus !== 'read') {
            $flags[] = sprintf('Kunjungan lapangan ditutup sebagai `%s`.', $visitStatus);
        }

        if (in_array($readingStatus, ['warning', 'invalid'], true)) {
            $flags[] = sprintf('Status pembacaan `%s` perlu dicek verifikator.', $readingStatus);
        }

        $recordedAtRaw = $validated['recorded_at'] ?? null;
        $recordedAt = $recordedAtRaw ? Carbon::parse((string) $recordedAtRaw) : now();

        if ($recordedAt->lt($period->period_start?->copy()->startOfDay()) || $recordedAt->gt($period->period_end?->copy()->endOfDay())) {
            $flags[] = 'Waktu catat berada di luar jendela periode baca meter.';
        }

        if ($visitStatus === 'read' && $previousReading instanceof MeterReading && $currentValue === $previousValue) {
            $flags[] = 'Stand meter stagnan dibanding periode sebelumnya.';
        }

        $accuracy = filled($validated['recorded_accuracy_meters'] ?? null)
            ? (float) $validated['recorded_accuracy_meters']
            : null;

        if ($accuracy !== null && $accuracy > 100) {
            $flags[] = sprintf('Akurasi GPS lemah (%.2f meter).', $accuracy);
        }

        return array_values(array_unique($flags));
    }

    protected function resolveFollowUpAction(string $visitStatus, array $reviewFlags): ?string
    {
        if ($visitStatus === 'read') {
            return $reviewFlags !== [] ? 'verification_required' : null;
        }

        return match ($visitStatus) {
            'house_empty', 'gate_locked', 'access_denied', 'meter_inaccessible' => 'customer_contact_required',
            'meter_damaged' => 'inspection_required',
            'unsafe_location' => 'supervisor_review',
            default => 'verification_required',
        };
    }

    protected function notificationChannelsForConnection(ServiceConnection $connection, string $visitStatus): array
    {
        if ($visitStatus === 'read') {
            return [];
        }

        $customer = $connection->customer;
        $channels = [];

        if (filled($customer?->email)) {
            $channels[] = 'email';
        }

        return $channels;
    }

    protected function notificationStatusForVisit(string $visitStatus, array $channels): string
    {
        if ($visitStatus === 'read') {
            return 'not_applicable';
        }

        return $channels !== [] ? 'pending' : 'unavailable';
    }

    protected function buildFailedReadingNotificationMessage(
        ServiceConnection $connection,
        array $validated,
        string $visitStatus,
        ?string $followUpAction
    ): string {
        $label = $this->visitStatusOptions()[$visitStatus] ?? 'kendala lapangan';
        $followUpLabel = $followUpAction !== null
            ? ($this->followUpActionOptions()[$followUpAction] ?? $followUpAction)
            : 'menunggu review operasional';
        $recordedAt = Carbon::parse((string) ($validated['recorded_at'] ?? now()))->format('d M Y H:i');

        return sprintf(
            'Yth. %s, pembacaan water meter untuk sambungan %s pada %s belum berhasil karena %s. Tindak lanjut saat ini: %s. Mohon siapkan akses meter atau hubungi admin jika diperlukan.',
            $connection->customer?->name ?? 'Pelanggan',
            $connection->service_number,
            $recordedAt,
            strtolower($label),
            strtolower($followUpLabel)
        );
    }

    protected function dispatchCustomerFailureNotification(
        MeterReading $reading,
        ?ServiceConnection $connection = null,
        bool $force = false
    ): void {
        if ($reading->visit_status === 'read') {
            return;
        }

        $connection = $connection instanceof ServiceConnection
            ? $connection
            : $reading->connection;

        if (! $connection instanceof ServiceConnection) {
            return;
        }

        $connection->loadMissing('customer');
        $channels = collect($reading->customer_notification_channels ?? [])
            ->filter(fn ($channel): bool => is_string($channel) && $channel !== '')
            ->values()
            ->all();

        if (! in_array('email', $channels, true)) {
            return;
        }

        if (! $force && $reading->customer_notification_status !== 'pending') {
            return;
        }

        $recipientEmail = trim((string) ($connection->customer?->email ?? ''));

        if ($recipientEmail === '') {
            $reading->forceFill([
                'customer_notification_status' => 'unavailable',
                'customer_notified_at' => null,
            ])->save();

            return;
        }

        try {
            Mail::raw((string) $reading->customer_notification_message, function ($message) use ($recipientEmail, $connection): void {
                $message->to($recipientEmail)
                    ->subject(sprintf('Info Pembacaan Water Meter %s', $connection->service_number));
            });

            $reading->forceFill([
                'customer_notification_status' => 'sent',
                'customer_notified_at' => now(),
            ])->save();
        } catch (Throwable) {
            $reading->forceFill([
                'customer_notification_status' => 'failed',
                'customer_notified_at' => null,
            ])->save();
        }
    }

    protected function ensurePeriodWritable(MeterReadingPeriod $period): void
    {
        $period->loadMissing('billingPeriod');

        if ($period->status === 'closed') {
            throw ValidationException::withMessages([
                'period' => 'Periode baca meter sudah ditutup. Buka atau ubah status periode dulu sebelum edit pembacaan.',
            ]);
        }

        if (in_array((string) ($period->billingPeriod?->status ?? ''), ['generated', 'closed'], true)) {
            throw ValidationException::withMessages([
                'period' => 'Periode baca meter sudah masuk workflow billing dan terkunci untuk menjaga konsistensi invoice.',
            ]);
        }
    }

    protected function ensureTirtaTenant(): void
    {
        if ((string) (tenant('saas_type') ?? '') !== 'tirta') {
            abort(404);
        }
    }

    protected function ensureSchemaReady(): void
    {
        $requiredTables = [
            'tenant_settings',
            'roles',
            'users',
            'service_areas',
            'service_connections',
            'meter_reading_periods',
            'meter_readings',
            'meter_reader_assignments',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                throw ValidationException::withMessages([
                    'schema' => 'Schema TirtaCatatMeter belum siap. Jalankan migrasi tenant terbaru dulu.',
                ]);
            }
        }
    }

    protected function redirectToPeriod(string $periodId, string $status): RedirectResponse
    {
        return redirect()
            ->route('tenant.tirta.meter-readings', ['period' => $periodId])
            ->with('status', $status);
    }

    protected function redirectToWorkspace(Request $request, string $status): RedirectResponse
    {
        $query = array_filter([
            'period' => (string) $request->input('period', $request->query('period', '')),
            'service_area_id' => (string) $request->input('service_area_id_filter', $request->query('service_area_id', '')),
            'user_id' => (string) $request->input('user_id_filter', $request->query('user_id', '')),
        ], static fn (?string $value): bool => is_string($value) && $value !== '');

        return redirect()
            ->route('tenant.tirta.meter-readings', $query)
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

    protected function cycleSettings(TenantSetting $setting): array
    {
        $penaltyBasis = (string) ($setting->getAttribute('billing_penalty_start_basis') ?? 'due_date');

        return [
            'meter_reading_window_start_day' => max((int) ($setting->getAttribute('meter_reading_window_start_day') ?? 25), 1),
            'meter_reading_window_end_day' => max((int) ($setting->getAttribute('meter_reading_window_end_day') ?? 30), 1),
            'billing_publish_offset_days' => max((int) ($setting->getAttribute('billing_publish_offset_days') ?? 1), 0),
            'billing_due_offset_days' => max((int) ($setting->getAttribute('billing_due_offset_days') ?? 10), 1),
            'billing_penalty_start_basis' => in_array($penaltyBasis, ['due_date', 'issued_at'], true)
                ? $penaltyBasis
                : 'due_date',
            'billing_penalty_grace_days' => max((int) ($setting->getAttribute('billing_penalty_grace_days') ?? 0), 0),
        ];
    }

    protected function cycleTimeline(?MeterReadingPeriod $period, array $cycleSettings): array
    {
        if ($period instanceof MeterReadingPeriod && $period->period_start !== null && $period->period_end !== null) {
            $windowStart = $period->period_start->copy()->startOfDay();
            $windowEnd = $period->period_end->copy()->startOfDay();
            $source = 'Periode aktif';
            $reference = $period->name;
        } else {
            [$windowStart, $windowEnd] = $this->nextMeterWindow($cycleSettings);
            $source = 'Preview default';
            $reference = sprintf('Jendela %s', $windowStart->format('M Y'));
        }

        $publishDate = $windowEnd->copy()->addDays($cycleSettings['billing_publish_offset_days']);
        $dueDate = $publishDate->copy()->addDays($cycleSettings['billing_due_offset_days']);
        $penaltyBasis = $cycleSettings['billing_penalty_start_basis'];
        $penaltyAnchor = $penaltyBasis === 'issued_at' ? $publishDate : $dueDate;
        $penaltyStartDate = $penaltyAnchor->copy()->addDays($cycleSettings['billing_penalty_grace_days'] + 1);

        return [
            'source' => $source,
            'reference' => $reference,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'publish_date' => $publishDate,
            'due_date' => $dueDate,
            'penalty_basis' => $penaltyBasis,
            'penalty_grace_days' => $cycleSettings['billing_penalty_grace_days'],
            'penalty_start_date' => $penaltyStartDate,
        ];
    }

    protected function nextMeterWindow(array $cycleSettings): array
    {
        $monthCursor = now()->copy()->startOfMonth();
        $windowStart = $this->monthDay($monthCursor, $cycleSettings['meter_reading_window_start_day']);
        $windowEnd = $this->monthDay($monthCursor, $cycleSettings['meter_reading_window_end_day']);

        if (now()->greaterThan($windowEnd->copy()->endOfDay())) {
            $monthCursor = $monthCursor->addMonth()->startOfMonth();
            $windowStart = $this->monthDay($monthCursor, $cycleSettings['meter_reading_window_start_day']);
            $windowEnd = $this->monthDay($monthCursor, $cycleSettings['meter_reading_window_end_day']);
        }

        return [$windowStart, $windowEnd];
    }

    protected function monthDay(Carbon $monthCursor, int $day): Carbon
    {
        $date = $monthCursor->copy();
        $date->day(min($day, $date->daysInMonth));

        return $date->startOfDay();
    }

    protected function meterReaders(): Collection
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->when(
                $this->tirtaAreaIsRestricted(),
                fn ($query) => $query->where(function ($builder): void {
                    $builder->whereNull('service_area_id')
                        ->orWhereIn('service_area_id', $this->tirtaAllowedAreaIds()->all());
                })
            )
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->canBeAssignedTirtaMeterReader())
            ->values();
    }

    protected function ensureCanAccessMeterReading(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canAccessTirtaMeterReadingWorkspace()) {
            abort(403, 'Akun ini tidak punya akses ke Catat Meter Tirta.');
        }
    }

    protected function ensureCanManageMeterReadingConfig(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canManageTirtaMeterReadingConfig()) {
            abort(403, 'Akun ini tidak punya akses untuk mengubah pengaturan atau assignment baca meter.');
        }
    }

    protected function ensureMeterReaderAssignedToConnection(ServiceConnection $connection): void
    {
        $this->abortIfOutsideTirtaArea(
            $this->tirtaConnectionAreaId($connection),
            'Sambungan ini berada di luar cakupan area kerja Anda.'
        );

        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->isMeterReader()) {
            return;
        }

        $setting = $this->tenantSetting();
        $defaultReaderId = (string) ($setting->getAttribute('default_meter_reader_user_id') ?? '');
        $configuredMode = (string) ($setting->getAttribute('meter_assignment_mode') ?? 'per_area');
        $usesServiceAreas = ServiceArea::query()->exists();
        $effectiveMode = $configuredMode === 'per_area' && $usesServiceAreas ? 'per_area' : 'global';

        $assignedUserId = null;

        if ($effectiveMode === 'global') {
            $assignedUserId = $defaultReaderId !== '' ? $defaultReaderId : null;
        } else {
            $areaId = $this->tirtaConnectionAreaId($connection);

            if ($areaId !== null) {
                $areas = ServiceArea::query()->select(['id', 'parent_id', 'name', 'area_type'])->get();
                $areaMap = $areas->keyBy(fn (ServiceArea $serviceArea): string => (string) $serviceArea->getKey());
                $activeAssignments = MeterReaderAssignment::query()
                    ->with('user')
                    ->where('is_active', true)
                    ->get()
                    ->keyBy('service_area_id');
                $assignmentArea = $areaMap->get((string) $areaId);

                if ($assignmentArea instanceof ServiceArea) {
                    $resolved = $this->resolveAreaAssignment($assignmentArea, $activeAssignments, $areaMap);
                    $assignedUserId = $resolved['assignment']?->user_id;
                }
            }

            if ($assignedUserId === null && $defaultReaderId !== '') {
                $assignedUserId = $defaultReaderId;
            }
        }

        if ($assignedUserId === null || (string) $assignedUserId !== (string) $user->id) {
            throw ValidationException::withMessages([
                'service_connection_id' => 'Sambungan ini tidak masuk assignment Anda. Hubungi admin untuk set petugas atau petugas global.',
            ]);
        }
    }

    protected function currentReaderName(): string
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        return trim((string) ($user?->name ?? ''));
    }

    protected function resolveDefaultMeterReader(TenantSetting $setting, Collection $meterReaders): ?User
    {
        $readerId = (string) ($setting->getAttribute('default_meter_reader_user_id') ?? '');

        if ($readerId === '') {
            return null;
        }

        $reader = $meterReaders->firstWhere('id', $readerId);

        return $reader instanceof User ? $reader : null;
    }

    protected function meterAssignmentMode(TenantSetting $setting): string
    {
        $mode = (string) ($setting->getAttribute('meter_assignment_mode') ?? 'per_area');

        return in_array($mode, ['global', 'per_area'], true) ? $mode : 'per_area';
    }

    protected function ensurePeriodConfigEditable(MeterReadingPeriod $period): void
    {
        $billingStatus = (string) ($period->billingPeriod?->status ?? '');

        if (in_array($billingStatus, ['generated', 'closed'], true)) {
            throw ValidationException::withMessages([
                'period' => 'Periode baca meter ini sudah dipakai di billing yang generated/closed, jadi tanggal dan statusnya tidak bisa diubah lagi.',
            ]);
        }
    }
}
