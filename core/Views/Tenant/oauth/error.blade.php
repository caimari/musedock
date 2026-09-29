@extends('layouts.login-tabler')

@section('title', $title)

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<div class="page py-5">
  <div class="container container-tight py-4">
    <div class="card card-md">
      <div class="card-body text-center">
        <div class="mb-3" style="font-size:2.5rem;color:#d63939;"><i class="bi bi-shield-exclamation"></i></div>
        <h2 class="h2 mb-2">{{ $title }}</h2>
        <p class="text-muted mb-4">{{ $message }}</p>
        @if($siteName)<p class="small text-muted mb-0">{{ $siteName }}</p>@endif
      </div>
    </div>
  </div>
</div>
@endsection
