/**
 * AI Image Generator - SweetAlert2 version
 * Adds an AI image generation button next to featured image fields.
 * Supports: OpenAI (DALL-E), MiniMax, Picalias, FAL
 */
(function() {
    'use strict';

    var sizesByProvider = {
        'openai': [
            { value: '1024x1024', label: '1024 x 1024 (cuadrada)' },
            { value: '1792x1024', label: '1792 x 1024 (panoramica)' },
            { value: '1024x1792', label: '1024 x 1792 (vertical)' },
            { value: '512x512', label: '512 x 512 (pequena)' }
        ],
        'minimax': [
            { value: '1024x1024', label: '1:1 - 1024 x 1024 (cuadrada)' },
            { value: '1280x720',  label: '16:9 - 1280 x 720 (panoramica)' },
            { value: '720x1280',  label: '9:16 - 720 x 1280 (vertical)' },
            { value: '1152x864',  label: '4:3 - 1152 x 864' },
            { value: '864x1152',  label: '3:4 - 864 x 1152' },
            { value: '1248x832',  label: '3:2 - 1248 x 832' },
            { value: '832x1248',  label: '2:3 - 832 x 1248' },
            { value: '1344x576',  label: '21:9 - 1344 x 576 (ultra-panoramica)' }
        ],
        'fal': [
            { value: '1024x1024', label: '1024 x 1024 (cuadrada)' },
            { value: '1792x1024', label: '1792 x 1024 (panoramica)' },
            { value: '1024x1792', label: '1024 x 1792 (vertical)' },
            { value: '512x512', label: '512 x 512 (pequena)' }
        ],
        'picalias': [
            { value: '1024x1024', label: '1024 x 1024 (cuadrada)' },
            { value: '1792x1024', label: '1792 x 1024 (panoramica)' },
            { value: '1024x1792', label: '1024 x 1792 (vertical)' },
            { value: '512x512', label: '512 x 512 (pequena)' }
        ],
        '_default': [
            { value: '1024x1024', label: '1024 x 1024 (cuadrada)' },
            { value: '1792x1024', label: '1792 x 1024 (panoramica)' },
            { value: '1024x1792', label: '1024 x 1792 (vertical)' },
            { value: '512x512', label: '512 x 512 (pequena)' }
        ]
    };

    var loadedProviders = [];

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.ai-image-trigger').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var targetInput = btn.getAttribute('data-target');
                var previewId = btn.getAttribute('data-preview');
                openAiImageModal(targetInput, previewId);
            });
        });
    });

    function buildSizeOptions(providerType) {
        var sizes = sizesByProvider[providerType] || sizesByProvider['_default'];
        return sizes.map(function(s) {
            return '<option value="' + s.value + '">' + s.label + '</option>';
        }).join('');
    }

    function buildProviderOptions() {
        var html = '<option value="" data-type="">Por defecto</option>';
        loadedProviders.forEach(function(p) {
            html += '<option value="' + p.id + '" data-type="' + p.provider_type + '">'
                + p.name + ' (' + p.provider_type + ' - ' + (p.model || 'default') + ')</option>';
        });
        return html;
    }

    function openAiImageModal(targetInputId, previewId) {
        // First load providers, then open the dialog
        fetch('/api/ai/image/providers')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success && data.providers) {
                loadedProviders = data.providers;
            }
            showGenerateDialog(targetInputId, previewId);
        })
        .catch(function() {
            loadedProviders = [];
            showGenerateDialog(targetInputId, previewId);
        });
    }

    function showGenerateDialog(targetInputId, previewId) {
        Swal.fire({
            title: '<i class="bi bi-stars text-primary"></i> Generar imagen con IA',
            html: '<div class="text-start">'
                + '<div class="mb-3">'
                + '<div class="d-flex justify-content-between align-items-center mb-1">'
                + '<label class="form-label fw-bold mb-0">Describe la imagen que deseas generar</label>'
                + '<button type="button" id="swalAiSuggestBtn" class="btn btn-sm btn-outline-info" title="La IA leera el contenido del post y sugerira un prompt para la imagen">'
                + '<i class="bi bi-magic me-1"></i> Sugerir desde el contenido'
                + '</button>'
                + '</div>'
                + '<textarea id="swalAiPrompt" class="form-control" rows="3" placeholder="Ej: Una ilustracion moderna y minimalista de tecnologia e inteligencia artificial con tonos azules..."></textarea>'
                + '</div>'
                + '<div class="row">'
                + '<div class="col-md-4 mb-3">'
                + '<label class="form-label">Proveedor</label>'
                + '<select id="swalAiProvider" class="form-select">' + buildProviderOptions() + '</select>'
                + '</div>'
                + '<div class="col-md-4 mb-3">'
                + '<label class="form-label">Formato / Tamano</label>'
                + '<select id="swalAiSize" class="form-select">' + buildSizeOptions('_default') + '</select>'
                + '</div>'
                + '<div class="col-md-4 mb-3">'
                + '<label class="form-label">Estilo</label>'
                + '<select id="swalAiStyle" class="form-select">'
                + '<option value="natural">Natural</option>'
                + '<option value="vivid">Vivid</option>'
                + '</select>'
                + '<small class="text-muted" id="swalStyleHint">Solo aplica a OpenAI DALL-E 3</small>'
                + '</div>'
                + '</div>'
                + '</div>',
            width: '700px',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-stars me-1"></i> Generar',
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            customClass: {
                htmlContainer: 'text-start'
            },
            didOpen: function() {
                // Suggest prompt from post content
                var suggestBtn = document.getElementById('swalAiSuggestBtn');
                if (suggestBtn) {
                    suggestBtn.addEventListener('click', function() {
                        // Read post title and content from the page
                        var title = '';
                        var content = '';
                        var titleInput = document.getElementById('title-input') || document.querySelector('input[name="title"]');
                        if (titleInput) title = titleInput.value.trim();

                        // Try TinyMCE first
                        if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                            content = tinymce.activeEditor.getContent({ format: 'text' });
                        }
                        // Fallback to textarea
                        if (!content) {
                            var contentArea = document.getElementById('content') || document.querySelector('textarea[name="content"]');
                            if (contentArea) content = contentArea.value;
                        }

                        if (!title && !content) {
                            Swal.showValidationMessage('No se encontro contenido en el post. Escribe algo primero.');
                            setTimeout(function() { Swal.resetValidationMessage(); }, 3000);
                            return;
                        }

                        // Truncate content to avoid huge prompts
                        if (content.length > 1500) content = content.substring(0, 1500) + '...';

                        suggestBtn.disabled = true;
                        suggestBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analizando...';

                        var aiPrompt = 'Based on this article, write ONE image prompt in English for an AI image generator (DALL-E / MiniMax). '
                            + 'Output ONLY the prompt. No reasoning. No explanation. No preamble. No quotes.\n\n'
                            + 'Title: ' + title + '\n'
                            + 'Content: ' + content;

                        fetch('/api/ai/generate', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                prompt: aiPrompt,
                                module: 'aiimage',
                                action: 'generate',
                                system_message: 'You output ONLY image generation prompts. Never explain, never reason, never add commentary. Your entire response must be a single image prompt in English, ready to paste into DALL-E or MiniMax. No quotes. No introductory text. Just the prompt description.'
                            })
                        })
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            suggestBtn.disabled = false;
                            suggestBtn.innerHTML = '<i class="bi bi-magic me-1"></i> Sugerir desde el contenido';
                            if (data.success && data.content) {
                                // Clean the response - remove HTML tags, quotes, extra whitespace
                                var clean = data.content.replace(/<[^>]*>/g, '').replace(/^["'\s]+|["'\s]+$/g, '').trim();
                                // If response has multiple paragraphs, take only the last substantial one (the actual prompt)
                                var paragraphs = clean.split(/\n\n+/).filter(function(p) { return p.trim().length > 20; });
                                if (paragraphs.length > 1) {
                                    clean = paragraphs[paragraphs.length - 1].trim();
                                }
                                document.getElementById('swalAiPrompt').value = clean;
                            } else {
                                Swal.showValidationMessage(data.message || 'No se pudo generar la sugerencia');
                                setTimeout(function() { Swal.resetValidationMessage(); }, 3000);
                            }
                        })
                        .catch(function(err) {
                            suggestBtn.disabled = false;
                            suggestBtn.innerHTML = '<i class="bi bi-magic me-1"></i> Sugerir desde el contenido';
                            Swal.showValidationMessage('Error: ' + err.message);
                            setTimeout(function() { Swal.resetValidationMessage(); }, 3000);
                        });
                    });
                }

                // Provider change -> update sizes
                var provSelect = document.getElementById('swalAiProvider');
                if (provSelect) {
                    provSelect.addEventListener('change', function() {
                        var sel = this.options[this.selectedIndex];
                        var pType = sel.getAttribute('data-type') || '_default';
                        var sizeSelect = document.getElementById('swalAiSize');
                        if (sizeSelect) {
                            sizeSelect.innerHTML = buildSizeOptions(pType);
                        }
                        var hint = document.getElementById('swalStyleHint');
                        if (hint) {
                            hint.style.display = (pType === 'openai' || pType === '' || pType === '_default') ? 'block' : 'none';
                        }
                    });
                }
            },
            preConfirm: function() {
                var prompt = document.getElementById('swalAiPrompt').value.trim();
                if (!prompt) {
                    Swal.showValidationMessage('Escribe una descripcion de la imagen');
                    return false;
                }
                return {
                    prompt: prompt,
                    size: document.getElementById('swalAiSize').value,
                    style: document.getElementById('swalAiStyle').value,
                    provider_id: document.getElementById('swalAiProvider').value || null
                };
            }
        }).then(function(result) {
            if (!result.isConfirmed || !result.value) return;

            // Show loading
            Swal.fire({
                title: 'Generando imagen...',
                html: '<div class="py-3">'
                    + '<div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>'
                    + '<p class="text-muted mb-0">Esto puede tardar unos segundos</p>'
                    + '</div>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });

            var payload = {
                prompt: result.value.prompt,
                size: result.value.size,
                style: result.value.style,
                module: 'aiimage'
            };
            if (result.value.provider_id) {
                payload.provider_id = parseInt(result.value.provider_id);
            }

            // Detect tenant context from URL (e.g. ?scope=tenant:28)
            var scopeMatch = window.location.search.match(/scope=tenant:(\d+)/);
            if (scopeMatch) {
                payload.tenant_id = parseInt(scopeMatch[1]);
            }

            fetch('/api/ai/image/generate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success && data.image) {
                    var imagePath = data.image.local_path;
                    // Show result with action buttons
                    Swal.fire({
                        title: '<i class="bi bi-check-circle text-success"></i> Imagen generada',
                        html: '<div class="text-center py-2">'
                            + '<img src="' + imagePath + '" class="img-fluid rounded shadow" style="max-height: 400px;" />'
                            + '<p class="text-muted mt-3 mb-0">'
                            + '<small>Proveedor: ' + (data.image.provider || '') + ' | Modelo: ' + (data.image.model || '') + '</small>'
                            + '</p>'
                            + '</div>',
                        width: '700px',
                        showCancelButton: true,
                        showDenyButton: true,
                        confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Usar esta imagen',
                        denyButtonText: '<i class="bi bi-arrow-repeat me-1"></i> Generar otra',
                        cancelButtonText: '<i class="bi bi-trash me-1"></i> Descartar',
                        confirmButtonColor: '#198754',
                        denyButtonColor: '#0d6efd',
                        cancelButtonColor: '#dc3545'
                    }).then(function(action) {
                        if (action.isConfirmed) {
                            // Set the image in the input field
                            var input = document.getElementById(targetInputId);
                            if (input) {
                                input.value = imagePath;
                                input.dispatchEvent(new Event('input', { bubbles: true }));
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            if (previewId) {
                                var previewEl = document.getElementById(previewId);
                                if (previewEl) {
                                    // If it's an <img> tag, set src directly; if a container div, replace innerHTML
                                    if (previewEl.tagName === 'IMG') {
                                        previewEl.src = imagePath;
                                        previewEl.style.opacity = '1';
                                    } else {
                                        previewEl.innerHTML = '<img src="' + imagePath + '" class="img-fluid rounded" alt="Preview" style="max-height: 200px; object-fit: cover;">';
                                    }
                                }
                            }
                            // Update status badge if present (header options partial)
                            var imageStatus = document.getElementById('image-status');
                            if (imageStatus) {
                                imageStatus.innerHTML = '<span class="badge bg-success">Imagen personalizada</span>';
                            }
                            // Show remove button if present
                            var removeBtn = document.getElementById('remove-header-image-btn');
                            if (!removeBtn) {
                                var selectBtn = document.getElementById('select-header-image-btn');
                                if (selectBtn) {
                                    var newRemoveBtn = document.createElement('button');
                                    newRemoveBtn.type = 'button';
                                    newRemoveBtn.className = 'btn btn-outline-danger';
                                    newRemoveBtn.id = 'remove-header-image-btn';
                                    newRemoveBtn.innerHTML = '<i class="bi bi-x-circle"></i> Eliminar imagen';
                                    selectBtn.parentNode.appendChild(newRemoveBtn);
                                }
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'Imagen aplicada',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true
                            });
                        } else if (action.isDenied) {
                            // Delete current and generate another
                            deleteAiImage(imagePath);
                            showGenerateDialog(targetInputId, previewId);
                        } else if (action.dismiss === Swal.DismissReason.cancel) {
                            // Discard - delete from server
                            deleteAiImage(imagePath);
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al generar',
                        text: data.message || 'Error desconocido del proveedor de imagen',
                        confirmButtonText: '<i class="bi bi-arrow-repeat me-1"></i> Reintentar',
                        showCancelButton: true,
                        cancelButtonText: 'Cerrar',
                        confirmButtonColor: '#0d6efd'
                    }).then(function(retry) {
                        if (retry.isConfirmed) {
                            showGenerateDialog(targetInputId, previewId);
                        }
                    });
                }
            })
            .catch(function(err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexion',
                    text: err.message || 'No se pudo conectar con el servidor',
                    confirmButtonText: '<i class="bi bi-arrow-repeat me-1"></i> Reintentar',
                    showCancelButton: true,
                    cancelButtonText: 'Cerrar',
                    confirmButtonColor: '#0d6efd'
                }).then(function(retry) {
                    if (retry.isConfirmed) {
                        showGenerateDialog(targetInputId, previewId);
                    }
                });
            });
        });
    }

    function deleteAiImage(imagePath) {
        if (!imagePath) return;
        fetch('/api/ai/image/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: imagePath })
        }).catch(function() {});
    }

})();
