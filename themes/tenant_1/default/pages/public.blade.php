@extends('layouts.base')

@section('title', $title)

@section('content')
<div class="container py-5">
  <h1>{{ $page->title }}</h1>
  <hr>
  <div>{!! $page->content !!}</div>
</div>
@endsection
