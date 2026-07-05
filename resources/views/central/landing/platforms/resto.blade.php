<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $landingBrand = trim((string) data_get($platformExperience ?? [], 'brand_name', ''));
        $landingHeadline = trim((string) data_get($platformExperience ?? [], 'headline', ''));
        $displayBrand = $landingBrand !== '' ? $landingBrand : 'RestoFlow';
        $brandInitial = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $displayBrand) ?: 'R', 0, 1));
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
            background-color: #fafafa; /* Neutral off-white premium */
        }
    </style>
</head>
<body x-data="{ mobileMenuOpen: false }">

    <!-- 1. NAVIGATION -->
    <nav class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-neutral-100">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="{{ route('central.home') }}" class="flex items-center gap-2">
                <div class="w-9 h-9 bg-neutral-950 rounded-xl flex items-center justify-center text-white font-bold text-lg">{{ $brandInitial }}</div>
                <span class="text-xl font-bold text-neutral-950 tracking-tight">{{ $displayBrand }}</span>
            </a>
            
            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center gap-8 text-sm font-medium text-neutral-600">
                <a href="#solusi" class="hover:text-neutral-950 transition">Solusi Operasional</a>
                <a href="#fitur" class="hover:text-neutral-950 transition">Ekosistem</a>
                <a href="#pricing" class="hover:text-neutral-950 transition">Harga Bisnis</a>
            </div>

            <!-- Desktop CTA -->
            <div class="hidden md:flex items-center gap-4">
                <a href="{{ route('central.register.create') }}" class="bg-neutral-950 hover:bg-neutral-800 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition shadow-sm">
                    Mulai Demo Gratis
                </a>
            </div>

            <!-- Mobile Menu Toggle -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-neutral-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div x-show="mobileMenuOpen" x-transition class="md:hidden border-b border-neutral-100 bg-white px-6 py-4 space-y-3 absolute w-full left-0">
            <a href="#solusi" class="block text-neutral-600 font-medium py-1">Solusi Operasional</a>
            <a href="#fitur" class="block text-neutral-600 font-medium py-1">Ekosistem</a>
            <a href="#pricing" class="block text-neutral-600 font-medium py-1">Harga Bisnis</a>
            <a href="{{ route('central.register.create') }}" class="block bg-neutral-950 text-white text-center py-2.5 rounded-xl text-sm font-medium">Mulai Demo Gratis</a>
        </div>
    </nav>

    <!-- 2. HERO SECTION -->
    <section class="relative pt-16 pb-24 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <!-- Badging Premium -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-neutral-100 text-neutral-800 mb-6">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Next-Gen POS & Supply Chain for F&B
            </span>
            
            <!-- Main Headline -->
            <h1 class="text-4xl md:text-6xl font-bold text-neutral-950 tracking-tight max-w-4xl mx-auto leading-[1.1]">
                {{ $landingHeadline !== '' ? $landingHeadline : 'Antrean Kasir Beres, Stok Dapur Terkontrol Otomatis' }}
            </h1>
            
            <!-- Subheadline -->
            <p class="mt-6 text-base md:text-xl text-neutral-500 max-w-2xl mx-auto leading-relaxed">
                Aplikasi kasir multi-device yang terintegrasi langsung dengan manajemen resep bahan baku (HPP), QR-Order mandiri, dan agregator ojek online.
            </p>

            <!-- Action Buttons -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('central.register.create') }}" class="w-full sm:w-auto bg-neutral-950 hover:bg-neutral-800 text-white px-8 py-4 rounded-xl font-medium transition shadow-lg shadow-neutral-950/10 text-center">
                    Coba Gratis di Tab / HP Anda
                </a>
                <a href="#solusi" class="w-full sm:w-auto flex items-center justify-center gap-2 text-neutral-700 hover:text-neutral-950 px-6 py-4 rounded-xl font-medium transition border border-neutral-200 bg-white">
                    Pelajari Manajemen Resep
                </a>
            </div>

            <!-- Dashboard Preview (Clean UI style) -->
            <div class="mt-16 relative mx-auto max-w-5xl rounded-2xl border border-neutral-200 bg-white p-3 shadow-2xl shadow-neutral-200/40">
                <div class="rounded-xl border border-neutral-100 bg-neutral-50 overflow-hidden aspect-[16/9] flex flex-col text-left">
                    <!-- Browser Top Bar Mock -->
                    <div class="bg-white border-b border-neutral-100 px-4 py-3 flex items-center justify-between">
                        <div class="flex gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-neutral-200"></span>
                            <span class="w-3 h-3 rounded-full bg-neutral-200"></span>
                            <span class="w-3 h-3 rounded-full bg-neutral-200"></span>
                        </div>
                        <div class="bg-neutral-50 text-[11px] text-neutral-400 px-3 py-1 rounded-md w-64 mx-auto text-left truncate">
                            pos.restoflow.id/live-orders
                        </div>
                        <span class="text-xs bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-medium border border-emerald-100 animate-pulse">Kasir Terhubung</span>
                    </div>
                    <!-- Live Orders Grid Mockup -->
                    <div class="p-6 flex-1 grid grid-cols-3 gap-4">
                        <!-- Meja 04 -->
                        <div class="bg-white p-4 rounded-xl border border-neutral-100 flex flex-col justify-between shadow-xs">
                            <div>
                                <div class="flex justify-between items-start">
                                    <span class="text-xs font-bold bg-neutral-100 text-neutral-800 px-2 py-1 rounded">Meja 04</span>
                                    <span class="text-[11px] text-neutral-400">Dine-in · 4 mnt lalu</span>
                                </div>
                                <ul class="mt-4 space-y-1.5 text-xs text-neutral-700">
                                    <li class="flex justify-between"><span>2x Iced Americano</span> <span class="font-medium text-neutral-400">Siap</span></li>
                                    <li class="flex justify-between"><span>1x Truffle Fries</span> <span class="font-medium text-amber-600">Di Dapur</span></li>
                                </ul>
                            </div>
                            <div class="pt-3 border-t border-neutral-100 flex justify-between items-center text-xs">
                                <span class="text-neutral-400">Total</span>
                                <span class="font-bold text-neutral-900">Rp 115.000</span>
                            </div>
                        </div>
                        <!-- Order Online -->
                        <div class="bg-white p-4 rounded-xl border border-neutral-100 flex flex-col justify-between shadow-xs">
                            <div>
                                <div class="flex justify-between items-start">
                                    <span class="text-xs font-bold bg-emerald-50 text-emerald-700 px-2 py-1 rounded border border-emerald-100">GrabFood #82A</span>
                                    <span class="text-[11px] text-neutral-400">Delivery · Baru</span>
                                </div>
                                <ul class="mt-4 space-y-1.5 text-xs text-neutral-700">
                                    <li class="flex justify-between"><span>1x Beef Burger Extra Cheese</span> <span class="font-medium text-neutral-400">Antrean</span></li>
                                    <li class="flex justify-between"><span>1x Matcha Latte XXL</span> <span class="font-medium text-neutral-400">Antrean</span></li>
                                </ul>
                            </div>
                            <div class="pt-3 border-t border-neutral-100 flex justify-between items-center text-xs">
                                <span class="text-neutral-400">Total</span>
                                <span class="font-bold text-neutral-900">Rp 88.500</span>
                            </div>
                        </div>
                        <!-- Status Bahan Baku Alert -->
                        <div class="bg-amber-50/40 p-4 rounded-xl border border-amber-100 flex flex-col justify-between">
                            <div>
                                <span class="text-xs font-bold text-amber-800 uppercase tracking-wider block">Peringatan Inventaris</span>
                                <p class="text-xs text-amber-700 mt-2 leading-relaxed">Bahan berikut di bawah batas aman penyimpanan minimum harian:</p>
                                <div class="mt-3 space-y-1.5 text-xs">
                                    <div class="flex justify-between text-neutral-700"><span>- Fresh Milk (Greenfields)</span> <span class="font-bold text-red-600">Sisa 2 Liter</span></div>
                                    <div class="flex justify-between text-neutral-700"><span>- Espresso Blend No.3</span> <span class="font-bold text-red-600">Sisa 0.5 Kg</span></div>
                                </div>
                            </div>
                            <span class="text-[11px] text-amber-700 underline cursor-pointer font-medium">Buat PO Otomatis ke Supplier</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. RESOLVING RETAIL PAIN POINTS -->
    <section id="solusi" class="py-24 bg-white border-y border-neutral-100">
        <div class="max-w-7xl mx-auto px-6">
            <div class="max-w-3xl mb-16">
                <h2 class="text-3xl font-bold text-neutral-950 tracking-tight">Kendalikan Restoran Tanpa Harus Berada di Outlet Setiap Hari</h2>
                <p class="mt-4 text-neutral-500">Didesain khusus untuk memecahkan friksi paling menyebalkan dalam operasional bisnis F&B modern.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Solusi 1 -->
                <div class="space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-neutral-950 text-white flex items-center justify-center font-bold text-sm">01</div>
                    <h3 class="text-lg font-bold text-neutral-950">Potong Komposisi Bahan (Ingredient-Level Inventory)</h3>
                    <p class="text-sm text-neutral-500 leading-relaxed">
                        Tiap kali 1 cup *Café Latte* terjual di kasir, sistem langsung memotong **200ml Susu** dan **18g Biji Kopi** di database gudang dapur. HPP terhitung presisi, fraud staf langsung kelihatan.
                    </p>
                </div>
                <!-- Solusi 2 -->
                <div class="space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-neutral-950 text-white flex items-center justify-center font-bold text-sm">02</div>
                    <h3 class="text-lg font-bold text-neutral-950">Omnichannel Order Sync</h3>
                    <p class="text-sm text-neutral-500 leading-relaxed">
                        Gak perlu banyak device tablet yang menumpuk di meja kasir. Pesanan dari GoFood, GrabFood, dan ShopeeFood masuk ke satu aplikasi *RestoFlow* yang sama. Printer dapur otomatis mencetak pesanan sesuai jenis kanal.
                    </p>
                </div>
                <!-- Solusi 3 -->
                <div class="space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-neutral-950 text-white flex items-center justify-center font-bold text-sm">03</div>
                    <h3 class="text-lg font-bold text-neutral-950">QR-Order Mandiri Tanpa Ribet App</h3>
                    <p class="text-sm text-neutral-500 leading-relaxed">
                        Tamu cukup scan QR code di meja, pilih menu lewat browser HP, dan bayar via QRIS/E-Wallet. Pesanan langsung masuk ke sistem kasir dan KDS (*Kitchen Display System*) tanpa perlu diproses manual oleh pelayan.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. INTERACTIVE ECOSYSTEM PREVIEW -->
    <section id="fitur" class="py-24 bg-neutral-50" x-data="{ activeFeature: 'kds' }">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid lg:grid-cols-5 gap-12 items-center">
                
                <!-- Tab Controls -->
                <div class="lg:col-span-2 space-y-3">
                    <h2 class="text-3xl font-bold text-neutral-950 tracking-tight mb-6">Kelola Multi-Outlet dalam Satu Genggaman Dashboard</h2>
                    
                    <button @click="activeFeature = 'kds'" :class="activeFeature === 'kds' ? 'bg-white border-neutral-200 text-neutral-950 shadow-sm' : 'border-transparent text-neutral-500'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <div class="text-sm font-semibold">Kitchen Display System (KDS)</div>
                    </button>

                    <button @click="activeFeature = 'recipe'" :class="activeFeature === 'recipe' ? 'bg-white border-neutral-200 text-neutral-950 shadow-sm' : 'border-transparent text-neutral-500'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <div class="text-sm font-semibold">Manajemen Resep & Margin HPP</div>
                    </button>

                    <button @click="activeFeature = 'cash'" :class="activeFeature === 'cash' ? 'bg-white border-neutral-200 text-neutral-950 shadow-sm' : 'border-transparent text-neutral-500'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <div class="text-sm font-semibold">Laporan Arus Kas & Multi-Outlet</div>
                    </button>
                </div>

                <!-- Display Visual Container -->
                <div class="lg:col-span-3 bg-white p-6 rounded-2xl border border-neutral-200 shadow-xl h-80 flex items-center justify-center text-neutral-400">
                    <div x-show="activeFeature === 'kds'" class="text-center" x-transition>
                        <p class="font-bold text-neutral-800 text-base mb-1">[Mockup UI: Layar Digital Dapur KDS]</p>
                        <p class="text-xs text-neutral-500 max-w-sm mx-auto mt-2">Tampilan monitor khusus koki untuk tracking durasi pengerjaan makanan. Berubah warna menjadi merah otomatis jika orderan melampaui batas SLA penyiapan 15 menit.</p>
                    </div>
                    <div x-show="activeFeature === 'recipe'" class="text-center" x-transition style="display: none;">
                        <p class="font-bold text-neutral-800 text-base mb-1">[Mockup UI: Formula Editor & Perhitungan Margin Profit]</p>
                        <p class="text-xs text-neutral-500 max-w-sm mx-auto mt-2">Input formula bahan baku pokok. Sistem akan otomatis menghitung rekomendasi harga jual minimal berdasarkan fluktuasi harga komoditas pasar ter-update.</p>
                    </div>
                    <div x-show="activeFeature === 'cash'" class="text-center" x-transition style="display: none;">
                        <p class="font-bold text-neutral-800 text-base mb-1">[Mockup UI: Konsolidasi Finansial Lintas Outlet]</p>
                        <p class="text-xs text-neutral-500 max-w-sm mx-auto mt-2">Grafik performa perbandingan revenue harian antara Outlet Cabang A, Cabang B, dan Cabang C lengkap dengan laporan laba-rugi bersih pasca dipotong pajak PB1.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- 5. PRICING STRUCTURE -->
    <section id="pricing" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold text-neutral-950 tracking-tight">Skema Paket Fleksibel Tanpa Biaya Tersembunyi</h2>
                <p class="mt-4 text-neutral-500">Pilih skema harga yang cocok dengan volume transaksi outlet Anda saat ini.</p>
            </div>

            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto items-stretch">
                <!-- Starter Tier -->
                <div class="bg-neutral-50 p-8 rounded-2xl border border-neutral-200/80 flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-bold text-neutral-400 uppercase tracking-wider block">Scale Up Cafe</span>
                        <h4 class="text-xl font-bold text-neutral-950 mt-1">Single Outlet Plan</h4>
                        <p class="mt-2 text-xs text-neutral-500">Cocok untuk kedai kopi independen, cloud kitchen, atau bisnis food truck lokal.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-neutral-950">Rp 299k</span>
                            <span class="text-sm text-neutral-400">/bulan</span>
                        </div>
                        <ul class="mt-8 space-y-3.5 text-sm text-neutral-600 border-t border-neutral-200/60 pt-6">
                            <li class="flex items-center gap-2">✓ Aplikasi Kasir POS Android / iOS / Web</li>
                            <li class="flex items-center gap-2">✓ Integrasi Semua Pembayaran Digital (QRIS)</li>
                            <li class="flex items-center gap-2">✓ Kelola Manajemen Inventaris Stok Dasar</li>
                            <li class="flex items-center gap-2 text-neutral-300">✗ Sinkronisasi Menu Agregator Online</li>
                        </ul>
                    </div>
                    <a href="{{ route('central.register.create') }}" class="mt-8 block text-center w-full py-3 px-4 bg-white border border-neutral-200 hover:bg-neutral-50 text-neutral-950 rounded-xl font-medium text-sm transition">Mulai Uji Coba</a>
                </div>

                <!-- Pro Tier -->
                <div class="bg-neutral-950 p-8 rounded-2xl border border-neutral-900 flex flex-col justify-between text-white relative shadow-xl">
                    <span class="absolute top-0 right-6 transform -translate-y-1/2 bg-amber-500 text-neutral-950 text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Rekomendasi F&B Owner</span>
                    <div>
                        <span class="text-xs font-bold text-neutral-500 uppercase tracking-wider block">Enterprise Growth</span>
                        <h4 class="text-xl font-bold mt-1">Multi-Outlet & Franchise</h4>
                        <p class="mt-2 text-xs text-neutral-400">Pilihan tepat untuk manajemen restoran skala besar dengan rantai pasok kompleks.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-3xl font-bold">Rp 650k</span>
                            <span class="text-sm text-neutral-500">/bulan /outlet</span>
                        </div>
                        <ul class="mt-8 space-y-3.5 text-sm text-neutral-300 border-t border-neutral-800 pt-6">
                            <li class="flex items-center gap-2">✓ Semua Fitur Single Outlet Plan</li>
                            <li class="flex items-center gap-2">✓ Pengontrolan Komposisi Resep Tingkat HPP</li>
                            <li class="flex items-center gap-2">✓ Agregator GrabFood / GoFood Terpusat</li>
                            <li class="flex items-center gap-2">✓ Fitur Transfer Stok Antar Gudang / Cabang</li>
                        </ul>
                    </div>
                    <a href="{{ route('central.register.create') }}" class="mt-8 block text-center w-full py-3 px-4 bg-white hover:bg-neutral-100 text-neutral-950 rounded-xl font-medium text-sm transition shadow-md">Hubungi Sales Tim</a>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. CLOSING CALL-TO-ACTION & FOOTER -->
    <footer id="demo" class="bg-neutral-950 text-white pt-24 pb-12 border-t border-neutral-900">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-bold tracking-tight">Hentikan Kebocoran Stok di Dapur Mulai Hari Ini</h2>
            <p class="mt-4 text-neutral-400 text-sm md:text-base max-w-lg mx-auto">Butuh kustomisasi modul khusus atau integrasi mesin kasir hardware yang sudah Anda miliki? Tim kami siap membantu konfigurasi langsung di lokasi.</p>
            
            <div class="mt-10">
                <a href="{{ route('central.register.create') }}" class="inline-flex items-center gap-2 bg-white text-neutral-950 px-8 py-4 rounded-xl font-semibold hover:bg-neutral-100 transition shadow-lg text-sm">
                    Konsultasikan Bersama F&B Expert Kami
                </a>
            </div>
            
            <div class="mt-20 pt-8 border-t border-neutral-900 text-xs text-neutral-600 flex flex-col sm:flex-row items-center justify-between gap-4">
                <span>&copy; 2026 {{ $displayBrand }}. Seluruh Hak Cipta Dilindungi.</span>
                <div class="flex gap-6">
                    <a href="{{ route('central.login') }}" class="hover:text-neutral-400">Masuk Panel Pusat</a>
                    <a href="{{ route('central.register.create') }}" class="hover:text-neutral-400">Ajukan Demo</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
