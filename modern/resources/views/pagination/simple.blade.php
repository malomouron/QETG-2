@if ($paginator->hasPages())
    <nav aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span>Précédent</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Précédent</a>
        @endif
        <span>Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant</a>
        @else
            <span>Suivant</span>
        @endif
    </nav>
@endif
