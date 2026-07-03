<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ tenant('name') ?? tenant('id') }} - Login</title>

        <style>
            :root { --tenant-primary: {{ $tenantSetting->theme_color ?? '#000000' }}; }
            .tenant-btn { background: var(--tenant-primary); color: #fff; border: 1px solid var(--tenant-primary); padding: 6px 10px; }
            .tenant-btn:hover { filter: brightness(0.9); }
        </style>
    </head>
    <body>
        <h1>Login Tenant</h1>

        <p>Tenant: {{ $tenantSetting->brand_name ?? tenant('name') ?? tenant('id') }}</p>

        @if ($errors->any())
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ url('/login') }}">
            @csrf

            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            </div>

            <div>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>

            <div>
                <label>
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>
            </div>

            <button class="tenant-btn" type="submit">Login</button>
        </form>
    </body>
</html>
