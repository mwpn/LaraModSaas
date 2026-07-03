<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Central - Tenant SaaS Switcher</title>
    </head>
    <body>
        <h1>Super Admin Tenant Switcher</h1>

        @if (session('status'))
            <p>{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Tenant ID</th>
                    <th>Nama Tenant</th>
                    <th>SaaS Type</th>
                    <th>Switch</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    <tr>
                        <td>{{ $tenant->id }}</td>
                        <td>{{ $tenant->name }}</td>
                        <td>{{ $tenant->saas_type ?? 'universal' }}</td>
                        <td>
                            <form method="POST" action="{{ route('central.super-admin.tenants.switch-saas', $tenant->id) }}">
                                @csrf
                                <select name="saas_type">
                                    @foreach ($availableSaasTypes as $saasType)
                                        <option value="{{ $saasType }}" @selected(($tenant->saas_type ?? 'universal') === $saasType)>
                                            {{ $saasType }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Belum ada tenant terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </body>
</html>
