@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Ajustes SEO y Social</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('settings.seo.update') }}" enctype="multipart/form-data">
      {!! csrf_field() !!}
      
      <!-- Sección de SEO -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Configuración SEO</h5>
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
                <img src="{{ asset($settings['og_image']) }}" alt="Imagen OG actual" style="max-height: 150px; max-width: 100%;" class="mt-2 border p-2">
                <p class="text-muted mt-1">Imagen actual: {{ $settings['og_image'] }}</p>
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
          <h5 class="mb-0">Redes Sociales</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Facebook</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fab fa-facebook"></i></span>
              <input type="url" name="social_facebook" class="form-control" value="{{ $settings['social_facebook'] ?? '' }}" placeholder="https://facebook.com/tuempresa">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Twitter</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fab fa-twitter"></i></span>
              <input type="url" name="social_twitter" class="form-control" value="{{ $settings['social_twitter'] ?? '' }}" placeholder="https://twitter.com/tuempresa">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Instagram</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fab fa-instagram"></i></span>
              <input type="url" name="social_instagram" class="form-control" value="{{ $settings['social_instagram'] ?? '' }}" placeholder="https://instagram.com/tuempresa">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">LinkedIn</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fab fa-linkedin"></i></span>
              <input type="url" name="social_linkedin" class="form-control" value="{{ $settings['social_linkedin'] ?? '' }}" placeholder="https://linkedin.com/company/tuempresa">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">YouTube</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fab fa-youtube"></i></span>
              <input type="url" name="social_youtube" class="form-control" value="{{ $settings['social_youtube'] ?? '' }}" placeholder="https://youtube.com/c/tuempresa">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Pinterest</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fab fa-pinterest"></i></span>
              <input type="url" name="social_pinterest" class="form-control" value="{{ $settings['social_pinterest'] ?? '' }}" placeholder="https://pinterest.com/tuempresa">
            </div>
          </div>
        </div>
      </div>
      
      <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-2"></i>Guardar cambios
        </button>
      </div>
    </form>
  </div>
</div>
@endsection