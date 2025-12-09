<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Editor Visual - GrapesJS</title>

    <!-- GrapesJS + Bootstrap 4 (archivos locales) -->
    <link href="{{ asset('assets/vendor/grapesjs/css/grapes.min.css') }}" rel="stylesheet"/>
    <link href="{{ asset('assets/vendor/grapesjs/css/grapesjs-blocks-bootstrap4.min.css') }}" rel="stylesheet"/>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: sans-serif;
        }

        #wrapper {
            display: flex;
            height: 100%;
        }

        #sidebar {
            width: 240px;
            background-color: #2d2f33;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 15px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.2);
            z-index: 10;
        }

        #sidebar h4 {
            margin-bottom: 1rem;
            font-size: 18px;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
        }

        #sidebar .btn {
            margin-top: 10px;
            text-align: left;
            width: 100%;
        }

        #blade-warning {
            background-color: #ffc107;
            color: #000;
            font-size: 13px;
            padding: 6px 10px;
            border-radius: 4px;
            margin-top: 1rem;
        }

        #gjs {
            flex-grow: 1;
            height: 100%;
        }

        .gjs-logo-version {
            display: none !important;
        }
    </style>
</head>
<body>
<div id="wrapper">
    <div id="sidebar">
        <div>
            <h4>Editor Visual</h4>
            <form id="saveForm" method="POST" action="{{ url('/musedock/theme-editor/' . $slug . '/builder-save') }}">
                {!! csrf_field() !!}
                <input type="hidden" name="filepath" value="{{ $filepath }}">
                <input type="hidden" name="html" id="htmlContent">
                <button type="submit" class="btn btn-sm btn-success">💾 Guardar</button>
            </form>

		{{-- Botón de previsualización --}}
        <a href="{{ url('/musedock/theme-editor/' . $slug . '/preview?file=' . urlencode($filepath)) }}" 
           target="_blank" 
           class="btn btn-sm btn-info w-100">
           👁️ Vista previa
        </a>
			
			
            @if (str_contains($html, '@'))
                <div id="blade-warning">
                    ⚠️ Esta página contiene directivas Blade (<code>@</code>). No se mostrarán correctamente en este editor visual.
                </div>
            @endif
        </div>

        <div>
            <a href="{{ url('/musedock/theme-editor/' . $slug . '/customize') }}" class="btn btn-sm btn-danger">← Salir del editor</a>
        </div>
    </div>

    <div id="gjs">{!! $html !!}</div>
</div>

<!-- Scripts (archivos locales) -->
<script src="{{ asset('assets/vendor/grapesjs/js/grapesjs.min.js') }}"></script>
<script src="{{ asset('assets/vendor/grapesjs/js/grapesjs-blocks-bootstrap4.min.js') }}"></script>

<script>
    const editor = grapesjs.init({
        container: '#gjs',
        fromElement: true,
        height: '100%',
        width: '100%',
        storageManager: false,
        // Deshabilitar telemetría para evitar errores de CSP
        telemetry: false,
        plugins: ['grapesjs-blocks-bootstrap4'],
        pluginsOpts: {
            'grapesjs-blocks-bootstrap4': {}
        },
        canvas: {
            styles: [
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'
            ]
        }
    });

    document.getElementById('saveForm').addEventListener('submit', function (e) {
        const html = editor.getHtml() + '<style>' + editor.getCss() + '</style>';
        document.getElementById('htmlContent').value = html;
    });
</script>
</body>
</html>
