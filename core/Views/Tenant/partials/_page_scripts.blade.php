<script>
document.addEventListener('DOMContentLoaded', function () {
  // --- Elementos del DOM ---
  const titleInput = document.querySelector('#title-input');
  const slugInput = document.querySelector('#slug-input');
  const prefixInput = document.querySelector('input[name="prefix"]');
  const resultSpan = document.getElementById('slug-check-result');
  const publishedInput = document.getElementById('published_at');
  const slugPreview = document.getElementById('slug-preview');
  const slugLockBtn = document.querySelector('#toggle-slug-edit');

  // --- Estado ---
  const pageId = "{{ $Page->id ?? 'new' }}";
  const isEditMode = {{ $isEdit ?? false ? 'true' : 'false' }};
  let timeoutId = null;
  let slugIsLocked = isEditMode;

  // --- Referencia al elemento del mensaje de estado ---
  let slugStatusElement = resultSpan;
  // ... (tu código para buscar el span si es necesario) ...

  // --- Limpiar/Ocultar mensaje de estado ---
  function clearSlugStatus() {
    if (slugStatusElement) {
      slugStatusElement.textContent = '';
      slugStatusElement.style.color = '';
    }
  }

  // --- Limpiar estado inicial en modo edición ---
  if (isEditMode) {
    clearSlugStatus();
  }

  // --- Event Listener para el botón de bloqueo/desbloqueo ---
  if (slugLockBtn) {
    slugInput.readOnly = slugIsLocked;
    slugLockBtn.innerHTML = slugIsLocked ? '<i class="bi bi-lock"></i>' : '<i class="bi bi-unlock"></i>';

    slugLockBtn.addEventListener('click', function() {
      slugIsLocked = !slugIsLocked;
      slugInput.readOnly = slugIsLocked;
      this.innerHTML = slugIsLocked ? '<i class="bi bi-lock"></i>' : '<i class="bi bi-unlock"></i>';
      if (!slugIsLocked) {
        slugInput.focus();
        if (slugInput.value) { checkSlug(slugInput.value); }
      } else {
        clearSlugStatus();
      }
    });
  }

  // --- Función para generar slug (slugify) --- ¡MEJORADA! ---
  function slugify(text) {
    return text.toString()               // Asegurar que sea string
      .normalize('NFD')               // Paso 1: Separar caracteres base de diacríticos (ej: á -> a + ´)
      .replace(/[\u0300-\u036f]/g, '') // Paso 2: Eliminar los diacríticos (ej: a + ´ -> a)
      .toLowerCase()                  // Convertir a minúsculas
      .trim()                         // Quitar espacios al inicio/final
      .replace(/[^\w\s-]/g, '')       // Paso 3: Eliminar caracteres que NO son alfanuméricos (\w), espacios (\s) o guiones (-)
      .replace(/\s+/g, '-')           // Paso 4: Reemplazar espacios (y secuencias de espacios) con un solo guion
      .replace(/\-\-+/g, '-')         // Paso 5: Reemplazar múltiples guiones con uno solo
      .replace(/^-+/, '')             // Paso 6: Eliminar guiones al principio
      .replace(/-+$/, '');            // Paso 7: Eliminar guiones al final
  }
  // ---------------------------------------------------------

  // --- Event Listener para Input del Título ---
  if (titleInput) {
    titleInput.addEventListener('input', function() {
      if (!slugIsLocked) {
        const cleanSlug = slugify(this.value);
        slugInput.value = cleanSlug;
        if (slugPreview) slugPreview.textContent = cleanSlug;
        checkSlug(cleanSlug);
      }
    });
  }

  // --- Event Listener para Input del Slug ---
  if (slugInput) {
    slugInput.addEventListener('input', function() {
      const clean = slugify(this.value);
      if (this.value !== clean) { this.value = clean; }
      if (slugPreview) slugPreview.textContent = clean;
      if (!slugIsLocked) { checkSlug(clean); }
    });
  }

  // --- Función para Verificar Slug (AJAX) ---
  function checkSlug(slug) {
    if (!slug || !slugInput || !slugStatusElement || slugIsLocked) {
        if (slugIsLocked) { clearSlugStatus(); }
        return;
    }
    const prefix = prefixInput ? prefixInput.value : 'p';
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      const formData = new URLSearchParams();
      formData.append('slug', slug);
      formData.append('prefix', prefix);
      if (pageId !== 'new') { formData.append('exclude_id', pageId); }
      const csrfToken = document.querySelector('input[name="_token"]')?.value ||
                        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      fetch('/ajax/check-slug', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        },
        body: formData.toString()
      })
      .then(res => { if (!res.ok) { throw new Error('Network response was not ok'); } return res.json(); })
      .then(data => {
        if (!slugIsLocked && slugStatusElement) {
            if (data.exists) {
                slugStatusElement.textContent = 'Este slug ya está en uso.';
                slugStatusElement.style.color = '#dc3545';
            } else {
                slugStatusElement.textContent = 'Slug disponible.';
                slugStatusElement.style.color = '#28a745';
            }
        } else if (slugStatusElement) { clearSlugStatus(); }
      })
      .catch((error) => {
        console.error("Error verificando slug:", error);
        if (!slugIsLocked && slugStatusElement) {
          slugStatusElement.textContent = 'Error al verificar.';
          slugStatusElement.style.color = '#fd7e14';
        } else if (slugStatusElement){ clearSlugStatus(); }
      });
    }, 400);
  }

  // --- Lógica de Fecha de Publicación ---
  if (publishedInput && !publishedInput.value) {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    publishedInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
  }

  // --- Verificación Inicial ---
  if (!isEditMode && slugInput && slugInput.value) {
     checkSlug(slugInput.value);
  }

}); // Fin DOMContentLoaded

function confirmDelete(id) {
  Swal.fire({
    title: '¿Estás seguro?',
    text: 'Esta acción eliminará permanentemente la página y no se puede deshacer',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      // Mostrar mensaje de carga
      Swal.fire({
        title: 'Eliminando...',
        text: 'Por favor espera mientras se elimina la página',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      // Crear un formulario simple para POST
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/{{ admin_path() }}/pages/' + id + '/delete'; // Usar la ruta POST correcta para tenant
      
      // Añadir token CSRF
      const csrfToken = document.querySelector('input[name="_token"]') || 
                        document.querySelector('input[name="_csrf"]');
      
      if (csrfToken) {
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = csrfToken.name;
        tokenInput.value = csrfToken.value;
        form.appendChild(tokenInput);
      }
      
      // Añadir el formulario al documento y enviarlo
      document.body.appendChild(form);
      form.submit();
    }
  });
}
</script>