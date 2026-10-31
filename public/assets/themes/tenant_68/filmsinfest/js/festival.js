// Menú móvil y ampliación de fotografías, exclusivos del festival.
document.addEventListener('DOMContentLoaded', () => {
    var toggle = document.querySelector('.fi-menu-toggle');
    var menu = document.getElementById('festival-menu');
    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            var open = menu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(open));
        });
        menu.querySelectorAll('.has-children>a').forEach((link) => {
            link.addEventListener('click', (event) => {
                if (window.matchMedia('(max-width:900px)').matches && !link.parentElement.classList.contains('is-open')) {
                    event.preventDefault();
                    link.parentElement.classList.add('is-open');
                }
            });
        });
    }
    var box = document.querySelector('.fi-lightbox');
    if (!box || typeof box.showModal !== 'function') return;
    var photo = box.querySelector('img');
    var links = Array.from(document.querySelectorAll('.page-body a[href]')).filter((a) => {
        return a.querySelector('img') && (/\/media\/(?:p|t)\//.test(a.getAttribute('href')) || /\.(?:jpg|jpeg|png|webp)(?:\?|$)/i.test(a.getAttribute('href')));
    });
    var index = 0;
    function show(next) {
        index = (next + links.length) % links.length;
        photo.src = links[index].href;
        photo.alt = links[index].querySelector('img').alt || 'Films Infest';
    }
    links.forEach((link, i) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();show(i);box.showModal();
        });
    });
    box.querySelector('.fi-lightbox-close').addEventListener('click', () => { box.close(); });
    box.querySelector('.fi-lightbox-prev').addEventListener('click', () => { show(index - 1); });
    box.querySelector('.fi-lightbox-next').addEventListener('click', () => { show(index + 1); });
    box.addEventListener('click', (event) => { if (event.target === box) box.close(); });
    box.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') show(index + 1);
        if (event.key === 'ArrowLeft') show(index - 1);
    });
});
