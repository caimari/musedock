@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Editando: {{ $filename }}</h1>

    @if (consume_flash('error'))
        <div class="alert alert-danger">{{ consume_flash('error') }}</div>
    @endif

    @if (consume_flash('success'))
        <div class="alert alert-success">{{ consume_flash('success') }}</div>
    @endif

    <form id="editorForm" action="{{ url('/musedock/theme-editor/' . $slug . '/update') }}" method="POST" onsubmit="return syncAceContent();">
        {!! csrf_field() !!}
        <input type="hidden" name="filepath" value="{{ $filepath }}">
        <input type="hidden" name="content" id="content">

        <div id="ace-editor" style="height: 500px; width: 100%; border: 1px solid #ccc; border-radius: 6px;">{{ htmlentities($content) }}</div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary" onclick="return confirm('¿Estás seguro de que deseas guardar los cambios?')">Guardar Cambios</button>
            <a href="{{ url('/musedock/theme-editor/' . $slug . '/customize') }}" class="btn btn-light">← Salir del editor</a>
        </div>
    </form>
</div>

<!-- Ace Editor (sin integrity) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.23.1/ace.js"></script>


<script>
    const editor = ace.edit("ace-editor");
    editor.setTheme("ace/theme/monokai");
    editor.session.setUseWrapMode(true);
    editor.setShowPrintMargin(false);
    editor.setOptions({ fontSize: "14px" });

    // Detectar modo según extensión
    const ext = "{{ pathinfo($filename, PATHINFO_EXTENSION) }}".toLowerCase();
    const modeMap = {
        'php': 'php',
        'blade': 'php',
        'html': 'html',
        'css': 'css',
        'js': 'javascript',
        'json': 'json',
        'xml': 'xml'
    };
    const mode = modeMap[ext] ?? 'text';
    editor.session.setMode("ace/mode/" + mode);

    function syncAceContent() {
        document.getElementById('content').value = editor.getValue();
        return true;
    }
</script>
@endsection
