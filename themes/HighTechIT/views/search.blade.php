@extends('layouts.app')

@section('title')
    {{ __('Search Results for') }} "{{ $query }}" | {{ site_setting('site_name', '') }}
@endsection

@section('description')
    {{ __('Search results for') }} {{ $query }}
@endsection

@section('content')

{{-- Page Header --}}
<div class="container-fluid page-header py-5 mb-5">
    <div class="container text-center py-5">
        <h1 class="display-4 text-white mb-4 animated slideInDown">{{ __('Search Results') }}</h1>
        <p class="text-white fs-5">{{ __('Results for') }}: "{{ $query }}"</p>
    </div>
</div>

{{-- Search Results --}}
<div class="container py-5">
    @if(isset($results) && count($results) > 0)
        <p class="mb-4">{{ __('Found') }} {{ count($results) }} {{ __('results') }}</p>

        <div class="row g-4">
            @foreach($results as $result)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="{{ url($result->slug ?? '#') }}">{{ $result->title ?? 'Sin título' }}</a>
                            </h5>
                            @if(!empty($result->excerpt))
                                <p class="card-text">{{ $result->excerpt }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="alert alert-info">
            <h4>{{ __('No results found') }}</h4>
            <p>{{ __('Try different keywords or browse our pages.') }}</p>
        </div>
    @endif
</div>

@endsection
