{{-- Switch between the two Owner pages under "Settings & Backup". --}}
<div class="filters">
    <a href="{{ route('settings.index') }}" class="chip {{ request()->routeIs('settings.*') ? 'on' : '' }}">Rates and rules</a>
    <a href="{{ route('backups.index') }}" class="chip {{ request()->routeIs('backups.*') ? 'on' : '' }}">Backup and restore</a>
</div>
