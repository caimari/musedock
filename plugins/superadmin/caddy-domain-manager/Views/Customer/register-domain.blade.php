@extends('Customer.layout')

@section('styles')
<style>
    .search-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .search-card .card-header {
        background: #667eea;
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 30px;
        text-align: center;
    }
    .search-input-group {
        max-width: 600px;
        margin: 0 auto;
    }
    .search-input-group .form-control {
        font-size: 1.2rem;
        padding: 15px 20px;
        border-radius: 10px 0 0 10px;
        border: 2px solid #e0e0e0;
    }
    .search-input-group .btn {
        padding: 15px 30px;
        border-radius: 0 10px 10px 0;
        font-weight: bold;
    }
    .domain-result {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        margin-bottom: 10px;
        transition: all 0.2s;
    }
    .domain-result:hover {
        border-color: #667eea;
        background: #f8f9ff;
    }
    .domain-result.unavailable {
        opacity: 0.6;
        background: #f8f9fa;
    }
    .domain-result .domain-name {
        font-size: 1.1rem;
        font-weight: 500;
    }
    .domain-result .domain-extension {
        color: #667eea;
    }
    .domain-result .price {
        font-size: 1.2rem;
        font-weight: bold;
        color: #28a745;
    }
    .domain-result .price.premium {
        color: #ffc107;
    }
    .domain-result .status-badge {
        font-size: 0.8rem;
        padding: 5px 12px;
        border-radius: 20px;
    }
    .domain-result .btn-register {
        background: #667eea;
        border: none;
        padding: 8px 20px;
        border-radius: 20px;
    }
    .domain-result .btn-register:hover {
        background: #5a6fd6;
    }
    .results-container {
        max-height: 500px;
        overflow-y: auto;
    }
    .loading-spinner {
        display: none;
        text-align: center;
        padding: 40px;
    }
    .recent-orders {
        margin-top: 30px;
    }
    .order-item {
        padding: 10px 15px;
        border-left: 3px solid #667eea;
        background: #f8f9fa;
        margin-bottom: 10px;
        border-radius: 0 5px 5px 0;
    }
    .order-item.registered {
        border-left-color: #28a745;
    }
    .order-item.failed {
        border-left-color: #dc3545;
    }
    .order-item.processing {
        border-left-color: #ffc107;
    }
</style>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card search-card">
            <div class="card-header">
                <h2 class="mb-2 text-white"><i class="bi bi-globe2 me-2 text-white"></i>Registrar Dominio</h2>
                <p class="mb-0 opacity-75">Busca y registra tu dominio perfecto</p>
            </div>
            <div class="card-body p-4">
                <!-- Buscador -->
                <form id="searchForm" onsubmit="searchDomains(event)">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf_token ?? csrf_token() ?>">
                    <div class="input-group search-input-group mb-4">
                        <input type="text" class="form-control" id="domainQuery" name="query"
                               placeholder="Escribe el nombre de tu dominio..."
                               autocomplete="off" required minlength="2">
                        <button class="btn btn-primary" type="submit" id="searchBtn">
                            <i class="bi bi-search me-2"></i>Buscar
                        </button>
                    </div>
                </form>

                <!-- Loading -->
                <div class="loading-spinner" id="loadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Buscando...</span>
                    </div>
                    <p class="mt-3 text-muted">Buscando disponibilidad...</p>
                </div>

                <!-- Resultados -->
                <div id="resultsContainer" class="results-container" style="display: none;">
                    <h5 class="mb-3"><i class="bi bi-list-check me-2"></i>Resultados de busqueda</h5>
                    <div id="resultsList"></div>
                </div>

                <!-- Sin resultados -->
                <div id="noResults" style="display: none;" class="text-center py-4">
                    <i class="bi bi-emoji-frown text-muted" style="font-size: 3rem;"></i>
                    <p class="mt-3 text-muted">No se encontraron dominios disponibles</p>
                </div>

                <!-- Ordenes recientes -->
                <?php if (!empty($recentOrders)): ?>
                <div class="recent-orders">
                    <h6 class="text-muted mb-3"><i class="bi bi-clock-history me-2"></i>Registros recientes</h6>
                    <?php foreach ($recentOrders as $order): ?>
                    <div class="order-item <?= $order['status'] ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($order['domain']) ?></strong>
                                <small class="text-muted ms-2"><?= date('d/m/Y', strtotime($order['created_at'])) ?></small>
                            </div>
                            <span class="badge bg-<?= $order['status'] === 'registered' ? 'success' : ($order['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info adicional -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-shield-check text-success" style="font-size: 2.5rem;"></i>
                        <h6 class="mt-3">Proteccion Cloudflare</h6>
                        <p class="text-muted small mb-0">SSL gratuito y proteccion DDoS incluidos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-lightning-charge text-warning" style="font-size: 2.5rem;"></i>
                        <h6 class="mt-3">Activacion Rapida</h6>
                        <p class="text-muted small mb-0">Tu dominio estara listo en minutos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-envelope-at text-info" style="font-size: 2.5rem;"></i>
                        <h6 class="mt-3">Email Routing</h6>
                        <p class="text-muted small mb-0">Recibe emails en tu dominio</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function searchDomains(event) {
    event.preventDefault();

    const query = document.getElementById('domainQuery').value.trim();
    if (query.length < 2) {
        Swal.fire({
            icon: 'warning',
            title: 'Busqueda muy corta',
            text: 'Introduce al menos 2 caracteres'
        });
        return;
    }

    // Mostrar loading
    document.getElementById('loadingSpinner').style.display = 'block';
    document.getElementById('resultsContainer').style.display = 'none';
    document.getElementById('noResults').style.display = 'none';
    document.getElementById('searchBtn').disabled = true;

    const formData = new FormData();
    formData.append('_csrf_token', '<?= $csrf_token ?? csrf_token() ?>');
    formData.append('query', query);

    fetch('/customer/domain/search', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('searchBtn').disabled = false;

        if (data.success && data.results && data.results.length > 0) {
            displayResults(data.results);
        } else {
            document.getElementById('noResults').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('searchBtn').disabled = false;
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al buscar dominios. Intenta de nuevo.'
        });
    });
}

function displayResults(results) {
    const container = document.getElementById('resultsList');
    container.innerHTML = '';

    results.forEach(result => {
        const isAvailable = result.available;
        const isPremium = result.is_premium;
        const price = result.price ? parseFloat(result.price).toFixed(2) : '---';
        const currency = result.currency || 'EUR';

        // Separar nombre y extension
        const parts = result.domain.split('.');
        const name = parts[0];
        const ext = parts.slice(1).join('.');

        const div = document.createElement('div');
        div.className = 'domain-result' + (isAvailable ? '' : ' unavailable');

        div.innerHTML = `
            <div>
                <span class="domain-name">${name}<span class="domain-extension">.${ext}</span></span>
                ${isPremium ? '<span class="badge bg-warning text-dark ms-2">Premium</span>' : ''}
            </div>
            <div class="d-flex align-items-center gap-3">
                ${isAvailable ?
                    `<span class="price ${isPremium ? 'premium' : ''}">${price} ${currency}</span>
                     <button class="btn btn-primary btn-register" onclick="selectDomain('${result.domain}', ${result.price || 0}, '${currency}')">
                        <i class="bi bi-cart-plus me-1"></i>Registrar
                     </button>` :
                    '<span class="status-badge bg-secondary text-white">No disponible</span>'
                }
            </div>
        `;

        container.appendChild(div);
    });

    document.getElementById('resultsContainer').style.display = 'block';
}

function selectDomain(domain, price, currency) {
    Swal.fire({
        title: 'Registrar ' + domain + '?',
        html: `
            <p>Precio: <strong>${price.toFixed(2)} ${currency}</strong> / año</p>
            <p class="text-muted small mb-3">Incluye: SSL, Cloudflare Protection, Email Routing</p>
            <p class="text-muted small"><i class="bi bi-info-circle me-1"></i>En el siguiente paso deberas completar los datos del registrante</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-arrow-right-circle me-1"></i> Continuar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Enviar seleccion al servidor
            const formData = new FormData();
            formData.append('_csrf_token', '<?= $csrf_token ?? csrf_token() ?>');
            formData.append('domain', domain);
            formData.append('price', price);
            formData.append('currency', currency);

            fetch('/customer/domain/select', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect || '/customer/register-domain/contact';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Error al seleccionar dominio'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexion'
                });
            });
        }
    });
}

</script>
@endsection
