@extends('central.layouts.master')

@section('page_title', 'Ops Health')
@section('page_subtitle', 'Pantau queue, payment, notification, automation, dan error log')

@section('content')
    <div class="page-grid">
        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-heart-pulse"></i> Health Snapshot</span>
                <h2>Operational Health</h2>
                <p>Panel cepat buat cek kesiapan queue, payment gateway, notifikasi, scheduler, dan error operasional terbaru.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Error 24 Jam</span>
                    <strong>{{ $errorCount24h }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Backup SOP</span>
                    <strong>Ready</strong>
                </div>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="card-head">
                <div>
                    <h3 class="card-title">Service Checks</h3>
                </div>
            </div>

            <div class="quick-grid">
                @foreach ($checks as $check)
                    <div class="quick-item">
                        <div>
                            <strong>{{ $check['label'] }}</strong>
                            <span>{{ $check['value'] }}</span>
                        </div>
                        <span class="{{ $check['status'] === 'ok' ? 'status-active' : 'status-pending' }}">{{ strtoupper($check['status']) }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="content-grid content-grid-2">
            <section class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Automation State</h3>
                    </div>
                </div>

                <div class="mini-list">
                    <div class="mini-row">
                        <span>Auto Generate Last Run</span>
                        <strong>{{ data_get($lastAutoGenerate, 'ran_at', '-') }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Auto Generate Source</span>
                        <strong>{{ data_get($lastAutoGenerate, 'source', '-') }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Reminder Last Run</span>
                        <strong>{{ data_get($lastReminderRun, 'ran_at', '-') }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Reminder Source</span>
                        <strong>{{ data_get($lastReminderRun, 'source', '-') }}</strong>
                    </div>
                </div>
            </section>

            <section class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Runbook</h3>
                    </div>
                </div>

                <div class="mini-list">
                    <div class="mini-row">
                        <span>Backup SOP</span>
                        <strong><a href="{{ route('central.super-admin.ops.backup-sop') }}">Buka Backup SOP</a></strong>
                    </div>
                    <div class="mini-row">
                        <span>Audit Logs</span>
                        <strong><a href="{{ route('central.super-admin.ops.logs') }}">Buka Activity Logs</a></strong>
                    </div>
                </div>
            </section>
        </div>

        <section class="dashboard-card">
            <div class="card-head">
                <div>
                    <h3 class="card-title">Recent Errors</h3>
                </div>
            </div>

            <div class="mini-list">
                @forelse ($recentErrors as $log)
                    <div class="mini-row" style="align-items: flex-start;">
                        <span>{{ $log->created_at?->format('d M Y H:i') }}</span>
                        <strong>{{ $log->summary }}</strong>
                    </div>
                @empty
                    <div class="mini-row">
                        <span>Error Log</span>
                        <strong>Belum ada error tercatat.</strong>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
