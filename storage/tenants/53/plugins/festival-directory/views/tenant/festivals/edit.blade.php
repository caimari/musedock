@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <a href="{{ festival_admin_url() }}" class="text-muted text-decoration-none">Festivales</a>
        <span class="mx-1 text-muted">/</span>
        <span>Editar: {{ $festival->name ?? '' }}</span>
      </div>
      <a href="{{ festival_admin_url() }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
      </a>
    </div>

    @if(session('error'))
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({ icon: 'error', title: 'Error', html: {!! json_encode(session('error')) !!}, confirmButtonColor: '#d33' });
      });
    </script>
    @endif

    @if(session('success'))
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({ icon: 'success', title: 'OK', text: {!! json_encode(session('success')) !!}, confirmButtonColor: '#3085d6', timer: 3000 });
      });
    </script>
    @endif

    <form method="POST" action="{{ festival_admin_url($festival->id) }}" id="festivalForm">
      @csrf
      <input type="hidden" name="_method" value="PUT">

      <div class="row">
        {{-- Main Column --}}
        <div class="col-lg-9">

          {{-- Identity --}}
          <div class="card mb-4">
            <div class="card-header"><i class="bi bi-card-heading me-2"></i><strong>Identidad</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label for="name" class="form-label">Nombre del Festival <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-lg" name="name" id="name"
                       value="{{ old('name', $festival->name ?? '') }}" required placeholder="Ej: Festival Internacional de Cine de San Sebastián">
              </div>
              <div class="mb-3">
                <label for="slug" class="form-label">Slug</label>
                <div class="input-group">
                  <span class="input-group-text">/festivals/</span>
                  <input type="text" class="form-control" name="slug" id="slug"
                         value="{{ old('slug', $festival->slug ?? '') }}" placeholder="se-genera-automaticamente">
                </div>
              </div>
              <div class="mb-3">
                <label for="short_description" class="form-label">Descripción corta</label>
                <textarea class="form-control" name="short_description" id="short_description" rows="2"
                          maxlength="300" placeholder="Resumen breve para tarjetas y listados (max 300 caracteres)">{{ old('short_description', $festival->short_description ?? '') }}</textarea>
                <small class="text-muted"><span id="charCount">0</span>/300</small>
              </div>
              <div class="mb-3">
                <label for="description" class="form-label">Descripción completa</label>
                <textarea class="form-control" name="description" id="description" rows="8"
                          placeholder="Información detallada del festival...">{{ old('description', $festival->description ?? '') }}</textarea>
              </div>
            </div>
          </div>

          {{-- Location --}}
          <div class="card mb-4">
            <div class="card-header"><i class="bi bi-geo-alt me-2"></i><strong>Ubicación</strong></div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="country" class="form-label">País <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="country" id="country"
                         value="{{ old('country', $festival->country ?? '') }}" required placeholder="Ej: España">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="city" class="form-label">Ciudad</label>
                  <input type="text" class="form-control" name="city" id="city"
                         value="{{ old('city', $festival->city ?? '') }}" placeholder="Ej: San Sebastián">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="venue" class="form-label">Sede</label>
                  <input type="text" class="form-control" name="venue" id="venue"
                         value="{{ old('venue', $festival->venue ?? '') }}" placeholder="Ej: Palacio Kursaal">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="address" class="form-label">Dirección</label>
                  <input type="text" class="form-control" name="address" id="address"
                         value="{{ old('address', $festival->address ?? '') }}">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="latitude" class="form-label">Latitud</label>
                  <input type="text" class="form-control" name="latitude" id="latitude"
                         value="{{ old('latitude', $festival->latitude ?? '') }}" placeholder="43.3183">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="longitude" class="form-label">Longitud</label>
                  <input type="text" class="form-control" name="longitude" id="longitude"
                         value="{{ old('longitude', $festival->longitude ?? '') }}" placeholder="-1.9812">
                </div>
              </div>
            </div>
          </div>

          {{-- Dates & Edition --}}
          <div class="card mb-4">
            <div class="card-header"><i class="bi bi-calendar-event me-2"></i><strong>Fechas y Edición</strong></div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-3 mb-3">
                  <label for="edition_number" class="form-label">Nº de edición</label>
                  <input type="number" class="form-control" name="edition_number" id="edition_number"
                         value="{{ old('edition_number', $festival->edition_number ?? '') }}" placeholder="42">
                </div>
                <div class="col-md-3 mb-3">
                  <label for="edition_year" class="form-label">Año</label>
                  <input type="number" class="form-control" name="edition_year" id="edition_year"
                         value="{{ old('edition_year', $festival->edition_year ?? date('Y')) }}">
                </div>
                <div class="col-md-3 mb-3">
                  <label for="frequency" class="form-label">Frecuencia</label>
                  <select class="form-select" name="frequency" id="frequency">
                    @foreach($frequencies as $key => $label)
                      <option value="{{ $key }}" {{ old('frequency', $festival->frequency ?? 'annual') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label for="start_date" class="form-label">Fecha de inicio</label>
                  <input type="date" class="form-control" name="start_date" id="start_date"
                         value="{{ old('start_date', $festival->start_date ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="end_date" class="form-label">Fecha de fin</label>
                  <input type="date" class="form-control" name="end_date" id="end_date"
                         value="{{ old('end_date', $festival->end_date ?? '') }}">
                </div>
                <div class="col-md-4 mb-3">
                  <label for="deadline_date" class="form-label">Deadline <small class="text-muted">(informativo)</small></label>
                  <input type="date" class="form-control" name="deadline_date" id="deadline_date"
                         value="{{ old('deadline_date', $festival->deadline_date ?? '') }}">
                </div>
              </div>
            </div>
          </div>

          {{-- Contact & Social --}}
          <div class="card mb-4">
            <div class="card-header"><i class="bi bi-globe me-2"></i><strong>Contacto y Redes Sociales</strong></div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="website_url" class="form-label">Sitio web</label>
                  <input type="url" class="form-control" name="website_url" id="website_url"
                         value="{{ old('website_url', $festival->website_url ?? '') }}" placeholder="https://...">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="email" class="form-label">Email público</label>
                  <input type="email" class="form-control" name="email" id="email"
                         value="{{ old('email', $festival->email ?? '') }}" placeholder="info@festival.com">
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="phone" class="form-label">Teléfono</label>
                  <input type="text" class="form-control" name="phone" id="phone"
                         value="{{ old('phone', $festival->phone ?? '') }}">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="contact_email" class="form-label">Email de contacto <small class="text-muted">(privado, para claims)</small></label>
                  <input type="email" class="form-control" name="contact_email" id="contact_email"
                         value="{{ old('contact_email', $festival->contact_email ?? '') }}">
                </div>
              </div>
              <hr>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label class="form-label"><i class="bi bi-facebook me-1"></i>Facebook</label>
                  <input type="url" class="form-control form-control-sm" name="social_facebook"
                         value="{{ old('social_facebook', $festival->social_facebook ?? '') }}" placeholder="https://facebook.com/...">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label"><i class="bi bi-instagram me-1"></i>Instagram</label>
                  <input type="url" class="form-control form-control-sm" name="social_instagram"
                         value="{{ old('social_instagram', $festival->social_instagram ?? '') }}" placeholder="https://instagram.com/...">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label"><i class="bi bi-twitter-x me-1"></i>X (Twitter)</label>
                  <input type="url" class="form-control form-control-sm" name="social_twitter"
                         value="{{ old('social_twitter', $festival->social_twitter ?? '') }}" placeholder="https://x.com/...">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label"><i class="bi bi-youtube me-1"></i>YouTube</label>
                  <input type="url" class="form-control form-control-sm" name="social_youtube"
                         value="{{ old('social_youtube', $festival->social_youtube ?? '') }}" placeholder="https://youtube.com/...">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label"><i class="bi bi-vimeo me-1"></i>Vimeo</label>
                  <input type="url" class="form-control form-control-sm" name="social_vimeo"
                         value="{{ old('social_vimeo', $festival->social_vimeo ?? '') }}" placeholder="https://vimeo.com/...">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label"><i class="bi bi-linkedin me-1"></i>LinkedIn</label>
                  <input type="url" class="form-control form-control-sm" name="social_linkedin"
                         value="{{ old('social_linkedin', $festival->social_linkedin ?? '') }}" placeholder="https://linkedin.com/...">
                </div>
              </div>
            </div>
          </div>

          {{-- Submissions (enlaces externos, neutral) --}}
          <div class="card mb-4">
            <div class="card-header"><i class="bi bi-send me-2"></i><strong>Submissions</strong>
              <small class="text-muted ms-2">(enlaces externos)</small>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">FilmFreeway</label>
                  <input type="url" class="form-control" name="submission_filmfreeway_url"
                         value="{{ old('submission_filmfreeway_url', $festival->submission_filmfreeway_url ?? '') }}" placeholder="https://filmfreeway.com/...">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Festhome</label>
                  <input type="url" class="form-control" name="submission_festhome_url"
                         value="{{ old('submission_festhome_url', $festival->submission_festhome_url ?? '') }}" placeholder="https://festhome.com/...">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Otra plataforma</label>
                  <input type="url" class="form-control" name="submission_other_url"
                         value="{{ old('submission_other_url', $festival->submission_other_url ?? '') }}" placeholder="https://...">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Estado de submissions</label>
                  <select class="form-select" name="submission_status">
                    @foreach($submissionStatuses as $key => $label)
                      <option value="{{ $key }}" {{ old('submission_status', $festival->submission_status ?? 'closed') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
          </div>

          {{-- SEO --}}
          <div class="card mb-4">
            <div class="card-header"><i class="bi bi-search me-2"></i><strong>SEO</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Título SEO</label>
                <input type="text" class="form-control" name="seo_title"
                       value="{{ old('seo_title', $festival->seo_title ?? '') }}" placeholder="Título para buscadores">
              </div>
              <div class="mb-3">
                <label class="form-label">Descripción SEO</label>
                <textarea class="form-control" name="seo_description" rows="2"
                          placeholder="Descripción para buscadores">{{ old('seo_description', $festival->seo_description ?? '') }}</textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Keywords</label>
                <input type="text" class="form-control" name="seo_keywords"
                       value="{{ old('seo_keywords', $festival->seo_keywords ?? '') }}" placeholder="festival, cine, cortometraje...">
              </div>
              <div class="mb-3">
                <label class="form-label">Imagen SEO / OG</label>
                <input type="url" class="form-control" name="seo_image"
                       value="{{ old('seo_image', $festival->seo_image ?? '') }}" placeholder="https://...">
              </div>
              <div class="form-check">
                <input type="checkbox" class="form-check-input" name="noindex" id="noindex" value="1"
                       {{ old('noindex', $festival->noindex ?? 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="noindex">No indexar (noindex)</label>
              </div>
            </div>
          </div>

        </div>

        {{-- Sidebar --}}
        <div class="col-lg-3">

          {{-- Publish --}}
          <div class="card mb-4">
            <div class="card-header"><strong>Publicación</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Estado</label>
                <select class="form-select" name="status">
                  @foreach($statuses as $key => $label)
                    @if(in_array($key, ['draft', 'published']))
                      <option value="{{ $key }}" {{ old('status', $festival->status ?? 'draft') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endif
                  @endforeach
                </select>
                <small class="text-muted">Los estados "Verificado" y "Reclamado" se asignan automáticamente.</small>
              </div>
              <div class="mb-3">
                <label class="form-label">Tipo de festival</label>
                <select class="form-select" name="type">
                  @foreach($types as $key => $label)
                    <option value="{{ $key }}" {{ old('type', $festival->type ?? 'film_festival') === $key ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="featured" id="featured" value="1"
                       {{ old('featured', $festival->featured ?? 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="featured">Destacado</label>
              </div>
              <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check-lg me-1"></i> {{ $isNew ? 'Crear Festival' : 'Actualizar' }}
                </button>
              </div>
            </div>
          </div>

          {{-- Images --}}
          <div class="card mb-4">
            <div class="card-header"><strong>Imágenes</strong></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Logo</label>
                <input type="url" class="form-control form-control-sm" name="logo"
                       value="{{ old('logo', $festival->logo ?? '') }}" placeholder="URL del logo">
              </div>
              <div class="mb-3">
                <label class="form-label">Imagen destacada</label>
                <input type="url" class="form-control form-control-sm" name="featured_image"
                       value="{{ old('featured_image', $festival->featured_image ?? '') }}" placeholder="URL de imagen">
              </div>
              <div class="mb-3">
                <label class="form-label">Imagen de portada</label>
                <input type="url" class="form-control form-control-sm" name="cover_image"
                       value="{{ old('cover_image', $festival->cover_image ?? '') }}" placeholder="URL de imagen">
              </div>
            </div>
          </div>

          {{-- Categories --}}
          <div class="card mb-4">
            <div class="card-header"><strong>Categorías</strong></div>
            <div class="card-body">
              @if(!empty($categories) && count($categories) > 0)
                <select class="form-select form-select-sm" name="categories[]" multiple size="6">
                  @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ in_array($cat->id, $selectedCategories) ? 'selected' : '' }}>{{ $cat->name }}</option>
                  @endforeach
                </select>
                <small class="text-muted">Ctrl+click para seleccionar varias</small>
              @else
                <p class="text-muted mb-2">Sin categorías.</p>
              @endif
              <a href="{{ festival_admin_url('categories/create') }}" class="btn btn-sm btn-outline-primary mt-2 d-block">+ Nueva categoría</a>
            </div>
          </div>

          {{-- Tags --}}
          <div class="card mb-4">
            <div class="card-header"><strong>Tags</strong></div>
            <div class="card-body">
              @if(!empty($tags) && count($tags) > 0)
                <select class="form-select form-select-sm" name="tags[]" multiple size="5">
                  @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" {{ in_array($tag->id, $selectedTags) ? 'selected' : '' }}>{{ $tag->name }}</option>
                  @endforeach
                </select>
              @else
                <p class="text-muted mb-2">Sin tags.</p>
              @endif
              <a href="{{ festival_admin_url('tags/create') }}" class="btn btn-sm btn-outline-primary mt-2 d-block">+ Nuevo tag</a>
            </div>
          </div>

          {{-- Cancel --}}
          <div class="text-center">
            <a href="{{ festival_admin_url() }}" class="btn btn-sm btn-outline-secondary">Cancelar</a>
          </div>
        </div>
      </div>

    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Auto-slug from name
  const nameInput = document.getElementById('name');
  const slugInput = document.getElementById('slug');
  let slugManual = slugInput.value !== '';

  slugInput.addEventListener('input', function() { slugManual = this.value !== ''; });

  if (nameInput) {
    nameInput.addEventListener('input', function() {
      if (!slugManual) {
        slugInput.value = this.value
          .toLowerCase()
          .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-|-$/g, '');
      }
    });
  }

  // Char counter for short_description
  const descArea = document.getElementById('short_description');
  const charCount = document.getElementById('charCount');
  if (descArea && charCount) {
    charCount.textContent = descArea.value.length;
    descArea.addEventListener('input', function() {
      charCount.textContent = this.value.length;
    });
  }
});
</script>
@endpush
