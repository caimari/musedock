@extends('layouts.app')

{{-- SEO --}}
@section('title')
    @if(!empty($is_home))
        @php $__subtitle = site_setting('site_subtitle', ''); @endphp
        {{ site_setting('site_name', '') . ($__subtitle ? ' | ' . $__subtitle : '') }}
    @else
        {{ __('blog.title') . ' | ' . site_setting('site_name', '') }}
    @endif
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
                    <a href="{{ blog_url($post->slug) }}" class="block relative overflow-hidden bg-gray-100" style="height: 220px;">
                        @php
                            if ($post->featured_image && !$post->hide_featured_image) {
                                $imageUrl = (str_starts_with($post->featured_image, '/') || str_starts_with($post->featured_image, 'http'))
                                    ? $post->featured_image
                                    : asset($post->featured_image);
                            } else {
                                $imageUrl = asset('themes/react-modern/assets/img/blog-placeholder.svg');
                            }
                            $imageUrl = media_thumb_url($imageUrl, 'medium');
                        @endphp
                        <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="absolute inset-0 w-full h-full object-cover hover:scale-105 transition-transform duration-300" loading="lazy">
                    </a>

                    {{-- Content --}}
                    <div class="px-5 pt-2 pb-4 flex flex-col flex-grow">
                        {{-- Meta --}}
                        <div class="flex items-center text-sm text-gray-500 mb-3">
                            <i class="far fa-calendar mr-2"></i>
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

                        {{-- Title --}}
                        <h2 class="text-xl font-bold mb-3 card-title-clamp">
                            <a href="{{ blog_url($post->slug) }}" class="text-gray-900 hover:text-primary-600 transition-colors">{{ $post->title }}</a>
                        </h2>

                        {{-- Excerpt --}}
                        @php
                            $__excerpt = $post->excerpt ?: strip_tags($post->content ?? '');
                            $__excerpt = trim(preg_replace('/\s+/', ' ', $__excerpt));
                            $__excerpt = mb_strlen($__excerpt) > 200 ? mb_substr($__excerpt, 0, 200) . '...' : $__excerpt;
                        @endphp
                        <p class="text-gray-600 mb-0 card-excerpt-clamp">{{ $__excerpt }}</p>

                        {{-- Read more --}}
                        <div class="mt-auto pt-3">
                            <a href="{{ blog_url($post->slug) }}" class="inline-block px-5 py-2 text-sm font-medium text-gray-600 bg-transparent border border-gray-300 rounded no-underline hover:no-underline hover:text-gray-900 hover:border-gray-500 hover:bg-gray-50 transition-all uppercase tracking-wide">
                                {{ __('blog.read_more') }}
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
