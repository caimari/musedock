@extends('layouts.app')

@section('title')
{{ ($nl_title ?? 'Newsletter') . ' | ' . ($siteName ?? site_setting('site_name', 'Newsletter')) }}
@endsection

@section('description')
{{ $nl_message ?? '' }}
@endsection

@section('content')
{{-- Página genérica de estado de la newsletter (confirmación / baja / error): respaldo para los temas que no traen la suya --}}
@php $state = $nl_state ?? 'error'; @endphp
<div style="min-height:55vh;display:flex;align-items:center;justify-content:center;padding:48px 16px">
  <div style="width:100%;max-width:520px;border:1px solid rgba(128,128,128,.25);border-radius:14px;padding:36px 30px;text-align:center">
    <h1 style="font-size:1.6rem;margin:0 0 .8rem">{{ $nl_title ?? 'Newsletter' }}</h1>
    <p style="margin:0 0 1.4rem;line-height:1.6">{{ $nl_message ?? '' }}</p>
    @if($state === 'confirm_unsubscribe')
    <form method="POST" action="{{ $nl_action ?? '' }}" style="display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap">
      {!! csrf_field() !!}
      <button type="submit" style="padding:.65rem 1.2rem;border:0;border-radius:8px;background:#c0392b;color:#fff;cursor:pointer;font:inherit">{{ ($nl_lang ?? 'es') === 'en' ? 'Yes, unsubscribe me' : 'Sí, darme de baja' }}</button>
      <a href="{{ url('/') }}" style="padding:.65rem 1.2rem;border:1px solid rgba(128,128,128,.5);border-radius:8px;text-decoration:none;color:inherit">{{ ($nl_lang ?? 'es') === 'en' ? 'No, keep me subscribed' : 'No, seguir suscrito' }}</a>
    </form>
    @else
    <a href="{{ url('/') }}" style="display:inline-block;padding:.65rem 1.2rem;border-radius:8px;background:#222;color:#fff;text-decoration:none">{{ ($nl_lang ?? 'es') === 'en' ? 'Back to home' : 'Volver al inicio' }}</a>
    @endif
  </div>
</div>
@endsection
