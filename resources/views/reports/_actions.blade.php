{{-- Filter, Print, and All Reports action buttons --}}
<div class="form-actions report-actions" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
    @if ($show ?? true)
        <button type="submit" class="btn" style="min-height: 44px; display: inline-flex; align-items: center; gap: 6px;">
            <x-icon name="filter" size="16" />
            <span>Filter</span>
        </button>
    @endif
    <button type="button" class="btn sec print-report-btn" onclick="window.print()" style="min-height: 44px; display: inline-flex; align-items: center; gap: 6px;">
        <x-icon name="printer" size="16" />
        <span>Print Report</span>
    </button>
    <a href="{{ route('reports.index') }}" class="btn sec" style="min-height: 44px; display: inline-flex; align-items: center; gap: 6px;">
        <x-icon name="arrow-left" size="16" />
        <span>All Reports</span>
    </a>
</div>
