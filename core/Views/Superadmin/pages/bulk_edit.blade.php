@extends('layouts.app')
@section('title', __('pages.bulk_edit_title'))
@section('content')
<div class="app-content">
  <div class="container">
    <h2 class="mb-4">{{ __('pages.bulk_edit_title') }}</h2>
    
    <form method="POST" action="{{ route('pages.bulk.update') }}">
      {!! csrf_field() !!}
      
      @foreach ($selectedIds as $id)
        <input type="hidden" name="selected[]" value="{{ $id }}">
      @endforeach
      
      <div class="card mb-4">
        <div class="card-header">{{ __('pages.bulk_common_options') }}</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">{{ __('pages.status') }}</label>
            <select name="status" class="form-select">
              <option value="">{{ __('pages.bulk_no_change') }}</option>
              <option value="published">{{ __('pages.published') }}</option>
              <option value="draft">{{ __('pages.draft') }}</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label">{{ __('pages.visibility') }}</label>
            <select name="visibility" class="form-select">
              <option value="">{{ __('pages.bulk_no_change') }}</option>
              <option value="public">{{ __('pages.visibility_public') }}</option>
              <option value="private">{{ __('pages.visibility_private') }}</option>
              <option value="members">{{ __('pages.visibility_members') }}</option>
            </select>
            <small class="form-text text-muted">{{ __('pages.visibility_help') }}</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label">{{ __('pages.publish_date') }}</label>
            <input type="datetime-local" name="published_at" class="form-control">
            <small class="form-text text-muted">{{ __('pages.bulk_leave_empty_no_change') }}</small>
          </div>
        </div>
      </div>
      
      <div class="card mb-4">
        <div class="card-header">{{ __('pages.bulk_selected_pages', ['count' => count($selectedPages)]) }}</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>{{ __('pages.title_field') }}</th>
                  <th>{{ __('pages.bulk_current_status') }}</th>
                  <th>{{ __('pages.visibility') }}</th>
                  <th>{{ __('pages.publish_date') }}</th>
                  <th>{{ __('pages.updated_at') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($selectedPages as $page)
                <tr>
                  <td>{{ $page->id }}</td>
                  <td>
                    <a href="{{ route('pages.edit', ['id' => $page->id]) }}" target="_blank">
                      {{ $page->title }}
                    </a>
                  </td>
                  <td>
                    @if($page->status === 'published')
                      <span class="badge bg-success">{{ __('pages.published') }}</span>
                    @else
                      <span class="badge bg-secondary">{{ __('pages.draft') }}</span>
                    @endif
                  </td>
                  <td>
                    @if($page->visibility === 'private')
                      <span class="badge bg-danger">{{ __('pages.private') }}</span>
                    @elseif($page->visibility === 'members')
                      <span class="badge bg-info">{{ __('pages.members') }}</span>
                    @else
                      <span class="badge bg-light text-dark">{{ __('pages.public') }}</span>
                    @endif
                  </td>
                  <td>
                    @if($page->published_at)
                      @if(is_string($page->published_at))
                        {{ date('d/m/Y H:i', strtotime($page->published_at)) }}
                      @else
                        {{ $page->published_at->format('d/m/Y H:i') }}
                      @endif
                    @else
                      <span class="text-muted">{{ __('pages.bulk_not_defined') }}</span>
                    @endif
                  </td>
                  <td>
                    @if($page->updated_at)
                      @if(is_string($page->updated_at))
                        {{ date('d/m/Y H:i', strtotime($page->updated_at)) }}
                      @else
                        {{ $page->updated_at->format('d/m/Y H:i') }}
                      @endif
                    @endif
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
      
      <div class="d-flex justify-content-between">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-1"></i> {{ __('pages.bulk_apply_all_changes') }}
        </button>
        <a href="{{ route('pages.index') }}" class="btn btn-secondary">
          <i class="fas fa-times me-1"></i> {{ __('common.cancel') }}
        </a>
      </div>
    </form>
  </div>
</div>
@endsection
