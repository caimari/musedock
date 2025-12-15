@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ $post->title . ' | ' . setting('site_name', 'MuseDock CMS') }}
@endsection

@section('description')
    {{ $post->excerpt ?? mb_substr(strip_tags($post->content), 0, 160) }}
@endsection

@section('content')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="lg:flex lg:gap-12">
        {{-- Contenido principal --}}
        <article class="lg:w-2/3">
            {{-- Imagen destacada --}}
            @if($post->featured_image && !$post->hide_featured_image)
                @php
                    $imageUrl = (str_starts_with($post->featured_image, '/media/') || str_starts_with($post->featured_image, 'http'))
                        ? $post->featured_image
                        : asset($post->featured_image);
                @endphp
                <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="w-full h-auto max-h-[500px] object-cover rounded-2xl shadow-lg mb-8">
            @endif

            {{-- Header --}}
            <header class="mb-8">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ $post->title }}</h1>

                {{-- Meta --}}
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>{{ format_datetime($post->published_at ?? $post->created_at) }}</span>
                    </div>
                    @if($post->view_count > 0)
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <span>{{ $post->view_count }} {{ __('blog.views') }}</span>
                    </div>
                    @endif
                </div>
            </header>

            {{-- Contenido --}}
            <div class="prose prose-lg max-w-none prose-indigo prose-headings:text-gray-900 prose-a:text-indigo-600 prose-img:rounded-xl">
                {!! $post->content !!}
            </div>

            {{-- Categorias y etiquetas --}}
            @if(!empty($post->categories) || !empty($post->tags))
            <div class="mt-10 pt-8 border-t border-gray-200">
                @if(!empty($post->categories))
                <div class="mb-4">
                    <span class="font-semibold text-gray-900 mr-2">{{ __('blog.categories') }}:</span>
                    @foreach($post->categories as $category)
                        <a href="/blog/category/{{ $category->slug }}" class="inline-block px-3 py-1 mr-2 mb-2 text-sm font-medium text-white bg-indigo-600 rounded-full hover:bg-indigo-700 transition-colors">{{ $category->name }}</a>
                    @endforeach
                </div>
                @endif

                @if(!empty($post->tags))
                <div>
                    <span class="font-semibold text-gray-900 mr-2">{{ __('blog.tags') }}:</span>
                    @foreach($post->tags as $tag)
                        <a href="/blog/tag/{{ $tag->slug }}" class="inline-block px-3 py-1 mr-2 mb-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-full hover:bg-gray-300 transition-colors">{{ $tag->name }}</a>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            {{-- Navegacion prev/next --}}
            @if(!empty($prevPost) || !empty($nextPost))
            <nav class="mt-10 pt-8 border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if(!empty($prevPost))
                    <a href="/blog/{{ $prevPost->slug }}" class="group flex items-center p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                        <svg class="w-6 h-6 text-gray-400 group-hover:text-indigo-600 mr-4 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        <div>
                            <span class="text-sm text-gray-500 block">{{ __('blog.previous_post') }}</span>
                            <span class="font-medium text-gray-900 group-hover:text-indigo-600 transition-colors">{{ $prevPost->title }}</span>
                        </div>
                    </a>
                    @endif

                    @if(!empty($nextPost))
                    <a href="/blog/{{ $nextPost->slug }}" class="group flex items-center justify-end p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors text-right">
                        <div>
                            <span class="text-sm text-gray-500 block">{{ __('blog.next_post') }}</span>
                            <span class="font-medium text-gray-900 group-hover:text-indigo-600 transition-colors">{{ $nextPost->title }}</span>
                        </div>
                        <svg class="w-6 h-6 text-gray-400 group-hover:text-indigo-600 ml-4 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                    @endif
                </div>
            </nav>
            @endif
        </article>

        {{-- Sidebar --}}
        <aside class="lg:w-1/3 mt-12 lg:mt-0">
            {{-- Categorias --}}
            @if(!empty($categories) && count($categories) > 0)
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">{{ __('blog.categories') }}</h3>
                <ul class="space-y-2">
                    @foreach($categories as $cat)
                    <li>
                        <a href="/blog/category/{{ $cat->slug }}" class="flex items-center text-gray-600 hover:text-indigo-600 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                            {{ $cat->name }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Posts recientes --}}
            @if(!empty($recentPosts) && count($recentPosts) > 0)
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">{{ __('blog.recent_posts') }}</h3>
                <ul class="space-y-4">
                    @foreach($recentPosts as $recentPost)
                    <li class="group">
                        <a href="/blog/{{ $recentPost->slug }}" class="block">
                            <span class="font-medium text-gray-900 group-hover:text-indigo-600 transition-colors">{{ $recentPost->title }}</span>
                            <span class="block text-sm text-gray-500 mt-1">
                                {{ format_date($recentPost->published_at ?? $recentPost->created_at) }}
                            </span>
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </aside>
    </div>
</div>

@endsection
