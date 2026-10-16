@extends('layouts::app')

@section('title', $title)

@section('content')
@php include APP_ROOT . '/modules/search-console/views/partials/domain-report.php'; @endphp
@endsection
