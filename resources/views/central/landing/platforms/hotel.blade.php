<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $landingBrand = trim((string) data_get($platformExperience ?? [], 'brand_name', ''));
        $landingHeadline = trim((string) data_get($platformExperience ?? [], 'headline', ''));
        $displayBrand = $landingBrand !== '' ? $landingBrand : 'InnSystem';
        $brandInitial = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $displayBrand) ?: 'I', 0, 1));
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
    <!-- Alpine.js for lightweight interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc; /* Slate 50 */
        }
    </style>
</head>
<body x-data="{ mobileMenuOpen: false }">

    <!-- 1. NAVBAR -->
    <nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <!-- Logo -->
            <a href="{{ route('central.home') }}" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-slate-900 rounded-lg flex items-center justify-center text-white font-bold text-lg">{{ $brandInitial }}</div>
                <span class="text-xl font-bold text-slate-900 tracking-tight">{{ $displayBrand }}</span>
            </a>
            
            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
                <a href="#fitur" class="hover:text-slate-950 transition">Fitur Utama</a>
                <a href="#preview" class="hover:text-slate-950 transition">Preview Sistem</a>
                <a href="#integrasi" class="hover:text-slate-950 transition">Integrasi</a>
                <a href="#harga" class="hover:text-slate-950 transition">Skala Harga</a>
            </div>

            <!-- Desktop CTA -->
            <div class="hidden md:flex items-center gap-4">
                <a href="{{ route('central.register.create') }}" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition shadow-sm">
                    Ajukan Demo Live
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div x-show="mobileMenuOpen" x-transition class="md:hidden border-b border-slate-100 bg-white px-6 py-4 space-y-3 absolute w-full left-0">
            <a href="#fitur" class="block text-slate-600 font-medium py-1">Fitur Utama</a>
            <a href="#preview" class="block text-slate-600 font-medium py-1">Preview Sistem</a>
            <a href="#integrasi" class="block text-slate-600 font-medium py-1">Integrasi</a>
            <a href="#harga" class="block text-slate-600 font-medium py-1">Skala Harga</a>
            <a href="{{ route('central.register.create') }}" class="block bg-slate-900 text-white text-center py-2.5 rounded-lg text-sm font-medium">Ajukan Demo Live</a>
        </div>
    </nav>

    <!-- 2. HERO SECTION -->
    <section class="relative pt-16 pb-24 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <!-- Tagline Kecil -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800 mb-6">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Built for Modern Hoteliers
            </span>
            
            <!-- Headline -->
            <h1 class="text-4xl md:text-6xl font-bold text-slate-900 tracking-tight max-w-4xl mx-auto leading-[1.1]">
                {{ $landingHeadline !== '' ? $landingHeadline : 'Isi Kamar Lebih Maksimal, Urus Operasional Tanpa Pusing Harian' }}
            </h1>
            
            <!-- Subheadline -->
            <p class="mt-6 text-base md:text-xl text-slate-500 max-w-2xl mx-auto leading-relaxed">
                Satu sistem terpusat untuk kelola reservasi, sinkronisasi otomatis ke OTA, dan laporan keuangan real-time. Didesain intuitif untuk staf dan owner.
            </p>

            <!-- CTA Buttons -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('central.register.create') }}" class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 text-white px-8 py-4 rounded-xl font-medium transition shadow-lg shadow-slate-950/10 text-center">
                    Coba Gratis 14 Hari
                </a>
                <a href="#preview" class="w-full sm:w-auto flex items-center justify-center gap-2 text-slate-700 hover:text-slate-950 px-6 py-4 rounded-xl font-medium transition border border-slate-200 bg-white">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" />
                    </svg>
                    Lihat Cara Kerja (3 Menit)
                </a>
            </div>

            <!-- Real Mockup Screenshot (Bukan 3D Render fiktif) -->
            <div class="mt-16 relative mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl shadow-slate-200/50">
                <div class="rounded-xl border border-slate-100 bg-slate-50 overflow-hidden aspect-[16/9] flex flex-col">
                    <!-- Browser Top Bar Mock -->
                    <div class="bg-white border-b border-slate-100 px-4 py-3 flex items-center gap-2">
                        <div class="flex gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-slate-200"></span>
                            <span class="w-3 h-3 rounded-full bg-slate-200"></span>
                            <span class="w-3 h-3 rounded-full bg-slate-200"></span>
                        </div>
                        <div class="bg-slate-50 text-[11px] text-slate-400 px-3 py-1 rounded-md w-64 mx-auto text-left truncate">
                            app.innsystem.com/dashboard/occupancy
                        </div>
                    </div>
                    <!-- Mock Dashboard Content UI -->
                    <div class="p-6 flex-1 flex flex-col justify-between text-left">
                        <div class="flex justify-between items-center">
                            <div>
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ringkasan Hari Ini</h4>
                                <p class="text-2xl font-bold text-slate-800 mt-1">Okupansi Kamar: 87.5%</p>
                            </div>
                            <span class="text-xs bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-md font-medium border border-emerald-100">+12% dari minggu lalu</span>
                        </div>
                        <!-- Placeholder Grid/Graph Riil -->
                        <div class="grid grid-cols-4 gap-4 mt-4 flex-1">
                            <div class="bg-white p-4 rounded-xl border border-slate-100 flex flex-col justify-between">
                                <span class="text-xs text-slate-400 font-medium">Check-In Terjadwal</span>
                                <span class="text-xl font-bold text-slate-800">14 Kamar</span>
                            </div>
                            <div class="bg-white p-4 rounded-xl border border-slate-100 flex flex-col justify-between">
                                <span class="text-xs text-slate-400 font-medium">Check-Out Terjadwal</span>
                                <span class="text-xl font-bold text-slate-800">9 Kamar</span>
                            </div>
                            <div class="bg-white p-4 rounded-xl border border-slate-100 flex flex-col justify-between">
                                <span class="text-xs text-slate-400 font-medium">Kamar Kotor (Housekeeping)</span>
                                <span class="text-xl font-bold text-slate-800 text-amber-600">4 Kamar</span>
                            </div>
                            <div class="bg-white p-4 rounded-xl border border-slate-100 flex flex-col justify-between">
                                <span class="text-xs text-slate-400 font-medium">Pendapatan Kotor Hari Ini</span>
                                <span class="text-xl font-bold text-slate-800">Rp 12.450.000</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. THE PAIN POINTS / SOLUTION SECTION -->
    <section id="fitur" class="py-24 bg-white border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold text-slate-900 tracking-tight">Dibuat Berdasarkan Masalah Riil Lapangan</h2>
                <p class="mt-4 text-slate-500">Kami tahu repotnya mengelola hotel jika datanya masih terpisah-pisah. Ini solusi taktis yang kami tawarkan.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Card 1 -->
                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-100 hover:border-slate-200 transition">
                    <div class="w-10 h-10 rounded-lg bg-slate-900 text-white flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Real-Time Channel Manager</h3>
                    <p class="mt-3 text-sm text-slate-500 leading-relaxed">
                        Tiap ada booking di Traveloka atau walk-in, kuota kamar di Agoda, Booking.com, dan tiket.com ter-update otomatis dalam 2 detik. Selamat tinggal *overbooking*.
                    </p>
                </div>
                <!-- Card 2 -->
                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-100 hover:border-slate-200 transition">
                    <div class="w-10 h-10 rounded-lg bg-slate-900 text-white flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Housekeeping App</h3>
                    <p class="mt-3 text-sm text-slate-500 leading-relaxed">
                        Staf kebersihan punya dashboard mandiri di HP mereka. Begitu tamu check-out, kamar langsung masuk antrean pembersihan secara otomatis tanpa perlu ditelepon manual.
                    </p>
                </div>
                <!-- Card 3 -->
                <div class="p-8 rounded-2xl bg-slate-50 border border-slate-100 hover:border-slate-200 transition">
                    <div class="w-10 h-10 rounded-lg bg-slate-900 text-white flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" /></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Automated Night Audit</h3>
                    <p class="mt-3 text-sm text-slate-500 leading-relaxed">
                        Mencegah kebocoran dana dari oknum front office. Semua transaksi billing, biaya resto, dan deposit dicocokkan otomatis oleh sistem tepat jam 12 malam.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. INTERACTIVE PREVIEW (TAB SWITCHER) -->
    <section id="preview" class="py-24 bg-slate-50" x-data="{ currentTab: 'fo' }">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid lg:grid-cols-5 gap-12 items-center">
                <!-- Left: Navigation Tabs -->
                <div class="lg:col-span-2 space-y-4">
                    <h2 class="text-3xl font-bold text-slate-900 tracking-tight mb-6">Satu Dashboard untuk Seluruh Ekosistem Hotel</h2>
                    
                    <button @click="currentTab = 'fo'" :class="currentTab === 'fo' ? 'bg-white border-slate-200 text-slate-950 shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-900'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <span class="w-2 h-2 mt-1.5 rounded-full" :class="currentTab === 'fo' ? 'bg-slate-900' : 'bg-transparent'"></span>
                        <div>
                            <h4 class="font-semibold text-sm">Front Office Desk</h4>
                            <p class="text-xs text-slate-400 mt-1">Check-in, check-out, & kelola skema kasur dalam 3 klik cepat.</p>
                        </div>
                    </button>

                    <button @click="currentTab = 'wa'" :class="currentTab === 'wa' ? 'bg-white border-slate-200 text-slate-950 shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-900'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <span class="w-2 h-2 mt-1.5 rounded-full" :class="currentTab === 'wa' ? 'bg-slate-900' : 'bg-transparent'"></span>
                        <div>
                            <h4 class="font-semibold text-sm">WhatsApp Automations</h4>
                            <p class="text-xs text-slate-400 mt-1">Kirim kwitansi, detail booking, dan kode kamar otomatis tanpa simpan nomor.</p>
                        </div>
                    </button>

                    <button @click="currentTab = 'owner'" :class="currentTab === 'owner' ? 'bg-white border-slate-200 text-slate-950 shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-900'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <span class="w-2 h-2 mt-1.5 rounded-full" :class="currentTab === 'owner' ? 'bg-slate-900' : 'bg-transparent'"></span>
                        <div>
                            <h4 class="font-semibold text-sm">Owner Analytics Dashboard</h4>
                            <p class="text-xs text-slate-400 mt-1">Pantau ADR (Average Daily Rate) dan RevPAR langsung dari HP kapan saja.</p>
                        </div>
                    </button>
                </div>

                <!-- Right: Content Display (Simulated Screen) -->
                <div class="lg:col-span-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-xl h-96 flex items-center justify-center text-slate-400 text-sm">
                    <div x-show="currentTab === 'fo'" class="text-center px-8" x-transition>
                        <div class="font-bold text-slate-800 text-lg mb-2">[Mockup UI: Front Office Calendar Grid]</div>
                        <p class="max-w-md text-slate-500 text-xs">Visualisasikan kalender interaktif menyerupai sistem reservasi riil. Tampilan room matrix yang rapi, pengelompokan tipe kamar (Deluxe, Suite), dan fungsi drag-and-drop untuk memindahkan reservasi tamu secara dinamis.</p>
                    </div>
                    <div x-show="currentTab === 'wa'" class="text-center px-8" x-transition style="display: none;">
                        <div class="font-bold text-slate-800 text-lg mb-2">[Mockup UI: WA Integration Template Settings]</div>
                        <p class="max-w-md text-slate-500 text-xs">Antarmuka pengaturan webhook WhatsApp. Pengguna bisa melakukan kustomisasi template pesan otomatis seperti: "Halo {nama_tamu}, terima kasih telah memesan kamar {tipe_kamar}..." lengkap dengan status trigger pengiriman.</p>
                    </div>
                    <div x-show="currentTab === 'owner'" class="text-center px-8" x-transition style="display: none;">
                        <div class="font-bold text-slate-800 text-lg mb-2">[Mockup UI: Grafik Keuangan & RevPAR Analytics]</div>
                        <p class="max-w-md text-slate-500 text-xs">Visualisasi data berupa chart line dan bar chart yang elegan untuk menampilkan metrik finansial krusial perhotelan seperti Pendapatan Kamar, Tingkat Hunian harian, dan analisis performa antar kanal OTA secara real-time.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. INTEGRATION BLOCK -->
    <section id="integrasi" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Terhubung Mulus ke Ekosistem Perhotelan Global</h3>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-12 opacity-60 grayscale hover:grayscale-0 transition-all duration-300">
                <!-- Cukup teks tebal yang rapi / logo inline svg jika ada, di bawah ini contoh teks representatif premium -->
                <span class="text-xl font-bold tracking-tight text-slate-700">Traveloka</span>
                <span class="text-xl font-bold tracking-tight text-slate-700">Agoda</span>
                <span class="text-xl font-bold tracking-tight text-slate-700">Booking.com</span>
                <span class="text-xl font-bold tracking-tight text-slate-700">Midtrans</span>
                <span class="text-xl font-bold tracking-tight text-slate-700">Xendit</span>
            </div>
        </div>
    </section>

    <!-- 6. PRICING PLAN -->
    <section id="harga" class="py-24 bg-slate-50 border-t border-slate-200/60">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold text-slate-900 tracking-tight">Investasi Sesuai Skala Properti</h2>
                <p class="mt-4 text-slate-500">Semua paket sudah termasuk gratis biaya konfigurasi awal dan training staf secara intensif.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 items-stretch">
                <!-- Tier 1 -->
                <div class="bg-white p-8 rounded-2xl border border-slate-200 flex flex-col justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Smart Guesthouse</h4>
                        <p class="mt-2 text-xs text-slate-500">Cocok untuk penginapan harian, kost eksklusif, atau penginapan < 20 kamar.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-slate-900">Rp 450k</span>
                            <span class="text-sm text-slate-400">/bulan</span>
                        </div>
                        <ul class="mt-8 space-y-4 text-sm text-slate-600">
                            <li class="flex gap-2.5">✓ Kelola maks 20 Kamar</li>
                            <li class="flex gap-2.5">✓ Manajemen PMS Dasar</li>
                            <li class="flex gap-2.5">✓ Laporan Pendapatan</li>
                            <li class="flex gap-2.5 text-slate-300">✗ Auto Channel Manager</li>
                        </ul>
                    </div>
                    <a href="{{ route('central.register.create') }}" class="mt-8 block text-center w-full py-3 px-4 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-medium text-sm transition">Mulai Paket Dasar</a>
                </div>

                <!-- Tier 2 (Popular) -->
                <div class="bg-slate-900 p-8 rounded-2xl border border-slate-800 flex flex-col justify-between text-white relative shadow-xl transform md:-translate-y-2">
                    <span class="absolute top-0 right-6 transform -translate-y-1/2 bg-emerald-500 text-white text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Paling Populer</span>
                    <div>
                        <h4 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Boutique & Business</h4>
                        <p class="mt-2 text-xs text-slate-400">Solusi andalan hotel komersial kelas menengah untuk efisiensi penuh.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-3xl font-bold">Rp 1.250k</span>
                            <span class="text-sm text-slate-400">/bulan</span>
                        </div>
                        <ul class="mt-8 space-y-4 text-sm text-slate-300">
                            <li class="flex gap-2.5">✓ Kelola hingga 75 Kamar</li>
                            <li class="flex gap-2.5">✓ Otomatisasi Channel Manager (OTA)</li>
                            <li class="flex gap-2.5">✓ Integrasi Engine WhatsApp Otomatis</li>
                            <li class="flex gap-2.5">✓ Multi Akses: 5 Akun Staf & Housekeeping</li>
                        </ul>
                    </div>
                    <a href="{{ route('central.register.create') }}" class="mt-8 block text-center w-full py-3 px-4 bg-white hover:bg-slate-100 text-slate-950 rounded-xl font-medium text-sm transition shadow-md">Rekomendasi Utama</a>
                </div>

                <!-- Tier 3 -->
                <div class="bg-white p-8 rounded-2xl border border-slate-200 flex flex-col justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Resort & Enterprise</h4>
                        <p class="mt-2 text-xs text-slate-500">Kustomisasi mendalam bagi multi-properti atau kompleks resort besar.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-slate-900">Custom</span>
                            <span class="text-sm text-slate-400">/kontrak</span>
                        </div>
                        <ul class="mt-8 space-y-4 text-sm text-slate-600">
                            <li class="flex gap-2.5">✓ Jumlah Kamar Tanpa Batas</li>
                            <li class="flex gap-2.5">✓ Sinkronisasi Multi-Cabang Properti</li>
                            <li class="flex gap-2.5">✓ Integrasi IoT Smart Door Lock Hardware</li>
                            <li class="flex gap-2.5">✓ Dedicated Support 24/7 & Akses API</li>
                        </ul>
                    </div>
                    <a href="{{ route('central.register.create') }}" class="mt-8 block text-center w-full py-3 px-4 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-medium text-sm transition">Hubungi Sales</a>
                </div>
            </div>
        </div>
    </section>

    <!-- 7. FOOTER CTA -->
    <footer id="demo" class="bg-slate-950 text-white py-24">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-bold tracking-tight">Siap Tinggalkan Cara Manual yang Melelahkan?</h2>
            <p class="mt-4 text-slate-400 text-sm md:text-base max-w-xl mx-auto">Gabung bersama manajemen hotel modern lainnya yang telah memotong waktu kerja administrasi hingga 40%.</p>
            
            <div class="mt-10">
                <a href="{{ route('central.register.create') }}" class="inline-flex items-center gap-2 bg-white text-slate-950 px-8 py-4 rounded-xl font-semibold hover:bg-slate-100 transition shadow-lg">
                    Hubungi Tim via WhatsApp
                </a>
            </div>
            
            <div class="mt-16 pt-8 border-t border-slate-900 text-xs text-slate-600 flex flex-col sm:flex-row items-center justify-between gap-4">
                <span>&copy; 2026 {{ $displayBrand }}. Hak Cipta Dilindungi.</span>
                <div class="flex gap-6">
                    <a href="{{ route('central.login') }}" class="hover:text-slate-400">Masuk Panel Pusat</a>
                    <a href="{{ route('central.register.create') }}" class="hover:text-slate-400">Ajukan Demo</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
