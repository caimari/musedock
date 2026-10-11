<?php
require __DIR__.'/mcp/bootstrap.php';
require APP_ROOT.'/vendor/eftec/bladeone/lib/BladeOne.php';
function csrf_token(){return 'test';}
function __($key){return $key;}
function csrf_field(){return '<input name="_csrf" value="test">';}
$blade=new Screenart\Musedock\BladeExtended(APP_ROOT.'/core/Views/Superadmin',sys_get_temp_dir().'/tenant-list-blade',eftec\bladeone\BladeOne::MODE_SLOW);
$source=file_get_contents(APP_ROOT.'/core/Views/Superadmin/tenants/index.blade.php');
$source=str_replace(["@extends('layouts.app')", "@section('title', __('tenants.title'))", "@section('content')",'@endsection',"@push('scripts')",'@endpush'],'',$source);
foreach ([0, 250] as $total) {
$html=$blade->runString($source,['ownership'=>'client','color'=>'blue','labelColors'=>[''=>'Sin color','blue'=>'Azul'],'tenants'=>[(object)['id'=>2,'name'=>'Test','domain'=>'test.example','created_at'=>null,'is_client_domain'=>1,'label_color'=>'blue']], 'search'=>'<test>', 'type'=>'subdomain','sort'=>'domain','direction'=>'desc','perPage'=>100,'page'=>1,'pages'=>max(1,(int)ceil($total/100)),'total'=>$total,'mainDomain'=>'musedock.com','listUrl'=>fn($changes=[])=>'/musedock/tenants?'.http_build_query($changes),'dateFormat'=>'d/m/Y','timeFormat'=>'H:i']);
if (!str_contains($html,'&lt;test&gt;') || !str_contains($html,'aria-sort="descending"') || ($total && !str_contains($html,'Siguiente'))) throw new Exception('Render incorrecto');
}
echo "PASS: filtros, ordenación, paginación y escape HTML\n";
