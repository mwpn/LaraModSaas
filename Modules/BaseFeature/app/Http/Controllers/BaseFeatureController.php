<?php

declare(strict_types=1);

namespace Modules\BaseFeature\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\BaseFeature\Models\TenantSetting;

class BaseFeatureController extends Controller
{
    public function landing(): View
    {
        return view('basefeature::landing', [
            'setting' => $this->tenantSetting(),
        ]);
    }

    public function dashboard(): View
    {
        return view('basefeature::index', [
            'setting' => $this->tenantSetting(),
        ]);
    }

    public function settings(): View
    {
        return view('basefeature::settings', [
            'setting' => $this->tenantSetting(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'theme_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $this->tenantSetting()->fill($validated)->save();

        return back()->with('status', 'Pengaturan web berhasil diperbarui.');
    }

    protected function tenantSetting(): TenantSetting
    {
        return TenantSetting::query()->firstOrCreate(
            [],
            [
                'brand_name' => (string) (tenant('name') ?? tenant('id') ?? config('app.name')),
                'description' => 'Landing page tenant belum dikustomisasi.',
                'theme_color' => '#000000',
            ]
        );
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('basefeature::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('basefeature::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('basefeature::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('basefeature::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
