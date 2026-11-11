@extends('layouts.app')

@section('title', $seoTitle . ' | ' . site_setting('site_name', 'Festivales'))
@section('description', $seoDesc)
@section('og_title', $seoTitle)
@section('og_description', $seoDesc)
@if($seoImage)
@section('og_image', $seoImage)
@endif

@section('content')
@include('festivals/partials/_assets')
<div class="container py-4 fd-scope">

  {{-- Breadcrumbs --}}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="{{ fd_path('/') }}">{{ fd_t('Inicio') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ fd_path('/festivals') }}">{{ fd_t('Festivales') }}</a></li>
      <li class="breadcrumb-item active">{{ $festival->name }}</li>
    </ol>
  </nav>

  {{-- Cover Image --}}
  @if($festival->cover_image)
  <div class="mb-4 rounded overflow-hidden" style="max-height:350px;">
    <img src="{{ $festival->cover_image }}" alt="{{ $festival->name }}" class="w-100" style="object-fit:cover;max-height:350px;">
  </div>
  @endif

  <div class="row">
    {{-- Main Content --}}
    <div class="col-lg-8">

      {{-- Header --}}
      <div class="d-flex align-items-start mb-4">
        @if($festival->logo)
        @php
          $__logoBg = class_exists('\FestivalDirectory\Services\Shortcode')
              ? \FestivalDirectory\Services\Shortcode::logoBg((string) $festival->logo, $festival->logo_bg ?? null)
              : '';
        @endphp
        <div class="me-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="{{ $__logoBg ? 'background:' . $__logoBg . ';padding:12px 18px;border-radius:8px;' : '' }}">
          <img src="{{ $festival->logo }}" alt="{{ $festival->name }} logo" style="max-width:170px;max-height:84px;width:auto;height:auto;object-fit:contain;display:block;">
        </div>
        @endif
        <div>
          <h1 class="h2 mb-1">{{ $festival->name }}
            @if($festival->status === 'verified' || $festival->status === 'claimed')
              <i class="bi bi-patch-check-fill text-primary" title="{{ fd_t('Perfil verificado') }}"></i>
            @endif
          </h1>
          <p class="text-muted mb-0">
            <i class="bi bi-geo-alt me-1"></i>{{ $festival->city ? $festival->city . ', ' : '' }}{{ $festival->country }}
            @if($festival->edition_number || $festival->edition_year)
              <span class="mx-2">|</span>
              @if($festival->edition_number){{ fd_edition($festival->edition_number) }} @endif
              @if($festival->edition_year)({{ $festival->edition_year }})@endif
            @endif
          </p>
        </div>
      </div>

      {{-- Badges --}}
      <div class="mb-3">
        <span class="badge bg-secondary">{{ fd_t($types[$festival->type] ?? $festival->type) }}</span>
        @if($festival->frequency && $festival->frequency !== 'annual')
          <span class="badge bg-info text-dark">{{ fd_t($frequencies[$festival->frequency] ?? $festival->frequency) }}</span>
        @endif
        @if($festival->submission_status === 'open')
          <span class="badge bg-success"><i class="bi bi-envelope-open me-1"></i>{{ fd_t('Submissions abiertas') }}</span>
        @elseif($festival->submission_status === 'upcoming')
          <span class="badge bg-warning text-dark">{{ fd_t('Submissions próximamente') }}</span>
        @endif
        @foreach($categories as $cat)
          <a href="{{ festival_url($cat->slug, 'category') }}" class="badge rounded-pill text-decoration-none" style="background:{{ $cat->color ?? '#6c757d' }}">{{ fd_t($cat->name) }}</a>
        @endforeach
      </div>

      {{-- Description --}}
      @php
        $__desc = fd_tr($festival, 'description');
        $__short = fd_tr($festival, 'short_description');
      @endphp
      @if($__desc)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">{{ fd_t('Sobre el festival') }}</h5>
          <div class="festival-description">{!! nl2br(e($__desc)) !!}</div>
        </div>
      </div>
      @elseif($__short)
      <div class="card mb-4">
        <div class="card-body">
          <p class="mb-0">{{ $__short }}</p>
        </div>
      </div>
      @endif

      {{-- Noticias y entrevistas (titular, fecha y extracto corto; el texto completo está en la web del festival) --}}
      @php
        $__per = preg_match('/^(3|10|25|50|100|all)$/', (string) ($_GET['news_per'] ?? '')) ? (string) $_GET['news_per'] : '';
        $__np = class_exists('\FestivalDirectory\Services\NewsBlock') ? \FestivalDirectory\Services\NewsBlock::pageFor($festival, (int) ($_GET['news_page'] ?? 1), $__per) : ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0, 'per' => ''];
        $__news = $__np['items'];
        // Enlace a una página/tamaño del bloque, conservando el tamaño elegido
        $__nl = function ($p, $per) use ($festival) {
            $q = [];
            if ($p > 1) { $q[] = 'news_page=' . (int) $p; }
            if ($per !== '') { $q[] = 'news_per=' . $per; }
            return festival_url($festival->slug) . ($q ? '?' . implode('&', $q) : '') . '#news';
        };
      @endphp
      @if(!empty($__news))
      <div class="card mb-4 fd-news" id="news">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-newspaper me-2"></i>{{ fd_t('Noticias y entrevistas') }}</h5>
          <ul class="list-unstyled mb-0">
            @foreach($__news as $__n)
            <li class="mb-3">
              <a href="{{ $__n['url'] }}" target="_blank" rel="noopener" class="fw-semibold">{{ $__n['title'] }}</a>
              @if(!empty($__n['published_at']))<small class="text-muted d-block">{{ date('d M Y', strtotime($__n['published_at'])) }}</small>@endif
              @if(!empty($__n['excerpt']))<span class="small d-block">{{ $__n['excerpt'] }}</span>@endif
            </li>
            @endforeach
          </ul>
          @if($__np['total'] > 3)
          <div class="small text-muted mt-2 mb-2">
            {{ fd_t('Mostrar') }}:
            @foreach(['3', '10', '25', '50', '100', 'all'] as $__c)
              @php $__active = ($__np['per'] === $__c); @endphp
              <a href="{{ $__nl(1, $__c) }}" rel="nofollow" class="{{ $__active ? 'fw-bold text-decoration-none' : '' }}">{{ $__c === 'all' ? fd_t('Todas') : $__c }}</a>{{ $__c !== 'all' ? ' · ' : '' }}
            @endforeach
          </div>
          @endif
          @if($__np['pages'] > 1)
          <nav aria-label="{{ fd_t('Noticias y entrevistas') }}" class="mb-3">
            <ul class="pagination pagination-sm mb-0 flex-wrap">
              @if($__np['page'] > 1)
                <li class="page-item"><a class="page-link" href="{{ $__nl($__np['page'] - 1, $__np['per']) }}" rel="nofollow">&laquo;</a></li>
              @endif
              @for($__i = max(1, $__np['page'] - 2); $__i <= min($__np['pages'], $__np['page'] + 2); $__i++)
                <li class="page-item {{ $__i === $__np['page'] ? 'active' : '' }}"><a class="page-link" href="{{ $__nl($__i, $__np['per']) }}" rel="nofollow">{{ $__i }}</a></li>
              @endfor
              @if($__np['page'] < $__np['pages'])
                <li class="page-item"><a class="page-link" href="{{ $__nl($__np['page'] + 1, $__np['per']) }}" rel="nofollow">&raquo;</a></li>
              @endif
            </ul>
          </nav>
          @endif
          @if($festival->website_url)
          <a href="{{ $festival->website_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-1">{{ fd_t('Ver más en la web del festival') }}</a>
          @endif
        </div>
      </div>
      @endif

      {{-- Dates --}}
      @if($festival->start_date || $festival->deadline_date)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-calendar-event me-2"></i>{{ fd_t('Fechas') }}</h5>
          <div class="row">
            @if($festival->start_date)
            <div class="col-md-4 mb-2">
              <small class="text-muted d-block">{{ fd_t('Fecha de inicio') }}</small>
              <strong>{{ date('d M Y', strtotime($festival->start_date)) }}</strong>
            </div>
            @endif
            @if($festival->end_date)
            <div class="col-md-4 mb-2">
              <small class="text-muted d-block">{{ fd_t('Fecha de fin') }}</small>
              <strong>{{ date('d M Y', strtotime($festival->end_date)) }}</strong>
            </div>
            @endif
            @if($festival->deadline_date)
            <div class="col-md-4 mb-2">
              <small class="text-muted d-block">{{ fd_t('Plazo de inscripción') }}</small>
              <strong>{{ date('d M Y', strtotime($festival->deadline_date)) }}</strong>
            </div>
            @endif
          </div>
        </div>
      </div>
      @endif

      {{-- Venue --}}
      @if($festival->venue || $festival->address)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-building me-2"></i>{{ fd_t('Sede') }}</h5>
          @if($festival->venue)<p class="mb-1"><strong>{{ $festival->venue }}</strong></p>@endif
          @if($festival->address)<p class="text-muted mb-0">{{ $festival->address }}</p>@endif
        </div>
      </div>
      @endif

    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">

      {{-- Submission Links (NEUTRAL - multi-platform) --}}
      @php
        $hasSubmissions = $festival->submission_filmfreeway_url || $festival->submission_festhome_url ||
                          $festival->submission_festgate_url || $festival->submission_other_url;
      @endphp
      @if($hasSubmissions)
      <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white"><i class="bi bi-send me-2"></i><strong>{{ fd_t('Enviar tu película') }}</strong></div>
        <div class="card-body">
          @if($festival->submission_filmfreeway_url)
          <a href="{{ $festival->submission_filmfreeway_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark btn-sm w-100 mb-2">
            <i class="bi bi-box-arrow-up-right me-1"></i> FilmFreeway
          </a>
          @endif
          @if($festival->submission_festhome_url)
          <a href="{{ $festival->submission_festhome_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark btn-sm w-100 mb-2">
            <i class="bi bi-box-arrow-up-right me-1"></i> Festhome
          </a>
          @endif
          @if($festival->submission_festgate_url)
          <a href="{{ $festival->submission_festgate_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm w-100 mb-2">
            <i class="bi bi-box-arrow-up-right me-1"></i> FestGate
          </a>
          @endif
          @if($festival->submission_other_url)
          <a href="{{ $festival->submission_other_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm w-100 mb-2">
            <i class="bi bi-box-arrow-up-right me-1"></i> {{ fd_t('Otra plataforma') }}
          </a>
          @endif
        </div>
      </div>
      @endif

      {{-- Contact & Links --}}
      <div class="card mb-4">
        <div class="card-header"><strong>{{ fd_t('Información') }}</strong></div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            @if($festival->website_url)
            <li class="mb-2"><i class="bi bi-globe me-2 text-primary"></i><a href="{{ $festival->website_url }}" target="_blank" rel="noopener noreferrer">{{ fd_t('Sitio web oficial') }}</a></li>
            @endif
            @if($festival->email)
            <li class="mb-2"><i class="bi bi-envelope me-2 text-primary"></i><a href="mailto:{{ $festival->email }}">{{ $festival->email }}</a></li>
            @endif
            @if($festival->phone)
            <li class="mb-2"><i class="bi bi-telephone me-2 text-primary"></i>{{ $festival->phone }}</li>
            @endif
          </ul>
        </div>
      </div>

      {{-- Social --}}
      @php
        $socials = [
            'facebook'  => ['icon' => 'bi bi-facebook',   'url' => $festival->social_facebook],
            'instagram' => ['icon' => 'bi bi-instagram',  'url' => $festival->social_instagram],
            'twitter'   => ['icon' => 'bi bi-twitter-x',  'url' => $festival->social_twitter],
            'youtube'   => ['icon' => 'bi bi-youtube',    'url' => $festival->social_youtube],
            'vimeo'     => ['icon' => 'bi bi-vimeo',      'url' => $festival->social_vimeo],
            'linkedin'  => ['icon' => 'bi bi-linkedin',   'url' => $festival->social_linkedin],
            'imdb'      => ['icon' => 'fab fa-imdb',      'url' => $festival->social_imdb ?? null],
        ];
        $hasSocials = array_filter($socials, fn($s) => !empty($s['url']));
      @endphp
      @if(!empty($hasSocials))
      <div class="card mb-4">
        <div class="card-header"><strong>{{ fd_t('Redes sociales') }}</strong></div>
        <div class="card-body">
          <div class="fd-social-row">
            @foreach($hasSocials as $name => $social)
              <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="fd-social" title="{{ ucfirst($name) }}" aria-label="{{ ucfirst($name) }}">
                <i class="{{ $social['icon'] }}"></i>
              </a>
            @endforeach
          </div>
        </div>
      </div>
      @endif

      {{-- Tags --}}
      @if(!empty($tags))
      <div class="card mb-4">
        <div class="card-header"><strong>Tags</strong></div>
        <div class="card-body">
          @foreach($tags as $tag)
            <a href="{{ festival_url($tag->slug, 'tag') }}" class="badge bg-light text-dark border text-decoration-none me-1 mb-1">{{ fd_t($tag->name) }}</a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Claim CTA --}}
      @if($festival->status !== 'claimed' && $festival->status !== 'verified' && !$hasPendingClaim)
      <div class="card mb-4 border-warning">
        <div class="card-body text-center">
          <i class="bi bi-shield-check" style="font-size:2rem;color:#ffc107"></i>
          <h6 class="mt-2">{{ fd_t('¿Es tu festival?') }}</h6>
          <p class="small text-muted">{{ fd_t('Si eres el organizador, reclama este perfil para gestionar tu información.') }}</p>
          <button type="button" class="btn btn-warning btn-sm" id="claimBtn">
            <i class="bi bi-shield-plus me-1"></i> {{ fd_t('Reclamar este perfil') }}
          </button>
        </div>
      </div>
      @endif

      {{-- Stats & Last updated --}}
      <div class="card mb-4 fd-stats">
        <div class="card-header"><strong>{{ fd_t('Ficha') }}</strong></div>
        <div class="card-body">
          <div class="fd-stat"><i class="bi bi-eye"></i><span><strong>{{ number_format($festival->view_count ?? 0) }}</strong> {{ fd_t('visitas') }}</span></div>
          @if($festival->updated_at)
          <div class="fd-stat"><i class="bi bi-clock"></i><span>{{ fd_t('Actualizado') }}: {{ date('d/m/Y', strtotime($festival->updated_at)) }}</span></div>
          @endif
        </div>
      </div>

    </div>
  </div>
</div>

{{-- JSON-LD --}}
@if(!empty($__jsonld_event))
<script type="application/ld+json">{!! $__jsonld_event !!}</script>
@endif
@if(!empty($__jsonld_breadcrumb))
<script type="application/ld+json">{!! $__jsonld_breadcrumb !!}</script>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const claimBtn = document.getElementById('claimBtn');
  if (claimBtn) {
    claimBtn.addEventListener('click', function() {
      Swal.fire({
        title: {!! json_encode(fd_t('Reclamar este perfil'), JSON_UNESCAPED_UNICODE) !!},
        html:
          '<p class="text-start small text-muted mb-3">' + {!! json_encode(e(fd_t('Si eres el organizador de este festival, completa el formulario para verificar tu identidad.')), JSON_UNESCAPED_UNICODE) !!} + '</p>' +
          '<input id="swal-name" class="swal2-input" placeholder="' + {!! json_encode(e(fd_t('Tu nombre completo')), JSON_UNESCAPED_UNICODE) !!} + '">' +
          '<input id="swal-email" class="swal2-input" placeholder="' + {!! json_encode(e(fd_t('Tu email')), JSON_UNESCAPED_UNICODE) !!} + '">' +
          '<input id="swal-role" class="swal2-input" placeholder="' + {!! json_encode(e(fd_t('Tu cargo (ej: Director, Programador)')), JSON_UNESCAPED_UNICODE) !!} + '">' +
          '<textarea id="swal-details" class="swal2-textarea" placeholder="' + {!! json_encode(e(fd_t('¿Cómo podemos verificar que representas a este festival?')), JSON_UNESCAPED_UNICODE) !!} + '"></textarea>' +
          '<div class="text-start mt-3 px-2">' +
            '<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:0.85rem;line-height:1.4">' +
              '<input type="checkbox" id="swal-authorized" style="margin-top:3px;min-width:16px">' +
              '<span>' + {!! json_encode(e(fd_t('Declaro que soy organizador o representante autorizado de este festival y que la información proporcionada es veraz.')), JSON_UNESCAPED_UNICODE) !!} + '</span>' +
            '</label>' +
          '</div>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: {!! json_encode(fd_t('Enviar solicitud'), JSON_UNESCAPED_UNICODE) !!},
        cancelButtonText: {!! json_encode(fd_t('Cancelar'), JSON_UNESCAPED_UNICODE) !!},
        confirmButtonColor: '#ffc107',
        preConfirm: () => {
          const name = document.getElementById('swal-name').value.trim();
          const email = document.getElementById('swal-email').value.trim();
          const role = document.getElementById('swal-role').value.trim();
          const details = document.getElementById('swal-details').value.trim();
          const authorized = document.getElementById('swal-authorized').checked;

          if (!name || !email || !role) {
            Swal.showValidationMessage({!! json_encode(fd_t('Nombre, email y cargo son obligatorios'), JSON_UNESCAPED_UNICODE) !!});
            return false;
          }
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            Swal.showValidationMessage({!! json_encode(fd_t('Email no válido'), JSON_UNESCAPED_UNICODE) !!});
            return false;
          }
          if (!authorized) {
            Swal.showValidationMessage({!! json_encode(fd_t('Debes confirmar que representas a este festival'), JSON_UNESCAPED_UNICODE) !!});
            return false;
          }

          return fetch('{{ festival_url($festival->slug) }}/claim', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
              user_name: name,
              user_email: email,
              user_role: role,
              verification_details: details,
              is_authorized: '1',
              _token: '{{ csrf_token() }}'
            })
          })
          .then(res => res.json())
          .then(data => {
            if (!data.success) {
              Swal.showValidationMessage(data.message || (data.errors ? data.errors.join(', ') : 'Error'));
              return false;
            }
            return data;
          })
          .catch(err => {
            Swal.showValidationMessage({!! json_encode(fd_t('Error de conexión'), JSON_UNESCAPED_UNICODE) !!});
            return false;
          });
        }
      }).then((result) => {
        if (result.isConfirmed && result.value) {
          Swal.fire({
            icon: 'success',
            title: {!! json_encode(fd_t('Solicitud enviada'), JSON_UNESCAPED_UNICODE) !!},
            text: result.value.message || {!! json_encode(fd_t('Te contactaremos pronto.'), JSON_UNESCAPED_UNICODE) !!},
            confirmButtonColor: '#3085d6'
          }).then(() => {
            location.reload();
          });
        }
      });
    });
  }
});
</script>
@endpush
