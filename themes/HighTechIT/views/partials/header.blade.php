@php
    $_siteName = site_setting('site_name', 'HighTech');
    $_sitePhone = site_setting('contact_phone', '+0123 456 7890');
    $_siteEmail = site_setting('contact_email', 'info@example.com');
    $_siteAddress = site_setting('contact_address', '23 Ranking Street, New York');
    $_facebookUrl = site_setting('social_facebook', '');
    $_twitterUrl = site_setting('social_twitter', '');
    $_instagramUrl = site_setting('social_instagram', '');
    $_linkedinUrl = site_setting('social_linkedin', '');
    $_logoText = themeOption('logo_text', $_siteName);
    $_topbarNote = themeOption('topbar_note', 'Note : We help you to Grow your Business');
@endphp

{{-- Topbar Start --}}
<div class="container-fluid bg-dark py-2 d-none d-md-flex">
    <div class="container">
        <div class="d-flex justify-content-between topbar">
            <div class="top-info">
                @if(!empty($_siteAddress))
                    <small class="me-3 text-white-50"><a href="#"><i class="fas fa-map-marker-alt me-2 text-secondary"></i></a>{{ $_siteAddress }}</small>
                @endif
                @if(!empty($_siteEmail))
                    <small class="me-3 text-white-50"><a href="mailto:{{ $_siteEmail }}"><i class="fas fa-envelope me-2 text-secondary"></i></a>{{ $_siteEmail }}</small>
                @endif
            </div>
            <div id="note" class="text-secondary d-none d-xl-flex"><small>{{ $_topbarNote }}</small></div>
            <div class="top-link">
                @if(!empty($_facebookUrl))
                    <a href="{{ $_facebookUrl }}" class="bg-light nav-fill btn btn-sm-square rounded-circle"><i class="fab fa-facebook-f text-primary"></i></a>
                @endif
                @if(!empty($_twitterUrl))
                    <a href="{{ $_twitterUrl }}" class="bg-light nav-fill btn btn-sm-square rounded-circle"><i class="fab fa-twitter text-primary"></i></a>
                @endif
                @if(!empty($_instagramUrl))
                    <a href="{{ $_instagramUrl }}" class="bg-light nav-fill btn btn-sm-square rounded-circle"><i class="fab fa-instagram text-primary"></i></a>
                @endif
                @if(!empty($_linkedinUrl))
                    <a href="{{ $_linkedinUrl }}" class="bg-light nav-fill btn btn-sm-square rounded-circle me-0"><i class="fab fa-linkedin-in text-primary"></i></a>
                @endif
            </div>
        </div>
    </div>
</div>
{{-- Topbar End --}}

{{-- Navbar Start --}}
<div class="container-fluid bg-primary">
    <div class="container">
        <nav class="navbar navbar-dark navbar-expand-lg py-0">
            <a href="{{ url('/') }}" class="navbar-brand">
                @php
                    $logoWords = explode(' ', $_logoText);
                    $firstWord = array_shift($logoWords);
                    $restWords = implode(' ', $logoWords);
                @endphp
                <h1 class="text-white fw-bold d-block">{{ $firstWord }}@if($restWords)<span class="text-secondary">{{ $restWords }}</span>@endif</h1>
            </a>
            <button type="button" class="navbar-toggler me-0" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse bg-transparent" id="navbarCollapse">
                <div class="navbar-nav ms-auto mx-xl-auto p-0">
                    @custommenu('main', null, [
                        'ul_id' => '',
                        'nav_class' => '',
                        'li_class' => 'nav-item',
                        'a_class' => 'nav-link',
                        'submenu_class' => 'dropdown-menu',
                        'li_submenu_class' => 'dropdown'
                    ])
                </div>
            </div>
            <div class="d-none d-xl-flex flex-shirink-0">
                <div id="phone-tada" class="d-flex align-items-center justify-content-center me-4">
                    <a href="tel:{{ $_sitePhone }}" class="position-relative animated tada infinite">
                        <i class="fa fa-phone-alt text-white fa-2x"></i>
                        <div class="position-absolute" style="top: -7px; left: 20px;">
                            <span><i class="fa fa-comment-dots text-secondary"></i></span>
                        </div>
                    </a>
                </div>
                <div class="d-flex flex-column pe-4 border-end">
                    <span class="text-white-50">{{ __('Have any questions?') }}</span>
                    <span class="text-secondary">{{ __('Call:') }} {{ $_sitePhone }}</span>
                </div>
                <div class="d-flex align-items-center justify-content-center ms-4">
                    <a href="{{ url('/search') }}"><i class="bi bi-search text-white fa-2x"></i></a>
                </div>
            </div>
        </nav>
    </div>
</div>
{{-- Navbar End --}}
