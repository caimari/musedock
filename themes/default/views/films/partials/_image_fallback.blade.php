<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('img').forEach(function(img) {
    img.addEventListener('error', function() {
      if (this.dataset.fallback) return;
      this.dataset.fallback = '1';
      var isRound = this.classList.contains('film-cast-photo') ||
                    this.classList.contains('actor-photo-hero') ||
                    this.classList.contains('actor-photo-lg') ||
                    this.classList.contains('rounded-circle') ||
                    this.closest('.film-cast-item');
      this.src = isRound ? '/assets/img/no-avatar.svg' : '/assets/img/no-poster.svg';
      this.style.objectFit = 'cover';
    });
  });
});
</script>
