@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Ajustes SEO y Social</h3>
  </div>

  <div class="card-body">
    <form method="POST" action="/{{ admin_path() }}/settings/seo" enctype="multipart/form-data">
      {!! csrf_field() !!}

      <!-- Sección de SEO -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-search me-2"></i>Configuración SEO</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Palabras clave (keywords)</label>
            <input type="text" name="site_keywords" class="form-control" value="{{ $settings['site_keywords'] ?? '' }}">
            <small class="text-muted">Palabras clave separadas por comas (ej: cms, web, contenido)</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Autor del sitio</label>
            <input type="text" name="site_author" class="form-control" value="{{ $settings['site_author'] ?? '' }}">
            <small class="text-muted">Nombre del autor o empresa propietaria del sitio</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Imagen para compartir en redes (Open Graph)</label>
            <input type="file" name="og_image" class="form-control" accept="image/*">
            <small class="text-muted">Tamaño recomendado: 1200x630 píxeles</small>

            @if(!empty($settings['og_image']))
              <div class="mt-2">
                <img src="/public/{{ $settings['og_image'] }}" alt="Imagen OG actual" style="max-height: 150px; max-width: 100%;" class="mt-2 border p-2 rounded">
                <p class="text-muted mt-1"><small>Imagen actual: {{ $settings['og_image'] }}</small></p>
              </div>
            @endif
          </div>

          <div class="mb-3">
            <label class="form-label">Usuario de Twitter</label>
            <div class="input-group">
              <span class="input-group-text">@</span>
              <input type="text" name="twitter_site" class="form-control" value="{{ $settings['twitter_site'] ?? '' }}" placeholder="usuario">
            </div>
            <small class="text-muted">Sin incluir el @ (ej: musedock)</small>
          </div>
        </div>
      </div>

      <!-- Sección de redes sociales -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-share me-2"></i>Redes Sociales</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Facebook</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-facebook"></i></span>
                  <input type="url" name="social_facebook" class="form-control" value="{{ $settings['social_facebook'] ?? '' }}" placeholder="https://facebook.com/tuempresa">
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Twitter / X</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-twitter-x"></i></span>
                  <input type="url" name="social_twitter" class="form-control" value="{{ $settings['social_twitter'] ?? '' }}" placeholder="https://twitter.com/tuempresa">
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Instagram</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-instagram"></i></span>
                  <input type="url" name="social_instagram" class="form-control" value="{{ $settings['social_instagram'] ?? '' }}" placeholder="https://instagram.com/tuempresa">
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">LinkedIn</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-linkedin"></i></span>
                  <input type="url" name="social_linkedin" class="form-control" value="{{ $settings['social_linkedin'] ?? '' }}" placeholder="https://linkedin.com/company/tuempresa">
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">YouTube</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-youtube"></i></span>
                  <input type="url" name="social_youtube" class="form-control" value="{{ $settings['social_youtube'] ?? '' }}" placeholder="https://youtube.com/c/tuempresa">
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Pinterest</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-pinterest"></i></span>
                  <input type="url" name="social_pinterest" class="form-control" value="{{ $settings['social_pinterest'] ?? '' }}" placeholder="https://pinterest.com/tuempresa">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save"></i> Guardar cambios
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
