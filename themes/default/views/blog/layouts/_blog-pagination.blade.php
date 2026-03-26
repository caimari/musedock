{{-- Blog Pagination --}}
@if(!empty($pagination) && $pagination['total_pages'] > 1)
@php
    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];
    $window = 2; // pages before/after current

    // Build the list of page numbers to show
    $pages = [];
    $pages[] = 1;
    if ($total > 1) $pages[] = $total;

    for ($i = max(2, $current - $window); $i <= min($total - 1, $current + $window); $i++) {
        $pages[] = $i;
    }

    $pages = array_unique($pages);
    sort($pages);
@endphp
<div class="row mt-4">
    <div class="col-12">
        <nav aria-label="Navegacion de paginas">
            <ul class="pagination justify-content-center flex-wrap">
                {{-- Previous --}}
                @if($current > 1)
                <li class="page-item">
                    <a class="page-link" href="?page={{ $current - 1 }}" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                @else
                <li class="page-item disabled">
                    <span class="page-link">&laquo;</span>
                </li>
                @endif

                {{-- Page numbers with ellipsis --}}
                @php $prev = 0; @endphp
                @foreach($pages as $p)
                    @if($p - $prev > 1)
                    <li class="page-item disabled">
                        <span class="page-link" style="border: none; background: transparent; color: #6c757d;">&hellip;</span>
                    </li>
                    @endif

                    @if($p == $current)
                    <li class="page-item active" aria-current="page">
                        <span class="page-link">{{ $p }}</span>
                    </li>
                    @else
                    <li class="page-item">
                        <a class="page-link" href="?page={{ $p }}">{{ $p }}</a>
                    </li>
                    @endif

                    @php $prev = $p; @endphp
                @endforeach

                {{-- Next --}}
                @if($current < $total)
                <li class="page-item">
                    <a class="page-link" href="?page={{ $current + 1 }}" aria-label="Siguiente">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
                @else
                <li class="page-item disabled">
                    <span class="page-link">&raquo;</span>
                </li>
                @endif
            </ul>

            {{-- Page info --}}
            <div class="text-center mt-2" style="font-size: 0.85rem; color: #6c757d;">
                Página {{ $current }} de {{ $total }}
            </div>
        </nav>
    </div>
</div>
@endif
