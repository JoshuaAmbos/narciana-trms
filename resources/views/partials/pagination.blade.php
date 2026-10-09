{{-- Simple "Previous / Next" pager in the app's own style. Needs: $paginator (LengthAwarePaginator) --}}
@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="btn sec disabled pager-btn" aria-disabled="true">‹ Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn sec pager-btn" rel="prev">‹ Previous</a>
        @endif
        <span class="muted pager-info">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} ({{ $paginator->total() }} in all)</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn sec pager-btn" rel="next">Next ›</a>
        @else
            <span class="btn sec disabled pager-btn" aria-disabled="true">Next ›</span>
        @endif
    </nav>
@endif
