{{-- Blog Pagination --}}
@if(!empty($pagination) && $pagination['total_pages'] > 1)
<div class="row mt-4">
    <div class="col-12">
        <nav aria-label="Navegacion de paginas">
            <ul class="pagination justify-content-center">
                @if($pagination['current_page'] > 1)
                <li class="page-item">
                    <a class="page-link" href="?page={{ $pagination['current_page'] - 1 }}" aria-label="Anterior" style="color: #6c757d; border-color: #dee2e6;">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                @else
                <li class="page-item disabled">
                    <span class="page-link" style="background-color: #f8f9fa; border-color: #dee2e6;">&laquo;</span>
                </li>
                @endif

                @for($i = 1; $i <= $pagination['total_pages']; $i++)
                    @if($i == $pagination['current_page'])
                    <li class="page-item active" aria-current="page">
                        <span class="page-link" style="background-color: #b8d4f1; border-color: #a8c9ee; color: #2c3e50;">{{ $i }}</span>
                    </li>
                    @else
                    <li class="page-item">
                        <a class="page-link" href="?page={{ $i }}" style="color: #6c757d; border-color: #dee2e6;">{{ $i }}</a>
                    </li>
                    @endif
                @endfor

                @if($pagination['current_page'] < $pagination['total_pages'])
                <li class="page-item">
                    <a class="page-link" href="?page={{ $pagination['current_page'] + 1 }}" aria-label="Siguiente" style="color: #6c757d; border-color: #dee2e6;">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
                @else
                <li class="page-item disabled">
                    <span class="page-link" style="background-color: #f8f9fa; border-color: #dee2e6;">&raquo;</span>
                </li>
                @endif
            </ul>
        </nav>
    </div>
</div>
@endif
