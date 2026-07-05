@extends('central.layouts.master')

@section('page_title', 'Activity Logs')
@section('page_subtitle', 'Audit trail dan error operasional pusat')

@section('content')
    <div class="page-grid">
        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-clipboard-list"></i> Audit Trail</span>
                <h2>Activity Logs</h2>
                <p>Pantau aksi sensitif seperti update settings, convert lead, payment update, dan error operasional dari satu workspace.</p>
            </div>
        </section>

        <section class="dashboard-card">
            <form method="GET" class="inline-actions" style="align-items: end;">
                <div style="max-width: 220px;">
                    <label class="field-label" for="log-level">Level</label>
                    <select id="log-level" name="level">
                        <option value="">Semua</option>
                        @foreach (['info', 'warning', 'error'] as $item)
                            <option value="{{ $item }}" @selected($level === $item)>{{ strtoupper($item) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="central-btn-secondary" type="submit">Filter</button>
            </form>
        </section>

        <section class="dashboard-card">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Level</th>
                            <th>Event</th>
                            <th>Summary</th>
                            <th>Actor</th>
                            <th>Target</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                                <td><span class="{{ $log->level === 'error' ? 'status-muted' : ($log->level === 'warning' ? 'status-pending' : 'status-active') }}">{{ strtoupper($log->level) }}</span></td>
                                <td>{{ $log->event_key }}</td>
                                <td>{{ $log->summary }}</td>
                                <td>{{ $log->actor_email ?: '-' }}</td>
                                <td>{{ $log->target_type && $log->target_id ? $log->target_type . ':' . $log->target_id : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">Belum ada activity log.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 16px;">
                {{ $logs->links() }}
            </div>
        </section>
    </div>
@endsection
