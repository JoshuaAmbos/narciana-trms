{{-- "Show" (submit the filter form), "Print" and "All reports" buttons at the end of a report's filter form. --}}
<div class="form-actions report-actions">
    @if ($show ?? true)<button type="submit" class="btn">Show</button>@endif
    <button type="button" class="btn sec" onclick="window.print()">Print</button>
    <a href="{{ route('reports.index') }}" class="btn sec">All reports</a>
</div>
