@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ __('blog.frontend.category') . ' | ' . __('blog.title') . ' | ' . setting('site_name', 'MuseDock CMS') }}
@endsection

@section('description')
    {{ setting('site_description', '') }}
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
                    <span class="text-gray-900 font-medium">{{ __('blog.categories') }}</span>
                </li>
            </ol>
        </nav>

        {{-- Header --}}
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-8 text-white">
            <h1 class="text-3xl font-bold mb-2">{{ __('blog.categories') }}</h1>
        </div>
    </div>

    {{-- Grid de Categorías --}}
    @if(!empty($categories) && count($categories) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($categories as $category)
            <a href="{{ blog_url($category->slug, 'category') }}" class="block">
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300 text-center">
                    @if($category->image)
                    <img src="{{ $category->image }}" alt="{{ $category->name }}" class="w-full h-44 object-cover" loading="lazy">
                    @endif
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-2">{{ $category->name }}</h2>
                        @if($category->description)
                        <p class="text-gray-600 text-sm mb-3">{{ mb_strlen($category->description) > 100 ? mb_substr($category->description, 0, 100) . '...' : $category->description }}</p>
                        @endif
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                            {{ $category->post_count ?? 0 }} {{ __('blog.posts') }}
                        </span>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    @else
        <div class="text-center py-16">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">{{ __('blog.frontend.no_posts') }}</h3>
            <div class="mt-6">
                <a href="{{ blog_url() }}" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                    {{ __('blog.view_all') }}
                </a>
            </div>
        </div>
    @endif
</div>

@endsection
