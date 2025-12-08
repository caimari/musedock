<footer>
    <!-- Footer Start -->
    <div class="footer-area footer-padding">
        <div class="container">
            <div class="row d-flex justify-content-between">
                
                <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6">
                   <div class="single-footer-caption mb-50">
                     <div class="single-footer-caption mb-30">
                          <!-- logo -->
                         <div class="footer-logo">
                             <a href="{{ url('/') }}"><img src="{{ asset('logo2_footer.png') }}" alt=""></a>
                         </div>
                         <div class="footer-tittle">
                             <div class="footer-pera">
                                 <p>{{ setting('footer_short_description', 'Descripción corta del sitio aquí.') }}</p>
                            </div>

                            <!-- Selector de idiomas como SELECT -->
                            <div class="language-selector my-4 text-left">
                                @php
                                    $pdo = \Screenart\Musedock\Database::connect();
                                    $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE active = 1 ORDER BY id ASC");
                                    $stmt->execute();
                                    $activeLanguages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                                    $currentLang = $_SESSION['lang'] ?? setting('language', 'es');
                                @endphp

                                <form action="" method="get" id="language-form" class="d-inline-block">
                                    <select name="lang" id="language-select" class="custom-language-select" style="width: 120px;" onchange="this.form.submit();">
                                        @foreach($activeLanguages as $lang)
                                            <option value="{{ $lang['code'] }}" {{ $currentLang == $lang['code'] ? 'selected' : '' }}>
                                                {{ $lang['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                         </div>
                         
                         <!-- social -->
                         <div class="footer-social">
                            @if(setting('social_facebook', ''))
                                <a href="{{ setting('social_facebook') }}" target="_blank"><i class="fab fa-facebook-square"></i></a>
                            @endif
                            @if(setting('social_twitter', ''))
                                <a href="{{ setting('social_twitter') }}" target="_blank"><i class="fab fa-twitter-square"></i></a>
                            @endif
                            @if(setting('social_instagram', ''))
                                <a href="{{ setting('social_instagram') }}" target="_blank"><i class="fab fa-instagram"></i></a>
                            @endif
                            @if(setting('social_linkedin', ''))
                                <a href="{{ setting('social_linkedin') }}" target="_blank"><i class="fab fa-linkedin"></i></a>
                            @endif
                            @if(setting('social_pinterest', ''))
                                <a href="{{ setting('social_pinterest') }}" target="_blank"><i class="fab fa-pinterest-square"></i></a>
                            @endif
                            @if(setting('social_youtube', ''))
                                <a href="{{ setting('social_youtube') }}" target="_blank"><i class="fab fa-youtube"></i></a>
                            @endif
                            @if(setting('social_google_plus', ''))
                                <a href="{{ setting('social_google_plus') }}" target="_blank"><i class="fab fa-google-plus-g"></i></a>
                            @endif
                        </div>
                     </div>
                   </div>
                </div>

                <div class="col-xl-2 col-lg-2 col-md-4 col-sm-5">
                    <div class="single-footer-caption mb-50">
                        @php
                            // Verificar si existe un menú para el área footer1
                            $pdo = \Screenart\Musedock\Database::connect();
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_menus WHERE location = 'footer1'");
                            $stmt->execute();
                            $hasFooter1Menu = (int)$stmt->fetchColumn() > 0;
                            
                            $footer1Title = '';
                            if ($hasFooter1Menu) {
                                $stmt = $pdo->prepare("
                                    SELECT mt.title
                                    FROM site_menus m
                                    JOIN site_menu_translations mt ON m.id = mt.menu_id
                                    WHERE m.location = 'footer1' AND mt.locale = ?
                                    ORDER BY mt.id DESC LIMIT 1
                                ");
                                $stmt->execute([setting('language', 'es')]);
                                $footer1Title = $stmt->fetchColumn();
                            }
                        @endphp
                        
                        @if($hasFooter1Menu)
                            {{-- Si tenemos un menú definido para footer1, mostrarlo --}}
                            <div class="footer-tittle">
                                @if($footer1Title)
                                    <h4>{{ $footer1Title }}</h4>
                                @endif
                                @custommenu('footer1', null, [
                                    'nav_class' => '',
                                    'li_class' => '',
                                    'a_class' => '',
                                    'submenu_class' => 'submenu'
                                ])
                            </div>
                        @else
                            {{-- Si no hay menú definido, intentar mostrar widgets --}}
                            @include('partials.widget-renderer', ['areaSlug' => 'footer1'])
                        @endif
                    </div>
                </div>

                <div class="col-xl-3 col-lg-3 col-md-4 col-sm-7">
                    <div class="single-footer-caption mb-50">
                        @php
                            // Verificar si existe un menú para el área footer2
                            $pdo = \Screenart\Musedock\Database::connect();
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_menus WHERE location = 'footer2'");
                            $stmt->execute();
                            $hasFooter2Menu = (int)$stmt->fetchColumn() > 0;
                            
                            $footer2Title = '';
                            if ($hasFooter2Menu) {
                                $stmt = $pdo->prepare("
                                    SELECT mt.title
                                    FROM site_menus m
                                    JOIN site_menu_translations mt ON m.id = mt.menu_id
                                    WHERE m.location = 'footer2' AND mt.locale = ?
                                    ORDER BY mt.id DESC LIMIT 1
                                ");
                                $stmt->execute([setting('language', 'es')]);
                                $footer2Title = $stmt->fetchColumn();
                            }
                        @endphp
                        
                        @if($hasFooter2Menu)
                            {{-- Si tenemos un menú definido para footer2, mostrarlo --}}
                            <div class="footer-tittle">
                                @if($footer2Title)
                                    <h4>{{ $footer2Title }}</h4>
                                @endif
                                @custommenu('footer2', null, [
                                    'nav_class' => '',
                                    'li_class' => '',
                                    'a_class' => '',
                                    'submenu_class' => 'submenu'
                                ])
                            </div>
                        @else
                            {{-- Si no hay menú definido, intentar mostrar widgets --}}
                            @include('partials.widget-renderer', ['areaSlug' => 'footer2'])
                        @endif
                    </div>
                </div>

                <div class="col-xl-3 col-lg-3 col-md-4 col-sm-5">
                    <div class="single-footer-caption mb-50">
                        @php
                            // Verificar si existe un menú para el área footer3
                            $pdo = \Screenart\Musedock\Database::connect();
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_menus WHERE location = 'footer3'");
                            $stmt->execute();
                            $hasFooter3Menu = (int)$stmt->fetchColumn() > 0;
                            
                            $footer3Title = '';
                            if ($hasFooter3Menu) {
                                $stmt = $pdo->prepare("
                                    SELECT mt.title
                                    FROM site_menus m
                                    JOIN site_menu_translations mt ON m.id = mt.menu_id
                                    WHERE m.location = 'footer3' AND mt.locale = ?
                                    ORDER BY mt.id DESC LIMIT 1
                                ");
                                $stmt->execute([setting('language', 'es')]);
                                $footer3Title = $stmt->fetchColumn();
                            }
                        @endphp
                        
                        @if($hasFooter3Menu)
                            {{-- Si tenemos un menú definido para footer3, mostrarlo --}}
                            <div class="footer-tittle">
                                @if($footer3Title)
                                    <h4>{{ $footer3Title }}</h4>
                                @endif
                                @custommenu('footer3', null, [
                                    'nav_class' => '',
                                    'li_class' => '',
                                    'a_class' => '',
                                    'submenu_class' => 'submenu'
                                ])
                            </div>
                        @elseif(isset($widgetContent) && isset($widgetContent['footer3']))
                            {{-- Si hay widgets para footer3, mostrarlos --}}
                            @include('partials.widget-renderer', ['areaSlug' => 'footer3'])
                        @else
                            {{-- Si no hay ni menú ni widgets, mostrar info de contacto --}}
                            <div class="footer-tittle">
                                <h4>{{ setting('footer_col4_title', 'Contacto') }}</h4>
                                <ul>
                                    @if(setting('contact_phone'))<li><a href="tel:{{ setting('contact_phone') }}">{{ setting('contact_phone') }}</a></li>@endif
                                    @if(setting('contact_email'))<li><a href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a></li>@endif
                                    @if(setting('contact_address'))<li><a href="#">{{ setting('contact_address') }}</a></li>@endif
                                    @if(setting('contact_whatsapp'))<li><a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', setting('contact_whatsapp')) }}"><i class="fab fa-whatsapp"></i> {{ setting('contact_whatsapp') }}</a></li>@endif
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- footer-bottom area -->
    <div class="footer-bottom-area footer-bg">
        <div class="container">
            <div class="footer-border">
                <div class="row d-flex align-items-center">
                    <div class="col-xl-12 ">
                        <div class="footer-copy-right text-center">
                            <p>
                                {!! setting('footer_copyright', '© Copyright MuseDock ' . date('Y') . '.') !!}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End-->
</footer>