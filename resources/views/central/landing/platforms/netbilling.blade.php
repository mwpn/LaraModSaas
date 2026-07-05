<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $landingBrand = trim((string) data_get($platformExperience ?? [], 'brand_name', ''));
        $landingHeadline = trim((string) data_get($platformExperience ?? [], 'headline', ''));
        $displayBrand = $landingBrand !== '' ? $landingBrand : 'NetFlow';
        $brandInitial = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $displayBrand) ?: 'N', 0, 1));
        $pageTitle = $landingHeadline !== ''
            ? $landingHeadline . ' | ' . $displayBrand
            : $displayBrand;
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0b0f19; /* Deep Cyber Dark Slate */
        }
    </style>
</head>
<body x-data="{ mobileMenuOpen: false }">

    <!-- 1. NAVIGATION -->
    <nav class="sticky top-0 z-50 bg-[#0b0f19]/80 backdrop-blur-md border-b border-slate-800/60">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="{{ route('central.home') }}" class="flex items-center gap-2">
                <div class="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-indigo-600/30">{{ $brandInitial }}</div>
                <span class="text-xl font-bold text-white tracking-tight">{{ $displayBrand }}</span>
            </a>
            
            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-400">
                <a href="#solusi" class="hover:text-white transition">Solusi Jaringan</a>
                <a href="#fitur" class="hover:text-white transition">Otomasi Radius</a>
                <a href="#pricing" class="hover:text-white transition">Skala Harga</a>
            </div>

            <!-- Desktop CTA -->
            <div class="hidden md:flex items-center gap-4">
                <a href="{{ route('central.register.create') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition shadow-md shadow-indigo-600/20">
                    Hubungi Integrator
                </a>
            </div>

            <!-- Mobile Menu Toggle -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-slate-400 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div x-show="mobileMenuOpen" x-transition class="md:hidden border-b border-slate-800 bg-[#0b0f19] px-6 py-4 space-y-3 absolute w-full left-0">
            <a href="#solusi" class="block text-slate-400 font-medium py-1">Solusi Jaringan</a>
            <a href="#fitur" class="block text-slate-400 font-medium py-1">Otomasi Radius</a>
            <a href="#pricing" class="block text-slate-400 font-medium py-1">Skala Harga</a>
            <a href="{{ route('central.register.create') }}" class="block bg-indigo-600 text-white text-center py-2.5 rounded-xl text-sm font-medium">Hubungi Integrator</a>
        </div>
    </nav>

    <!-- 2. HERO SECTION -->
    <section class="relative pt-20 pb-28 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 text-center relative z-10">
            <!-- Badging Premium -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-800 text-indigo-400 mb-6 border border-slate-700">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span> Multi-Router API: MikroTik RouterOS v6/v7 & OLT Compatible
            </span>
            
            <!-- Main Headline -->
            <h1 class="text-4xl md:text-6xl font-bold text-white tracking-tight max-w-4xl mx-auto leading-[1.15]">
                {{ $landingHeadline !== '' ? $landingHeadline : 'Tagihan Otomatis, Jatuh Tempo Langsung Isolir Tanpa Manual' }}
            </h1>
            
            <!-- Subheadline -->
            <p class="mt-6 text-base md:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
                Kelola ribuan pelanggan PPPoE, Hotspot Voucheran, hingga log redaman kabel FO dalam satu panel tersinkronisasi API MikroTik secara real-time.
            </p>

            <!-- Action Buttons -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('central.register.create') }}" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-4 rounded-xl font-medium transition shadow-lg shadow-indigo-600/30 text-center">
                    Mulai Integrasi Gratis 7 Hari
                </a>
                <a href="#solusi" class="w-full sm:w-auto flex items-center justify-center gap-2 text-slate-300 hover:text-white px-6 py-4 rounded-xl font-medium transition border border-slate-800 bg-slate-900/50 backdrop-blur-sm">
                    Lihat Topologi Kompatibilitas
                </a>
            </div>

            <!-- Dashboard Preview (Clean Cyber Dark UI style) -->
            <div class="mt-16 relative mx-auto max-w-5xl rounded-2xl border border-slate-800 bg-slate-900/40 p-3 shadow-2xl shadow-indigo-950/20">
                <div class="rounded-xl border border-slate-800/80 bg-[#0d1321] overflow-hidden aspect-[16/9] flex flex-col text-left">
                    <!-- Browser Top Bar Mock -->
                    <div class="bg-[#0b0f19] border-b border-slate-800 px-4 py-3 flex items-center justify-between">
                        <div class="flex gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-slate-800"></span>
                            <span class="w-3 h-3 rounded-full bg-slate-800"></span>
                            <span class="w-3 h-3 rounded-full bg-slate-800"></span>
                        </div>
                        <div class="bg-[#121829] text-[11px] text-slate-500 px-3 py-1 rounded-md w-64 mx-auto text-left truncate border border-slate-800/60">
                            panel.netflow.id/router-1/pppoe
                        </div>
                        <span class="text-xs bg-emerald-950/60 text-emerald-400 px-2 py-0.5 rounded font-medium border border-emerald-900/50">API Connected</span>
                    </div>
                    <!-- Live Network/Billing Monitor Grid Mockup -->
                    <div class="p-6 flex-1 grid grid-cols-3 gap-4">
                        <!-- Stat 1 -->
                        <div class="bg-[#111827] p-5 rounded-xl border border-slate-800 flex flex-col justify-between">
                            <div>
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Pelanggan Aktif (PPPoE)</span>
                                <h3 class="text-2xl font-bold text-white mt-2">842 User <span class="text-xs text-slate-500 font-normal">Online</span></h3>
                                <p class="text-[11px] text-slate-500 mt-1">Total trafik saat ini: 1.2 Gbps / 2 Gbps IX</p>
                            </div>
                            <div class="pt-3 border-t border-slate-800/60 text-[11px] text-indigo-400 flex items-center gap-1">
                                <span>Sinkronisasi API: 0.4ms lalu</span>
                            </div>
                        </div>
                        <!-- Stat 2 -->
                        <div class="bg-[#111827] p-5 rounded-xl border border-slate-800 flex flex-col justify-between">
                            <div>
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Isolir Otomatis Hari Ini</span>
                                <h3 class="text-2xl font-bold text-amber-500 mt-2">14 Router Secret</h3>
                                <p class="text-[11px] text-slate-400 mt-1">Jatuh tempo terlewati per 00:00 WIB</p>
                            </div>
                            <div class="pt-3 border-t border-slate-800/60 text-[11px] text-slate-500">
                                Dial-out dialihkan ke halaman peringatan loket.
                            </div>
                        </div>
                        <!-- Live Network Alerts -->
                        <div class="bg-red-950/20 p-5 rounded-xl border border-red-900/40 flex flex-col justify-between">
                            <div>
                                <span class="text-xs font-bold text-red-400 uppercase tracking-wider block">System Status Logs</span>
                                <div class="mt-3 space-y-2 text-xs">
                                    <div class="p-2 bg-[#171e30] rounded border border-slate-800">
                                        <div class="flex justify-between font-medium text-slate-200"><span>Router-Garut-Core</span> <span class="text-red-400">High CPU (91%)</span></div>
                                        <p class="text-[10px] text-slate-500 mt-0.5">Saran: Optimalkan filter firewall / raw rules.</p>
                                    </div>
                                    <div class="p-2 bg-[#171e30] rounded border border-slate-800">
                                        <div class="flex justify-between font-medium text-slate-200"><span>OLT-Tirta-Ring2</span> <span class="text-amber-400">LOS Alarm</span></div>
                                        <p class="text-[10px] text-slate-500 mt-0.5">Redaman > -27dBm terdeteksi di 3 ODP.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Subtle Glow Background behind UI Mockup -->
        <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[600px] h-[250px] bg-indigo-900/10 blur-[120px] rounded-full -z-10"></div>
    </section>

    <!-- 3. PAIN POINTS IN ISP / RT-RW NET MANAGEMENT -->
    <section id="solusi" class="py-24 bg-[#0d1220] border-y border-slate-800/60">
        <div class="max-w-7xl mx-auto px-6">
            <div class="max-w-3xl mb-16">
                <h2 class="text-3xl font-bold text-white tracking-tight">Otomatisasi Total, Biarkan Router Anda Bekerja Sendiri</h2>
                <p class="mt-4 text-slate-400">Mengapa Anda harus begadang setiap akhir bulan hanya untuk memutus profile PPPoE atau menonaktifkan voucher pelanggan secara manual?</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Masalah 1 -->
                <div class="p-6 rounded-2xl bg-[#111726] border border-slate-800/60">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm mb-6 shadow-md shadow-indigo-600/20">01</div>
                    <h3 class="text-lg font-bold text-white">Dynamic Profile & Auto Isolir via API</h3>
                    <p class="text-sm text-slate-400 leading-relaxed mt-2">
                        Sistem memantau tanggal jatuh tempo setiap *secret*. Begitu waktu habis, akun pelanggan otomatis digeser ke *Address List* khusus isolir. Pelanggan tidak bisa internetan, dan browser mereka otomatis dialihkan ke halaman tagihan loket lokal.
                    </p>
                </div>
                <!-- Masalah 2 -->
                <div class="p-6 rounded-2xl bg-[#111726] border border-slate-800/60">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm mb-6 shadow-md shadow-indigo-600/20">02</div>
                    <h3 class="text-lg font-bold text-white">Notifikasi H-3 WhatsApp Billing Gateway</h3>
                    <p class="text-sm text-slate-400 leading-relaxed mt-2">
                        Kirim pengingat pembayaran otomatis langsung ke WhatsApp pelanggan tanpa menyita waktu admin Anda. Pelanggan bisa klik tautan di pesan, bayar lewat QRIS, dan dalam 2 detik sistem langsung melakukan *re-enable* akun di MikroTik secara otomatis.
                    </p>
                </div>
                <!-- Masalah 3 -->
                <div class="p-6 rounded-2xl bg-[#111726] border border-slate-800/60">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm mb-6 shadow-md shadow-indigo-600/20">03</div>
                    <h3 class="text-lg font-bold text-white">Manajemen OLT & Inventaris ODP</h3>
                    <p class="text-sm text-slate-400 leading-relaxed mt-2">
                        Bukan sekadar software billing kasir biasa. Lengkap dengan modul pemetaan kabel optik (*Fiber Optic Ring*), pencatatan sisa port pada ODP di lapangan, hingga kalkulator redaman dBm untuk memastikan kestabilan *bandwidth delivery* ke rumah warga.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. INTERACTIVE SYSTEM WORKFLOW PREVIEW -->
    <section id="fitur" class="py-24 bg-[#0b0f19]" x-data="{ activeFeature: 'mikrotik' }">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid lg:grid-cols-5 gap-12 items-center">
                
                <!-- Tab Controls -->
                <div class="lg:col-span-2 space-y-3">
                    <h2 class="text-3xl font-bold text-white tracking-tight mb-6">Mendukung Multi-Tenant & Distribusi Terpusat</h2>
                    
                    <button @click="activeFeature = 'mikrotik'" :class="activeFeature === 'mikrotik' ? 'bg-[#111726] border-slate-700 text-white shadow-lg' : 'border-transparent text-slate-500'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <div class="text-sm font-semibold">Integrasi MikroTik API / Radius Server</div>
                    </button>

                    <button @click="activeFeature = 'voucher'" :class="activeFeature === 'voucher' ? 'bg-[#111726] border-slate-700 text-white shadow-lg' : 'border-transparent text-slate-500'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <div class="text-sm font-semibold">Generator Voucher Hotspot Multi-Kriteria</div>
                    </button>

                    <button @click="activeFeature = 'olt'" :class="activeFeature === 'olt' ? 'bg-[#111726] border-slate-700 text-white shadow-lg' : 'border-transparent text-slate-500'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <div class="text-sm font-semibold">Monitoring Redaman OLT & ONU Pemetaan</div>
                    </button>
                </div>

                <!-- Display Visual Container -->
                <div class="lg:col-span-3 bg-[#0d1321] p-6 rounded-2xl border border-slate-800 shadow-2xl shadow-indigo-950/20 h-80 flex items-center justify-center text-slate-500">
                    <div x-show="activeFeature === 'mikrotik'" class="text-center px-6" x-transition>
                        <p class="font-bold text-slate-200 text-base mb-1">[Mockup UI: Sinkronisasi Skrip Router & Sync Queue]</p>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto mt-2">Kelola Simple Queue, Queue Tree, hingga pembagian Parent Bandwidth antar wilayah secara dinamis langsung dari web tanpa perlu membuka aplikasi Winbox berkali-kali.</p>
                    </div>
                    <div x-show="activeFeature === 'voucher'" class="text-center px-6" x-transition style="display: none;">
                        <p class="font-bold text-slate-200 text-base mb-1">[Mockup UI: Kontrol Masa Aktif & Print Layout Template]</p>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto mt-2">Cetak ratusan kupon voucher WiFi prabayar dalam hitungan detik dengan profil pembatasan kecepatan (*rate-limit*), masa aktif berjalan, serta skema harga khusus untuk mitra agen/warung retail terdekat.</p>
                    </div>
                    <div x-show="activeFeature === 'olt'" class="text-center px-6" x-transition style="display: none;">
                        <p class="font-bold text-slate-200 text-base mb-1">[Mockup UI: Topologi GPON/EPON & Log Rx Power]</p>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto mt-2">Pantau kondisi kesehatan port PON di OLT Anda secara visual. Dapatkan alert instan jika ada modem ONU pelanggan mengalami redaman drop drastis akibat kabel serat optik terjepit atau tertekuk pohon.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- 5. PRICING STRUCTURE -->
    <section id="pricing" class="py-24 bg-[#0d1220] border-t border-slate-800/60">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold text-white tracking-tight">Skema Biaya Transparan Tanpa Batasan Jumlah Router</h2>
                <p class="mt-4 text-slate-400">Pilihlah paket berdasarkan jumlah total Sambungan Rumah (SR) / Pelanggan Aktif yang Anda kelola saat ini.</p>
            </div>

            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto items-stretch">
                <!-- Starter Tier -->
                <div class="bg-[#111726] p-8 rounded-2xl border border-slate-800 flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider block">Skala RT/RW Net</span>
                        <h4 class="text-xl font-bold text-white mt-1">SaaS Lite Network</h4>
                        <p class="mt-2 text-xs text-slate-400">Paling pas untuk pengusaha jaringan mandiri yang mengelola kluster lokal berskala kecil hingga menengah.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-white">Rp 199k</span>
                            <span class="text-sm text-slate-500">/bulan</span>
                        </div>
                        <ul class="mt-8 space-y-3.5 text-sm text-slate-300 border-t border-slate-800 pt-6">
                            <li class="flex items-center gap-2">✓ Maksimal 300 Pelanggan Aktif (PPPoE/Hotspot)</li>
                            <li class="flex items-center gap-2">✓ Otomasi Isolir via API Konektor</li>
                            <li class="flex items-center gap-2">✓ Integrasi Loket Pembayaran E-Wallet & QRIS</li>
                            <li class="flex items-center gap-2 text-slate-600">✗ Modul Manajemen Kabel FO & Redaman OLT</li>
                        </ul>
                    </div>
                    <a href="{{ route('central.register.create') }}" class="mt-8 block text-center w-full py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-medium text-sm transition">Coba Paket Lite</a>
                </div>

                <!-- Pro Tier -->
                <div class="bg-indigo-950/40 p-8 rounded-2xl border border-indigo-500/30 flex flex-col justify-between text-white relative shadow-xl shadow-indigo-950/40">
                    <span class="absolute top-0 right-6 transform -translate-y-1/2 bg-indigo-600 text-white text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Pilihan ISP Berlisensi / WISP</span>
                    <div>
                        <span class="text-xs font-bold text-indigo-300 uppercase tracking-wider block">ISP Core Scale</span>
                        <h4 class="text-xl font-bold mt-1">Enterprise Operator</h4>
                        <p class="mt-2 text-xs text-indigo-200">Kapasitas penuh dengan keandalan tinggi untuk operasi infrastruktur skala regional.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-3xl font-bold">Rp 599k</span>
                            <span class="text-sm text-indigo-400">/bulan</span>
                        </div>
                        <ul class="mt-8 space-y-3.5 text-sm text-indigo-200 border-t border-indigo-900 pt-6">
                            <li class="flex items-center gap-2">✓ Pelanggan Aktif Tanpa Batasan (Unlimited)</li>
                            <li class="flex items-center gap-2">✓ Dukungan Integrasi Radius Server Terpusat</li>
                            <li class="flex items-center gap-2">✓ Modul Peta GIS ODP & Monitoring Kesehatan OLT</li>
                            <li class="flex items-center gap-2">✓ Multi-Administrator Akses Kontrol Level Hak Akses</li>
                        </ul>
                    </div>
                    <a href="{{ route('central.register.create') }}" class="mt-8 block text-center w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-medium text-sm transition shadow-md shadow-indigo-600/20">Mulai Paket Operator</a>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. CLOSING CALL-TO-ACTION & FOOTER -->
    <footer id="demo" class="bg-[#070a12] text-white pt-24 pb-12 border-t border-slate-900">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-bold tracking-tight">Kendalikan Infrastruktur Jaringan Tanpa Kebocoran Finansial</h2>
            <p class="mt-4 text-slate-400 text-sm md:text-base max-w-lg mx-auto">Kami menyediakan skrip template siap pakai untuk mempermudah migrasi konfigurasi router lama Anda dalam hitungan menit tanpa merusak sesi pengguna saat ini.</p>
            
            <div class="mt-10">
                <a href="{{ route('central.register.create') }}" class="inline-flex items-center gap-2 bg-white text-slate-950 px-8 py-4 rounded-xl font-semibold hover:bg-slate-100 transition shadow-lg text-sm">
                    Hubungi Tim Network Engineer Kami
                </a>
            </div>
            
            <div class="mt-20 pt-8 border-t border-slate-900/60 text-xs text-slate-600 flex flex-col sm:flex-row items-center justify-between gap-4">
                <span>&copy; 2026 {{ $displayBrand }}. Seluruh Hak Cipta Dilindungi.</span>
                <div class="flex gap-6">
                    <a href="{{ route('central.login') }}" class="hover:text-slate-400">Masuk Panel Pusat</a>
                    <a href="{{ route('central.register.create') }}" class="hover:text-slate-400">Ajukan Demo</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
