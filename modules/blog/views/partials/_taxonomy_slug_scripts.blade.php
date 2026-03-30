@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const nameInput = document.getElementById('name');
  const slugInput = document.getElementById('slug');
  const resultSpan = document.getElementById('slug-check-result');
  const toggleSlugBtn = document.getElementById('toggle-slug-edit');

  if (!slugInput || !resultSpan) return;

  const type = "{{ $type ?? '' }}"; // category | tag
  const endpoint = type === 'tag'
    ? '/ajax/blog/check-tag-slug'
    : '/ajax/blog/check-category-slug';

  const entityId = "{{ $entityId ?? 'new' }}";
  const isEditMode = entityId !== 'new';

  let timeoutId = null;
  let slugIsLocked = !!(isEditMode && toggleSlugBtn);

  function clearSlugStatus() {
    resultSpan.textContent = '';
    resultSpan.style.color = '';
  }

  function slugify(text) {
    return text.toString()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/\-\-+/g, '-')
      .replace(/^-+/, '')
      .replace(/-+$/, '');
  }

  function checkSlug(slug) {
    if (!slug || slugIsLocked) {
      if (slugIsLocked) clearSlugStatus();
      return;
    }

    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      const formData = new URLSearchParams();
      formData.append('slug', slug);
      if (entityId !== 'new') formData.append('exclude_id', entityId);

      const csrfToken = document.querySelector('input[name="_token"]')?.value ||
                        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken
        },
        body: formData.toString()
      })
      .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
      })
      .then(data => {
        if (slugIsLocked) return;
        if (data.exists) {
          resultSpan.textContent = 'Este slug ya está en uso.';
          resultSpan.style.color = '#dc3545';
        } else {
          resultSpan.textContent = 'Slug disponible.';
          resultSpan.style.color = '#28a745';
        }
      })
      .catch(() => {
        if (slugIsLocked) return;
        resultSpan.textContent = 'Error al verificar.';
        resultSpan.style.color = '#fd7e14';
      });
    }, 350);
  }

  if (toggleSlugBtn) {
    slugInput.readOnly = slugIsLocked;
    toggleSlugBtn.addEventListener('click', function () {
      slugIsLocked = !slugIsLocked;
      slugInput.readOnly = slugIsLocked;
      const icon = this.querySelector('i');
      if (icon) icon.className = slugIsLocked ? 'bi bi-lock' : 'bi bi-unlock';
      if (!slugIsLocked) {
        slugInput.focus();
        if (slugInput.value) checkSlug(slugInput.value);
      } else {
        clearSlugStatus();
      }
    });
  }

  if (nameInput) {
    nameInput.addEventListener('input', function () {
      if (slugIsLocked) return;
      const manual = slugInput.dataset.manual === '1';
      if (manual) return;
      const clean = slugify(this.value);
      slugInput.value = clean;
      checkSlug(clean);
    });
  }

  slugInput.addEventListener('input', function () {
    const clean = slugify(this.value);
    if (this.value !== clean) this.value = clean;
    slugInput.dataset.manual = this.value ? '1' : '0';
    checkSlug(clean);
  });

  if (!isEditMode && slugInput.value) {
    checkSlug(slugInput.value);
  }
});
</script>
@endpush

