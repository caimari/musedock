{{-- Documentation Template: Styles + Scripts --}}
<style>
/* ===== DOCS LAYOUT ===== */
.docs-layout {
    background: #fff;
    min-height: 80vh;
}

/* ===== SIDEBAR ===== */
.docs-sidebar-col {
    position: relative;
}
.docs-sidebar {
    position: sticky;
    top: 20px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    padding: 1.5rem 0 2rem;
    font-size: 0.875rem;
    scrollbar-width: thin;
    scrollbar-color: #ddd transparent;
}
.docs-sidebar::-webkit-scrollbar { width: 4px; }
.docs-sidebar::-webkit-scrollbar-thumb { background: #ddd; border-radius: 2px; }

.docs-sidebar-header { margin-bottom: 1rem; }
.docs-sidebar-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1a1a2e;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.docs-sidebar-title:hover { color: var(--header-link-hover-color, #2563eb); }
.docs-sidebar-title i { font-size: 1.1rem; }

/* Search */
.docs-search { margin-bottom: 1rem; }
.docs-search-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e5ea;
    border-radius: 6px;
    font-size: 0.8rem;
    outline: none;
    transition: border-color 0.2s;
    background: #f8f9fb;
}
.docs-search-input:focus {
    border-color: var(--header-link-hover-color, #2563eb);
    background: #fff;
}

/* Nav sections */
.docs-nav-section { margin-bottom: 0.25rem; }
.docs-nav-section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.5rem 0.5rem;
    border: none;
    background: none;
    font-size: 0.8rem;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    border-radius: 4px;
    transition: background 0.15s;
}
.docs-nav-section-title:hover { background: #f3f4f6; }
.docs-nav-section-title.active { color: var(--header-link-hover-color, #2563eb); }
.docs-chevron { transition: transform 0.2s; flex-shrink: 0; }
.docs-nav-section-title.active .docs-chevron,
.docs-nav-section-title[aria-expanded="true"] .docs-chevron {
    transform: rotate(180deg);
}

.docs-nav-links {
    list-style: none;
    padding: 0;
    margin: 0;
    display: none;
    padding-left: 0.5rem;
}
.docs-nav-links.show { display: block; }
.docs-nav-links li a {
    display: block;
    padding: 0.35rem 0.75rem;
    color: #6b7280;
    text-decoration: none;
    font-size: 0.82rem;
    border-left: 2px solid transparent;
    border-radius: 0 4px 4px 0;
    transition: all 0.15s;
    line-height: 1.4;
}
.docs-nav-links li a:hover {
    color: #1f2937;
    background: #f9fafb;
    border-left-color: #d1d5db;
}
.docs-nav-links li a.active {
    color: var(--header-link-hover-color, #2563eb);
    background: rgba(37, 99, 235, 0.05);
    border-left-color: var(--header-link-hover-color, #2563eb);
    font-weight: 500;
}

.docs-nav-root-link { margin-bottom: 0.15rem; }
.docs-nav-root-link a {
    display: block;
    padding: 0.4rem 0.75rem;
    color: #374151;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    border-radius: 4px;
    transition: all 0.15s;
}
.docs-nav-root-link a:hover { background: #f3f4f6; }
.docs-nav-root-link a.active {
    color: var(--header-link-hover-color, #2563eb);
    background: rgba(37, 99, 235, 0.05);
}

/* ===== BREADCRUMB ===== */
.docs-breadcrumb {
    font-size: 0.8rem;
    color: #9ca3af;
    padding: 1rem 0 0.5rem;
}
.docs-breadcrumb a { color: #6b7280; text-decoration: none; }
.docs-breadcrumb a:hover { color: var(--header-link-hover-color, #2563eb); }
.docs-breadcrumb-sep { margin: 0 0.35rem; }
.docs-breadcrumb-current { color: #374151; font-weight: 500; }

/* ===== ARTICLE ===== */
.docs-title {
    font-size: 2rem;
    font-weight: 700;
    color: #111827;
    margin: 0.5rem 0 1.5rem;
    line-height: 1.2;
    letter-spacing: -0.02em;
}

.docs-body {
    font-size: 0.95rem;
    line-height: 1.75;
    color: #374151;
}
.docs-body h2 {
    font-size: 1.4rem;
    font-weight: 600;
    color: #111827;
    margin-top: 2.5rem;
    margin-bottom: 0.75rem;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid #e5e7eb;
}
.docs-body h3 {
    font-size: 1.15rem;
    font-weight: 600;
    color: #1f2937;
    margin-top: 2rem;
    margin-bottom: 0.5rem;
}
.docs-body h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
}
.docs-body p { margin-bottom: 1rem; }
.docs-body a { color: var(--header-link-hover-color, #2563eb); }
.docs-body a:hover { text-decoration: underline; }

/* Code blocks */
.docs-body pre {
    position: relative;
    background: #1e293b;
    color: #e2e8f0;
    padding: 1rem 1.25rem;
    border-radius: 8px;
    overflow-x: auto;
    font-size: 0.85rem;
    line-height: 1.6;
    margin: 1.25rem 0;
}
.docs-body code {
    font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
    font-size: 0.85em;
}
.docs-body :not(pre) > code {
    background: #f1f5f9;
    color: #dc2626;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    font-size: 0.82em;
}

/* Copy button */
.docs-code-copy {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    color: #94a3b8;
    padding: 0.3rem 0.6rem;
    border-radius: 4px;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}
.docs-code-copy:hover { background: rgba(255,255,255,0.2); color: #fff; }
.docs-code-copy.copied { background: #16a34a; color: #fff; border-color: #16a34a; }

/* Tables */
.docs-body table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.25rem 0;
    font-size: 0.875rem;
}
.docs-body th, .docs-body td {
    padding: 0.6rem 0.8rem;
    border: 1px solid #e5e7eb;
    text-align: left;
}
.docs-body th {
    background: #f8fafc;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #4b5563;
}
.docs-body tr:hover td { background: #fafbfc; }

/* Blockquotes / callouts */
.docs-body blockquote {
    border-left: 3px solid var(--header-link-hover-color, #2563eb);
    background: #eff6ff;
    padding: 1rem 1.25rem;
    margin: 1.25rem 0;
    border-radius: 0 6px 6px 0;
    color: #1e40af;
    font-size: 0.9rem;
}
.docs-body blockquote p:last-child { margin-bottom: 0; }

/* Lists */
.docs-body ul, .docs-body ol {
    padding-left: 1.5rem;
    margin-bottom: 1rem;
}
.docs-body li { margin-bottom: 0.35rem; }

/* ===== PAGINATION ===== */
.docs-pagination {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 3rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}
.docs-pagination-prev, .docs-pagination-next {
    display: flex;
    flex-direction: column;
    padding: 0.75rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s;
    max-width: 48%;
}
.docs-pagination-prev:hover, .docs-pagination-next:hover {
    border-color: var(--header-link-hover-color, #2563eb);
    background: #f8fafc;
    text-decoration: none;
}
.docs-pagination-next { text-align: right; margin-left: auto; }
.docs-pagination-label { font-size: 0.75rem; color: #9ca3af; margin-bottom: 0.2rem; }
.docs-pagination-title { font-size: 0.9rem; font-weight: 500; color: var(--header-link-hover-color, #2563eb); }

/* ===== TABLE OF CONTENTS (right) ===== */
.docs-toc-col { position: relative; }
.docs-toc {
    position: sticky;
    top: 20px;
    padding: 1.5rem 0;
    font-size: 0.78rem;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
}
.docs-toc-title {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #9ca3af;
    margin-bottom: 0.75rem;
}
.docs-toc a {
    display: block;
    padding: 0.25rem 0;
    color: #9ca3af;
    text-decoration: none;
    border-left: 2px solid transparent;
    padding-left: 0.75rem;
    line-height: 1.4;
    transition: all 0.15s;
}
.docs-toc a:hover { color: #374151; }
.docs-toc a.active {
    color: var(--header-link-hover-color, #2563eb);
    border-left-color: var(--header-link-hover-color, #2563eb);
}
.docs-toc a.toc-h3 { padding-left: 1.5rem; font-size: 0.75rem; }

/* ===== RESPONSIVE ===== */
@media (max-width: 991px) {
    .docs-toc-col { display: none; }
    .docs-content-col { flex: 0 0 100%; max-width: 100%; }
    .docs-sidebar-col {
        flex: 0 0 100%;
        max-width: 100%;
        order: -1;
    }
    .docs-sidebar {
        position: relative;
        top: 0;
        max-height: none;
        padding: 1rem 0;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 1rem;
    }
    .docs-nav-links { display: none; }
    .docs-nav-links.show { display: block; }
}
@media (max-width: 575px) {
    .docs-title { font-size: 1.5rem; }
    .docs-body { font-size: 0.9rem; }
    .docs-pagination { flex-direction: column; }
    .docs-pagination-prev, .docs-pagination-next { max-width: 100%; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== Table of Contents (auto-generated from H2/H3) =====
    var tocNav = document.getElementById('docs-toc-nav');
    var article = document.querySelector('.docs-body');
    if (tocNav && article) {
        var headings = article.querySelectorAll('h2, h3');
        headings.forEach(function(h, i) {
            // Add ID to heading if missing
            if (!h.id) {
                h.id = 'heading-' + i;
            }
            var a = document.createElement('a');
            a.href = '#' + h.id;
            a.textContent = h.textContent;
            if (h.tagName === 'H3') a.classList.add('toc-h3');
            tocNav.appendChild(a);
        });

        // Scroll spy: highlight active TOC item
        var tocLinks = tocNav.querySelectorAll('a');
        if (tocLinks.length > 0) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        tocLinks.forEach(function(l) { l.classList.remove('active'); });
                        var active = tocNav.querySelector('a[href="#' + entry.target.id + '"]');
                        if (active) active.classList.add('active');
                    }
                });
            }, { rootMargin: '-80px 0px -70% 0px' });
            headings.forEach(function(h) { observer.observe(h); });
        }
    }

    // ===== Sidebar accordion =====
    document.querySelectorAll('.docs-nav-section-title').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var links = btn.nextElementSibling;
            if (links) links.classList.toggle('show');
            btn.classList.toggle('active');
        });
    });

    // ===== Sidebar search =====
    var searchInput = document.getElementById('docs-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('.docs-nav-links li').forEach(function(li) {
                var text = li.textContent.toLowerCase();
                li.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
            // Show all sections when searching
            if (q) {
                document.querySelectorAll('.docs-nav-links').forEach(function(ul) { ul.classList.add('show'); });
            }
        });
    }

    // ===== Copy button for code blocks =====
    document.querySelectorAll('.docs-body pre').forEach(function(pre) {
        var btn = document.createElement('button');
        btn.className = 'docs-code-copy';
        btn.textContent = 'Copiar';
        btn.addEventListener('click', function() {
            var code = pre.querySelector('code');
            var text = code ? code.textContent : pre.textContent;
            navigator.clipboard.writeText(text).then(function() {
                btn.textContent = 'Copiado!';
                btn.classList.add('copied');
                setTimeout(function() {
                    btn.textContent = 'Copiar';
                    btn.classList.remove('copied');
                }, 2000);
            });
        });
        pre.style.position = 'relative';
        pre.appendChild(btn);
    });
});
</script>
