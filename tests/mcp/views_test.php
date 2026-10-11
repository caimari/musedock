<?php
require __DIR__.'/bootstrap.php';
$bladeFile=$argv[1]??APP_ROOT.'/vendor/eftec/bladeone/lib/BladeOne.php';
if (!is_file($bladeFile)) throw new RuntimeException('Instala BladeOne o indica su fichero de biblioteca como argumento.');
require $bladeFile;
function csrf_field(){return '<input type="hidden" name="_csrf" value="test-csrf">';}
function admin_path(){return 'admin';}
$directory=sys_get_temp_dir().'/cms-mcp-views-'.bin2hex(random_bytes(8)); mkdir($directory,0700);
try {
    $blade=new eftec\bladeone\BladeOne($directory,$directory,eftec\bladeone\BladeOne::MODE_SLOW);
    foreach (['Superadmin','Tenant'] as $mode) {
        $source=file_get_contents(APP_ROOT.'/core/Views/'.$mode.'/mcp/direct.blade.php');
        $source=str_replace("@extends('layouts.app')",'', $source);
        $source=str_replace(["@section('title', $".'title)',"@section('content')","@endsection","@push('scripts')","@endpush"],'',$source);
        $data=['title'=>'MCP','resource'=>['id'=>'plugin:cross-publisher','name'=>'Plugin','url'=>'https://cms.example/mcp/cross-publisher','enabled'=>false,'allow_drafts'=>false,'owned_sites'=>[1],'revision'=>1],
            'tenantMode'=>$mode==='Tenant','base'=>'/musedock/settings/mcp','back'=>'/musedock/settings/mcp?resource=plugin%3Across-publisher','deploymentEnabled'=>false,'pluginInstalled'=>true,'error'=>null,
            'newClient'=>['id'=>'test-client','secret'=>'<secret&test>'],'scopes'=>['musedock-cms:read','musedock-cms:drafts:create'],
            'sites'=>[['id'=>1,'name'=>'Site','domain'=>'a.example','group_id'=>10]],'groups'=>[['id'=>10,'name'=>'Grupo']],
            'clients'=>[['id'=>'test-client','name'=>'<script>evil</script>','callback'=>'https://client.example/callback','enabled'=>true,'scopes'=>['musedock-cms:read']]],
            'grants'=>[['id'=>'g','client'=>'test-client','active'=>true,'resource_revision'=>1]],
            'requests'=>[['id'=>'req','expires'=>time()+600,'payload'=>['plan'=>['content'=>'<script>evil</script>']]]],
            'events'=>[['created'=>time(),'action'=>'request.created','detail'=>'{}']]];
        $html=$blade->runString($source,$data);
        if (!str_contains($html,'name="_csrf"') || str_contains($html,'<script>evil</script>') || !str_contains($html,'&lt;script&gt;evil&lt;/script&gt;') || !str_contains($html,'type="password"')) throw new RuntimeException('Fallo de render o escape de la vista '.$mode);
    }
    echo "PASS: 2 Blade views rendered, CSRF fields, HTML escaping and hidden secrets.\n";
} finally {
    foreach (glob($directory.'/*') as $file) if (is_file($file)) unlink($file);
    rmdir($directory);
}
