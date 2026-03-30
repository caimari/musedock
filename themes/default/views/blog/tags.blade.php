@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ __('blog.frontend.tag') . ' | ' . __('blog.title') . ' | ' . site_setting('site_name', '') }}
@endsection

@section('description')
    {{ site_setting('site_description', '') }}
@endsection

@section('content')

<div class="container py-5">
    {{-- Cabecera --}}
    <div class="mb-4 pb-3 border-bottom">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2" style="background: transparent; padding-left: 0;">
                <li class="breadcrumb-item"><a href="/" style="color: #333;">{{ __('common.home') }}</a></li>
                @if(blog_prefix() !== '')
                <li class="breadcrumb-item"><a href="{{ blog_url() }}" style="color: #333;">{{ __('blog.title') }}</a></li>
                @endif
                <li class="breadcrumb-item active" aria-current="page" style="color: #666;">{{ __('blog.tags') }}</li>
            </ol>
        </nav>
    </div>

    @if(!empty($tags) && count($tags) > 0)
        <div class="d-flex flex-wrap gap-2 py-4">
        @foreach($tags as $tag)
            @php
                $__color = !empty($tag->color) ? trim($tag->color) : null;
                if ($__color) {
                    $__hex = ltrim($__color, '#');
                    if (strlen($__hex) === 3) { $__hex = $__hex[0].$__hex[0].$__hex[1].$__hex[1].$__hex[2].$__hex[2]; }
                    $__r = hexdec(substr($__hex,0,2));
                    $__g = hexdec(substr($__hex,2,2));
                    $__b = hexdec(substr($__hex,4,2));
                    $__chipStyle = "background:rgba({$__r},{$__g},{$__b},0.10);color:{$__color};border-color:rgba({$__r},{$__g},{$__b},0.32);";
                } else {
                    $__chipStyle = 'background:#eaf0fb;color:#1a4fa0;border-color:rgba(154,184,232,0.8);';
                }
            @endphp
            <a href="{{ blog_url($tag->slug, 'tag') }}" class="tx-chip-tag-page" style="{{ $__chipStyle }}">
                {{ $tag->name }}
                @if(($tag->post_count ?? 0) > 0)
                <span class="tx-chip-count">{{ $tag->post_count }}</span>
                @endif
            </a>
        @endforeach
        </div>

        <style>
        .tx-chip-tag-page {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            font-family: 'JetBrains Mono', 'Fira Mono', 'Courier New', monospace;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            border-radius: 3px;
            border: 1px solid;
            text-decoration: none;
            white-space: nowrap;
            transition: filter 0.15s ease, opacity 0.15s ease;
            line-height: 1.7;
        }
        .tx-chip-tag-page:hover {
            filter: brightness(0.85);
            text-decoration: none;
        }
        .tx-chip-tag-page:visited { color: inherit; }
        .tx-chip-count {
            display: inline-block;
            font-size: 9px;
            opacity: 0.7;
            font-weight: 400;
        }
        </style>
    @else
        <div class="text-center py-5">
            <i class="bi bi-tags" style="font-size: 4rem; color: #ccc;"></i>
            <p class="text-muted mt-3">{{ __('blog.frontend.no_posts') }}</p>
            <a href="{{ blog_url() }}" class="btn btn-outline-primary mt-2">{{ __('blog.view_all') }}</a>
        </div>
    @endif
</div>

@endsection
