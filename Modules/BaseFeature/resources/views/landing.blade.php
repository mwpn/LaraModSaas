<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</title>
    </head>
    <body>
        <header>
            <h1>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</h1>
            <p>Tipe bisnis: {{ tenant('saas_type') ?? 'universal' }}</p>
        </header>

        <main>
            <p>{{ $setting?->description ?? 'Landing page tenant belum dikustomisasi.' }}</p>
            <p>Warna tema: {{ $setting?->theme_color ?? '#000000' }}</p>
            <p><a href="{{ route('tenant.dashboard') }}">Masuk ke Dashboard</a></p>
        </main>
    </body>
</html>
