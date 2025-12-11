@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ __('blog.title') . ' | ' . setting('site_name', 'MuseDock CMS') }}
@endsection

@section('description')
    {{ setting('site_description', '') }}
@endsection

@section('content')

{{-- Hero Section --}}
<section class="relative bg-gradient-to-br from-primary-600 via-secondary-600 to-accent-600 text-white py-16 md:py-24 overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-64 h-64 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full translate-x-1/2 translate-y-1/2"></div>
    </div>

    <div class="container-custom relative z-10">
        <div class="max-w-3xl mx-auto text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4 text-shadow">{{ __('blog.title') }}</h1>
            <p class="text-xl text-white/90">{{ setting('site_description', '') }}</p>
            <div class="divider-gradient mx-auto mt-6"></div>
        </div>
    </div>
</section>

{{-- Blog Grid --}}
<section class="py-12 md:py-20 bg-gray-50">
    <div class="container-custom">
        @if(!empty($posts) && count($posts) > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($posts as $post)
                <article class="card flex flex-col">
                    {{-- Imagen --}}
                    <a href="/blog/{{ $post->slug }}" class="block overflow-hidden">
                        @php
                            if ($post->featured_image && !$post->hide_featured_image) {
                                $imageUrl = (str_starts_with($post->featured_image, '/media/') || str_starts_with($post->featured_image, 'http'))
                                    ? $post->featured_image
                                    : asset($post->featured_image);
                            } else {
                                $imageUrl = asset('themes/react-modern/assets/img/blog-placeholder.svg');
                            }
                        @endphp
                        <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="w-full h-48 object-cover hover:scale-105 transition-transform duration-300">
                    </a>

                    {{-- Content --}}
                    <div class="p-6 flex flex-col flex-grow">
                        {{-- Meta --}}
                        <div class="flex items-center text-sm text-gray-500 mb-3">
                            <i class="far fa-calendar mr-2"></i>
                            <span>{{ $post->published_at ? date('d M Y', strtotime($post->published_at)) : date('d M Y', strtotime($post->created_at)) }}</span>
                        </div>

                        {{-- Title --}}
                        <h2 class="text-xl font-bold mb-3">
                            <a href="/blog/{{ $post->slug }}" class="text-gray-900 hover:text-primary-600 transition-colors">{{ $post->title }}</a>
                        </h2>

                        {{-- Excerpt --}}
                        @if($post->excerpt)
                        <p class="text-gray-600 mb-4 flex-grow">{{ mb_strlen($post->excerpt) > 120 ? mb_substr($post->excerpt, 0, 120) . '...' : $post->excerpt }}</p>
                        @endif

                        {{-- Read more --}}
                        <div class="mt-auto pt-4">
                            <a href="/blog/{{ $post->slug }}" class="inline-flex items-center text-primary-600 font-semibold hover:text-secondary-600 transition-colors">
                                {{ __('blog.read_more') }}
                                <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </article>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if(!empty($pagination) && $pagination['total_pages'] > 1)
            <nav class="mt-12 flex justify-center">
                <div class="flex items-center gap-2">
                    @if($pagination['current_page'] > 1)
                    <a href="?page={{ $pagination['current_page'] - 1 }}" class="btn-secondary px-4 py-2">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    @endif

                    @for($i = 1; $i <= $pagination['total_pages']; $i++)
                        @if($i == $pagination['current_page'])
                        <span class="btn-primary px-4 py-2">{{ $i }}</span>
                        @else
                        <a href="?page={{ $i }}" class="btn-secondary px-4 py-2">{{ $i }}</a>
                        @endif
                    @endfor

                    @if($pagination['current_page'] < $pagination['total_pages'])
                    <a href="?page={{ $pagination['current_page'] + 1 }}" class="btn-secondary px-4 py-2">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    @endif
                </div>
            </nav>
            @endif
        @else
            {{-- Empty state --}}
            <div class="text-center py-16">
                <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-newspaper text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ __('blog.no_posts') }}</h3>
                <p class="text-gray-600">No hay publicaciones disponibles en este momento.</p>
            </div>
        @endif
    </div>
</section>

@endsection
