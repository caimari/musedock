@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ $category->name . ' | ' . __('blog.title') . ' | ' . setting('site_name', 'MuseDock CMS') }}
@endsection

@section('description')
    {{ $category->description ?? setting('site_description', '') }}
@endsection

@section('content')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    {{-- Breadcrumb y Header --}}
    <div class="mb-10">
        {{-- Breadcrumb --}}
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-sm">
                <li>
                    <a href="/" class="text-gray-500 hover:text-indigo-600 transition-colors">{{ __('common.home') }}</a>
                </li>
                @if(blog_prefix() !== '')
                <li>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </li>
                <li>
                    <a href="{{ blog_url() }}" class="text-gray-500 hover:text-indigo-600 transition-colors">{{ __('blog.title') }}</a>
                </li>
                @endif
                <li>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </li>
                <li>
                    <a href="{{ blog_url('', 'category') }}" class="text-gray-500 hover:text-indigo-600 transition-colors">{{ __('blog.frontend.category') }}</a>
                </li>
                <li>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </li>
                <li>
                    <span class="text-gray-900 font-medium">{{ $category->name }}</span>
                </li>
            </ol>
        </nav>

        {{-- Header --}}
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-8 text-white">
            <h1 class="text-3xl font-bold mb-2">{{ $category->name }}</h1>
            @if($category->description)
            <p class="text-indigo-100">{{ $category->description }}</p>
            @endif
        </div>
    </div>

    {{-- Grid de Posts --}}
    @if(!empty($posts) && count($posts) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($posts as $post)
            <article class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300 flex flex-col">
                {{-- Imagen destacada --}}
                <a href="{{ blog_url($post->slug) }}" class="block relative overflow-hidden bg-gray-100" style="height: 220px;">
                    @php
                        if ($post->featured_image && !($post->hide_featured_image ?? false)) {
                            $imageUrl = (str_starts_with($post->featured_image, '/') || str_starts_with($post->featured_image, 'http'))
                                ? $post->featured_image
                                : asset($post->featured_image);
                        } else {
                            $imageUrl = '/themes/react-modern/assets/img/blog-placeholder.svg';
                        }
                        $imageUrl = media_thumb_url($imageUrl, 'medium');
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="absolute inset-0 w-full h-full object-cover hover:scale-105 transition-transform duration-300" loading="lazy">
                </a>

                {{-- Contenido --}}
                <div class="px-5 pt-2 pb-4 flex flex-col flex-grow">
                    {{-- Meta --}}
                    <div class="flex items-center text-sm text-gray-500 mb-3">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>{{ format_date($post->published_at ?? $post->created_at) }}</span>
                        @php
                            $postAuthorName = null;
                            if (!empty($post->user_id)) {
                                if (($post->user_type ?? '') === 'superadmin' && !empty($post->tenant_id)) {
                                    $__pdo = \Screenart\Musedock\Database::connect();
                                    $__stmt = $__pdo->prepare("SELECT name FROM admins WHERE tenant_id = ? AND is_root_admin = 1 LIMIT 1");
                                    $__stmt->execute([$post->tenant_id]);
                                    $__ra = $__stmt->fetch(\PDO::FETCH_OBJ);
                                    $postAuthorName = $__ra ? $__ra->name : null;
                                }
                                if (!$postAuthorName) {
                                    $__author = match($post->user_type ?? 'admin') {
                                        'superadmin' => \Screenart\Musedock\Models\SuperAdmin::find($post->user_id),
                                        'admin' => \Screenart\Musedock\Models\Admin::find($post->user_id),
                                        'user' => \Screenart\Musedock\Models\User::find($post->user_id),
                                        default => null,
                                    };
                                    $postAuthorName = $__author ? $__author->name : null;
                                }
                            }
                        @endphp
                        @if($postAuthorName)
                        <span class="ml-3"><i class="far fa-user mr-1"></i>{{ $postAuthorName }}</span>
                        @endif
                    </div>

                    {{-- Titulo --}}
                    <h2 class="text-xl font-semibold text-gray-900 mb-3 hover:text-indigo-600 transition-colors card-title-clamp">
                        <a href="{{ blog_url($post->slug) }}">{{ $post->title }}</a>
                    </h2>

                    {{-- Excerpt --}}
                    @php
                        $__excerpt = $post->excerpt ?: strip_tags($post->content ?? '');
                        $__excerpt = trim(preg_replace('/\s+/', ' ', $__excerpt));
                        $__excerpt = mb_strlen($__excerpt) > 200 ? mb_substr($__excerpt, 0, 200) . '...' : $__excerpt;
                    @endphp
                    <p class="text-gray-600 mb-0 card-excerpt-clamp">{{ $__excerpt }}</p>

                    {{-- Leer mas --}}
                    <div class="mt-auto pt-3">
                        <a href="{{ blog_url($post->slug) }}" class="inline-block px-5 py-2 text-sm font-medium text-gray-600 bg-transparent border border-gray-300 rounded no-underline hover:no-underline hover:text-gray-900 hover:border-gray-500 hover:bg-gray-50 transition-all uppercase tracking-wide">
                            {{ __('blog.read_more') }}
                        </a>
                    </div>
                </div>
            </article>
            @endforeach
        </div>

        {{-- Paginacion --}}
        @if(!empty($pagination) && $pagination['total_pages'] > 1)
        <nav class="mt-12 flex justify-center" aria-label="Pagination">
            <div class="flex items-center space-x-2">
                @if($pagination['current_page'] > 1)
                <a href="?page={{ $pagination['current_page'] - 1 }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                @endif

                @for($i = 1; $i <= $pagination['total_pages']; $i++)
                    @if($i == $pagination['current_page'])
                    <span class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg">{{ $i }}</span>
                    @else
                    <a href="?page={{ $i }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">{{ $i }}</a>
                    @endif
                @endfor

                @if($pagination['current_page'] < $pagination['total_pages'])
                <a href="?page={{ $pagination['current_page'] + 1 }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
                @endif
            </div>
        </nav>
        @endif
    @else
        {{-- Estado vacio --}}
        <div class="text-center py-16">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
            </svg>
            @php
                $noPostsCatText = __('blog.no_posts_category');
                if ($noPostsCatText === 'blog.no_posts_category') {
                    $noPostsCatText = detectLanguage() === 'es' ? 'No hay posts en esta categoria.' : 'No posts in this category.';
                }
            @endphp
            <h3 class="mt-4 text-lg font-medium text-gray-900">{{ $noPostsCatText }}</h3>
            <div class="mt-6">
                <a href="{{ blog_url() }}" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                    {{ __('blog.view_all') }}
                </a>
            </div>
        </div>
    @endif
</div>

<style>
.card-title-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.6em;
    line-height: 1.3;
}
.card-excerpt-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 3.9em;
    line-height: 1.3;
}
</style>

@endsection
