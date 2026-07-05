<footer class="footer-shell">
    <div class="footer-inner">
        <small>&copy; {{ date('Y') }} {{ $tenantSetting->brand_name ?? tenant('name') ?? tenant('id') }}</small>
        <small>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }} Workspace</small>
    </div>
</footer>
