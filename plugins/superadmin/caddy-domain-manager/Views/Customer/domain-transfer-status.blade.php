@extends('Customer.layout')

@section('styles')
<style>
    .status-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .status-card .card-header {
        background: linear-gradient(135deg, #17a2b8, #138496);
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 25px;
    }
    .domain-display {
        font-size: 1.5rem;
        font-weight: bold;
        margin-top: 8px;
    }
    .status-timeline {
        position: relative;
        padding-left: 40px;
        margin: 30px 0;
    }
    .status-timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 3px;
        background: #e0e0e0;
    }
    .timeline-step {
        position: relative;
        padding: 20px 0;
    }
    .timeline-step::before {
        content: '';
        position: absolute;
        left: -32px;
        top: 25px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #e0e0e0;
        border: 3px solid white;
        box-shadow: 0 0 0 3px #e0e0e0;
    }
    .timeline-step.completed::before {
        background: #28a745;
        box-shadow: 0 0 0 3px #d4edda;
    }
    .timeline-step.active::before {
        background: #17a2b8;
        box-shadow: 0 0 0 3px #d1ecf1;
        animation: pulse 2s infinite;
    }
    .timeline-step.failed::before {
        background: #dc3545;
        box-shadow: 0 0 0 3px #f8d7da;
    }
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.2); }
    }
    .timeline-step h6 {
        font-weight: 600;
        margin-bottom: 5px;
    }
    .timeline-step p {
        color: #6c757d;
        margin: 0;
        font-size: 0.9rem;
    }
    .status-badge {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 25px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .status-badge.pending { background: #fff3cd; color: #856404; }
    .status-badge.in_progress { background: #d1ecf1; color: #0c5460; }
    .status-badge.act { background: #d4edda; color: #155724; }
    .status-badge.fai { background: #f8d7da; color: #721c24; }
    .status-badge.completed { background: #28a745; color: white; }
    .info-box {
        background: #e3f8fc;
        border-left: 4px solid #17a2b8;
        padding: 15px;
        border-radius: 5px;
        margin: 20px 0;
    }
    .warning-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 5px;
        margin: 20px 0;
    }
    .error-box {
        background: #f8d7da;
        border-left: 4px solid #dc3545;
        padding: 15px;
        border-radius: 5px;
        margin: 20px 0;
    }
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-row .label {
        color: #6c757d;
    }
    .detail-row .value {
        font-weight: 500;
    }
    .back-link {
        color: #6c757d;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        margin-bottom: 20px;
    }
    .back-link:hover {
        color: #17a2b8;
    }
    .back-link i {
        margin-right: 5px;
    }
    .refresh-btn {
        background: transparent;
        border: 1px solid #17a2b8;
        color: #17a2b8;
        border-radius: 20px;
        padding: 5px 15px;
        transition: all 0.3s;
    }
    .refresh-btn:hover {
        background: #17a2b8;
        color: white;
    }
    .actions-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
    }
</style>
@endsection

@section('content')
<div class="container py-4">
    <a href="/customer/dashboard" class="back-link">
        <i class="fas fa-arrow-left"></i> Volver al Dashboard
    </a>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card status-card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><i class="fas fa-exchange-alt"></i> Estado de Transferencia</h4>
                            <div class="domain-display">{{ $transfer['domain_name'] }}</div>
                        </div>
                        <button type="button" class="refresh-btn" onclick="refreshStatus()">
                            <i class="fas fa-sync-alt"></i> Actualizar
                        </button>
                    </div>
                </div>
                <div class="card-body p-4">

                    <!-- Estado Actual -->
                    <div class="text-center mb-4">
                        <span class="status-badge {{ strtolower($transfer['status']) }}">
                            @if($transfer['status'] === 'pending')
                                Pendiente
                            @elseif($transfer['status'] === 'in_progress')
                                En Proceso
                            @elseif($transfer['status'] === 'ACT')
                                Completada
                            @elseif($transfer['status'] === 'FAI')
                                Fallida
                            @elseif($transfer['status'] === 'completed')
                                Completada
                            @else
                                {{ $transfer['status'] }}
                            @endif
                        </span>
                    </div>

                    <!-- Timeline -->
                    <div class="status-timeline">
                        @php
                            $status = $transfer['status'];
                            $step1 = in_array($status, ['pending', 'in_progress', 'ACT', 'completed', 'FAI']) ? 'completed' : '';
                            $step2 = in_array($status, ['in_progress', 'ACT', 'completed']) ? 'completed' : ($status === 'pending' ? 'active' : '');
                            $step3 = in_array($status, ['ACT', 'completed']) ? 'completed' : ($status === 'in_progress' ? 'active' : '');
                            $step4 = $status === 'completed' ? 'completed' : ($status === 'ACT' ? 'active' : '');
                            if ($status === 'FAI') {
                                $step2 = 'failed';
                                $step3 = '';
                                $step4 = '';
                            }
                        @endphp

                        <div class="timeline-step {{ $step1 }}">
                            <h6><i class="fas fa-check-circle"></i> Solicitud Enviada</h6>
                            <p>La transferencia ha sido iniciada</p>
                        </div>

                        <div class="timeline-step {{ $step2 }}">
                            <h6><i class="fas fa-clock"></i> Verificacion del Registrador</h6>
                            <p>El registrador actual debe aprobar la transferencia</p>
                        </div>

                        <div class="timeline-step {{ $step3 }}">
                            <h6><i class="fas fa-exchange-alt"></i> Procesando Transferencia</h6>
                            <p>La transferencia esta siendo procesada por los registradores</p>
                        </div>

                        <div class="timeline-step {{ $step4 }}">
                            <h6><i class="fas fa-flag-checkered"></i> Completada</h6>
                            <p>El dominio ha sido transferido exitosamente</p>
                        </div>
                    </div>

                    @if($transfer['status'] === 'FAI')
                    <div class="error-box">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Transferencia Fallida</strong><br>
                        @if(!empty($transfer['error_message']))
                            {{ $transfer['error_message'] }}
                        @else
                            La transferencia no pudo completarse. Posibles causas:
                            <ul class="mb-0 mt-2">
                                <li>Codigo de autorizacion (EPP) incorrecto</li>
                                <li>El dominio esta bloqueado para transferencias</li>
                                <li>El registrador actual rechazo la solicitud</li>
                            </ul>
                        @endif
                    </div>
                    @endif

                    @if($transfer['status'] === 'ACT')
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong>Transferencia Activa</strong><br>
                        El dominio ha sido transferido a OpenProvider. Ahora puedes completar la configuracion
                        para vincularlo a tu cuenta de MuseDock.
                    </div>

                    <div class="actions-card">
                        <h6><i class="fas fa-cogs"></i> Completar Configuracion</h6>
                        <p class="text-muted">Vincula el dominio a tu cuenta y configura el hosting.</p>
                        <button type="button" class="btn btn-success" onclick="completeTransfer()">
                            <i class="fas fa-check"></i> Completar Transferencia
                        </button>
                    </div>
                    @endif

                    @if($transfer['status'] === 'completed')
                    <div class="info-box">
                        <i class="fas fa-check-circle"></i>
                        <strong>Transferencia Completada</strong><br>
                        El dominio ya esta configurado y activo en tu cuenta de MuseDock.
                    </div>
                    @endif

                    <!-- Detalles -->
                    <div class="mt-4">
                        <h6 class="text-muted mb-3"><i class="fas fa-info"></i> Detalles de la Transferencia</h6>

                        <div class="detail-row">
                            <span class="label">ID de Transferencia</span>
                            <span class="value">#{{ $transfer['id'] }}</span>
                        </div>

                        @if(!empty($transfer['openprovider_transfer_id']))
                        <div class="detail-row">
                            <span class="label">ID OpenProvider</span>
                            <span class="value">{{ $transfer['openprovider_transfer_id'] }}</span>
                        </div>
                        @endif

                        <div class="detail-row">
                            <span class="label">Dominio</span>
                            <span class="value">{{ $transfer['domain_name'] }}</span>
                        </div>

                        <div class="detail-row">
                            <span class="label">Fecha de Solicitud</span>
                            <span class="value">{{ date('d/m/Y H:i', strtotime($transfer['created_at'])) }}</span>
                        </div>

                        @if(!empty($transfer['completed_at']))
                        <div class="detail-row">
                            <span class="label">Fecha de Completado</span>
                            <span class="value">{{ date('d/m/Y H:i', strtotime($transfer['completed_at'])) }}</span>
                        </div>
                        @endif

                        @if(!empty($transfer['openprovider_status']))
                        <div class="detail-row">
                            <span class="label">Estado OpenProvider</span>
                            <span class="value">{{ $transfer['openprovider_status'] }}</span>
                        </div>
                        @endif
                    </div>

                    <div class="warning-box mt-4">
                        <i class="fas fa-clock"></i>
                        <strong>Tiempo de Procesamiento</strong><br>
                        Las transferencias de dominio pueden tardar entre 1-7 dias habiles dependiendo del
                        registrador actual y la extension del dominio.
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = '{{ $csrf_token }}';
const transferId = {{ $transfer['id'] }};

function refreshStatus() {
    location.reload();
}

async function completeTransfer() {
    if (!confirm('Completar la transferencia y vincular el dominio a tu cuenta?')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);

        const response = await fetch(`/customer/transfer-domain/${transferId}/complete`, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('Dominio configurado correctamente!');
            location.href = '/customer/dashboard';
        } else {
            alert(data.message || 'Error al completar la transferencia');
        }

    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexion');
    }
}

// Auto-refresh cada 30 segundos si esta en proceso
@if(in_array($transfer['status'], ['pending', 'in_progress']))
setTimeout(() => {
    location.reload();
}, 30000);
@endif
</script>
@endsection
