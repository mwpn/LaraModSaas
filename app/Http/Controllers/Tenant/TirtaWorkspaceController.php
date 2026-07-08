<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\Concerns\InteractsWithTirtaAreaScope;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Modules\BaseFeature\Models\TenantSetting;

class TirtaWorkspaceController extends Controller
{
    use InteractsWithTirtaAreaScope;

    public function index(): View|RedirectResponse
    {
        $this->ensureTirtaTenant();

        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if ($user instanceof User && $user->isMeterReader()) {
            return redirect()->route('tenant.tirta.meter-readings');
        }

        $setting = $this->tenantSetting();
        $hasWarehouseRoute = Route::has('tenant.tirta.warehouse');
        $canManageUsers = $user instanceof User && $user->canManageUsers();
        $canAccessMasterData = $user instanceof User && $user->canAccessTirtaMasterData();
        $canAccessMeterReading = $user instanceof User && $user->canAccessTirtaMeterReadingWorkspace();
        $canAccessBilling = $user instanceof User && $user->canAccessTirtaBilling();
        $canManageBilling = $user instanceof User && $user->canManageTirtaBilling();
        $canRecordBillingPayment = $user instanceof User && $user->canRecordTirtaBillingPayment();
        $canAccessWarehouse = $hasWarehouseRoute && $user instanceof User && $user->canAccessTirtaWarehouse();
        $canApproveProcurement = $user instanceof User && $user->canApproveTirtaProcurementRequest();
        $roleLabel = $user instanceof User ? $user->tirtaRoleLabel() : 'Pengguna Tenant';
        $roleLabelOverrides = $setting->getAttribute('role_label_overrides');
        if (is_string($roleLabelOverrides) && $roleLabelOverrides !== '') {
            $decodedOverrides = json_decode($roleLabelOverrides, true);
            $roleLabelOverrides = is_array($decodedOverrides) ? $decodedOverrides : null;
        }
        if (is_array($roleLabelOverrides) && filled($user?->roleSlug())) {
            $customRoleLabel = $roleLabelOverrides[(string) $user->roleSlug()] ?? null;
            if (filled($customRoleLabel)) {
                $roleLabel = trim((string) $customRoleLabel);
            }
        }
        $areaScopeLabel = $this->tirtaAreaScopeLabel();

        $quickActions = [];
        $rolePlaybook = [];

        if ($canAccessMasterData) {
            $quickActions[] = [
                'label' => 'Master Tirta',
                'description' => 'Kelola area, golongan, pelanggan, sambungan, dan tarif.',
                'route' => route('tenant.tirta.master-data'),
                'icon' => 'fa-layer-group',
            ];
            $rolePlaybook[] = 'Kelola master area, pelanggan, sambungan, dan tarif Tirta.';
        }

        if ($canAccessMeterReading) {
            $quickActions[] = [
                'label' => 'Catat Meter',
                'description' => 'Kelola periode baca meter dan input angka meter per sambungan.',
                'route' => route('tenant.tirta.meter-readings'),
                'icon' => 'fa-gauge-high',
            ];
            $rolePlaybook[] = $user instanceof User && $user->isMeterReader()
                ? 'Input angka meter lapangan sesuai area kerja yang ditugaskan.'
                : 'Pantau periode baca meter dan review progres pembacaan.';
        }

        if ($canAccessBilling) {
            $quickActions[] = [
                'label' => 'Billing Tirta',
                'description' => $canManageBilling
                    ? 'Buka periode billing, generate invoice, dan review status tagihan.'
                    : 'Review invoice, aging piutang, dan catat pembayaran pelanggan.',
                'route' => route('tenant.tirta.billing'),
                'icon' => 'fa-file-invoice-dollar',
            ];
            $rolePlaybook[] = $canManageBilling
                ? 'Kelola billing period, generate invoice, denda, dan lifecycle sambungan.'
                : ($canRecordBillingPayment
                    ? 'Review invoice dan catat pembayaran pelanggan dari loket/penagihan.'
                    : 'Pantau status billing sesuai scope akses Anda.');
        }

        if ($canAccessWarehouse) {
            $quickActions[] = [
                'label' => 'Warehouse',
                'description' => $canApproveProcurement
                    ? 'Review workflow request dan approve pengadaan pusat sesuai otorisasi.'
                    : 'Kontrol stok pipa, water meter, dan mutasi antar gudang, unit, rayon, atau cabang.',
                'route' => route('tenant.tirta.warehouse'),
                'icon' => 'fa-warehouse',
            ];
            $rolePlaybook[] = $canApproveProcurement
                ? 'Approve pengadaan pusat dan review alur request warehouse.'
                : 'Kelola request barang, mutasi stok, master item, dan lokasi warehouse.';
        }

        $quickActions[] = [
            'label' => 'Profil Saya',
            'description' => 'Ubah data akun, avatar, dan password sendiri.',
            'route' => route('tenant.profile.edit'),
            'icon' => 'fa-id-badge',
        ];

        if ($canManageUsers) {
            $quickActions[] = [
                'label' => 'Kelola Pengguna',
                'description' => 'Atur akun admin/operator tenant sesuai role dan area kerja.',
                'route' => route('tenant.users.index'),
                'icon' => 'fa-users-cog',
            ];
            $rolePlaybook[] = 'Kelola user tenant, role operasional, area kerja, dan status akses.';
            $quickActions[] = [
                'label' => 'Pengaturan Brand',
                'description' => 'Sinkronkan identitas web tenant Tirta.',
                'route' => route('tenant.settings'),
                'icon' => 'fa-palette',
            ];
            $rolePlaybook[] = 'Atur label role, branding tenant, dan master pendukung workspace.';
        }

        $quickActions[] = [
            'label' => 'Preview Landing',
            'description' => 'Lihat landing tenant yang aktif.',
            'route' => route('tenant.home'),
            'icon' => 'fa-globe',
        ];

        return view('basefeature::tirta.workspace', [
            'setting' => $setting,
            'workspaceStats' => [
                [
                    'label' => 'Status Workspace',
                    'value' => 'Sprint 4',
                    'hint' => 'Billing operasional mulai aktif',
                    'icon' => 'fa-water',
                ],
                [
                    'label' => 'Role Aktif',
                    'value' => $roleLabel,
                    'hint' => $areaScopeLabel ? 'Scoped ke area kerja' : 'Akses tenant global',
                    'icon' => 'fa-layer-group',
                ],
                [
                    'label' => 'Scope Area',
                    'value' => $areaScopeLabel ?? 'Semua Area',
                    'hint' => $areaScopeLabel ? 'Berlaku untuk area ini dan turunannya' : 'Tidak dibatasi per area',
                    'icon' => 'fa-map-location-dot',
                ],
                [
                    'label' => 'Database',
                    'value' => (string) (tenant()?->database()?->getName() ?? '-'),
                    'hint' => 'Schema workspace aktif',
                    'icon' => 'fa-database',
                ],
            ],
            'workQueues' => [
                [
                    'title' => 'Master Pelanggan & Sambungan',
                    'description' => 'Siapkan data SR, nomor pelanggan, alamat layanan, dan status sambungan untuk fondasi billing air.',
                    'anchor' => 'master-pelanggan',
                    'status' => 'Sprint 2 Selesai',
                ],
                [
                    'title' => 'Pembacaan Meter Bulanan',
                    'description' => 'Alur input angka meter, review pemakaian, dan validasi lonjakan konsumsi untuk petugas lapangan.',
                    'anchor' => 'pembacaan-meter',
                    'status' => 'Sprint 3 Selesai',
                ],
                [
                    'title' => 'Tagihan & Pembayaran',
                    'description' => 'Generate invoice, kontrol piutang, dan pengiriman tagihan digital ke pelanggan.',
                    'anchor' => 'tagihan-pembayaran',
                    'status' => 'Sprint 4 Aktif',
                ],
            ],
            'quickActions' => $quickActions,
            'rolePlaybook' => $rolePlaybook,
            'readinessChecklist' => [
                'Struktur workspace Tirta sudah tersedia dan siap dipakai sebagai panel kerja utama.',
                'Navigasi tenant sudah diarahkan ke halaman operasional Tirta dan Catat Meter.',
                'Master pelanggan, sambungan, dan tarif sudah siap jadi basis pembacaan meter.',
                'Workspace catat meter siap dipakai untuk periode baca dan input meter bulanan.',
                'Sprint billing sudah punya jalur kerja untuk generate invoice dan review status tagihan.',
                $areaScopeLabel ? sprintf('Akun aktif dibatasi ke area %s dan turunannya.', $areaScopeLabel) : 'Akun aktif tidak dibatasi ke area tertentu.',
            ],
            'accessContext' => [
                'role_label' => $roleLabel,
                'area_scope_label' => $areaScopeLabel,
                'can_manage_users' => $canManageUsers,
                'can_access_backoffice' => $user instanceof User && $user->canAccessTirtaBackoffice(),
            ],
        ]);
    }

    protected function ensureTirtaTenant(): void
    {
        if ((string) (tenant('saas_type') ?? '') !== 'tirta') {
            abort(404);
        }
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
