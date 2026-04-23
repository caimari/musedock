@extends('layouts.app')

@section('title', $seoTitle . ' | ' . site_setting('site_name', 'FestivalNews'))
@section('description', $seoDesc)
@section('og_title', $seoTitle)
@section('og_description', $seoDesc)
@if($seoImage)
@section('og_image', $seoImage)
@endif

@section('content')
<div class="container py-4">

  {{-- Breadcrumbs --}}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="/">Inicio</a></li>
      <li class="breadcrumb-item"><a href="/festivals">Festivales</a></li>
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
        <img src="{{ $festival->logo }}" alt="{{ $festival->name }} logo" class="me-3 rounded" style="width:80px;height:80px;object-fit:cover;">
        @endif
        <div>
          <h1 class="h2 mb-1">{{ $festival->name }}
            @if($festival->status === 'verified' || $festival->status === 'claimed')
              <i class="bi bi-patch-check-fill text-primary" title="Perfil verificado"></i>
            @endif
          </h1>
          <p class="text-muted mb-0">
            <i class="bi bi-geo-alt me-1"></i>{{ $festival->city ? $festival->city . ', ' : '' }}{{ $festival->country }}
            @if($festival->edition_number || $festival->edition_year)
              <span class="mx-2">|</span>
              @if($festival->edition_number){{ $festival->edition_number }}ª edición @endif
              @if($festival->edition_year)({{ $festival->edition_year }})@endif
            @endif
          </p>
        </div>
      </div>

      {{-- Badges --}}
      <div class="mb-3">
        <span class="badge bg-secondary">{{ $types[$festival->type] ?? $festival->type }}</span>
        @if($festival->frequency && $festival->frequency !== 'annual')
          <span class="badge bg-info text-dark">{{ $frequencies[$festival->frequency] ?? $festival->frequency }}</span>
        @endif
        @if($festival->submission_status === 'open')
          <span class="badge bg-success"><i class="bi bi-envelope-open me-1"></i>Submissions abiertas</span>
        @elseif($festival->submission_status === 'upcoming')
          <span class="badge bg-warning text-dark">Submissions próximamente</span>
        @endif
        @foreach($categories as $cat)
          <a href="{{ festival_url($cat->slug, 'category') }}" class="badge rounded-pill text-decoration-none" style="background:{{ $cat->color ?? '#6c757d' }}">{{ $cat->name }}</a>
        @endforeach
      </div>

      {{-- Description --}}
      @if($festival->description)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Sobre el festival</h5>
          <div class="festival-description">{!! nl2br(e($festival->description)) !!}</div>
        </div>
      </div>
      @elseif($festival->short_description)
      <div class="card mb-4">
        <div class="card-body">
          <p class="mb-0">{{ $festival->short_description }}</p>
        </div>
      </div>
      @endif

      {{-- Dates --}}
      @if($festival->start_date || $festival->deadline_date)
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-calendar-event me-2"></i>Fechas</h5>
          <div class="row">
            @if($festival->start_date)
            <div class="col-md-4 mb-2">
              <small class="text-muted d-block">Inicio</small>
              <strong>{{ date('d M Y', strtotime($festival->start_date)) }}</strong>
            </div>
            @endif
            @if($festival->end_date)
            <div class="col-md-4 mb-2">
              <small class="text-muted d-block">Fin</small>
              <strong>{{ date('d M Y', strtotime($festival->end_date)) }}</strong>
            </div>
            @endif
            @if($festival->deadline_date)
            <div class="col-md-4 mb-2">
              <small class="text-muted d-block">Deadline</small>
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
          <h5 class="card-title"><i class="bi bi-building me-2"></i>Sede</h5>
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
        <div class="card-header bg-primary text-white"><i class="bi bi-send me-2"></i><strong>Enviar tu película</strong></div>
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
            <i class="bi bi-box-arrow-up-right me-1"></i> Otra plataforma
          </a>
          @endif
        </div>
      </div>
      @endif

      {{-- Contact & Links --}}
      <div class="card mb-4">
        <div class="card-header"><strong>Información</strong></div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            @if($festival->website_url)
            <li class="mb-2"><i class="bi bi-globe me-2 text-primary"></i><a href="{{ $festival->website_url }}" target="_blank" rel="noopener noreferrer">Sitio web oficial</a></li>
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
            'facebook'  => ['icon' => 'bi-facebook',   'url' => $festival->social_facebook],
            'instagram' => ['icon' => 'bi-instagram',  'url' => $festival->social_instagram],
            'twitter'   => ['icon' => 'bi-twitter-x',  'url' => $festival->social_twitter],
            'youtube'   => ['icon' => 'bi-youtube',    'url' => $festival->social_youtube],
            'vimeo'     => ['icon' => 'bi-vimeo',      'url' => $festival->social_vimeo],
            'linkedin'  => ['icon' => 'bi-linkedin',   'url' => $festival->social_linkedin],
        ];
        $hasSocials = array_filter($socials, fn($s) => !empty($s['url']));
      @endphp
      @if(!empty($hasSocials))
      <div class="card mb-4">
        <div class="card-header"><strong>Redes sociales</strong></div>
        <div class="card-body">
          <div class="d-flex gap-2 flex-wrap">
            @foreach($hasSocials as $name => $social)
              <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark btn-sm" title="{{ ucfirst($name) }}">
                <i class="bi {{ $social['icon'] }}"></i>
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
            <a href="{{ festival_url($tag->slug, 'tag') }}" class="badge bg-light text-dark border text-decoration-none me-1 mb-1">{{ $tag->name }}</a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Claim CTA --}}
      @if($festival->status !== 'claimed' && $festival->status !== 'verified' && !$hasPendingClaim)
      <div class="card mb-4 border-warning">
        <div class="card-body text-center">
          <i class="bi bi-shield-check" style="font-size:2rem;color:#ffc107"></i>
          <h6 class="mt-2">¿Es tu festival?</h6>
          <p class="small text-muted">Si eres el organizador, reclama este perfil para gestionar tu información.</p>
          <button type="button" class="btn btn-warning btn-sm" id="claimBtn">
            <i class="bi bi-shield-plus me-1"></i> Reclamar este perfil
          </button>
        </div>
      </div>
      @endif

      {{-- Stats & Last updated --}}
      <div class="text-muted small text-center">
        <i class="bi bi-eye me-1"></i> {{ number_format($festival->view_count ?? 0) }} visitas
        @if($festival->updated_at)
          <br><i class="bi bi-clock me-1"></i> Actualizado: {{ date('d/m/Y', strtotime($festival->updated_at)) }}
        @endif
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
        title: 'Reclamar este perfil',
        html:
          '<p class="text-start small text-muted mb-3">Si eres el organizador de este festival, completa el formulario para verificar tu identidad.</p>' +
          '<input id="swal-name" class="swal2-input" placeholder="Tu nombre completo">' +
          '<input id="swal-email" class="swal2-input" placeholder="Tu email">' +
          '<input id="swal-role" class="swal2-input" placeholder="Tu cargo (ej: Director, Programador)">' +
          '<textarea id="swal-details" class="swal2-textarea" placeholder="¿Cómo podemos verificar que representas a este festival?"></textarea>' +
          '<div class="text-start mt-3 px-2">' +
            '<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:0.85rem;line-height:1.4">' +
              '<input type="checkbox" id="swal-authorized" style="margin-top:3px;min-width:16px">' +
              '<span>Declaro que soy organizador o representante autorizado de este festival y que la información proporcionada es veraz.</span>' +
            '</label>' +
          '</div>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Enviar solicitud',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ffc107',
        preConfirm: () => {
          const name = document.getElementById('swal-name').value.trim();
          const email = document.getElementById('swal-email').value.trim();
          const role = document.getElementById('swal-role').value.trim();
          const details = document.getElementById('swal-details').value.trim();
          const authorized = document.getElementById('swal-authorized').checked;

          if (!name || !email || !role) {
            Swal.showValidationMessage('Nombre, email y cargo son obligatorios');
            return false;
          }
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            Swal.showValidationMessage('Email no válido');
            return false;
          }
          if (!authorized) {
            Swal.showValidationMessage('Debes confirmar que representas a este festival');
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
            Swal.showValidationMessage('Error de conexión');
            return false;
          });
        }
      }).then((result) => {
        if (result.isConfirmed && result.value) {
          Swal.fire({
            icon: 'success',
            title: 'Solicitud enviada',
            text: result.value.message || 'Te contactaremos pronto.',
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
