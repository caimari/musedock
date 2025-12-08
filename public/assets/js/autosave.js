/**
 * Sistema de Autoguardado con Toggle ON/OFF
 * Compatible con Blog Posts y Pages
 *
 * Uso:
 * const autosave = new AutosaveManager({
 *   endpoint: '/musedock/blog/posts/123/autosave',
 *   interval: 60000, // 60 segundos
 *   fields: ['title', 'content', 'excerpt']
 * });
 */

class AutosaveManager {
  constructor(options) {
    this.endpoint = options.endpoint;
    this.interval = options.interval || 60000; // Default: 60 segundos
    this.fields = options.fields || ['title', 'content', 'excerpt'];
    this.storageKey = `autosave_enabled_${window.location.pathname}`;

    this.intervalId = null;
    this.isSaving = false;
    this.lastSaveTime = null;

    this.init();
  }

  init() {
    this.createToggleButton();
    this.loadSavedState();
    this.attachEventListeners();

    // Mostrar último guardado si existe
    if (this.lastSaveTime) {
      this.updateSaveIndicator(`Último guardado: ${this.formatTime(this.lastSaveTime)}`);
    }
  }

  createToggleButton() {
    // Buscar el contenedor donde insertar el botón (normalmente arriba del formulario)
    const formHeader = document.querySelector('.card-header, .d-flex.justify-content-between');

    if (!formHeader) {
      console.warn('No se encontró contenedor para el botón de autoguardado');
      return;
    }

    // Crear contenedor del toggle
    const toggleContainer = document.createElement('div');
    toggleContainer.className = 'autosave-toggle-container d-flex align-items-center';
    toggleContainer.innerHTML = `
      <div class="form-check form-switch me-3">
        <input class="form-check-input" type="checkbox" id="autosave-toggle" style="cursor: pointer;">
        <label class="form-check-label" for="autosave-toggle" style="cursor: pointer;">
          <i class="bi bi-cloud-arrow-up"></i> Autoguardado
        </label>
      </div>
      <small id="autosave-indicator" class="text-muted"></small>
    `;

    // Insertar al final del header
    formHeader.appendChild(toggleContainer);
  }

  loadSavedState() {
    const savedState = localStorage.getItem(this.storageKey);
    const toggle = document.getElementById('autosave-toggle');

    if (toggle) {
      toggle.checked = savedState === 'true';

      if (toggle.checked) {
        this.startAutosave();
      }
    }
  }

  attachEventListeners() {
    const toggle = document.getElementById('autosave-toggle');

    if (toggle) {
      toggle.addEventListener('change', (e) => {
        if (e.target.checked) {
          this.enableAutosave();
        } else {
          this.disableAutosave();
        }
      });
    }
  }

  enableAutosave() {
    localStorage.setItem(this.storageKey, 'true');
    this.startAutosave();
    this.updateSaveIndicator('Autoguardado activado');

    // Mostrar notificación
    this.showNotification('Autoguardado activado', 'success');
  }

  disableAutosave() {
    localStorage.setItem(this.storageKey, 'false');
    this.stopAutosave();
    this.updateSaveIndicator('Autoguardado desactivado');

    // Mostrar notificación
    this.showNotification('Autoguardado desactivado', 'info');
  }

  startAutosave() {
    // Limpiar intervalo existente si lo hay
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }

    // Iniciar nuevo intervalo
    this.intervalId = setInterval(() => {
      this.performAutosave();
    }, this.interval);

    console.log(`Autoguardado iniciado (cada ${this.interval / 1000} segundos)`);
  }

  stopAutosave() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }

    console.log('Autoguardado detenido');
  }

  async performAutosave() {
    if (this.isSaving) {
      console.log('Guardado en progreso, saltando...');
      return;
    }

    this.isSaving = true;
    this.updateSaveIndicator('<span class="spinner-border spinner-border-sm me-1"></span>Guardando...');

    // Recopilar datos de los campos
    const data = {};
    this.fields.forEach(field => {
      const element = document.getElementById(field) ||
                     document.querySelector(`[name="${field}"]`) ||
                     document.querySelector(`textarea[name="${field}"]`);

      if (element) {
        data[field] = element.value;
      }
    });

    try {
      const response = await fetch(this.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
      });

      const result = await response.json();

      if (result.success) {
        this.lastSaveTime = new Date();
        this.updateSaveIndicator(`<i class="bi bi-check-circle text-success"></i> Guardado: ${this.formatTime(this.lastSaveTime)}`);
        console.log('Autoguardado exitoso', result);
      } else {
        this.updateSaveIndicator('<i class="bi bi-exclamation-circle text-danger"></i> Error al guardar');
        console.error('Error en autoguardado:', result.message);
      }
    } catch (error) {
      this.updateSaveIndicator('<i class="bi bi-exclamation-circle text-danger"></i> Error de conexión');
      console.error('Error en autoguardado:', error);
    } finally {
      this.isSaving = false;
    }
  }

  updateSaveIndicator(message) {
    const indicator = document.getElementById('autosave-indicator');
    if (indicator) {
      indicator.innerHTML = message;
    }
  }

  showNotification(message, type = 'info') {
    if (typeof Swal !== 'undefined') {
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
      });

      Toast.fire({
        icon: type,
        title: message
      });
    }
  }

  formatTime(date) {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    return `${hours}:${minutes}:${seconds}`;
  }

  // Método público para forzar guardado manual
  async saveNow() {
    await this.performAutosave();
  }

  // Método público para destruir la instancia
  destroy() {
    this.stopAutosave();
    const toggle = document.getElementById('autosave-toggle');
    if (toggle) {
      toggle.removeEventListener('change', this.attachEventListeners);
    }
  }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
  window.AutosaveManager = AutosaveManager;
}
