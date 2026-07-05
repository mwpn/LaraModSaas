<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice['invoice_number'] }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    @php
        $manualTransfer = $paymentSettings['manual_transfer'] ?? [];
        $manualTransferPayment = (array) data_get($invoice, 'payment.manual_transfer', []);
        $qris = $paymentSettings['qris'] ?? [];
        $qrisContent = (string) data_get($invoice, 'payment.qris.content', '');
        $paymentStatus = strtoupper((string) data_get($invoice, 'payment.status', $invoice['status']));
        $expectedTransferAmount = (int) data_get($manualTransferPayment, 'expected_amount', $invoice['invoice_total']);
        $baseTransferAmount = (int) data_get($manualTransferPayment, 'base_amount', $invoice['invoice_total']);
        $uniqueTransferCode = (int) data_get($manualTransferPayment, 'unique_code', 0);
        $manualEvidenceMessageId = (string) data_get($manualTransferPayment, 'evidence.message_id', '');
    @endphp

    <div class="mx-auto max-w-5xl px-4 py-10">
        <div class="grid gap-6 lg:grid-cols-[1.4fr,0.9fr]">
            <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-slate-950/30">
                @if (session('status'))
                    <div class="mb-4 rounded-2xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Public Payment Page</p>
                        <h1 class="mt-2 text-3xl font-semibold">{{ $tenant->name }}</h1>
                        <p class="mt-2 text-sm text-slate-300">Invoice {{ $invoice['invoice_number'] }} untuk periode {{ $invoice['period_label'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-sky-400/30 bg-sky-500/10 px-4 py-3 text-right">
                        <div class="text-xs uppercase tracking-[0.3em] text-sky-200">Status</div>
                        <div class="mt-1 text-xl font-semibold">{{ strtoupper($invoice['status']) }}</div>
                        <div class="text-sm text-sky-100">{{ $paymentStatus }}</div>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-slate-900/50 p-4">
                        <div class="text-xs uppercase tracking-[0.3em] text-slate-500">Total</div>
                        <div class="mt-2 text-2xl font-semibold">Rp {{ number_format((int) $invoice['invoice_total'], 0, ',', '.') }}</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-slate-900/50 p-4">
                        <div class="text-xs uppercase tracking-[0.3em] text-slate-500">Jatuh Tempo</div>
                        <div class="mt-2 text-lg font-semibold">{{ optional($invoice['due_at'])->format('d M Y H:i') ?? '-' }}</div>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-slate-900/50 p-4">
                        <div class="text-xs uppercase tracking-[0.3em] text-slate-500">Tenant ID</div>
                        <div class="mt-2 text-lg font-semibold">{{ $tenant->id }}</div>
                    </div>
                </div>

                @if (!empty($manualTransfer['enabled']) && $expectedTransferAmount > 0)
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-amber-400/20 bg-amber-500/10 p-4">
                            <div class="text-xs uppercase tracking-[0.3em] text-amber-200/80">Base Amount</div>
                            <div class="mt-2 text-xl font-semibold">Rp {{ number_format($baseTransferAmount, 0, ',', '.') }}</div>
                        </div>
                        <div class="rounded-2xl border border-amber-400/20 bg-amber-500/10 p-4">
                            <div class="text-xs uppercase tracking-[0.3em] text-amber-200/80">Kode Unik</div>
                            <div class="mt-2 text-xl font-semibold">{{ str_pad((string) $uniqueTransferCode, 3, '0', STR_PAD_LEFT) }}</div>
                        </div>
                        <div class="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4">
                            <div class="text-xs uppercase tracking-[0.3em] text-emerald-200/80">Transfer Exact</div>
                            <div class="mt-2 text-2xl font-semibold">Rp {{ number_format($expectedTransferAmount, 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endif

                @if ($publicPaymentNote !== '')
                    <div class="mt-6 rounded-2xl border border-white/10 bg-slate-900/60 p-4 text-sm leading-6 text-slate-200">
                        {{ $publicPaymentNote }}
                    </div>
                @endif

                <div class="mt-6 rounded-3xl border border-white/10 bg-slate-900/40 p-5">
                    <h2 class="text-lg font-semibold">Instruksi Pembayaran</h2>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold">QRIS</h3>
                                    <p class="text-sm text-slate-400">Buat atau refresh QRIS langsung dari halaman ini.</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs {{ !empty($qris['enabled']) ? 'bg-emerald-500/15 text-emerald-200' : 'bg-slate-700 text-slate-300' }}">
                                    {{ !empty($qris['enabled']) ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>

                            @if (!empty($qris['enabled']))
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <form method="POST" action="{{ route('central.public-invoice.create-qris', [$tenant->id, $invoice['invoice_number']]) }}">
                                        @csrf
                                        <button class="rounded-2xl bg-sky-500 px-4 py-2 text-sm font-medium text-white hover:bg-sky-400" type="submit">Buat QRIS</button>
                                    </form>

                                    <form method="POST" action="{{ route('central.public-invoice.check-qris-status', [$tenant->id, $invoice['invoice_number']]) }}">
                                        @csrf
                                        <button class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-slate-100 hover:bg-white/10" type="submit">Cek Status</button>
                                    </form>
                                </div>

                                <div class="mt-4 rounded-2xl border border-dashed border-white/10 bg-slate-950/50 p-4">
                                    <div class="text-xs uppercase tracking-[0.3em] text-slate-500">QRIS Payload</div>
                                    <pre class="mt-3 whitespace-pre-wrap break-all text-sm text-slate-200">{{ $qrisContent !== '' ? $qrisContent : 'Belum ada QRIS aktif untuk invoice ini.' }}</pre>
                                </div>
                            @endif
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold">Transfer Manual</h3>
                                    <p class="text-sm text-slate-400">Transfer harus exact sesuai nominal unik di bawah supaya bisa auto-match.</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs {{ !empty($manualTransfer['enabled']) ? 'bg-emerald-500/15 text-emerald-200' : 'bg-slate-700 text-slate-300' }}">
                                    {{ !empty($manualTransfer['enabled']) ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>

                            @if ($expectedTransferAmount > 0)
                                <div class="mt-4 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4">
                                    <div class="text-xs uppercase tracking-[0.3em] text-emerald-200/80">Nominal Transfer Tepat</div>
                                    <div class="mt-2 text-3xl font-semibold text-emerald-100">Rp {{ number_format($expectedTransferAmount, 0, ',', '.') }}</div>
                                    <div class="mt-2 text-sm text-emerald-50/90">Base Rp {{ number_format($baseTransferAmount, 0, ',', '.') }} + kode unik {{ str_pad((string) $uniqueTransferCode, 3, '0', STR_PAD_LEFT) }}</div>
                                </div>
                            @endif

                            <div class="mt-4 space-y-3 text-sm text-slate-200">
                                <div>
                                    <div class="text-slate-500">Bank</div>
                                    <div>{{ data_get($manualTransfer, 'bank_name', '-') }}</div>
                                </div>
                                <div>
                                    <div class="text-slate-500">Atas Nama</div>
                                    <div>{{ data_get($manualTransfer, 'account_name', '-') }}</div>
                                </div>
                                <div>
                                    <div class="text-slate-500">Nomor Rekening</div>
                                    <div>{{ data_get($manualTransfer, 'account_number', '-') }}</div>
                                </div>
                                <div>
                                    <div class="text-slate-500">Catatan</div>
                                    <div>{{ data_get($manualTransfer, 'notes', '-') }}</div>
                                </div>
                                @if ($manualEvidenceMessageId !== '')
                                    <div>
                                        <div class="text-slate-500">Evidence Matched</div>
                                        <div>{{ $manualEvidenceMessageId }}</div>
                                    </div>
                                @endif
                            </div>

                            @if (!empty($manualTransfer['enabled']) && !in_array((string) ($invoice['status'] ?? 'issued'), ['paid', 'void'], true))
                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <form method="POST" action="{{ route('central.public-invoice.confirm-manual-transfer', [$tenant->id, $invoice['invoice_number']]) }}">
                                        @csrf
                                        <button class="rounded-2xl bg-emerald-500 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-400" type="submit">
                                            Saya Sudah Transfer, Cek Otomatis
                                        </button>
                                    </form>
                                    <span class="text-xs text-slate-400">Klik ini setelah transfer. Sistem langsung scan inbox BCA dan cari nominal exact Rp {{ number_format($expectedTransferAmount, 0, ',', '.') }}.</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
                    <h2 class="text-lg font-semibold">Ringkasan</h2>
                    <div class="mt-4 space-y-4 text-sm text-slate-200">
                        <div>
                            <div class="text-slate-500">Invoice Number</div>
                            <div>{{ $invoice['invoice_number'] }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500">Metode Aktif</div>
                            <div>{{ !empty($qris['enabled']) ? 'QRIS' : 'QRIS off' }} / {{ !empty($manualTransfer['enabled']) ? 'Transfer Manual' : 'Transfer off' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500">Status Bayar</div>
                            <div>{{ $paymentStatus }}</div>
                        </div>
                        @if ($expectedTransferAmount > 0)
                            <div>
                                <div class="text-slate-500">Exact Transfer</div>
                                <div>Rp {{ number_format($expectedTransferAmount, 0, ',', '.') }}</div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="rounded-3xl border border-white/10 bg-white/5 p-6 text-sm text-slate-300">
                    <h2 class="text-lg font-semibold text-slate-100">Butuh Bantuan?</h2>
                    <p class="mt-3">Kalau status belum berubah setelah bayar, hubungi admin platform dan sebutkan nomor invoice ini agar proses verifikasi lebih cepat.</p>
                </section>
            </aside>
        </div>
    </div>
</body>
</html>
