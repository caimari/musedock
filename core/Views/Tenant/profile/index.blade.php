@extends('layouts.app')

@section('title', $title ?? 'Mi Perfil')

@section('content')
<div class="app-content">
  <div class="container-fluid">

    <h2 class="mb-4">{{ $title ?? 'Mi Perfil' }}</h2>

    {{-- Alertas con SweetAlert2 --}}
    @php
    $successMessage = function_exists('consume_flash') ? consume_flash('success') : (session()->pull('success') ?? '');
    $errorMessage = function_exists('consume_flash') ? consume_flash('error') : (session()->pull('error') ?? '');
    @endphp

    @if ($successMessage)
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'success',
            title: 'Correcto',
            text: <?php echo json_encode($successMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            confirmButtonColor: '#3085d6'
          });
        });
      </script>
    @endif
    @if ($errorMessage)
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: <?php echo json_encode($errorMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            confirmButtonColor: '#d33'
          });
        });
      </script>
    @endif

    <div class="row">
      {{-- Columna izquierda - Avatar --}}
      <div class="col-md-4 col-xl-3">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">Avatar</h5>
          </div>
          <div class="card-body text-center">
            @php
              $initial = strtoupper(substr($user->name, 0, 1));
              $hasAvatar = $user->avatar && file_exists(APP_ROOT . '/storage/avatars/' . $user->avatar);
            @endphp

            <div class="mb-3" id="avatar-container">
              @if($hasAvatar)
                <img src="{{ admin_url('/avatar/' . $user->avatar) }}" id="avatar-preview" class="rounded-circle img-fluid" alt="{{ $user->name }}" style="width: 128px; height: 128px; object-fit: cover;">
              @else
                <div id="avatar-preview" class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 128px; height: 128px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; font-size: 48px;">
                  {{ $initial }}
                </div>
              @endif
            </div>

            <div class="mb-3">
              <input type="file" id="avatar-input" accept="image/jpeg,image/jpg,image/png,image/gif" style="display: none;">
              <button type="button" class="btn btn-primary btn-sm me-2" id="upload-avatar-btn">
                <i class="bi bi-upload"></i> Subir Avatar
              </button>
              @if($hasAvatar)
                <button type="button" class="btn btn-danger btn-sm" id="delete-avatar-btn">
                  <i class="bi bi-trash"></i> Eliminar
                </button>
              @endif
            </div>

            <small class="text-muted d-block">JPG, PNG o GIF. Máximo 2MB</small>
          </div>
        </div>
      </div>

      {{-- Columna derecha - Formularios --}}
      <div class="col-md-8 col-xl-9">

        {{-- Información básica --}}
        <div class="card mb-3">
          <div class="card-header">
            <h5 class="card-title mb-0">Información Básica</h5>
          </div>
          <div class="card-body">
            <form method="POST" action="{{ admin_url('/profile/update-name') }}">
              @csrf
              <div class="mb-3">
                <label for="name" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ $user->name }}" required>
              </div>
              <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Guardar Nombre</button>
              </div>
            </form>
          </div>
        </div>

        {{-- Cambiar Email --}}
        <div class="card mb-3">
          <div class="card-header">
            <h5 class="card-title mb-0">Email</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="email" name="email" value="{{ $user->email }}" required>
              <small class="text-muted">Para cambiar el email, necesitarás confirmar tu contraseña</small>
            </div>
            <div class="d-flex justify-content-end">
              <button type="button" class="btn btn-primary" id="change-email-btn">Cambiar Email</button>
            </div>
          </div>
        </div>

        {{-- Seguridad --}}
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">Seguridad</h5>
          </div>
          <div class="card-body">
            <p class="text-muted mb-3">Gestiona la seguridad de tu cuenta</p>
            <button type="button" class="btn btn-primary" id="change-password-btn">
              <i class="bi bi-shield-lock"></i> Cambiar Contraseña
            </button>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const avatarInput = document.getElementById('avatar-input');
  const uploadAvatarBtn = document.getElementById('upload-avatar-btn');
  const deleteAvatarBtn = document.getElementById('delete-avatar-btn');
  const avatarPreview = document.getElementById('avatar-preview');

  // Abrir selector de archivo
  uploadAvatarBtn.addEventListener('click', function() {
    avatarInput.click();
  });

  // Subir avatar
  avatarInput.addEventListener('change', function() {
    if (!this.files || !this.files[0]) return;

    const file = this.files[0];

    // Validar tamaño
    if (file.size > 2 * 1024 * 1024) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'El archivo es muy grande. Máximo 2MB.'
      });
      return;
    }

    // Validar tipo
    if (!['image/jpeg', 'image/jpg', 'image/png', 'image/gif'].includes(file.type)) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Tipo de archivo no permitido. Solo JPG, PNG o GIF.'
      });
      return;
    }

    // Subir archivo
    const formData = new FormData();
    formData.append('avatar', file);

    // Obtener CSRF token desde el meta tag o desde el formulario
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    fetch('{{ admin_url("/profile/upload-avatar") }}', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken
      },
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Actualizar avatar en la página de perfil
        const container = document.getElementById('avatar-container');
        const headerContainer = document.getElementById('header-avatar-container');
        const userName = '{{ $user->name }}';
        const initial = userName.charAt(0).toUpperCase();

        if (container) {
          container.innerHTML = `
            <img src="${data.avatar_url}?t=${Date.now()}" id="avatar-preview" class="rounded-circle img-fluid" alt="${userName}" style="width: 128px; height: 128px; object-fit: cover;">
          `;
        }

        // Actualizar avatar en el header
        if (headerContainer) {
          headerContainer.innerHTML = `
            <img id="header-avatar-img" src="${data.avatar_url}?t=${Date.now()}" class="avatar img-fluid rounded-circle me-1" alt="${userName}" style="width: 32px; height: 32px; object-fit: cover;" />
          `;
        }

        // Agregar botón de eliminar si no existe
        if (!document.getElementById('delete-avatar-btn')) {
          const uploadBtn = document.getElementById('upload-avatar-btn');
          if (uploadBtn) {
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-danger btn-sm';
            deleteBtn.id = 'delete-avatar-btn';
            deleteBtn.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
            uploadBtn.parentElement.appendChild(deleteBtn);

            // Agregar evento al nuevo botón
            deleteBtn.addEventListener('click', handleDeleteAvatar);
          }
        }

        Swal.fire({
          icon: 'success',
          title: 'Avatar actualizado',
          text: 'Tu avatar se ha actualizado correctamente',
          timer: 2000,
          showConfirmButton: false
        });
      } else {
        throw new Error(data.error || 'Error desconocido');
      }
    })
    .catch(error => {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: error.message
      });
    });
  });

  // Función para manejar la eliminación del avatar
  function handleDeleteAvatar() {
    Swal.fire({
      title: '¿Estás seguro?',
      text: 'Se eliminará tu avatar actual',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        // Obtener CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        fetch('{{ admin_url("/profile/delete-avatar") }}', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Actualizar avatar en la página de perfil (volver a inicial)
            const container = document.getElementById('avatar-container');
            const headerContainer = document.getElementById('header-avatar-container');
            const userName = '{{ $user->name }}';
            const initial = userName.charAt(0).toUpperCase();

            if (container) {
              container.innerHTML = `
                <div id="avatar-preview" class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 128px; height: 128px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; font-size: 48px;">
                  ${initial}
                </div>
              `;
            }

            // Actualizar avatar en el header (volver a inicial)
            if (headerContainer) {
              headerContainer.innerHTML = `
                <span id="header-avatar-initial" class="avatar rounded-circle me-1 d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; font-size: 14px;">
                  ${initial}
                </span>
              `;
            }

            // Eliminar botón de eliminar
            const deleteBtn = document.getElementById('delete-avatar-btn');
            if (deleteBtn) {
              deleteBtn.remove();
            }

            Swal.fire({
              icon: 'success',
              title: 'Avatar eliminado',
              text: 'Tu avatar se ha eliminado correctamente',
              timer: 2000,
              showConfirmButton: false
            });
          } else {
            throw new Error(data.error || 'Error desconocido');
          }
        })
        .catch(error => {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
          });
        });
      }
    });
  }

  // Eliminar avatar
  if (deleteAvatarBtn) {
    deleteAvatarBtn.addEventListener('click', handleDeleteAvatar);
  }

  // Cambiar email con SweetAlert2
  const changeEmailBtn = document.getElementById('change-email-btn');
  const emailInput = document.getElementById('email');

  if (changeEmailBtn) {
    changeEmailBtn.addEventListener('click', async function(e) {
      e.preventDefault();

      const newEmail = emailInput.value.trim();
      const currentEmail = '{{ $user->email }}';

      // Validar que el email haya cambiado
      if (newEmail === currentEmail) {
        Swal.fire({
          icon: 'info',
          title: 'Sin cambios',
          text: 'El email no ha cambiado'
        });
        return;
      }

      // Validar formato de email
      if (!newEmail || !newEmail.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Por favor ingresa un email válido'
        });
        return;
      }

      // Paso 1: Mostrar advertencia y pedir contraseña
      const { value: password } = await Swal.fire({
        title: '¿Cambiar email?',
        html: `
          <div class="text-start mb-3">
            <p class="mb-3">
              <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
              <strong>¡Atención! Estás a punto de cambiar tu dirección de email.</strong>
            </p>
            <div class="alert alert-warning mb-2">
              <small><strong>Email actual:</strong> ${currentEmail}</small>
            </div>
            <div class="alert alert-info mb-3">
              <small><strong>Nuevo email:</strong> ${newEmail}</small>
            </div>
            <div class="alert alert-secondary mb-0">
              <small>
                <strong>Importante:</strong>
                <ul class="mb-0 ps-3 mt-1" style="font-size: 0.9em;">
                  <li>Usarás este email para iniciar sesión</li>
                  <li>Asegúrate de tener acceso a esta dirección</li>
                  <li>Las notificaciones se enviarán aquí</li>
                </ul>
              </small>
            </div>
          </div>
        `,
        input: 'password',
        inputLabel: 'Confirma tu contraseña actual para continuar',
        inputPlaceholder: 'Ingresa tu contraseña actual',
        inputAttributes: {
          autocomplete: 'current-password',
          autocapitalize: 'off',
          autocorrect: 'off'
        },
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cambiar email',
        cancelButtonText: 'Cancelar',
        width: '600px',
        customClass: {
          htmlContainer: 'text-start'
        },
        inputValidator: (value) => {
          if (!value) {
            return 'Debes ingresar tu contraseña actual';
          }
        }
      });

      if (password) {
        // Mostrar loading
        Swal.fire({
          title: 'Cambiando email...',
          html: 'Por favor espera mientras se actualiza tu email.',
          allowOutsideClick: false,
          allowEscapeKey: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        // Crear formulario y enviar
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ admin_url("/profile/update-email") }}';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);

        const emailInput = document.createElement('input');
        emailInput.type = 'hidden';
        emailInput.name = 'email';
        emailInput.value = newEmail;
        form.appendChild(emailInput);

        const passwordInput = document.createElement('input');
        passwordInput.type = 'hidden';
        passwordInput.name = 'current_password';
        passwordInput.value = password;
        form.appendChild(passwordInput);

        document.body.appendChild(form);
        form.submit();
      }
    });
  }

  // Cambiar contraseña con SweetAlert2
  const changePasswordBtn = document.getElementById('change-password-btn');

  if (changePasswordBtn) {
    changePasswordBtn.addEventListener('click', async function(e) {
      e.preventDefault();

      // Paso 1: Solicitar contraseña actual
      const { value: currentPassword } = await Swal.fire({
        title: 'Cambiar Contraseña',
        html: '<p class="text-muted mb-3">Primero, confirma tu contraseña actual</p>',
        input: 'password',
        inputLabel: 'Contraseña Actual',
        inputPlaceholder: 'Ingresa tu contraseña actual',
        inputAttributes: {
          autocomplete: 'current-password'
        },
        showCancelButton: true,
        confirmButtonText: 'Siguiente',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
          if (!value) {
            return 'Debes ingresar tu contraseña actual';
          }
        }
      });

      if (!currentPassword) return;

      // Paso 2: Solicitar nueva contraseña
      const { value: formValues } = await Swal.fire({
        title: 'Nueva Contraseña',
        html: `
          <div class="text-start">
            <div class="mb-3">
              <label for="swal-input1" class="form-label">Nueva Contraseña</label>
              <input type="password" id="swal-input1" class="form-control" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
            </div>
            <div class="mb-3">
              <label for="swal-input2" class="form-label">Confirmar Nueva Contraseña</label>
              <input type="password" id="swal-input2" class="form-control" placeholder="Repite la contraseña" autocomplete="new-password">
            </div>
          </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Siguiente',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
          const newPassword = document.getElementById('swal-input1').value;
          const confirmPassword = document.getElementById('swal-input2').value;

          if (!newPassword || !confirmPassword) {
            Swal.showValidationMessage('Todos los campos son obligatorios');
            return false;
          }

          if (newPassword.length < 6) {
            Swal.showValidationMessage('La contraseña debe tener al menos 6 caracteres');
            return false;
          }

          if (newPassword !== confirmPassword) {
            Swal.showValidationMessage('Las contraseñas no coinciden');
            return false;
          }

          return { newPassword, confirmPassword };
        }
      });

      if (!formValues) return;

      // Paso 3: Confirmación final
      const result = await Swal.fire({
        title: '¿Cambiar contraseña?',
        html: `
          <div class="text-start">
            <p class="mb-3">
              <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
              <strong>¡Atención! Estás a punto de cambiar tu contraseña.</strong>
            </p>
            <div class="alert alert-info mb-0">
              <h6 class="alert-heading mb-2">
                <i class="bi bi-info-circle me-2"></i>
                Consecuencias de este cambio:
              </h6>
              <ul class="mb-0 ps-3">
                <li>Tu contraseña actual dejará de funcionar inmediatamente</li>
                <li>Deberás usar la nueva contraseña para iniciar sesión</li>
                <li>Es recomendable guardar la nueva contraseña en un lugar seguro</li>
                <li>Si olvidas la nueva contraseña, necesitarás restablecerla</li>
              </ul>
            </div>
          </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cambiar contraseña',
        cancelButtonText: 'Cancelar',
        width: '600px',
        customClass: {
          htmlContainer: 'text-start'
        }
      });

      if (result.isConfirmed) {
        // Mostrar loading
        Swal.fire({
          title: 'Cambiando contraseña...',
          html: 'Por favor espera mientras se actualiza tu contraseña.',
          allowOutsideClick: false,
          allowEscapeKey: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        // Crear formulario y enviar
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ admin_url("/profile/update-password") }}';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);

        const currentPassInput = document.createElement('input');
        currentPassInput.type = 'hidden';
        currentPassInput.name = 'current_password';
        currentPassInput.value = currentPassword;
        form.appendChild(currentPassInput);

        const newPassInput = document.createElement('input');
        newPassInput.type = 'hidden';
        newPassInput.name = 'new_password';
        newPassInput.value = formValues.newPassword;
        form.appendChild(newPassInput);

        const confirmPassInput = document.createElement('input');
        confirmPassInput.type = 'hidden';
        confirmPassInput.name = 'confirm_password';
        confirmPassInput.value = formValues.confirmPassword;
        form.appendChild(confirmPassInput);

        document.body.appendChild(form);
        form.submit();
      }
    });
  }
});
</script>
@endpush
