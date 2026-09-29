{{-- Simple "Previous / Next" pager in the app's own style. Needs: $paginator (LengthAwarePaginator) --}}
@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pages">
        @if ($paginator->onFirstPage())
            <span class="btn sec disabled">‹ Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn sec">‹ Previous</a>
        @endif
        <span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} ({{ $paginator->total() }} in all)</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn sec">Next ›</a>
        @else
            <span class="btn sec disabled">Next ›</span>
        @endif
    </nav>
@endif
