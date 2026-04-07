@extends('layouts.app')

@section('title', ($title ?? 'Planes') . ' | ' . site_setting('site_name', ''))

@section('description', 'Elige el plan de hosting que mejor se adapte a tu proyecto. Desde gratis hasta planes profesionales con dominio propio y más.')

@section('content')
<main>
  <section style="padding: 50px 0 60px; background: linear-gradient(135deg, #f0f4ff 0%, #e8edf8 100%);">
    <div class="container">
      {{-- Header compacto --}}
      <div class="text-center" style="margin-bottom:40px;">
        <h1 style="color:#243141; font-size:2rem; font-weight:700; margin-bottom:8px;">{{ $title ?? 'Planes de hosting' }}</h1>
        <p style="color:#6c757d; font-size:1.05rem; margin:0;">Empieza gratis. Escala cuando lo necesites.</p>
      </div>

      {{-- Plans grid --}}
      @php
        $planCount = count($plans);
        // Determinar ancho de columnas según número de planes
        $colClass = match(true) {
            $planCount === 1 => 'col-md-6 col-lg-5',
            $planCount === 2 => 'col-md-6 col-lg-5',
            $planCount === 3 => 'col-md-6 col-lg-4',
            default => 'col-md-6 col-lg-3',
        };
      @endphp

      <div class="row g-4 justify-content-center">
        @foreach($plans as $i => $plan)
        @php
          $isPopular = !$plan->isFree() && ($planCount > 1 ? $i === 1 : false);
          $product = $plan->shop_product_id ? \Shop\Models\Product::find($plan->shop_product_id) : null;
          $features = $plan->getFeatures();
        @endphp

        <div class="{{ $colClass }}">
          <div style="
            background: #fff;
            border-radius: 16px;
            {{ $isPopular ? 'border: 2px solid #4e73df; box-shadow: 0 8px 30px rgba(78,115,223,0.15);' : 'border: 1px solid #e3e6f0; box-shadow: 0 2px 12px rgba(0,0,0,0.06);' }}
            padding: 32px 28px;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
          " onmouseenter="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 35px rgba(0,0,0,0.12)';" onmouseleave="this.style.transform='';this.style.boxShadow='{{ $isPopular ? '0 8px 30px rgba(78,115,223,0.15)' : '0 2px 12px rgba(0,0,0,0.06)' }}';">

            @if($isPopular)
              <div style="position:absolute; top:-12px; left:50%; transform:translateX(-50%);">
                <span style="background:#4e73df; color:#fff; padding:4px 16px; border-radius:20px; font-size:0.75rem; font-weight:600; letter-spacing:0.5px;">RECOMENDADO</span>
              </div>
            @endif

            {{-- Plan name --}}
            <div style="text-align:center; margin-bottom:16px;">
              <h3 style="color:#243141; font-size:1.3rem; font-weight:700; margin:0;">{{ e($plan->name) }}</h3>
              @if($plan->description)
                <p style="color:#8a94a6; font-size:0.85rem; margin:6px 0 0;">{{ e($plan->description) }}</p>
              @endif
            </div>

            {{-- Price --}}
            <div style="text-align:center; margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid #f0f0f0;">
              @if($plan->isFree())
                <span style="font-size:2.4rem; font-weight:800; color:#28a745; line-height:1;">Gratis</span>
                <div style="color:#8a94a6; font-size:0.8rem; margin-top:4px;">Para siempre</div>
              @else
                <span style="font-size:2.4rem; font-weight:800; color:#4e73df; line-height:1;">{{ $product ? $product->getFormattedPrice() : '—' }}</span>
                <span style="color:#8a94a6; font-size:0.9rem;">/mes</span>
              @endif
            </div>

            {{-- Features --}}
            <ul style="list-style:none; padding:0; margin:0 0 24px; flex-grow:1;">
              <li style="display:flex; align-items:center; padding:6px 0; font-size:0.9rem; color:#4a5568;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="#28a745" style="flex-shrink:0; margin-right:10px;"><path d="M8 0a8 8 0 110 16A8 8 0 018 0zm3.78 5.22a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0L4.22 8.28a.75.75 0 011.06-1.06L7 8.94l3.72-3.72a.75.75 0 011.06 0z"/></svg>
                <strong style="margin-right:4px;">{{ $plan->disk_mb >= 1024 ? round($plan->disk_mb / 1024, 1) . ' GB' : $plan->disk_mb . ' MB' }}</strong> almacenamiento
              </li>
              <li style="display:flex; align-items:center; padding:6px 0; font-size:0.9rem; color:#4a5568;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="#28a745" style="flex-shrink:0; margin-right:10px;"><path d="M8 0a8 8 0 110 16A8 8 0 018 0zm3.78 5.22a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0L4.22 8.28a.75.75 0 011.06-1.06L7 8.94l3.72-3.72a.75.75 0 011.06 0z"/></svg>
                <strong style="margin-right:4px;">{{ $plan->max_pages }}</strong> páginas
              </li>
              <li style="display:flex; align-items:center; padding:6px 0; font-size:0.9rem; color:#4a5568;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="#28a745" style="flex-shrink:0; margin-right:10px;"><path d="M8 0a8 8 0 110 16A8 8 0 018 0zm3.78 5.22a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0L4.22 8.28a.75.75 0 011.06-1.06L7 8.94l3.72-3.72a.75.75 0 011.06 0z"/></svg>
                <strong style="margin-right:4px;">{{ $plan->max_posts }}</strong> artículos de blog
              </li>

              @if($plan->ssl_included)
              <li style="display:flex; align-items:center; padding:6px 0; font-size:0.9rem; color:#4a5568;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="#28a745" style="flex-shrink:0; margin-right:10px;"><path d="M8 0a8 8 0 110 16A8 8 0 018 0zm3.78 5.22a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0L4.22 8.28a.75.75 0 011.06-1.06L7 8.94l3.72-3.72a.75.75 0 011.06 0z"/></svg>
                Certificado SSL gratuito
              </li>
              @endif

              @if($plan->custom_domain)
              <li style="display:flex; align-items:center; padding:6px 0; font-size:0.9rem; color:#4a5568;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="#28a745" style="flex-shrink:0; margin-right:10px;"><path d="M8 0a8 8 0 110 16A8 8 0 018 0zm3.78 5.22a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0L4.22 8.28a.75.75 0 011.06-1.06L7 8.94l3.72-3.72a.75.75 0 011.06 0z"/></svg>
                Dominio propio incluido
              </li>
              @else
              <li style="display:flex; align-items:center; padding:6px 0; font-size:0.9rem; color:#4a5568;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="#28a745" style="flex-shrink:0; margin-right:10px;"><path d="M8 0a8 8 0 110 16A8 8 0 018 0zm3.78 5.22a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0L4.22 8.28a.75.75 0 011.06-1.06L7 8.94l3.72-3.72a.75.75 0 011.06 0z"/></svg>
                Subdominio .musedock.com
              </li>
              @endif

              @if($plan->email_accounts > 0)
              <li style="display:flex; align-items:center; padding:6px 0; font-size:0.9rem; color:#4a5568;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="#28a745" style="flex-shrink:0; margin-right:10px;"><path d="M8 0a8 8 0 110 16A8 8 0 018 0zm3.78 5.22a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0L4.22 8.28a.75.75 0 011.06-1.06L7 8.94l3.72-3.72a.75.75 0 011.06 0z"/></svg>
                {{ $plan->email_accounts }} cuenta(s) de email
              </li>
              @endif

              @foreach($features as $feature)
              <li style="display:flex; align-items:center; padding:6px 0; font-size:0.9rem; color:#4a5568;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="#28a745" style="flex-shrink:0; margin-right:10px;"><path d="M8 0a8 8 0 110 16A8 8 0 018 0zm3.78 5.22a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06 0L4.22 8.28a.75.75 0 011.06-1.06L7 8.94l3.72-3.72a.75.75 0 011.06 0z"/></svg>
                {{ e($feature) }}
              </li>
              @endforeach
            </ul>

            {{-- CTA --}}
            <a href="/register?plan={{ e($plan->slug) }}" style="
              display:block;
              text-align:center;
              padding:12px 24px;
              border-radius:8px;
              font-weight:600;
              font-size:0.95rem;
              text-decoration:none;
              transition:all 0.2s;
              {{ $isPopular || $plan->isFree()
                  ? 'background:#4e73df; color:#fff; border:2px solid #4e73df;'
                  : 'background:#fff; color:#4e73df; border:2px solid #4e73df;'
              }}
            " onmouseenter="this.style.opacity='0.85'" onmouseleave="this.style.opacity='1'">
              {{ $plan->isFree() ? 'Empezar gratis' : 'Elegir plan' }}
            </a>
          </div>
        </div>
        @endforeach
      </div>

      {{-- Trust badges --}}
      <div style="text-align:center; margin-top:40px; display:flex; justify-content:center; gap:32px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:6px; color:#8a94a6; font-size:0.85rem;">
          <svg width="18" height="18" viewBox="0 0 16 16" fill="#8a94a6"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
          Pago seguro con Stripe
        </div>
        <div style="display:flex; align-items:center; gap:6px; color:#8a94a6; font-size:0.85rem;">
          <svg width="18" height="18" viewBox="0 0 16 16" fill="#8a94a6"><path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/><path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/></svg>
          Cancela cuando quieras
        </div>
        <div style="display:flex; align-items:center; gap:6px; color:#8a94a6; font-size:0.85rem;">
          <svg width="18" height="18" viewBox="0 0 16 16" fill="#8a94a6"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94z"/></svg>
          Soporte incluido
        </div>
      </div>
    </div>
  </section>
</main>
@endsection
