{{-- Report heading (screen and print). Needs: $title, $period (text) --}}
<div class="statement-head report-head">
    <div><b>Narciana House Space Rental</b><br><span class="muted">Km. 6 Carnation St., Buhangin, Davao City</span></div>
    <div class="r"><b>{{ $title }}</b><br><span class="muted">{{ $period }}</span>
        <span class="sub">Generated {{ now()->format('j M Y, g:i A') }} by {{ auth()->user()->role }}</span></div>
</div>
