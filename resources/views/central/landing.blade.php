<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'LaraModSaaS') }}</title>
    </head>
    <body>
        <h1>{{ config('app.name', 'LaraModSaaS') }}</h1>

        <p>Multi-SaaS Suite (Modular Monolith + Multi-Database Tenancy)</p>

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

        <h2>Masuk</h2>
        <ul>
            <li><a href="{{ url('/login') }}">Login Super Admin (Central)</a></li>
            <li><a href="{{ url('/super-admin/tenants') }}">Panel Super Admin</a></li>
        </ul>

        <h2>Daftar Tenant</h2>
        <h3>Pilih Paket Produk</h3>
        <div>
            <button type="button" onclick="pickSaasType('resto')">Pilih Paket: Resto</button>
            <button type="button" onclick="pickSaasType('hotel')">Pilih Paket: Hotel</button>
            <button type="button" onclick="pickSaasType('tirta')">Pilih Paket: Tirta</button>
            <button type="button" onclick="pickSaasType('netbilling')">Pilih Paket: Netbilling</button>
            <button type="button" onclick="pickSaasType('universal')">Pilih Paket: Universal</button>
        </div>

        <p id="selectedPackage"></p>

        <form id="registerForm" method="POST" action="{{ url('/register') }}">
            @csrf

            <div>
                <label for="business_name">Nama Bisnis</label>
                <input id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" required>
            </div>

            <div>
                <label for="subdomain">Subdomain</label>
                <input id="subdomain" type="text" name="subdomain" value="{{ old('subdomain') }}" required>
            </div>

            <div>
                <label for="saas_type">Paket</label>
                <select id="saas_type" name="saas_type">
                    @foreach (['resto' => 'Resto', 'hotel' => 'Hotel', 'tirta' => 'Tirta', 'netbilling' => 'Netbilling', 'universal' => 'Universal'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('saas_type', 'universal') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit">Buat Tenant</button>
        </form>

        <h2>Contoh URL Tenant</h2>
        <p><code>https://subdomain.{{ config('tenancy.central_domains.0') }}</code></p>

        <script>
            function pickSaasType(type) {
                var select = document.getElementById('saas_type');
                var labelMap = {
                    resto: 'Resto',
                    hotel: 'Hotel',
                    tirta: 'Tirta',
                    netbilling: 'Netbilling',
                    universal: 'Universal'
                };

                if (select) {
                    select.value = type;
                }

                var el = document.getElementById('selectedPackage');
                if (el) {
                    el.textContent = 'Paket terpilih: ' + (labelMap[type] || type);
                }

                var form = document.getElementById('registerForm');
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                pickSaasType(document.getElementById('saas_type')?.value || 'universal');
            });
        </script>
    </body>
</html>
