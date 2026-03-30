{{-- Card para SEO General --}}
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Optimización para Motores de Búsqueda (SEO) - Opcional</strong>
    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-ai-seo" title="Generar campos SEO con IA">
      <i class="bi bi-magic"></i> Generar con IA
    </button>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">Define cómo quieres que esta página aparezca en los resultados de búsqueda y redes sociales.</p>

    {{-- SEO Title --}}
    <div class="mb-3">
      <label for="seo_title" class="form-label">Título SEO</label>
      <input type="text" class="form-control" id="seo_title" name="seo_title" value="{{ old('seo_title', $Page->seo_title ?? '') }}" placeholder="Título para motores de búsqueda" autocomplete="off">
      <small class="text-muted">Si se deja vacío, se usará el título principal de la página.</small>
    </div>

    {{-- SEO Description --}}
    <div class="mb-3">
      <label for="seo_description" class="form-label">Descripción SEO</label>
      <textarea class="form-control" id="seo_description" name="seo_description" rows="3" placeholder="Descripción corta para motores de búsqueda (meta description)" autocomplete="off">{{ old('seo_description', $Page->seo_description ?? '') }}</textarea>
    </div>

    {{-- SEO Keywords (Opcional) --}}
    <div class="mb-3">
      <label for="seo_keywords" class="form-label">Palabras Clave SEO</label>
      <input type="text" class="form-control" id="seo_keywords" name="seo_keywords" value="{{ old('seo_keywords', $Page->seo_keywords ?? '') }}" placeholder="Palabras clave separadas por comas (opcional)" autocomplete="off">
      <small class="text-muted">Ej: cms, php, desarrollo web</small>
    </div>

    {{-- Canonical URL --}}
    <div class="mb-3">
      <label for="canonical_url" class="form-label">URL Canónica</label>
      <input type="url" class="form-control" id="canonical_url" name="canonical_url" value="{{ old('canonical_url', $Page->canonical_url ?? '') }}" placeholder="https://ejemplo.com/url-preferida" autocomplete="off">
      <small class="text-muted">Si esta página es duplicado de otra, indica aquí la URL original.</small>
    </div>

    {{-- Robots Directive --}}
    <div class="mb-3">
      <label for="robots_directive" class="form-label">Directiva para Robots</label>
      <select class="form-select" id="robots_directive" name="robots_directive">
         @php $currentRobots = old('robots_directive', $Page->robots_directive ?? 'index,follow'); @endphp
        <option value="index,follow" @selected($currentRobots === 'index,follow')>Indexar y Seguir (index, follow)</option>
        <option value="noindex,follow" @selected($currentRobots === 'noindex,follow')>No Indexar pero Seguir (noindex, follow)</option>
        <option value="index,nofollow" @selected($currentRobots === 'index,nofollow')>Indexar pero No Seguir (index, nofollow)</option>
        <option value="noindex,nofollow" @selected($currentRobots === 'noindex,nofollow')>No Indexar y No Seguir (noindex, nofollow)</option>
      </select>
      <small class="text-muted">Indica a los buscadores si deben indexar y seguir los enlaces de esta página.</small>
    </div>

     {{-- SEO Image (Ruta o URL) --}}
     <div class="mb-3">
         <label for="seo_image" class="form-label">Imagen SEO / Open Graph</label>
         <input type="text" class="form-control" id="seo_image" name="seo_image" value="{{ old('seo_image', $Page->seo_image ?? '') }}" placeholder="URL de la imagen principal para compartir" autocomplete="off">
         <small class="text-muted">Idealmente 1200x630px. Se usa la imagen destacada si se deja vacío.</small>
     </div>

  </div>
</div>

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
        <input type="text" class="form-control" id="twitter_title" name="twitter_title" value="{{ old('twitter_title', $Page->twitter_title ?? '') }}" autocomplete="off">
        <small class="text-muted">Si se deja vacío, se usará el Título SEO o el título principal.</small>
    </div>

    {{-- Twitter Description --}}
    <div class="mb-3">
        <label for="twitter_description" class="form-label">Descripción para X</label>
        <textarea class="form-control" id="twitter_description" name="twitter_description" rows="2" autocomplete="off">{{ old('twitter_description', $Page->twitter_description ?? '') }}</textarea>
        <small class="text-muted">Si se deja vacía, se usará la Descripción SEO.</small>
    </div>

    {{-- Twitter Image --}}
    <div class="mb-3">
        <label for="twitter_image" class="form-label">Imagen para X</label>
        <input type="text" class="form-control" id="twitter_image" name="twitter_image" value="{{ old('twitter_image', $Page->twitter_image ?? '') }}" placeholder="URL de la imagen específica para X" autocomplete="new-password" data-lpignore="true" data-1p-ignore>
         <small class="text-muted">Si se deja vacía, se usará la Imagen SEO/Open Graph o la imagen destacada.</small>
    </div>

  </div>
</div>

{{-- Script: Auto-fill imágenes SEO desde imagen destacada + Generación IA --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Auto-sync featured image to SEO/OG/Twitter image fields ---
    const featuredInput = document.getElementById('featured_image');
    const seoImageInput = document.getElementById('seo_image');
    const twitterImageInput = document.getElementById('twitter_image');

    // Store the real saved values from PHP (before Chrome autocomplete can touch them)
    const savedSeoImage = {!! json_encode(old('seo_image', $Page->seo_image ?? '')) !!};
    const savedTwitterImage = {!! json_encode(old('twitter_image', $Page->twitter_image ?? '')) !!};

    function syncFeaturedImage(force) {
        if (!featuredInput) return;
        const url = featuredInput.value.trim();
        if (seoImageInput && !seoImageInput.value.trim()) {
            seoImageInput.value = url;
        }
        if (twitterImageInput && (!twitterImageInput.value.trim() || force)) {
            twitterImageInput.value = url || '';
        }
    }

    // On page load: restore real values and kill Chrome autocomplete junk
    setTimeout(function() {
        if (twitterImageInput) {
            const currentVal = twitterImageInput.value.trim();
            const isUrl = currentVal === '' || currentVal.startsWith('http') || currentVal.startsWith('/');
            if (!isUrl) {
                twitterImageInput.value = savedTwitterImage;
            }
            if (!twitterImageInput.value.trim() && featuredInput && featuredInput.value.trim()) {
                twitterImageInput.value = featuredInput.value.trim();
            }
        }
        if (seoImageInput) {
            const currentVal = seoImageInput.value.trim();
            const isUrl = currentVal === '' || currentVal.startsWith('http') || currentVal.startsWith('/');
            if (!isUrl) {
                seoImageInput.value = savedSeoImage;
            }
            if (!seoImageInput.value.trim() && featuredInput && featuredInput.value.trim()) {
                seoImageInput.value = featuredInput.value.trim();
            }
        }
    }, 200);

    if (featuredInput) {
        featuredInput.addEventListener('change', syncFeaturedImage);
        const observer = new MutationObserver(function() { syncFeaturedImage(); });
        observer.observe(featuredInput, { attributes: true, attributeFilter: ['value'] });
        let lastVal = featuredInput.value;
        setInterval(function() {
            if (featuredInput.value !== lastVal) {
                lastVal = featuredInput.value;
                syncFeaturedImage();
            }
        }, 1000);
    }

    // --- AI SEO Generation ---
    const btnAiSeo = document.getElementById('btn-ai-seo');
    if (!btnAiSeo) return;

    btnAiSeo.addEventListener('click', async function() {
        // Get content from TinyMCE or textarea
        const titleInput = document.getElementById('title') || document.querySelector('[name="title"]');
        const title = titleInput ? titleInput.value.trim() : '';

        let content = '';
        if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
            content = tinymce.activeEditor.getContent({ format: 'text' }).substring(0, 2000);
        } else {
            const contentArea = document.getElementById('content') || document.querySelector('[name="content"]');
            if (contentArea) content = contentArea.value.substring(0, 2000);
        }

        if (!title && !content) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Sin contenido', 'Escribe un título y contenido antes de generar el SEO.', 'warning');
            } else {
                alert('Escribe un título y contenido antes de generar el SEO.');
            }
            return;
        }

        // Show loading state
        const originalHTML = btnAiSeo.innerHTML;
        btnAiSeo.disabled = true;
        btnAiSeo.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generando...';

        try {
            const prompt = `Analiza el siguiente contenido y genera campos SEO optimizados. Responde SOLO en formato JSON válido, sin explicaciones ni markdown. El JSON debe tener exactamente estas claves:

{
  "seo_title": "Título SEO optimizado (máx 60 caracteres)",
  "seo_description": "Meta description atractiva (máx 155 caracteres)",
  "seo_keywords": "palabra1, palabra2, palabra3, palabra4, palabra5",
  "twitter_title": "Título corto para X/Twitter (máx 70 caracteres)",
  "twitter_description": "Descripción para X/Twitter (máx 200 caracteres)"
}

Título del contenido: ${title}

Contenido (primeros 2000 caracteres):
${content}

IMPORTANTE: Responde SOLO el JSON, sin bloques de código, sin explicaciones. El idioma debe coincidir con el del contenido.`;

            const response = await fetch('/api/ai/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    prompt: prompt,
                    action: 'generate',
                    _csrf: document.querySelector('[name="_csrf"]')?.value || '{{ csrf_token() }}'
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Error al generar SEO');
            }

            // Parse the AI response - extract JSON from the content
            let aiText = result.content || result.text || '';
            // Strip HTML tags if any
            aiText = aiText.replace(/<[^>]*>/g, '').trim();
            // Try to extract JSON from the response
            const jsonMatch = aiText.match(/\{[\s\S]*\}/);
            if (!jsonMatch) {
                throw new Error('La IA no devolvió JSON válido');
            }

            const seoData = JSON.parse(jsonMatch[0]);

            // Fill in the fields (only if currently empty or user confirms overwrite)
            const hasExisting = document.getElementById('seo_title').value.trim() ||
                               document.getElementById('seo_description').value.trim();

            let shouldFill = true;
            if (hasExisting) {
                if (typeof Swal !== 'undefined') {
                    const confirmResult = await Swal.fire({
                        title: 'Campos SEO existentes',
                        text: 'Ya hay datos SEO. ¿Quieres reemplazarlos con los generados por IA?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, reemplazar',
                        cancelButtonText: 'Cancelar'
                    });
                    shouldFill = confirmResult.isConfirmed;
                } else {
                    shouldFill = confirm('Ya hay datos SEO. ¿Quieres reemplazarlos?');
                }
            }

            if (shouldFill) {
                if (seoData.seo_title) document.getElementById('seo_title').value = seoData.seo_title;
                if (seoData.seo_description) document.getElementById('seo_description').value = seoData.seo_description;
                if (seoData.seo_keywords) document.getElementById('seo_keywords').value = seoData.seo_keywords;
                if (seoData.twitter_title) document.getElementById('twitter_title').value = seoData.twitter_title;
                if (seoData.twitter_description) document.getElementById('twitter_description').value = seoData.twitter_description;

                // Auto-fill images from featured if empty
                syncFeaturedImage();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'SEO generado', text: 'Los campos SEO se han rellenado con IA.', timer: 2000, showConfirmButton: false });
                }
            }

        } catch (error) {
            console.error('Error AI SEO:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', error.message || 'No se pudo generar el SEO con IA.', 'error');
            } else {
                alert('Error: ' + (error.message || 'No se pudo generar el SEO'));
            }
        } finally {
            btnAiSeo.disabled = false;
            btnAiSeo.innerHTML = originalHTML;
        }
    });
});
</script>
