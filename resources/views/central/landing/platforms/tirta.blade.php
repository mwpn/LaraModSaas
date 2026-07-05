<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $landingBrand = trim((string) data_get($platformExperience ?? [], 'brand_name', ''));
        $landingHeadline = trim((string) data_get($platformExperience ?? [], 'headline', ''));
        $displayBrand = $landingBrand !== '' ? $landingBrand : 'Aqualytic';
        $brandInitial = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $displayBrand) ?: 'A', 0, 1));
        $pageTitle = $landingHeadline !== ''
            ? $landingHeadline . ' | ' . $displayBrand
            : $displayBrand;
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f4f7f6; /* Soft cool mint/light gray tint */
        }
    </style>
</head>
<body x-data="{ mobileMenuOpen: false }">

    <nav class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200/60">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <a href="{{ route('central.home') }}" class="flex items-center gap-2">
                <div class="w-9 h-9 bg-cyan-900 rounded-xl flex items-center justify-center text-white font-bold text-lg">{{ $brandInitial }}</div>
                <span class="text-xl font-bold text-cyan-950 tracking-tight">{{ $displayBrand }}</span>
            </a>
            
            <div class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
                <a href="#solusi" class="hover:text-cyan-950 transition">Masalah Lapangan</a>
                <a href="#fitur" class="hover:text-cyan-950 transition">Fitur Utama</a>
                <a href="#pricing" class="hover:text-cyan-950 transition">Paket & Skala</a>
            </div>

            <div class="hidden md:flex items-center gap-4">
                <a href="{{ route('central.register.create') }}" class="bg-cyan-900 hover:bg-cyan-950 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition shadow-sm">
                    Ajukan Demo Sistem
                </a>
            </div>

            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>

        <div x-show="mobileMenuOpen" x-transition class="md:hidden border-b border-slate-200 bg-white px-6 py-4 space-y-3 absolute w-full left-0">
            <a href="#solusi" class="block text-slate-600 font-medium py-1">Masalah Lapangan</a>
            <a href="#fitur" class="block text-slate-600 font-medium py-1">Fitur Utama</a>
            <a href="#pricing" class="block text-slate-600 font-medium py-1">Paket & Skala</a>
            <a href="{{ route('central.register.create') }}" class="block bg-cyan-900 text-white text-center py-2.5 rounded-xl text-sm font-medium">Ajukan Demo Sistem</a>
        </div>
    </nav>

    <section class="relative pt-16 pb-24 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-cyan-50 text-cyan-800 mb-6 border border-cyan-100">
                <span class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse"></span> Sistem Terintegrasi Pamsimas, KPSPAM, & Perumda
            </span>
            
            <h1 class="text-4xl md:text-6xl font-bold text-slate-900 tracking-tight max-w-4xl mx-auto leading-[1.15]">
                {{ $landingHeadline !== '' ? $landingHeadline : 'Catat Meter Air Digital, Tagihan Terkirim via WA, Bebas Tunggakan' }}
            </h1>
            
            <p class="mt-6 text-base md:text-xl text-slate-500 max-w-2xl mx-auto leading-relaxed">
                Tinggalkan pencatatan kertas yang rentan manipulasi. Mudahkan warga membayar tagihan air secara mandiri lewat loket digital harian.
            </p>

            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('central.register.create') }}" class="w-full sm:w-auto bg-cyan-900 hover:bg-cyan-950 text-white px-8 py-4 rounded-xl font-medium transition shadow-lg shadow-cyan-950/10 text-center">
                    Coba Demo Catat Meter Gratis
                </a>
                <a href="#solusi" class="w-full sm:w-auto flex items-center justify-center gap-2 text-slate-700 hover:text-cyan-950 px-6 py-4 rounded-xl font-medium transition border border-slate-200 bg-white">
                    Lihat Fitur Alur Pembayaran
                </a>
            </div>

            <div class="mt-16 relative mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl shadow-slate-200/40">
                <div class="rounded-xl border border-slate-100 bg-slate-50 overflow-hidden aspect-[16/9] flex flex-col text-left">
                    <div class="bg-white border-b border-slate-100 px-4 py-3 flex items-center justify-between">
                        <div class="flex gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-slate-200"></span>
                            <span class="w-3 h-3 rounded-full bg-slate-200"></span>
                            <span class="w-3 h-3 rounded-full bg-slate-200"></span>
                        </div>
                        <div class="bg-slate-50 text-[11px] text-slate-400 px-3 py-1 rounded-md w-64 mx-auto text-left truncate">
                            admin.aqualytic.id/billing/summary
                        </div>
                        <span class="text-xs bg-cyan-50 text-cyan-700 px-2 py-0.5 rounded font-medium border border-cyan-100">Bulan Operasional: Juli 2026</span>
                    </div>
                    <div class="p-6 flex-1 grid grid-cols-3 gap-4">
                        <div class="bg-white p-5 rounded-xl border border-slate-100 flex flex-col justify-between shadow-xs">
                            <div>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Tagihan Terbit</span>
                                <h3 class="text-2xl font-bold text-slate-800 mt-2">Rp 48.250.000</h3>
                                <p class="text-[11px] text-slate-400 mt-1">Dari total 1,240 Sambungan Rumah (SR)</p>
                            </div>
                            <div class="pt-3 border-t border-slate-100 text-[11px] text-emerald-600 flex items-center gap-1">
                                <span>92% Sukses Terkirim WhatsApp</span>
                            </div>
                        </div>
                        <div class="bg-white p-5 rounded-xl border border-slate-100 flex flex-col justify-between shadow-xs">
                            <div>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Sudah Terbayar (Bulan Ini)</span>
                                <h3 class="text-2xl font-bold text-emerald-600 mt-2">Rp 39.120.000</h3>
                                <p class="text-[11px] text-slate-400 mt-1">Sebagian besar via QRIS & Transfer Bank</p>
                            </div>
                            <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500">
                                Sisa piutang berjalan: Rp 9.130.000
                            </div>
                        </div>
                        <div class="bg-amber-50/40 p-5 rounded-xl border border-amber-100 flex flex-col justify-between">
                            <div>
                                <span class="text-xs font-bold text-amber-800 uppercase tracking-wider block">Anomali Pemakaian (Indikasi Bocor)</span>
                                <div class="mt-3 space-y-2 text-xs">
                                    <div class="p-2 bg-white rounded border border-amber-100">
                                        <div class="flex justify-between font-medium text-slate-800"><span>SR-0482 (Bpk. Slamet)</span> <span class="text-red-600">+140%</span></div>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Lonjakan: 12m³ menjadi 29m³ bulan ini.</p>
                                    </div>
                                    <div class="p-2 bg-white rounded border border-amber-100">
                                        <div class="flex justify-between font-medium text-slate-800"><span>SR-1022 (Ibu Aminah)</span> <span class="text-red-600">+85%</span></div>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Potensi pipa pecah paska meteran warga.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="solusi" class="py-24 bg-white border-y border-slate-200/60">
        <div class="max-w-7xl mx-auto px-6">
            <div class="max-w-3xl mb-16">
                <h2 class="text-3xl font-bold text-slate-900 tracking-tight">Menyudahi Kerugian Klasik Pengelolaan Air Komunitas</h2>
                <p class="mt-4 text-slate-500">Mengapa cara lama catat buku manual selalu menyisakan masalah piutang bengkak dan kebocoran volume air?</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100">
                    <div class="w-10 h-10 rounded-xl bg-cyan-900 text-white flex items-center justify-center font-bold text-sm mb-6">01</div>
                    <h3 class="text-lg font-bold text-slate-900">Petugas Cukup Bawa HP Ke Rumah Warga</h3>
                    <p class="text-sm text-slate-500 leading-relaxed mt-2">
                        Tidak perlu lagi mencatat di lembaran kertas lalu diinput ulang ke Excel kantor. Melalui aplikasi Android khusus, petugas lapangan cukup ketik angka meteran terbaru, foto physical meteran sebagai bukti otentik, dan simpan. 
                    </p>
                </div>
                <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100">
                    <div class="w-10 h-10 rounded-xl bg-cyan-900 text-white flex items-center justify-center font-bold text-sm mb-6">02</div>
                    <h3 class="text-lg font-bold text-slate-900">Invoice WhatsApp Otomatis Tanpa Print Kertas</h3>
                    <p class="text-sm text-slate-500 leading-relaxed mt-2">
                        Begitu data meteran di-submit petugas lapangan, detik itu juga sistem otomatis menembak rincian tagihan kubikasi beserta link pembayaran digital ke WhatsApp kepala keluarga pelanggan. Hemat biaya cetak struk bulanan.
                    </p>
                </div>
                <div class="p-6 rounded-2xl bg-slate-50 border border-slate-100">
                    <div class="w-10 h-10 rounded-xl bg-cyan-900 text-white flex items-center justify-center font-bold text-sm mb-6">03</div>
                    <h3 class="text-lg font-bold text-slate-900">Deteksi Air Hilang (Water Losses / NRW Management)</h3>
                    <p class="text-sm text-slate-500 leading-relaxed mt-2">
                        Bandingkan volume air keluar dari pompa utama (Meter Induk) dengan akumulasi pemakaian di meteran rumah warga. Ketahui secara pasti persentase air bocor atau pencurian jalur ilegal sebelum merugikan kas kas organisasi.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section id="fitur" class="py-24 bg-slate-50" x-data="{ activeFeature: 'billing' }">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid lg:grid-cols-5 gap-12 items-center">
                
                <div class="lg:col-span-2 space-y-3">
                    <h2 class="text-3xl font-bold text-slate-900 tracking-tight mb-6">Dirancang Fleksibel Mengikuti Regulasi Tarif Lokal</h2>
                    
                    <button @click="activeFeature = 'billing'" :class="activeFeature === 'billing' ? 'bg-white border-slate-200 text-cyan-950 shadow-sm' : 'border-transparent text-slate-500'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <div class="text-sm font-semibold">Skema Tarif Bertingkat (Progresif)</div>
                    </button>

                    <button @click="activeFeature = 'payment'" :class="activeFeature === 'payment' ? 'bg-white border-slate-200 text-cyan-950 shadow-sm' : 'border-transparent text-slate-500'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <div class="text-sm font-semibold">Integrasi Loket Pembayaran Desa & PPOB</div>
                    </button>

                    <button @click="activeFeature = 'denda'" :class="activeFeature === 'denda' ? 'bg-white border-slate-200 text-cyan-950 shadow-sm' : 'border-transparent text-slate-500'" class="w-full text-left p-5 rounded-xl border flex gap-4 transition items-start">
                        <div class="text-sm font-semibold">Manajemen Denda Keterlambatan Otomatis</div>
                    </button>
                </div>

                <div class="lg:col-span-3 bg-white p-6 rounded-2xl border border-slate-200 shadow-xl h-80 flex items-center justify-center text-slate-400">
                    <div x-show="activeFeature === 'billing'" class="text-center" x-transition>
                        <p class="font-bold text-slate-800 text-base mb-1">[Mockup UI: Pengaturan Multi-Tarif Progresif]</p>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-2">Bebas atur tarif air per golongan: misal Golongan Rumah Tangga Rp2.000/m³ (0-10m³) dan naik menjadi Rp3.500/m³ jika pemakaian di atas 10m³, lengkap dengan biaya beban administrasi tetap.</p>
                    </div>
                    <div x-show="activeFeature === 'payment'" class="text-center" x-transition style="display: none;">
                        <p class="font-bold text-slate-800 text-base mb-1">[Mockup UI: Gateway Kasir Finansial & QRIS Terpusat]</p>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-2">Tampilan bagi admin kantor desa/loket fisik untuk menerima setoran tunai warga. Otomatis terintegrasi dengan penarikan dana non-tunai via Bank transfer, VA, Alfamart/Indomaret terdekat.</p>
                    </div>
                    <div x-show="activeFeature === 'denda'" class="text-center" x-transition style="display: none;">
                        <p class="font-bold text-slate-800 text-base mb-1">[Mockup UI: Aturan Otomasi Denda & Blokir Sambungan]</p>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-2">Sistem otomatis menambahkan denda (misal Rp5.000 per tanggal 20) atau mengubah status pelanggan menjadi 'Tunggakan Kritis' yang otomatis masuk ke daftar penertiban pipa lapangan.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section id="pricing" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold text-slate-900 tracking-tight">Investasi Berdasarkan Kapasitas Pengguna</h2>
                <p class="mt-4 text-slate-500">Biaya operasional transparan tanpa ada batas maksimal jumlah petugas lapangan Anda.</p>
            </div>

            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto items-stretch">
                <div class="bg-slate-50 p-8 rounded-2xl border border-slate-200/80 flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Skala Pengelola Desa</span>
                        <h4 class="text-xl font-bold text-slate-900 mt-1">KPSPAM / Pamsimas Pro</h4>
                        <p class="mt-2 text-xs text-slate-500">Cocok untuk pengelolaan air tingkat RW, Dusun, atau Bumdes dengan kapasitas sambungan terukur.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-slate-900">Rp 350k</span>
                            <span class="text-sm text-slate-400">/bulan</span>
                        </div>
                        <ul class="mt-8 space-y-3.5 text-sm text-slate-600 border-t border-slate-200/60 pt-6">
                            <li class="flex items-center gap-2">✓ Maksimal 1.500 Sambungan Rumah (SR)</li>
                            <li class="flex items-center gap-2">✓ Aplikasi Android Input Meter Mandiri</li>
                            <li class="flex items-center gap-2">✓ Integrasi Tagihan Pembayaran WA</li>
                            <li class="flex items-center gap-2 text-slate-300">✗ Modul Analisis Kebocoran Air (Meter Induk)</li>
                        </ul>
                    </div>
                    <a href="{{ route('central.register.create') }}" class="mt-8 block text-center w-full py-3 px-4 bg-white border border-slate-200 hover:bg-slate-50 text-slate-950 rounded-xl font-medium text-sm transition">Mulai Coba Sistem</a>
                </div>

                <div class="bg-cyan-950 p-8 rounded-2xl border border-cyan-900 flex flex-col justify-between text-white relative shadow-xl">
                    <span class="absolute top-0 right-6 transform -translate-y-1/2 bg-amber-500 text-neutral-950 text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md">Pilihan Perumda / BUMDES Besar</span>
                    <div>
                        <span class="text-xs font-bold text-cyan-400 uppercase tracking-wider block">Komersial & Regional</span>
                        <h4 class="text-xl font-bold mt-1">Multi-Wilayah & Korporasi</h4>
                        <p class="mt-2 text-xs text-cyan-200">Kapasitas tinggi untuk wilayah cakupan luas dengan dukungan data rekayasa teknik tingkat lanjut.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-3xl font-bold">Custom</span>
                            <span class="text-sm text-cyan-400">/Kontrak Tahunan</span>
                        </div>
                        <ul class="mt-8 space-y-3.5 text-sm text-cyan-200 border-t border-cyan-900 pt-6">
                            <li class="flex items-center gap-2">✓ Sambungan Rumah (SR) Tanpa Batas</li>
                            <li class="flex items-center gap-2">✓ Sinkronisasi Laporan Keuangan Antar Wilayah</li>
                            <li class="flex items-center gap-2">✓ Modul Analisis NRW (Bocor Pipa Induk)</li>
                            <li class="flex items-center gap-2">✓ Integrasi IoT Smart Water Meter Hardware (API)</li>
                        </ul>
                    </div>
                    <a href="{{ route('central.register.create') }}" class="mt-8 block text-center w-full py-3 px-4 bg-white hover:bg-slate-100 text-slate-950 rounded-xl font-medium text-sm transition shadow-md">Hubungi Account Executive</a>
                </div>
            </div>
        </div>
    </section>

    <footer id="demo" class="bg-slate-950 text-white pt-24 pb-12 border-t border-slate-900">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-bold tracking-tight">Modernisasi Sistem Pengelolaan Air Bersih Komunitas Anda</h2>
            <p class="mt-4 text-slate-400 text-sm md:text-base max-w-lg mx-auto">Kami menyediakan tim pendampingan migrasi database warga dari format Excel lama secara gratis hingga sistem siap operasional penuh.</p>
            
            <div class="mt-10">
                <a href="{{ route('central.register.create') }}" class="inline-flex items-center gap-2 bg-white text-slate-950 px-8 py-4 rounded-xl font-semibold hover:bg-slate-100 transition shadow-lg text-sm">
                    Jadwalkan Demo Uji Coba Lapangan
                </a>
            </div>
            
            <div class="mt-20 pt-8 border-t border-slate-900 text-xs text-slate-600 flex flex-col sm:flex-row items-center justify-between gap-4">
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
