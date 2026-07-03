@extends('basefeature::layouts.master')

@section('content')
    <h1>Dashboard Tenant</h1>

    <ul>
        <li>Tenant: {{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</li>
        <li>SaaS Type: {{ tenant('saas_type') ?? 'universal' }}</li>
        <li>Database: {{ tenant()?->database()?->getName() }}</li>
        <li>Theme Color: {{ $setting?->theme_color ?? '#000000' }}</li>
    </ul>

    <h2>Statistik (Mock)</h2>
    <ul>
        <li>Transaksi hari ini: 12</li>
        <li>Pengguna aktif: 3</li>
        <li>Status sistem: OK</li>
    </ul>
@endsection
