@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-hdd me-2"></i>{{ $title }}</h2>
                <p class="text-muted mb-0">Espacio disponible en tu plan</p>
            </div>
        </div>

        @include('partials.alerts-sweetalert2')

        @php
            $quotaMb     = $storageQuota ?? 0;
            $usedBytes   = $storageUsedBytes ?? 0;
            $usedMb      = $usedBytes / 1048576;
            $freeMb      = max(0, $quotaMb - $usedMb);
            $pct         = $quotaMb > 0 ? min(100, round(($usedMb / $quotaMb) * 100, 1)) : 0;
            $barClass    = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');

            // Formatear bytes legible
            function fmtSize(float $mb): string {
                if ($mb >= 1024) return number_format($mb / 1024, 2) . ' GB';
                return number_format($mb, 0) . ' MB';
            }
        @endphp

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="bi bi-pie-chart me-2 text-primary"></i>Uso del espacio</h5>
                            <span class="badge bg-secondary fs-6">{{ $pct }}% usado</span>
                        </div>

                        <div class="progress mb-3" style="height: 18px; border-radius: 9px;">
                            <div class="progress-bar {{ $barClass }}" role="progressbar"
                                 style="width: {{ $pct }}%; border-radius: 9px;"
                                 aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>

                        <div class="row text-center">
                            <div class="col-4">
                                <div class="p-3 rounded" style="background:#f8f9fa;">
                                    <div class="fw-bold fs-5 text-danger">{{ fmtSize($usedMb) }}</div>
                                    <small class="text-muted">Usado</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 rounded" style="background:#f8f9fa;">
                                    <div class="fw-bold fs-5 text-success">{{ fmtSize($freeMb) }}</div>
                                    <small class="text-muted">Libre</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 rounded" style="background:#f8f9fa;">
                                    <div class="fw-bold fs-5 text-primary">{{ fmtSize($quotaMb) }}</div>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                        </div>

                        @if($pct >= 90)
                        <div class="alert alert-danger mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Espacio casi agotado.</strong> Considera ampliar tu plan o eliminar archivos no utilizados.
                        </div>
                        @elseif($pct >= 70)
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            Has usado más del 70% de tu espacio disponible.
                        </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-4 text-center">
                        <i class="bi bi-arrow-up-circle" style="font-size: 2.5rem; color: #6c757d;"></i>
                        <h5 class="mt-3 mb-2">¿Necesitas más espacio?</h5>
                        <p class="text-muted mb-3">Contacta con nosotros para ampliar la cuota de almacenamiento de tu plan.</p>
                        <a href="mailto:support@musedock.com"
                           class="btn btn-primary">
                            <i class="bi bi-envelope me-2"></i>Solicitar más espacio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
