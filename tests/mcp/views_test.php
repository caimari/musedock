<?php
require __DIR__.'/bootstrap.php';
$bladeFile=$argv[1]??APP_ROOT.'/vendor/eftec/bladeone/lib/BladeOne.php';
if (!is_file($bladeFile)) throw new RuntimeException('Instala BladeOne o indica su fichero de biblioteca como argumento.');
require $bladeFile;
function csrf_field(){return '<input type="hidden" name="_csrf" value="test-csrf">';}
function admin_path(){return 'admin';}
function route($name){
    if ($name==='settings') return '/musedock/settings';
    if ($name==='tenant-defaults.index') return '/musedock/settings/tenant-defaults';
    if (str_starts_with($name,'settings.')) return '/musedock/settings/'.substr($name,9);
    throw new RuntimeException('Unexpected route '.$name);
}
$directory=sys_get_temp_dir().'/cms-mcp-views-'.bin2hex(random_bytes(8)); mkdir($directory,0700);
try {
    foreach (['Superadmin','Tenant'] as $mode) {
        $blade=new eftec\bladeone\BladeOne(APP_ROOT.'/core/Views/'.$mode,$directory,eftec\bladeone\BladeOne::MODE_SLOW);
        $source=file_get_contents(APP_ROOT.'/core/Views/'.$mode.'/mcp/direct.blade.php');
        $source=str_replace("@extends('layouts.app')",'', $source);
        $source=str_replace(["@section('title', $".'title)',"@section('content')","@endsection","@push('scripts')","@endpush"],'',$source);
        $data=['resourceOptions'=>['main'=>'Dominio principal','plugin:cross-publisher'=>'Cross Publisher'],'schedules'=>[],'title'=>'MCP','resource'=>['id'=>'plugin:cross-publisher','name'=>'Plugin','url'=>'https://cms.example/mcp/cross-publisher','enabled'=>false,'allow_drafts'=>false,'owned_sites'=>[1],'revision'=>1],
            'tenantMode'=>$mode==='Tenant','base'=>'/musedock/settings/mcp','back'=>'/musedock/settings/mcp?resource=plugin%3Across-publisher','deploymentEnabled'=>false,'pluginInstalled'=>true,'error'=>null,
            'newClient'=>['id'=>'test-client','secret'=>'<secret&test>'],'scopes'=>['musedock-cms:read','musedock-cms:drafts:create'],
            'sites'=>[['id'=>1,'name'=>'Site','domain'=>'a.example','group_id'=>10]],'groups'=>[['id'=>10,'name'=>'Grupo']],
            'clients'=>[['id'=>'test-client','name'=>'<script>evil</script>','callback'=>'https://client.example/callback','enabled'=>true,'scopes'=>['musedock-cms:read']]],
            'grants'=>[['id'=>'g','client'=>'test-client','active'=>true,'resource_revision'=>1]],
            'requests'=>[['id'=>'req','expires'=>time()+600,'payload'=>['plan'=>['content'=>'<script>evil</script>']]]],
            'events'=>[['created'=>time(),'action'=>'request.created','detail'=>'{}']]];
        $html=$blade->runString($source,$data);
        if (!str_contains($html,'Opciones de Ajustes')) throw new RuntimeException('Falta navegación de ajustes.');
        if (!str_contains($html,'name="_csrf"') || str_contains($html,'<script>evil</script>') || !str_contains($html,'&lt;script&gt;evil&lt;/script&gt;') || !str_contains($html,'type="password"')) throw new RuntimeException('Fallo de render o escape de la vista '.$mode);
        if (!str_contains($html,'Paso 1') || !str_contains($html,'Paso 2') || !str_contains($html,'data-mcp-site-group="10"')) throw new RuntimeException('Faltan pasos o grupo del sitio.');
        if (substr_count($html,'href="https://a.example" target="_blank" rel="noopener noreferrer"')!==2) throw new RuntimeException('Faltan enlaces seguros al dominio en ambos pasos.');
        $data['resource']['owned_sites']=[];
        $html=$blade->runString($source,$data);
        if (!str_contains($html,'Todavía no hay sitios disponibles') || str_contains($html,'name="sites[]"')) throw new RuntimeException('No se explica la falta de sitios guardados.');
        $data['resource']['owned_sites']=[1]; $data['sites'][0]['group_id']=null;
        $html=$blade->runString($source,$data);
        if (!str_contains($html,'Sin grupo editorial') || str_contains($html,'name="sites[]"')) throw new RuntimeException('Un sitio sin grupo aparece disponible para el asistente.');
        $data['resource']['id']='main'; $data['sites']=[]; $data['groups']=[];
        $html=$blade->runString($source,$data);
        if (str_contains($html,'Paso 1') || str_contains($html,'Acceso de este asistente')) throw new RuntimeException('El dominio principal muestra selección de otros sitios.');
    }
    echo "PASS: 2 Blade views rendered, CSRF fields, HTML escaping and hidden secrets.\n";
} finally {
    foreach (glob($directory.'/*') as $file) if (is_file($file)) unlink($file);
    rmdir($directory);
}
