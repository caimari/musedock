@extends('layouts.app')

@section('title')
    {{ ($translation->seo_title ?? $page->seo_title ?? $translation->title ?? $page->title ?? __('home_title')) . ' | ' . setting('site_name', config('app_name', 'MuseDock CMS')) }}
@endsection

@section('keywords')
    {{ $translation->seo_keywords ?? $page->seo_keywords ?? setting('site_keywords', 'Palabras clave predeterminadas') }}
@endsection

@section('og_title')
    {{ $translation->seo_title ?? $page->seo_title ?? $translation->title ?? $page->title ?? __('home_title') }}
@endsection

@section('og_description')
    {{ $translation->seo_description ?? $page->seo_description ?? setting('site_description', 'Descripción predeterminada') }}
@endsection

@section('content')

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-row">
            <div class="hero-content-col">
                <h1 class="hero-title">
                    {{ $translation->title ?? $page->title ?? 'Amazingly flexible, customizable and easy to use' }}
                </h1>

                <button class="hero-btn">
                    {{ __('home_get_started', 'Get Started') }}
                </button>

                <div class="hero-rating">
                    @for($i = 0; $i < 5; $i++)
                    <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 22 20">
                        <path d="M20.924 7.625a1.523 1.523 0 0 0-1.238-1.044l-5.051-.734-2.259-4.577a1.534 1.534 0 0 0-2.752 0L7.365 5.847l-5.051.734A1.535 1.535 0 0 0 1.463 9.2l3.656 3.563-.863 5.031a1.532 1.532 0 0 0 2.226 1.616L11 17.033l4.518 2.375a1.534 1.534 0 0 0 2.226-1.617l-.863-5.03L20.537 9.2a1.523 1.523 0 0 0 .387-1.575Z"></path>
                    </svg>
                    @endfor
                </div>

                <p class="hero-testimonial">
                    {!! apply_filters('the_content', $translation->content ?? $page->content ?? "It's easy to set up and the support experience is the best and unparalleled comparatively.") !!}
                </p>
            </div>

            <div class="hero-form-col">
                <form class="hero-form">
                    <h6>{{ __('home_form_title', 'Start the project') }}</h6>

                    <div class="form-row form-row-2cols">
                        <input type="text" name="firstName" placeholder="{{ __('home_form_firstname', 'First name') }}" required>
                        <input type="text" name="lastName" placeholder="{{ __('home_form_lastname', 'Last name') }}" required>
                    </div>

                    <div class="form-group">
                        <input type="email" name="email" placeholder="{{ __('home_form_email', 'youremail@website.com') }}" required>
                    </div>

                    <div class="form-group">
                        <input type="text" name="country" placeholder="{{ __('home_form_country', 'Country') }}" required>
                    </div>

                    <div class="form-group">
                        <textarea name="message" rows="4" placeholder="{{ __('home_form_message', 'Write your thoughts here...') }}"></textarea>
                    </div>

                    <div class="form-checkbox">
                        <input id="terms" type="checkbox" value="" required>
                        <label for="terms">
                            {{ __('home_form_terms_text', 'I have read and acknowledge the') }}
                            <a href="{{ url('/terms') }}">{{ __('home_form_terms_link', 'Terms and Conditions') }}</a>.
                        </label>
                    </div>

                    <button type="submit" class="submit-btn">
                        {{ __('home_form_submit', 'Submit Inquiry') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <div class="features-header">
            <h2>{{ __('features_title', 'Why Choose Us?') }}</h2>
            <p>{{ __('features_subtitle', 'Discover the features that make our platform stand out from the rest') }}</p>
        </div>

        <div class="features-grid">
            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h3>{{ __('feature_1_title', 'Fast Performance') }}</h3>
                <p>{{ __('feature_1_desc', 'Lightning-fast loading speeds ensure your users never have to wait') }}</p>
            </div>

            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>{{ __('feature_2_title', 'Secure & Reliable') }}</h3>
                <p>{{ __('feature_2_desc', 'Enterprise-level security keeps your data safe and protected') }}</p>
            </div>

            <div class="feature-box">
                <div class="feature-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <h3>{{ __('feature_3_title', 'Easy Customization') }}</h3>
                <p>{{ __('feature_3_desc', 'Customize every aspect to match your brand and requirements') }}</p>
            </div>
        </div>
    </div>
</section>

@endsection
