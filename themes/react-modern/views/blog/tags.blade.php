@extends('layouts.app')

{{-- SEO --}}
@section('title')
    {{ __('blog.frontend.tag') . ' | ' . __('blog.title') . ' | ' . setting('site_name', 'MuseDock CMS') }}
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
                    <span class="text-gray-900 font-medium">{{ __('blog.tags') }}</span>
                </li>
            </ol>
        </nav>

        {{-- Header --}}
        <div class="bg-gradient-to-r from-gray-700 to-gray-900 rounded-2xl p-8 text-white">
            <h1 class="text-3xl font-bold mb-2">{{ __('blog.tags') }}</h1>
        </div>
    </div>

    {{-- Cloud de Tags --}}
    @if(!empty($tags) && count($tags) > 0)
        <div class="flex flex-wrap gap-4 justify-center py-8">
            @foreach($tags as $tag)
                <a href="{{ blog_url($tag->slug, 'tag') }}" class="inline-flex items-center px-5 py-3 rounded-full text-base font-medium bg-white border border-gray-200 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-200 shadow-sm hover:shadow transition-all duration-200">
                    {{ $tag->name }}
                    <span class="ml-2 inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">{{ $tag->post_count ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="text-center py-16">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
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
