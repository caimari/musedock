@extends('layouts.app')

@section('title')
{{ ($nl_title ?? 'Newsletter') . ' | ' . ($siteName ?? site_setting('site_name', 'MuseDock')) }}
@endsection

@section('description')
{{ $nl_message ?? '' }}
@endsection

@push('styles')
<style>
  .nl-status-area {
    min-height: 62vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 60px 18px;
    background: #f6f9fe;
  }
  .nl-status-card {
    width: 100%;
    max-width: 520px;
    background: #ffffff;
    border: 1px solid #e6ecf5;
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(24, 39, 75, 0.08);
    padding: 40px 36px;
    text-align: center;
  }
  .nl-status-icon {
    width: 74px;
    height: 74px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    margin-bottom: 20px;
  }
  .nl-status-icon.ok      { background: #e7f7ee; color: #1a9c5b; }
  .nl-status-icon.bye     { background: #eef2f9; color: #52627a; }
  .nl-status-icon.error   { background: #fdecec; color: #d3453f; }
  .nl-status-card h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2a3d;
    margin: 0 0 10px;
  }
  .nl-status-card p {
    font-size: 0.98rem;
    color: #5a6a80;
    line-height: 1.55;
    margin: 0 0 26px;
  }
  .nl-status-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
  }
  .nl-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 22px;
    border-radius: 9px;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .15s ease;
    border: 1px solid transparent;
  }
  .nl-btn-primary { background: #2f6df6; color: #fff; }
  .nl-btn-primary:hover { background: #2159d8; color: #fff; }
  .nl-btn-ghost { background: #fff; color: #2f4a6b; border-color: #d3dded; }
  .nl-btn-ghost:hover { background: #f2f6fc; color: #1f3350; }
</style>
@endpush

@section('content')
@php
  $state = $nl_state ?? 'error';
  $iconClass = $state === 'confirmed' ? 'ok' : ($state === 'unsubscribed' ? 'bye' : 'error');
  $iconFa    = $state === 'confirmed' ? 'fa-check' : ($state === 'unsubscribed' ? 'fa-envelope-o' : 'fa-exclamation');
@endphp
<div class="nl-status-area">
  <div class="nl-status-card">
    <div class="nl-status-icon {{ $iconClass }}"><i class="fa {{ $iconFa }}"></i></div>
    <h1>{{ $nl_title ?? 'Newsletter' }}</h1>
    <p>{{ $nl_message ?? '' }}</p>
    <div class="nl-status-actions">
      <a href="{{ url('/') }}" class="nl-btn nl-btn-primary">Volver al inicio</a>
      @if(($nl_showResubscribe ?? false))
      <a href="{{ url('/') }}#newsletter" class="nl-btn nl-btn-ghost">Volver a suscribirme</a>
      @endif
    </div>
  </div>
</div>
@endsection
