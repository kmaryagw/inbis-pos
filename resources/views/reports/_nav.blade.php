<ul class="nav nav-pills mb-3 no-print">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i class="bi bi-bar-chart me-1"></i>Laporan umum</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.events.*') ? 'active' : '' }}" href="{{ route('reports.events.index') }}"><i class="bi bi-calendar-check me-1"></i>Laporan per kegiatan</a></li>
</ul>
