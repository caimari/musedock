{{-- Card para SEO General --}}
<div class="card mb-4">
  <div class="card-header">
    <strong>Optimización para Motores de Búsqueda (SEO) - Opcional</strong>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">Define cómo quieres que esta página aparezca en los resultados de búsqueda y redes sociales.</p>

    {{-- SEO Title --}}
    <div class="mb-3">
      <label for="seo_title" class="form-label">Título SEO</label>
      <input type="text" class="form-control" id="seo_title" name="seo_title" value="{{ old('seo_title', $Page->seo_title ?? '') }}" placeholder="Título para motores de búsqueda">
      <small class="text-muted">Si se deja vacío, se usará el título principal de la página.</small>
    </div>

    {{-- SEO Description --}}
    <div class="mb-3">
      <label for="seo_description" class="form-label">Descripción SEO</label>
      <textarea class="form-control" id="seo_description" name="seo_description" rows="3" placeholder="Descripción corta para motores de búsqueda (meta description)">{{ old('seo_description', $Page->seo_description ?? '') }}</textarea>
    </div>

    {{-- SEO Keywords (Opcional) --}}
    <div class="mb-3">
      <label for="seo_keywords" class="form-label">Palabras Clave SEO</label>
      <input type="text" class="form-control" id="seo_keywords" name="seo_keywords" value="{{ old('seo_keywords', $Page->seo_keywords ?? '') }}" placeholder="Palabras clave separadas por comas (opcional)">
      <small class="text-muted">Ej: cms, php, desarrollo web</small>
    </div>

    {{-- Canonical URL --}}
    <div class="mb-3">
      <label for="canonical_url" class="form-label">URL Canónica</label>
      <input type="url" class="form-control" id="canonical_url" name="canonical_url" value="{{ old('canonical_url', $Page->canonical_url ?? '') }}" placeholder="https://ejemplo.com/url-preferida">
      <small class="text-muted">Si esta página es duplicado de otra, indica aquí la URL original.</small>
    </div>

    {{-- Robots Directive --}}
    <div class="mb-3">
      <label for="robots_directive" class="form-label">Directiva para Robots</label>
      <select class="form-select" id="robots_directive" name="robots_directive">
         {{-- Usamos 'index,follow' como valor por defecto implícito si no hay valor guardado o old --}}
        @php $currentRobots = old('robots_directive', $Page->robots_directive ?? 'index,follow'); @endphp
        <option value="index,follow" @selected($currentRobots === 'index,follow')>Indexar y Seguir (index, follow)</option>
        <option value="noindex,follow" @selected($currentRobots === 'noindex,follow')>No Indexar pero Seguir (noindex, follow)</option>
        <option value="index,nofollow" @selected($currentRobots === 'index,nofollow')>Indexar pero No Seguir (index, nofollow)</option>
        <option value="noindex,nofollow" @selected($currentRobots === 'noindex,nofollow')>No Indexar y No Seguir (noindex, nofollow)</option>
         {{-- Opción para permitir NULL si es necesario (descomentar si se prefiere) --}}
         {{-- <option value="" @selected($currentRobots === null || $currentRobots === '')>Permitir valor por defecto del sistema</option> --}}
      </select>
      <small class="text-muted">Indica a los buscadores si deben indexar y seguir los enlaces de esta página.</small>
    </div>

     {{-- SEO Image (Ruta o URL) --}}
     <div class="mb-3">
         <label for="seo_image" class="form-label">Imagen SEO / Open Graph</label>
         <input type="text" class="form-control" id="seo_image" name="seo_image" value="{{ old('seo_image', $Page->seo_image ?? '') }}" placeholder="URL de la imagen principal para compartir">
         <small class="text-muted">Idealmente 1200x630px. Pega la URL completa.</small>
         {{-- Aquí podrías integrar un gestor de medios si tienes uno --}}
     </div>

  </div>
</div> {{-- Fin Card SEO General --}}

{{-- Card para X (Twitter) Cards --}}
<div class="card mb-4">
  <div class="card-header">
    <strong>Tarjetas de X / Twitter (Opcional)</strong>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">Personaliza cómo se muestra esta página cuando se comparte en X (antes Twitter). Estos datos son específicos para X y usan el formato "Twitter Cards".</p>

    {{-- Twitter Title --}}
    <div class="mb-3">
        <label for="twitter_title" class="form-label">Título para X</label>
        <input type="text" class="form-control" id="twitter_title" name="twitter_title" value="{{ old('twitter_title', $Page->twitter_title ?? '') }}">
        <small class="text-muted">Si se deja vacío, se usará el Título SEO o el título principal.</small>
    </div>

    {{-- Twitter Description --}}
    <div class="mb-3">
        <label for="twitter_description" class="form-label">Descripción para X</label>
        <textarea class="form-control" id="twitter_description" name="twitter_description" rows="2">{{ old('twitter_description', $Page->twitter_description ?? '') }}</textarea>
        <small class="text-muted">Si se deja vacía, se usará la Descripción SEO.</small>
    </div>

    {{-- Twitter Image --}}
    <div class="mb-3">
        <label for="twitter_image" class="form-label">Imagen para X</label>
        <input type="text" class="form-control" id="twitter_image" name="twitter_image" value="{{ old('twitter_image', $Page->twitter_image ?? '') }}" placeholder="URL de la imagen específica para X">
         <small class="text-muted">Si se deja vacía, se usará la Imagen SEO/Open Graph. Idealmente formato cuadrado o 2:1.</small>
    </div>

  </div>
</div> {{-- Fin Card X/Twitter --}}