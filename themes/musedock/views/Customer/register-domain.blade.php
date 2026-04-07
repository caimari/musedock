@extends('Customer.layout')

@section('panel_content')
<style>
/* ===== Register Domain — Custom CSS ===== */
.rd-sandbox-banner {
    display: flex; align-items: center; gap: 8px;
    background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px;
    padding: 10px 14px; margin-bottom: 14px;
    font-size: 0.82rem; color: #7c6200;
}
.rd-sandbox-banner i { font-size: 1rem; color: #e6a800; margin-right: 6px; }

/* Search card */
.rd-search-card {
    background: #fff; border: 1px solid #edf0f5; border-radius: 10px;
    overflow: hidden; margin-bottom: 16px;
}
.rd-search-header {
    background: #4e73df; color: #fff; padding: 22px 24px; text-align: center;
}
.rd-search-header h2 {
    font-size: 1.1rem; font-weight: 700; margin: 0 0 4px; color: #fff;
}
.rd-search-header h2 i { margin-right: 6px; }
.rd-search-header p {
    font-size: 0.82rem; margin: 0; color: #fff !important; opacity: 0.85;
}
.rd-search-body { padding: 20px 24px; }

/* Search form */
.rd-search-row {
    display: flex; gap: 0; max-width: 560px; margin: 0 auto 12px;
}
.rd-search-input {
    flex: 1; min-width: 0;
    font-size: 0.88rem; padding: 10px 14px;
    border: 1px solid #dde1e7; border-right: none;
    border-radius: 8px 0 0 8px; outline: none;
    color: #243141; background: #fff;
}
.rd-search-input::placeholder { color: #8a94a6; }
.rd-search-input:focus { border-color: #4e73df; }
.rd-search-btn {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.82rem; font-weight: 600; padding: 10px 18px;
    background: #4e73df; color: #fff; border: none;
    border-radius: 0 8px 8px 0; cursor: pointer;
    white-space: nowrap; transition: background 0.15s;
}
.rd-search-btn:hover { background: #3b5fcc; }
.rd-search-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.rd-search-btn i { margin-right: 6px; }

/* Small inline spinner */
.rd-loading {
    display: none; align-items: center; justify-content: center; gap: 8px;
    padding: 14px 0; font-size: 0.82rem; color: #8a94a6;
}
.rd-spinner {
    display: inline-block; width: 18px; height: 18px;
    border: 2px solid #dde1e7; border-top-color: #4e73df;
    border-radius: 50%;
    animation: rd-spin 0.6s linear infinite;
}
@keyframes rd-spin { to { transform: rotate(360deg); } }

/* Results */
.rd-results { display: none; }
.rd-results-title {
    font-size: 0.88rem; font-weight: 600; color: #243141; margin-bottom: 10px;
}
.rd-results-title i { margin-right: 6px; color: #4e73df; }
.rd-results-list { max-height: 400px; overflow-y: auto; }

.rd-result-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 14px; border: 1px solid #edf0f5; border-radius: 8px;
    margin-bottom: 8px; transition: border-color 0.15s, background 0.15s;
}
.rd-result-row:hover { border-color: #4e73df; background: #f6f8ff; }
.rd-result-row.rd-unavailable { opacity: 0.55; background: #fafbfc; }
.rd-domain-name { font-size: 0.88rem; font-weight: 500; color: #243141; }
.rd-domain-ext { color: #4e73df; }
.rd-premium-tag {
    display: inline-block; font-size: 0.68rem; font-weight: 600;
    background: #fff3cd; color: #856404; padding: 2px 8px;
    border-radius: 10px; margin-left: 8px;
}
.rd-result-right { display: flex; align-items: center; gap: 10px; }
.rd-price { font-size: 0.88rem; font-weight: 700; color: #28a745; }
.rd-price.rd-price-premium { color: #e6a800; }
.rd-register-btn {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.78rem; font-weight: 600; padding: 6px 14px;
    background: #4e73df; color: #fff; border: none;
    border-radius: 16px; cursor: pointer; transition: background 0.15s;
}
.rd-register-btn:hover { background: #3b5fcc; }
.rd-register-btn i { margin-right: 6px; }
.rd-unavailable-tag {
    font-size: 0.72rem; font-weight: 500; padding: 4px 10px;
    background: #8a94a6; color: #fff; border-radius: 10px;
}

/* No results */
.rd-no-results {
    display: none; text-align: center; padding: 24px 0; color: #8a94a6;
}
.rd-no-results i { font-size: 2rem; display: block; margin-bottom: 8px; }
.rd-no-results p { font-size: 0.82rem; margin: 0; }

/* Recent orders */
.rd-recent { margin-top: 18px; }
.rd-recent-title {
    font-size: 0.82rem; font-weight: 600; color: #8a94a6; margin-bottom: 10px;
}
.rd-recent-title i { margin-right: 6px; }
.rd-order-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 12px; background: #fafbfc;
    border-left: 3px solid #4e73df; border-radius: 0 6px 6px 0;
    margin-bottom: 8px; font-size: 0.82rem;
}
.rd-order-item.rd-status-registered { border-left-color: #28a745; }
.rd-order-item.rd-status-failed { border-left-color: #dc3545; }
.rd-order-item.rd-status-processing { border-left-color: #e6a800; }
.rd-order-domain { font-weight: 600; color: #243141; }
.rd-order-date { color: #8a94a6; font-size: 0.75rem; margin-left: 8px; }
.rd-order-badge {
    font-size: 0.7rem; font-weight: 600; padding: 3px 10px;
    border-radius: 10px; color: #fff;
}
.rd-badge-registered { background: #28a745; }
.rd-badge-failed { background: #dc3545; }
.rd-badge-processing { background: #e6a800; }

/* Feature cards */
.rd-features {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
    margin-top: 16px;
}
.rd-feature-card {
    background: #fff; border: 1px solid #edf0f5; border-radius: 10px;
    padding: 18px 14px; text-align: center;
}
.rd-feature-card i {
    font-size: 1.6rem; display: block; margin-bottom: 8px; margin-right: 0;
}
.rd-feature-card h4 {
    font-size: 0.82rem; font-weight: 600; color: #243141; margin: 0 0 4px;
}
.rd-feature-card p {
    font-size: 0.75rem; color: #8a94a6; margin: 0;
}
.rd-icon-green { color: #28a745; }
.rd-icon-amber { color: #e6a800; }
.rd-icon-blue { color: #4e73df; }

@media (max-width: 640px) {
    .rd-features { grid-template-columns: 1fr; }
    .rd-search-body { padding: 14px; }
    .rd-result-row { flex-direction: column; align-items: flex-start; gap: 8px; }
    .rd-result-right { width: 100%; justify-content: flex-end; }
}
</style>

<?php if (($openprovider_mode ?? 'sandbox') === 'sandbox'): ?>
<div class="rd-sandbox-banner">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div><strong>Modo Sandbox:</strong> Los registros de dominios son de prueba y no se procesaran en produccion.</div>
</div>
<?php endif; ?>

<div class="rd-search-card">
    <div class="rd-search-header">
        <h2><i class="bi bi-globe2"></i>Registrar Dominio</h2>
        <p>Busca y registra tu dominio perfecto</p>
    </div>
    <div class="rd-search-body">
        <form id="searchForm" onsubmit="searchDomains(event)">
            <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">
            <div class="rd-search-row">
                <input type="text" class="rd-search-input" id="domainQuery" name="query"
                       placeholder="Escribe el nombre de tu dominio..."
                       autocomplete="off" required minlength="2">
                <button class="rd-search-btn" type="submit" id="searchBtn">
                    <i class="bi bi-search"></i>Buscar
                </button>
            </div>
        </form>

        <div class="rd-loading" id="loadingSpinner">
            <span class="rd-spinner"></span>
            Buscando disponibilidad...
        </div>

        <div class="rd-results" id="resultsContainer">
            <div class="rd-results-title"><i class="bi bi-list-check"></i>Resultados de busqueda</div>
            <div class="rd-results-list" id="resultsList"></div>
        </div>

        <div class="rd-no-results" id="noResults">
            <i class="bi bi-emoji-frown"></i>
            <p>No se encontraron dominios disponibles</p>
        </div>

        <?php if (!empty($recentOrders)): ?>
        <div class="rd-recent">
            <div class="rd-recent-title"><i class="bi bi-clock-history"></i>Registros recientes</div>
            <?php foreach ($recentOrders as $order): ?>
            <div class="rd-order-item rd-status-<?= $order['status'] ?>">
                <div>
                    <span class="rd-order-domain"><?= htmlspecialchars($order['domain']) ?><?= isset($order['extension']) ? '.' . htmlspecialchars($order['extension']) : '' ?></span>
                    <span class="rd-order-date"><?= date('d/m/Y', strtotime($order['created_at'])) ?></span>
                </div>
                <span class="rd-order-badge rd-badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="rd-features">
    <div class="rd-feature-card">
        <i class="bi bi-shield-check rd-icon-green"></i>
        <h4>Proteccion Cloudflare</h4>
        <p>SSL gratuito y proteccion DDoS incluidos</p>
    </div>
    <div class="rd-feature-card">
        <i class="bi bi-lightning-charge rd-icon-amber"></i>
        <h4>Activacion Rapida</h4>
        <p>Tu dominio estara listo en minutos</p>
    </div>
    <div class="rd-feature-card">
        <i class="bi bi-envelope-at rd-icon-blue"></i>
        <h4>Email Routing</h4>
        <p>Recibe emails en tu dominio</p>
    </div>
</div>

<script>
function searchDomains(event) {
    event.preventDefault();
    var query = document.getElementById('domainQuery').value.trim();
    if (query.length < 2) {
        Swal.fire({ icon: 'warning', title: 'Busqueda muy corta', text: 'Introduce al menos 2 caracteres' });
        return;
    }

    document.getElementById('loadingSpinner').style.display = 'flex';
    document.getElementById('resultsContainer').style.display = 'none';
    document.getElementById('noResults').style.display = 'none';
    document.getElementById('searchBtn').disabled = true;

    var formData = new FormData();
    formData.append('_csrf_token', '<?= $csrf_token ?? csrf_token() ?>');
    formData.append('query', query);

    fetch('/customer/domain/search', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('searchBtn').disabled = false;
        if (data.success && data.results && data.results.length > 0) {
            displayResults(data.results);
        } else {
            document.getElementById('noResults').style.display = 'block';
        }
    })
    .catch(function(err) {
        console.error('Error:', err);
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('searchBtn').disabled = false;
        Swal.fire({ icon: 'error', title: 'Error', text: 'Error al buscar dominios. Intenta de nuevo.' });
    });
}

function displayResults(results) {
    var container = document.getElementById('resultsList');
    container.innerHTML = '';

    results.forEach(function(result) {
        var isAvailable = result.available;
        var isPremium = result.is_premium;
        var price = result.price ? parseFloat(result.price).toFixed(2) : '---';
        var currency = result.currency || 'EUR';

        var parts = result.domain.split('.');
        var name = parts[0];
        var ext = parts.slice(1).join('.');

        var div = document.createElement('div');
        div.className = 'rd-result-row' + (isAvailable ? '' : ' rd-unavailable');

        var leftHtml = '<div><span class="rd-domain-name">' + name + '<span class="rd-domain-ext">.' + ext + '</span></span>';
        if (isPremium) leftHtml += '<span class="rd-premium-tag">Premium</span>';
        leftHtml += '</div>';

        var rightHtml = '<div class="rd-result-right">';
        if (isAvailable) {
            rightHtml += '<span class="rd-price' + (isPremium ? ' rd-price-premium' : '') + '">' + price + ' ' + currency + '</span>';
            rightHtml += '<button class="rd-register-btn" onclick="selectDomain(\'' + result.domain + '\', ' + (result.price || 0) + ', \'' + currency + '\')"><i class="bi bi-cart-plus"></i>Registrar</button>';
        } else {
            rightHtml += '<span class="rd-unavailable-tag">No disponible</span>';
        }
        rightHtml += '</div>';

        div.innerHTML = leftHtml + rightHtml;
        container.appendChild(div);
    });

    document.getElementById('resultsContainer').style.display = 'block';
}

function selectDomain(domain, price, currency) {
    Swal.fire({
        title: 'Registrar ' + domain + '?',
        html: '<p>Precio: <strong>' + price.toFixed(2) + ' ' + currency + '</strong> / a\u00f1o</p>' +
              '<p style="font-size:0.82rem;color:#8a94a6;margin-bottom:10px;">Incluye: SSL, Cloudflare Protection, Email Routing</p>' +
              '<p style="font-size:0.78rem;color:#8a94a6;"><i class="bi bi-info-circle" style="margin-right:6px"></i>En el siguiente paso deberas completar los datos del registrante</p>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4e73df',
        cancelButtonColor: '#8a94a6',
        confirmButtonText: '<i class="bi bi-arrow-right-circle" style="margin-right:6px"></i> Continuar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            var formData = new FormData();
            formData.append('_csrf_token', '<?= $csrf_token ?? csrf_token() ?>');
            formData.append('domain', domain);
            formData.append('price', price);
            formData.append('currency', currency);

            fetch('/customer/domain/select', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.href = data.redirect || '/customer/register-domain/contact';
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error al seleccionar dominio' });
                }
            })
            .catch(function(err) {
                console.error('Error:', err);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexion' });
            });
        }
    });
}
</script>
@endsection
