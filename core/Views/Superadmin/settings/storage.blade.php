@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="bi bi-hdd me-2"></i>{{ $title }}</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('settings.storage.update') }}">
      {!! csrf_field() !!}

      <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Nota:</strong> Estos ajustes modifican las variables del archivo <code>.env</code>. Asegúrate de tener una copia de seguridad antes de hacer cambios.
      </div>

      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0">Disco de almacenamiento</h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Disco principal</label>
            <select name="filesystem_disk" id="filesystem_disk" class="form-select">
              <option value="local" {{ ($envConfig['FILESYSTEM_DISK'] ?? 'local') === 'local' ? 'selected' : '' }}>Local (por defecto)</option>
              <option value="r2" {{ ($envConfig['FILESYSTEM_DISK'] ?? '') === 'r2' ? 'selected' : '' }}>Cloudflare R2</option>
              <option value="s3" {{ ($envConfig['FILESYSTEM_DISK'] ?? '') === 's3' ? 'selected' : '' }}>Amazon S3</option>
              <option value="ionos" {{ ($envConfig['FILESYSTEM_DISK'] ?? '') === 'ionos' ? 'selected' : '' }}>IONOS S3</option>
            </select>
            <small class="text-muted">Selecciona el disco principal para almacenar archivos subidos</small>
          </div>
        </div>
      </div>

      <!-- Cloudflare R2 -->
      <div class="card mb-4 storage-config" id="config-r2" style="{{ ($envConfig['FILESYSTEM_DISK'] ?? 'local') === 'r2' ? '' : 'display:none;' }}">
        <div class="card-header bg-warning text-dark">
          <h5 class="mb-0"><i class="bi bi-cloud me-2"></i>Cloudflare R2</h5>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Access Key ID</label>
              <input type="text" name="r2_access_key_id" class="form-control" value="{{ $envConfig['R2_ACCESS_KEY_ID'] ?? '' }}" placeholder="Tu Access Key ID de R2">
            </div>
            <div class="col-md-6">
              <label class="form-label">Secret Access Key</label>
              <input type="password" name="r2_secret_access_key" class="form-control" placeholder="Dejar vacío para mantener actual">
              <small class="text-muted">{{ !empty($envConfig['R2_SECRET_ACCESS_KEY']) ? 'Configurado (dejar vacío para mantener)' : 'No configurado' }}</small>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label">Bucket</label>
              <input type="text" name="r2_bucket" class="form-control" value="{{ $envConfig['R2_BUCKET'] ?? '' }}" placeholder="nombre-bucket">
            </div>
            <div class="col-md-4">
              <label class="form-label">Endpoint</label>
              <input type="text" name="r2_endpoint" class="form-control" value="{{ $envConfig['R2_ENDPOINT'] ?? '' }}" placeholder="https://xxx.r2.cloudflarestorage.com">
            </div>
            <div class="col-md-4">
              <label class="form-label">URL pública</label>
              <input type="text" name="r2_url" class="form-control" value="{{ $envConfig['R2_URL'] ?? '' }}" placeholder="https://cdn.tudominio.com">
              <small class="text-muted">URL del dominio personalizado o público</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Amazon S3 -->
      <div class="card mb-4 storage-config" id="config-s3" style="{{ ($envConfig['FILESYSTEM_DISK'] ?? 'local') === 's3' ? '' : 'display:none;' }}">
        <div class="card-header bg-warning text-dark">
          <h5 class="mb-0"><i class="bi bi-cloud me-2"></i>Amazon S3</h5>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Access Key ID</label>
              <input type="text" name="aws_access_key_id" class="form-control" value="{{ $envConfig['AWS_ACCESS_KEY_ID'] ?? '' }}" placeholder="Tu Access Key ID de AWS">
            </div>
            <div class="col-md-6">
              <label class="form-label">Secret Access Key</label>
              <input type="password" name="aws_secret_access_key" class="form-control" placeholder="Dejar vacío para mantener actual">
              <small class="text-muted">{{ !empty($envConfig['AWS_SECRET_ACCESS_KEY']) ? 'Configurado (dejar vacío para mantener)' : 'No configurado' }}</small>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label">Región</label>
              <select name="aws_default_region" class="form-select">
                <option value="us-east-1" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'us-east-1' ? 'selected' : '' }}>US East (N. Virginia)</option>
                <option value="us-east-2" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'us-east-2' ? 'selected' : '' }}>US East (Ohio)</option>
                <option value="us-west-1" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'us-west-1' ? 'selected' : '' }}>US West (N. California)</option>
                <option value="us-west-2" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'us-west-2' ? 'selected' : '' }}>US West (Oregon)</option>
                <option value="eu-west-1" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'eu-west-1' ? 'selected' : '' }}>EU (Ireland)</option>
                <option value="eu-west-2" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'eu-west-2' ? 'selected' : '' }}>EU (London)</option>
                <option value="eu-west-3" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'eu-west-3' ? 'selected' : '' }}>EU (Paris)</option>
                <option value="eu-central-1" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'eu-central-1' ? 'selected' : '' }}>EU (Frankfurt)</option>
                <option value="eu-south-1" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'eu-south-1' ? 'selected' : '' }}>EU (Milan)</option>
                <option value="ap-northeast-1" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'ap-northeast-1' ? 'selected' : '' }}>Asia Pacific (Tokyo)</option>
                <option value="ap-southeast-1" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'ap-southeast-1' ? 'selected' : '' }}>Asia Pacific (Singapore)</option>
                <option value="sa-east-1" {{ ($envConfig['AWS_DEFAULT_REGION'] ?? '') === 'sa-east-1' ? 'selected' : '' }}>South America (São Paulo)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Bucket</label>
              <input type="text" name="aws_bucket" class="form-control" value="{{ $envConfig['AWS_BUCKET'] ?? '' }}" placeholder="nombre-bucket">
            </div>
            <div class="col-md-4">
              <label class="form-label">URL pública (opcional)</label>
              <input type="text" name="aws_url" class="form-control" value="{{ $envConfig['AWS_URL'] ?? '' }}" placeholder="https://cdn.tudominio.com">
              <small class="text-muted">Dejar vacío para usar URL por defecto de S3</small>
            </div>
          </div>
        </div>
      </div>

      <!-- IONOS S3 -->
      <div class="card mb-4 storage-config" id="config-ionos" style="{{ ($envConfig['FILESYSTEM_DISK'] ?? 'local') === 'ionos' ? '' : 'display:none;' }}">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="bi bi-cloud me-2"></i>IONOS S3 Object Storage</h5>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Access Key ID</label>
              <input type="text" name="ionos_access_key_id" class="form-control" value="{{ $envConfig['IONOS_ACCESS_KEY_ID'] ?? '' }}" placeholder="Tu Access Key ID de IONOS">
            </div>
            <div class="col-md-6">
              <label class="form-label">Secret Access Key</label>
              <input type="password" name="ionos_secret_access_key" class="form-control" placeholder="Dejar vacío para mantener actual">
              <small class="text-muted">{{ !empty($envConfig['IONOS_SECRET_ACCESS_KEY']) ? 'Configurado (dejar vacío para mantener)' : 'No configurado' }}</small>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label">Bucket</label>
              <input type="text" name="ionos_bucket" class="form-control" value="{{ $envConfig['IONOS_BUCKET'] ?? '' }}" placeholder="nombre-bucket">
            </div>
            <div class="col-md-3">
              <label class="form-label">Región</label>
              <select name="ionos_region" class="form-select">
                <option value="de" {{ ($envConfig['IONOS_REGION'] ?? 'de') === 'de' ? 'selected' : '' }}>Alemania (de)</option>
                <option value="eu-central-2" {{ ($envConfig['IONOS_REGION'] ?? '') === 'eu-central-2' ? 'selected' : '' }}>EU Central 2</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Endpoint</label>
              <input type="text" name="ionos_endpoint" class="form-control" value="{{ $envConfig['IONOS_ENDPOINT'] ?? 'https://s3-eu-central-1.ionoscloud.com' }}" placeholder="https://s3-eu-central-1.ionoscloud.com">
            </div>
            <div class="col-md-3">
              <label class="form-label">URL pública</label>
              <input type="text" name="ionos_url" class="form-control" value="{{ $envConfig['IONOS_URL'] ?? '' }}" placeholder="https://bucket.s3-eu-central-1.ionoscloud.com">
            </div>
          </div>
        </div>
      </div>

      <!-- Local Storage Info -->
      <div class="card mb-4 storage-config" id="config-local" style="{{ ($envConfig['FILESYSTEM_DISK'] ?? 'local') === 'local' ? '' : 'display:none;' }}">
        <div class="card-header" style="background-color: #d4edda; color: #155724;">
          <h5 class="mb-0"><i class="bi bi-hdd me-2"></i>Almacenamiento Local</h5>
        </div>
        <div class="card-body">
          <div class="alert alert-success mb-0">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Almacenamiento local activo.</strong> Los archivos se guardan en el servidor en el directorio <code>storage/app/public</code>.
            <br><small class="text-muted">No se requiere configuración adicional para el almacenamiento local.</small>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background-color: #e7f1ff; color: #0d6efd;">
          <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información sobre proveedores</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-4 mb-3">
              <h6><i class="bi bi-cloud-fill text-warning me-1"></i> Cloudflare R2</h6>
              <small class="text-muted">
                Compatible con S3<br>
                Sin costes de egress<br>
                <a href="https://developers.cloudflare.com/r2/" target="_blank">Documentación</a>
              </small>
            </div>
            <div class="col-md-4 mb-3">
              <h6><i class="bi bi-cloud-fill text-warning me-1"></i> Amazon S3</h6>
              <small class="text-muted">
                El estándar de la industria<br>
                Múltiples regiones globales<br>
                <a href="https://aws.amazon.com/s3/" target="_blank">Documentación</a>
              </small>
            </div>
            <div class="col-md-4 mb-3">
              <h6><i class="bi bi-cloud-fill text-primary me-1"></i> IONOS S3</h6>
              <small class="text-muted">
                Compatible con S3<br>
                Servidores en Europa<br>
                <a href="https://docs.ionos.com/cloud/storage/s3-object-storage" target="_blank">Documentación</a>
              </small>
            </div>
          </div>
        </div>
      </div>

      <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-2"></i>Guardar configuración
        </button>
      </div>
    </form>

    <hr class="my-4">

    <!-- TENANT STORAGE CONFIGURATION -->
    <form method="POST" action="{{ route('settings.storage.tenant.update') }}">
      {!! csrf_field() !!}

      <h4 class="mb-4"><i class="bi bi-people me-2"></i>Configuración de almacenamiento para Tenants</h4>

      <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Multi-tenant:</strong> Configura qué discos de almacenamiento están disponibles para los tenants y la cuota de espacio por defecto.
      </div>

      <!-- Discos disponibles para tenants -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-hdd-stack me-2"></i>Discos disponibles para Tenants</h5>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">Selecciona qué discos de almacenamiento pueden usar los tenants. El superadmin siempre tiene acceso a todos los discos.</p>

          <div class="row">
            <div class="col-md-6 mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="tenant_disk_media" name="tenant_disk_media_enabled" value="true" {{ ($envConfig['TENANT_DISK_MEDIA_ENABLED'] ?? 'true') === 'true' ? 'checked' : '' }}>
                <label class="form-check-label" for="tenant_disk_media">
                  <strong><i class="bi bi-hdd me-1"></i>Local Seguro</strong>
                  <br><small class="text-muted">Almacenamiento en /storage/app/media/ - Recomendado</small>
                </label>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="tenant_disk_local" name="tenant_disk_local_enabled" value="true" {{ ($envConfig['TENANT_DISK_LOCAL_ENABLED'] ?? 'false') === 'true' ? 'checked' : '' }}>
                <label class="form-check-label" for="tenant_disk_local">
                  <strong><i class="bi bi-folder me-1"></i>Local Legacy</strong>
                  <br><small class="text-muted">Archivos en /public/assets/uploads/ - Deprecated</small>
                </label>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="tenant_disk_r2" name="tenant_disk_r2_enabled" value="true" {{ ($envConfig['TENANT_DISK_R2_ENABLED'] ?? 'true') === 'true' ? 'checked' : '' }}>
                <label class="form-check-label" for="tenant_disk_r2">
                  <strong><i class="bi bi-cloud me-1"></i>Cloudflare R2</strong>
                  <br><small class="text-muted">CDN global - Recomendado para producción</small>
                </label>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="tenant_disk_s3" name="tenant_disk_s3_enabled" value="true" {{ ($envConfig['TENANT_DISK_S3_ENABLED'] ?? 'false') === 'true' ? 'checked' : '' }}>
                <label class="form-check-label" for="tenant_disk_s3">
                  <strong><i class="bi bi-cloud-arrow-up me-1"></i>Amazon S3</strong>
                  <br><small class="text-muted">Almacenamiento en Amazon Web Services</small>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cuota de almacenamiento por defecto -->
      <div class="card mb-4">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Cuota de almacenamiento por defecto</h5>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">Esta cuota se asignará automáticamente a los nuevos tenants. Puedes modificar la cuota individual de cada tenant desde su configuración.</p>

          <div class="row align-items-end">
            <div class="col-md-4">
              <label class="form-label">Cuota por defecto</label>
              <div class="input-group">
                <input type="number" name="tenant_default_storage_quota_mb" class="form-control" value="{{ $envConfig['TENANT_DEFAULT_STORAGE_QUOTA_MB'] ?? 1024 }}" min="100" max="102400">
                <span class="input-group-text">MB</span>
              </div>
            </div>
            <div class="col-md-8">
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelector('[name=tenant_default_storage_quota_mb]').value = 512">512 MB</button>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.querySelector('[name=tenant_default_storage_quota_mb]').value = 1024">1 GB</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelector('[name=tenant_default_storage_quota_mb]').value = 2048">2 GB</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelector('[name=tenant_default_storage_quota_mb]').value = 5120">5 GB</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelector('[name=tenant_default_storage_quota_mb]').value = 10240">10 GB</button>
              </div>
              <small class="text-muted d-block mt-2">
                <i class="bi bi-lightbulb me-1"></i>Valores típicos: 512 MB (trial), 1 GB (free), 5 GB (basic), 10 GB (pro)
              </small>
            </div>
          </div>
        </div>
      </div>

      <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <button type="submit" class="btn btn-success">
          <i class="bi bi-save me-2"></i>Guardar configuración de Tenants
        </button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const diskSelect = document.getElementById('filesystem_disk');
  const configSections = document.querySelectorAll('.storage-config');

  function showDiskConfig(disk) {
    configSections.forEach(section => {
      section.style.display = 'none';
    });
    const targetConfig = document.getElementById('config-' + disk);
    if (targetConfig) {
      targetConfig.style.display = 'block';
    }
  }

  if (diskSelect) {
    diskSelect.addEventListener('change', function() {
      showDiskConfig(this.value);
    });
  }
});
</script>
@endpush
