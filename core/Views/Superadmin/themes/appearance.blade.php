@extends('layouts.app')

@section('title', $title ?? 'Personalizar Apariencia')

@section('content')
@php $__adminPath = $adminBasePath ?? 'musedock'; @endphp
@include('shared.themes._appearance-form')
@endsection
