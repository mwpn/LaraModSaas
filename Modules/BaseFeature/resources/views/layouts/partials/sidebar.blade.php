<aside>
    <nav>
        <a href="{{ route('tenant.dashboard') }}">Dashboard Utama</a>

        @if (tenant('saas_type') === 'resto')
            <a href="#">[Menu Resto] Kasir</a>
            <a href="#">[Menu Resto] Manajemen Meja</a>
        @elseif (tenant('saas_type') === 'hotel')
            <a href="#">[Menu Hotel] Reservasi Kamar</a>
            <a href="#">[Menu Hotel] Housekeeping</a>
        @elseif (tenant('saas_type') === 'tirta')
            <a href="#">[Menu Air] Catat Meteran</a>
            <a href="#">[Menu Air] Tagihan Pelanggan</a>
        @elseif (tenant('saas_type') === 'netbilling')
            <a href="#">[Menu ISP] Paket PPPoE</a>
            <a href="#">[Menu ISP] Isolasi Mikrotik</a>
        @endif

        <a href="{{ route('tenant.settings') }}">Pengaturan Web</a>
    </nav>
</aside>
