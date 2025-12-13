@php
    $_siteName = site_setting('site_name', 'HighTech');
    $_siteDescription = site_setting('site_description', 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Soluta facere delectus qui placeat inventore consectetur repellendus optio debitis.');
    $_siteAddress = site_setting('contact_address', '123 Street, New York, USA');
    $_sitePhone = site_setting('contact_phone', '+123 456 7890');
    $_siteEmail = site_setting('contact_email', 'info@example.com');
    $_facebookUrl = site_setting('social_facebook', '');
    $_twitterUrl = site_setting('social_twitter', '');
    $_instagramUrl = site_setting('social_instagram', '');
    $_linkedinUrl = site_setting('social_linkedin', '');
    $_logoText = themeOption('logo_text', $_siteName);
    $_copyrightText = themeOption('copyright_text', $_siteName);
    $_currentYear = date('Y');
@endphp

{{-- Footer Start --}}
<div class="container-fluid footer bg-dark wow fadeIn" data-wow-delay=".3s">
    <div class="container pt-5 pb-4">
        <div class="row g-5">
            <div class="col-lg-3 col-md-6">
                <a href="{{ url('/') }}">
                    @php
                        $logoWords = explode(' ', $_logoText);
                        $firstWord = array_shift($logoWords);
                        $restWords = implode(' ', $logoWords);
                    @endphp
                    <h1 class="text-white fw-bold d-block">{{ $firstWord }}@if($restWords)<span class="text-secondary">{{ $restWords }}</span>@endif</h1>
                </a>
                <p class="mt-4 text-light">{{ $_siteDescription }}</p>
                <div class="d-flex hightech-link">
                    @if(!empty($_facebookUrl))
                        <a href="{{ $_facebookUrl }}" class="btn-light nav-fill btn btn-square rounded-circle me-2"><i class="fab fa-facebook-f text-primary"></i></a>
                    @endif
                    @if(!empty($_twitterUrl))
                        <a href="{{ $_twitterUrl }}" class="btn-light nav-fill btn btn-square rounded-circle me-2"><i class="fab fa-twitter text-primary"></i></a>
                    @endif
                    @if(!empty($_instagramUrl))
                        <a href="{{ $_instagramUrl }}" class="btn-light nav-fill btn btn-square rounded-circle me-2"><i class="fab fa-instagram text-primary"></i></a>
                    @endif
                    @if(!empty($_linkedinUrl))
                        <a href="{{ $_linkedinUrl }}" class="btn-light nav-fill btn btn-square rounded-circle me-0"><i class="fab fa-linkedin-in text-primary"></i></a>
                    @endif
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="#" class="h3 text-secondary">{{ __('Quick Links') }}</a>
                <div class="mt-4 d-flex flex-column short-link">
                    @custommenu('footer-links', null, [
                        'ul_id' => '',
                        'nav_class' => '',
                        'li_class' => '',
                        'a_class' => 'mb-2 text-white'
                    ])
                    @include('partials.widget-renderer', ['areaSlug' => 'footer-links'])
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="#" class="h3 text-secondary">{{ __('Help') }}</a>
                <div class="mt-4 d-flex flex-column help-link">
                    @custommenu('footer-help', null, [
                        'ul_id' => '',
                        'nav_class' => '',
                        'li_class' => '',
                        'a_class' => 'mb-2 text-white'
                    ])
                    @include('partials.widget-renderer', ['areaSlug' => 'footer-help'])
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="#" class="h3 text-secondary">{{ __('Contact Us') }}</a>
                <div class="text-white mt-4 d-flex flex-column contact-link">
                    @if(!empty($_siteAddress))
                        <a href="#" class="pb-3 text-light border-bottom border-primary"><i class="fas fa-map-marker-alt text-secondary me-2"></i> {{ $_siteAddress }}</a>
                    @endif
                    @if(!empty($_sitePhone))
                        <a href="tel:{{ $_sitePhone }}" class="py-3 text-light border-bottom border-primary"><i class="fas fa-phone-alt text-secondary me-2"></i> {{ $_sitePhone }}</a>
                    @endif
                    @if(!empty($_siteEmail))
                        <a href="mailto:{{ $_siteEmail }}" class="py-3 text-light border-bottom border-primary"><i class="fas fa-envelope text-secondary me-2"></i> {{ $_siteEmail }}</a>
                    @endif
                </div>
            </div>
        </div>
        <hr class="text-light mt-5 mb-4">
        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <span class="text-light"><a href="{{ url('/') }}" class="text-secondary"><i class="fas fa-copyright text-secondary me-2"></i>{{ $_copyrightText }}</a>, {{ __('All rights reserved.') }}</span>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <span class="text-light">{{ __('Powered by') }} <a href="https://musedock.net" class="text-secondary">MuseDock</a></span>
            </div>
        </div>
    </div>
</div>
{{-- Footer End --}}
