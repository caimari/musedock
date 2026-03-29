@extends('layouts.app')

@section('title', $title ?? 'Personalizar Apariencia')

@section('content')
@php $__adminPath = admin_path(); @endphp
@include('shared.themes._appearance-form')
@endsection
