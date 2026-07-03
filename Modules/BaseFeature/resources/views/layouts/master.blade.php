<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ tenant('name') ?? tenant('id') }} - Dashboard</title>

        <style>
            :root { --tenant-primary: {{ $tenantSetting->theme_color ?? '#000000' }}; }
            header { border-bottom: 2px solid var(--tenant-primary); padding: 12px; display: flex; justify-content: space-between; align-items: center; }
            main { padding: 12px; }
            aside { padding: 12px; border-right: 2px solid var(--tenant-primary); }
            nav a { display: block; padding: 6px 8px; text-decoration: none; color: inherit; }
            nav a:hover { background: var(--tenant-primary); color: #fff; }
            .tenant-btn { background: var(--tenant-primary); color: #fff; border: 1px solid var(--tenant-primary); padding: 6px 10px; }
            .tenant-btn:hover { filter: brightness(0.9); }
        </style>
    </head>
    <body>
        @include('basefeature::layouts.partials.header')
        @include('basefeature::layouts.partials.sidebar')

        <main>
            @yield('content')
        </main>

        @include('basefeature::layouts.partials.footer')
    </body>
</html>
