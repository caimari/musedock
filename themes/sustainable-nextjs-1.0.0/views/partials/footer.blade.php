<footer class="footer-area">
    <div class="container">
        <div class="row pt-5 pb-4">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="footer-widget">
                    <h4>{{ site_setting('site_name', 'MuseDock') }}</h4>
                    <p>{{ site_setting('site_description', '') }}</p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="footer-widget">
                    <h4>{{ __('footer.column1') }}</h4>
                    @custommenu('footer1', null, [
                        'ul_id' => 'footer-menu-1',
                        'nav_class' => 'footer-menu',
                        'li_class' => '',
                        'a_class' => ''
                    ])
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="footer-widget">
                    <h4>{{ __('footer.column2') }}</h4>
                    @custommenu('footer2', null, [
                        'ul_id' => 'footer-menu-2',
                        'nav_class' => 'footer-menu',
                        'li_class' => '',
                        'a_class' => ''
                    ])
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="footer-widget">
                    <h4>{{ __('footer.column3') }}</h4>
                    @custommenu('footer3', null, [
                        'ul_id' => 'footer-menu-3',
                        'nav_class' => 'footer-menu',
                        'li_class' => '',
                        'a_class' => ''
                    ])
                </div>
            </div>
        </div>

        <div class="row border-top pt-4 pb-4">
            <div class="col-md-6 text-center text-md-left mb-3 mb-md-0">
                <p class="mb-0">&copy; {{ date('Y') }} {{ site_setting('site_name', 'MuseDock') }}. {{ __('footer.all_rights_reserved') }}</p>
            </div>
            <div class="col-md-6 text-center text-md-right">
                <div class="footer-social">
                    @if(site_setting('social_facebook', ''))
                        <a href="{{ site_setting('social_facebook') }}" target="_blank"><i class="fab fa-facebook-f"></i></a>
                    @endif
                    @if(site_setting('social_twitter', ''))
                        <a href="{{ site_setting('social_twitter') }}" target="_blank"><i class="fab fa-twitter"></i></a>
                    @endif
                    @if(site_setting('social_instagram', ''))
                        <a href="{{ site_setting('social_instagram') }}" target="_blank"><i class="fab fa-instagram"></i></a>
                    @endif
                    @if(site_setting('social_linkedin', ''))
                        <a href="{{ site_setting('social_linkedin') }}" target="_blank"><i class="fab fa-linkedin-in"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</footer>
