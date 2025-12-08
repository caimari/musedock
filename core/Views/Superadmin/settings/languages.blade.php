@extends('layouts.app')

@section('title', 'Idiomas disponibles')

@section('content')
<form method="POST" action="{{ route('settings.languages.update') }}">
    {!! csrf_field() !!}

    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Idiomas disponibles</h5>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($languages as $lang)
                    @php $active = (int) $lang->active === 1; @endphp
                    <button
                        type="button"
                        class="btn language-toggle {{ $active ? 'btn-primary text-white' : 'btn-outline-primary' }}"
                        data-lang="{{ $lang->code }}">
                        {{ $lang->name }} <small>({{ $lang->code }})</small>
                    </button>
                @endforeach
            </div>
        </div>
        <div class="card-footer text-end">
            <input type="hidden" name="languages" id="selected-languages" value="">
            <button type="submit" class="btn btn-success">Guardar</button>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('.language-toggle');
    const selectedInput = document.getElementById('selected-languages');
    let selectedLanguages = [];

    // Cargar los idiomas ya activos
    buttons.forEach(button => {
        if (button.classList.contains('btn-primary')) {
            selectedLanguages.push(button.dataset.lang);
        }
    });

    updateHiddenField();

    buttons.forEach(button => {
        button.addEventListener('click', function () {
            const lang = this.dataset.lang;
            const isActive = this.classList.contains('btn-primary');

            if (isActive) {
                this.classList.remove('btn-primary');
                this.classList.remove('text-white');
                this.classList.add('btn-outline-primary');
                selectedLanguages = selectedLanguages.filter(code => code !== lang);
            } else {
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-primary');
                this.classList.add('text-white');
                selectedLanguages.push(lang);
            }

            updateHiddenField();
        });
    });

    function updateHiddenField() {
        selectedInput.value = JSON.stringify(selectedLanguages);
        console.log('Idiomas seleccionados:', selectedLanguages); // para debug
    }
});
</script>
@endsection
