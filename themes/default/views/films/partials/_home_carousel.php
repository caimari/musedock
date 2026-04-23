<?php
/**
 * Film Library: Home Carousel / Cartelera
 * Infinite paging carousel — loads more films via API when reaching the end.
 * Variables: $homeFilms (array of Film), $homeCarouselTitle (string)
 */
if (empty($homeFilms)) return;
$cid = 'fc-' . substr(md5(uniqid()), 0, 8);
?>
<div class="film-home-section pt-2 pb-3">
  <div class="film-carousel-container">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h3 class="film-section-title mb-0"><?= htmlspecialchars($homeCarouselTitle ?? 'Cartelera') ?></h3>
      <div class="d-flex align-items-center gap-2">
        <button type="button" class="film-nav-btn" id="<?= $cid ?>-prev">
          <svg class="nav-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
          <span class="nav-spinner" style="display:none;width:14px;height:14px;border:2px solid #ddd;border-top-color:var(--header-link-hover-color,#ff5e15);border-radius:50%;animation:fcSpin .6s linear infinite;"></span>
        </button>
        <button type="button" class="film-nav-btn" id="<?= $cid ?>-next">
          <svg class="nav-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
          <span class="nav-spinner" style="display:none;width:14px;height:14px;border:2px solid #ddd;border-top-color:var(--header-link-hover-color,#ff5e15);border-radius:50%;animation:fcSpin .6s linear infinite;"></span>
        </button>
      </div>
    </div>
    <div class="film-carousel-viewport" id="<?= $cid ?>-vp">
      <div class="film-carousel-track" id="<?= $cid ?>">
        <?php foreach ($homeFilms as $film): ?>
        <a href="/films/<?= htmlspecialchars($film->slug) ?>" class="film-carousel-item">
          <div class="film-carousel-poster-wrap">
            <?php if ($film->poster_path): ?>
              <img src="<?= htmlspecialchars(film_poster_url($film->poster_path, 'w342')) ?>" alt="<?= htmlspecialchars($film->title) ?>" class="film-carousel-poster" loading="lazy">
            <?php else: ?>
              <div class="film-carousel-poster film-carousel-no-poster">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5"><rect x="2" y="3" width="20" height="18" rx="2"/><path d="M7 3v18M17 3v18M2 12h20"/></svg>
              </div>
            <?php endif; ?>
            <?php if ($film->tmdb_rating): ?>
              <span class="film-carousel-rating"><?= number_format($film->tmdb_rating, 1) ?></span>
            <?php endif; ?>
          </div>
          <p class="film-carousel-title"><?= htmlspecialchars($film->title) ?></p>
          <span class="film-carousel-meta"><?= htmlspecialchars($film->year ?? '') ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="text-end mt-2">
      <a href="/films" class="film-section-link">Ver más &rarr;</a>
    </div>
  </div>
</div>

<style>
.film-carousel-container {
  max-width: var(--content-max-width, 1140px);
  margin: 0 auto;
  padding: 0 15px;
}
.sidebar-layout-content .film-carousel-container { max-width: 980px; }
body.header-boxed .film-carousel-container { max-width: var(--content-max-width, 1140px); }
@media (max-width: 1199px) { .film-carousel-container { padding: 0 20px; } }
@media (max-width: 767px) { .film-carousel-container { padding: 0 15px; } }
.film-home-section { margin-top: -2rem; }
.film-section-title {
  font-size: 1.2rem; font-weight: 700;
  color: var(--content-heading-color, #0f172a);
}
.film-section-link {
  font-size: 0.78rem; color: #94a3b8; text-decoration: none; transition: color 0.2s;
}
.film-section-link:hover { color: var(--header-link-hover-color, #ff5e15); }
.film-nav-btn {
  display: flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 50%;
  border: 1px solid #d1d5db; background: #fff; color: #6b7280;
  cursor: pointer; transition: all 0.2s; padding: 0;
}
.film-nav-btn:hover {
  border-color: var(--header-link-hover-color, #ff5e15);
  color: var(--header-link-hover-color, #ff5e15);
}
.film-carousel-viewport { overflow: hidden; position: relative; }
.film-carousel-track { display: flex; gap: 14px; transition: transform 0.4s ease; }
.film-carousel-item { flex: 0 0 auto; text-decoration: none; }
.film-carousel-poster-wrap {
  position: relative; border-radius: 6px; overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}
.film-carousel-poster-wrap:hover {
  transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.12);
}
.film-carousel-poster { width: 100%; aspect-ratio: 2/3; object-fit: cover; display: block; }
.film-carousel-no-poster {
  background: #f1f5f9; aspect-ratio: 2/3; display: flex; align-items: center; justify-content: center;
}
.film-carousel-rating {
  position: absolute; top: 5px; right: 5px;
  background: rgba(0,0,0,0.7); color: #f5c518;
  font-weight: 700; font-size: 0.65rem; padding: 1px 5px; border-radius: 3px;
}
.film-carousel-title {
  color: var(--content-text-color, #1e293b);
  font-size: 0.75rem; font-weight: 600; line-height: 1.2;
  margin: 5px 0 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.film-carousel-meta { color: #94a3b8; font-size: 0.68rem; }
@keyframes fcSpin { to { transform: rotate(360deg); } }
.film-nav-btn.loading .nav-arrow { display: none; }
.film-nav-btn.loading .nav-spinner { display: inline-block !important; }
</style>

<script>
(function() {
  var vp = document.getElementById('<?= $cid ?>-vp');
  var track = document.getElementById('<?= $cid ?>');
  var prev = document.getElementById('<?= $cid ?>-prev');
  var next = document.getElementById('<?= $cid ?>-next');
  if (!track || !prev || !next || !vp) return;

  var gap = 14;
  var offset = 0;
  var apiPage = 2; // page 1 already rendered server-side
  var loading = false;
  var noMore = false;

  function getItemW() {
    var el = track.querySelector('.film-carousel-item');
    return el ? el.offsetWidth + gap : 154;
  }

  function visibleCount() {
    return Math.floor((vp.offsetWidth + gap) / getItemW());
  }

  function totalItems() {
    return track.querySelectorAll('.film-carousel-item').length;
  }

  function sizeItems() {
    var w = vp.offsetWidth;
    var n = Math.floor((w + gap) / 154);
    if (n < 2) n = 2;
    var iw = Math.floor((w - (n - 1) * gap) / n);
    track.querySelectorAll('.film-carousel-item').forEach(function(el) { el.style.width = iw + 'px'; });
  }

  function render() {
    sizeItems();
    track.style.transform = 'translateX(-' + offset + 'px)';
  }

  function addCard(f) {
    var poster = f.poster_url || '';
    var el = document.createElement('a');
    el.href = '/films/' + f.slug;
    el.className = 'film-carousel-item';
    el.style.textDecoration = 'none';
    el.innerHTML =
      '<div class="film-carousel-poster-wrap">' +
      (poster ? '<img src="' + poster + '" class="film-carousel-poster" loading="lazy">' :
        '<div class="film-carousel-poster film-carousel-no-poster"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="1.5"><rect x="2" y="3" width="20" height="18" rx="2"/></svg></div>') +
      (f.tmdb_rating ? '<span class="film-carousel-rating">' + parseFloat(f.tmdb_rating).toFixed(1) + '</span>' : '') +
      '</div>' +
      '<p class="film-carousel-title">' + f.title + '</p>' +
      '<span class="film-carousel-meta">' + (f.year || '') + '</span>';
    track.appendChild(el);
  }

  function setBtnLoading(btn, on) {
    if (on) btn.classList.add('loading');
    else btn.classList.remove('loading');
  }

  function loadMore(callback) {
    if (loading || noMore) { if (callback) callback(); return; }
    loading = true;
    setBtnLoading(next, true);
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/films/api/catalog?page=' + apiPage + '&limit=12', true);
    xhr.onreadystatechange = function() {
      if (xhr.readyState !== 4) return;
      loading = false;
      setBtnLoading(next, false);
      if (xhr.status !== 200) { if (callback) callback(); return; }
      try {
        var d = JSON.parse(xhr.responseText);
        if (!d.success || !d.films || d.films.length === 0) { noMore = true; if (callback) callback(); return; }
        d.films.forEach(addCard);
        apiPage++;
        if (apiPage > d.totalPages) noMore = true;
        sizeItems();
        if (callback) callback();
      } catch(e) { noMore = true; if (callback) callback(); }
    };
    xhr.send();
  }

  next.addEventListener('click', function() {
    if (loading) return;
    var iw = getItemW();
    var vc = visibleCount();
    var maxOff = (totalItems() - vc) * iw;
    if (maxOff < 0) maxOff = 0;

    offset += vc * iw;
    if (offset > maxOff) {
      if (!noMore) {
        loadMore(function() {
          var newMax = (totalItems() - visibleCount()) * getItemW();
          if (offset > newMax) offset = newMax;
          if (offset < 0) offset = 0;
          render();
        });
        return;
      }
      offset = 0;
    }
    render();
  });

  prev.addEventListener('click', function() {
    var iw = getItemW();
    var vc = visibleCount();
    offset -= vc * iw;
    if (offset < 0) {
      var maxOff = (totalItems() - vc) * iw;
      offset = maxOff > 0 ? maxOff : 0;
    }
    render();
  });

  render();
  window.addEventListener('resize', function() { offset = 0; render(); });
})();
</script>
