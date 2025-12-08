@extends('layouts.app')

@section('title', __('tenants.edit'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('tenants.edit') }}</h3>
                </div>

                <form method="POST" action="/musedock/tenants/{{ $tenant->id }}/update">
                    {!! csrf_field() !!}

                    <div class="card-body">

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('tenants.name') }}</label>
                            <input type="text" class="form-control" id="name" name="name"
                                value="{{ old('name') ?: $tenant->name }}">
                            {!! form_error('name') !!}
                        </div>

                        <div class="mb-3">
                            <label for="domain" class="form-label">{{ __('tenants.domain') }}</label>
                            <input type="text" class="form-control" id="domain" name="domain"
                                value="{{ old('domain') ?: $tenant->domain }}">
                            {!! form_error('domain') !!}
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">{{ __('tenants.status') }}</label>
                            <select class="form-select" name="status" id="status">
                                <option value="active" {{ (old('status') ?: $tenant->status) === 'active' ? 'selected' : '' }}>
                                    {{ __('tenants.active') }}
                                </option>
                                <option value="inactive" {{ (old('status') ?: $tenant->status) === 'inactive' ? 'selected' : '' }}>
                                    {{ __('tenants.inactive') }}
                                </option>
                            </select>
                        </div>

                    </div>

                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-success">{{ __('common.update') }}</button>
                        <a href="/musedock/tenants" class="btn btn-secondary">{{ __('common.cancel') }}</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection
