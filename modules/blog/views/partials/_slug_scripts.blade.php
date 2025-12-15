@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const titleInput = document.getElementById('title-input');
  const slugInput = document.getElementById('slug-input');
  const resultSpan = document.getElementById('slug-check-result');
  const slugPreview = document.getElementById('slug-preview');
  const slugLockBtn = document.getElementById('toggle-slug-edit');

  if (!slugInput) return;

  const postId = "{{ $post->id ?? 'new' }}";
  const isEditMode = postId !== 'new';
  const prefix = 'blog';
  const moduleName = 'blog';

  let timeoutId = null;
  let slugIsLocked = isEditMode;

  function clearSlugStatus() {
    if (!resultSpan) return;
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
    if (!slug || !resultSpan || slugIsLocked) {
      if (slugIsLocked) clearSlugStatus();
      return;
    }

    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      const formData = new URLSearchParams();
      formData.append('slug', slug);
      formData.append('prefix', prefix);
      formData.append('module', moduleName);
      if (postId !== 'new') formData.append('exclude_id', postId);

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
      .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
      })
      .then(data => {
        if (slugIsLocked || !resultSpan) return;
        if (data.exists) {
          resultSpan.textContent = 'Este slug ya está en uso.';
          resultSpan.style.color = '#dc3545';
        } else {
          resultSpan.textContent = 'Slug disponible.';
          resultSpan.style.color = '#28a745';
        }
      })
      .catch(() => {
        if (slugIsLocked || !resultSpan) return;
        resultSpan.textContent = 'Error al verificar.';
        resultSpan.style.color = '#fd7e14';
      });
    }, 350);
  }

  if (slugLockBtn) {
    slugInput.readOnly = slugIsLocked;
    slugLockBtn.innerHTML = slugIsLocked ? '<i class="bi bi-lock"></i>' : '<i class="bi bi-unlock"></i>';
    slugLockBtn.addEventListener('click', function () {
      slugIsLocked = !slugIsLocked;
      slugInput.readOnly = slugIsLocked;
      this.innerHTML = slugIsLocked ? '<i class="bi bi-lock"></i>' : '<i class="bi bi-unlock"></i>';
      if (!slugIsLocked) {
        slugInput.focus();
        if (slugInput.value) checkSlug(slugInput.value);
      } else {
        clearSlugStatus();
      }
    });
  }

  if (titleInput) {
    titleInput.addEventListener('input', function () {
      if (slugIsLocked) return;
      const manual = slugInput.dataset.manual === '1';
      if (manual) return;
      const cleanSlug = slugify(this.value);
      slugInput.value = cleanSlug;
      if (slugPreview) slugPreview.textContent = cleanSlug;
      checkSlug(cleanSlug);
    });
  }

  slugInput.addEventListener('input', function () {
    const clean = slugify(this.value);
    if (this.value !== clean) this.value = clean;
    slugInput.dataset.manual = this.value ? '1' : '0';
    if (slugPreview) slugPreview.textContent = clean;
    if (!slugIsLocked) checkSlug(clean);
  });

  if (!isEditMode && slugInput.value) {
    checkSlug(slugInput.value);
  }
});
</script>
@endpush
