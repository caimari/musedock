{{-- themes/default/views/403.blade.php --}}
@extends('layouts.app')

@section('title')
    403 - Acceso Denegado | {{ site_setting('site_name', '') }}
@endsection

@section('description')
    No tienes permisos para acceder a este contenido.
@endsection

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="error-box text-center" style="background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%); border-radius: 20px; padding: 3rem 2rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);">

                {{-- Error Icon --}}
                <div class="error-icon mb-4" style="font-size: 6rem; animation: shake 2s ease-in-out infinite;">
                    🔒
                </div>

                {{-- Error Code --}}
                <div class="error-code mb-4" style="font-size: 6rem; font-weight: 900; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1;">
                    403
                </div>

                {{-- Error Title --}}
                <h1 class="error-title mb-3" style="font-size: 2rem; font-weight: 700; color: #2d3748;">
                    Acceso Denegado
                </h1>

                {{-- Error Message --}}
                <p class="error-message mb-4" style="font-size: 1.1rem; color: #718096; line-height: 1.6;">
                    No tienes permisos para acceder a este contenido.
                    <br>
                    Esta página es privada y solo puede ser vista por administradores autorizados.
                </p>

                {{-- Actions --}}
                <div class="btn-container mt-4">
                    <a href="/" class="btn btn-primary" style="padding: 1rem 2.5rem; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none; border-radius: 50px; font-weight: 600; box-shadow: 0 10px 30px rgba(245, 87, 108, 0.4); transition: all 0.3s ease;">
                        🏠 Volver al Inicio
                    </a>
                    <a href="javascript:history.back()" class="btn btn-secondary ms-2" style="padding: 1rem 2.5rem; background: #edf2f7; color: #2d3748; border: none; border-radius: 50px; font-weight: 600; transition: all 0.3s ease;">
                        ← Volver Atrás
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes shake {
    0%, 100% { transform: rotate(0deg); }
    10%, 30%, 50%, 70%, 90% { transform: rotate(-10deg); }
    20%, 40%, 60%, 80% { transform: rotate(10deg); }
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(245, 87, 108, 0.6) !important;
}

.btn-secondary:hover {
    background: #e2e8f0 !important;
    transform: translateY(-2px);
}
</style>
@endsection
