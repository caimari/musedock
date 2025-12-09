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
