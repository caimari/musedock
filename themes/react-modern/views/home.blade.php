@extends('layouts.app')

@section('title', setting('site_name', 'MuseDock') . ' - Inicio')
@section('description', setting('site_description', 'Bienvenido a nuestro sitio web'))

@section('content')
<!-- Hero Section -->
<section class="relative bg-gradient-to-br from-primary-600 via-secondary-600 to-accent-600 text-white py-20 md:py-32 overflow-hidden">
    <!-- Formas decorativas de fondo -->
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-64 h-64 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full translate-x-1/2 translate-y-1/2"></div>
    </div>

    <div class="container-custom relative z-10">
        <div class="max-w-3xl mx-auto text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6 text-shadow">
                Bienvenido a {{ setting('site_name', 'MuseDock') }}
            </h1>

            <p class="text-xl md:text-2xl mb-8 text-white/90">
                {{ setting('site_description', 'Tu sitio web moderno construido con React, TypeScript y Tailwind CSS') }}
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="/about" class="btn-primary">
                    Conocer más
                    <i class="fas fa-arrow-right ml-2"></i>
                </a>

                <a href="/contact" class="btn-secondary bg-white/20 border-white text-white hover:bg-white/30">
                    Contactar
                    <i class="fas fa-envelope ml-2"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Scroll indicator -->
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
        <i class="fas fa-chevron-down text-white/50 text-2xl"></i>
    </div>
</section>

<!-- Features Section -->
<section class="py-16 md:py-24 bg-gray-50">
    <div class="container-custom">
        <div class="text-center mb-16">
            <h2 class="text-3xl md:text-5xl font-bold mb-4 text-gradient">
                Características Principales
            </h2>

            <div class="divider-gradient mx-auto mb-6"></div>

            <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                Todo lo que necesitas para crear un sitio web moderno y profesional
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="card p-8 text-center">
                <div class="w-16 h-16 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-bolt text-white text-2xl"></i>
                </div>

                <h3 class="text-xl font-bold mb-3">Ultra Rápido</h3>

                <p class="text-gray-600">
                    Construido con React y Vite para un rendimiento excepcional
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="card p-8 text-center">
                <div class="w-16 h-16 bg-gradient-to-br from-accent-500 to-primary-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-mobile-alt text-white text-2xl"></i>
                </div>

                <h3 class="text-xl font-bold mb-3">Responsive</h3>

                <p class="text-gray-600">
                    Diseño adaptable a todos los dispositivos y tamaños de pantalla
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="card p-8 text-center">
                <div class="w-16 h-16 bg-gradient-to-br from-secondary-500 to-accent-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-paint-brush text-white text-2xl"></i>
                </div>

                <h3 class="text-xl font-bold mb-3">Personalizable</h3>

                <p class="text-gray-600">
                    Fácil de personalizar con Tailwind CSS y opciones de tema
                </p>
            </div>

            <!-- Feature 4 -->
            <div class="card p-8 text-center">
                <div class="w-16 h-16 bg-gradient-to-br from-primary-500 to-accent-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-shield-alt text-white text-2xl"></i>
                </div>

                <h3 class="text-xl font-bold mb-3">Seguro</h3>

                <p class="text-gray-600">
                    Protección CSRF, validación de datos y mejores prácticas de seguridad
                </p>
            </div>

            <!-- Feature 5 -->
            <div class="card p-8 text-center">
                <div class="w-16 h-16 bg-gradient-to-br from-accent-500 to-secondary-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-code text-white text-2xl"></i>
                </div>

                <h3 class="text-xl font-bold mb-3">TypeScript</h3>

                <p class="text-gray-600">
                    Código tipado y auto-completado para desarrollo más rápido
                </p>
            </div>

            <!-- Feature 6 -->
            <div class="card p-8 text-center">
                <div class="w-16 h-16 bg-gradient-to-br from-secondary-500 to-primary-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-database text-white text-2xl"></i>
                </div>

                <h3 class="text-xl font-bold mb-3">Dinámico</h3>

                <p class="text-gray-600">
                    Menús, widgets y configuraciones desde la base de datos
                </p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-16 md:py-24 bg-gradient-to-r from-primary-600 to-secondary-600 text-white">
    <div class="container-custom text-center">
        <h2 class="text-3xl md:text-5xl font-bold mb-6">
            ¿Listo para comenzar?
        </h2>

        <p class="text-xl mb-8 max-w-2xl mx-auto text-white/90">
            Únete a miles de usuarios que ya están usando MuseDock para sus proyectos
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="/contact" class="btn-secondary bg-white text-primary-600 hover:bg-gray-100">
                Contáctanos ahora
                <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
</section>
@endsection
