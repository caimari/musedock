<?php
namespace MuseDockForms;
use PDO;
final class Controller
{
    public function show($slug): string {
        $f=Forms::form((string)$slug);if(!$f)return Forms::page('Formulario no disponible','<p>No existe un formulario activo.</p>',404);
        return $this->render($f);
    }
    private function render(array $f,string $error='',array $values=[]): string {
        $english=($f['locale']??'es')==='en';$fields=json_decode($f['fields'],true)?:[];
        $body=($error?'<p class="md-error" role="alert">'.Forms::e($error).'</p>':'').'<p>'.Forms::e($f['description']).'</p><form method="post" action="/md-forms/submit/'.rawurlencode($f['slug']).'">'.csrf_field().Forms::fields($fields,$values).'<div class="md-trap" aria-hidden="true"><label>Website<input name="contact_website" tabindex="-1" autocomplete="off"></label></div><p>'.($english?'See how your application data is processed in our ':'Consulta el tratamiento de tus datos en nuestra ').'<a href="/privacy" target="_top">'.($english?'privacy policy':'política de privacidad').'</a>.</p><label><input type="checkbox" name="privacy_ack" value="1" required> '.($english?'I have read the privacy information.':'He leído la información de privacidad.').'</label><p><button type="submit">'.($english?'Send application':'Enviar solicitud').'</button></p></form>';
        return Forms::page($f['title'],$body.'<script src="/assets/modules/musedock-forms/builder.js" defer></script>');
    }
    public function submit($slug): string {
        Forms::csrf();Forms::throttle('form');$f=Forms::form((string)$slug);if(!$f)return Forms::page('No disponible','',404);
        if(!empty($_POST['contact_website']))return Forms::page('Solicitud recibida','<p>Gracias.</p>');
        [$values,$errors]=Forms::validate(json_decode($f['fields'],true)?:[],$_POST);
        if(($_POST['privacy_ack']??'')!=='1')$errors['privacy_ack']='Debes leer la información de privacidad.';
        if($errors){http_response_code(422);return $this->render($f,implode(' · ',array_map(fn($k,$v)=>$k.': '.$v,array_keys($errors),$errors)),$values);}
        $data=json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        Forms::query('INSERT INTO md_form_submissions (tenant_id,form_id,data,source,source_id,privacy_version,submitted_at) VALUES (?,?,?, ?,NULL,?,CURRENT_TIMESTAMP)',[Forms::tenant(),$f['id'],$data,'native','privacy-2026-10-31']);
        return Forms::page('Solicitud recibida / Application received','<p>Tu solicitud ha quedado registrada para revisión por la organización. / Your application has been saved for review.</p><p><a href="/" target="_top">Volver al inicio / Home</a></p>');
    }
    public function admin(): string {
        Forms::admin();$tid=Forms::tenant();$forms=Forms::query('SELECT f.*, (SELECT COUNT(*) FROM md_form_submissions s WHERE s.form_id=f.id AND s.tenant_id=f.tenant_id) AS total FROM md_forms f WHERE tenant_id=? ORDER BY id',[$tid])->fetchAll(PDO::FETCH_ASSOC);
        $body='<p><a href="/'.Forms::e(admin_path()).'/dashboard">Panel</a> · <a href="/md-members/manage">Socios</a></p>';
        foreach($forms as $f)$body.='<section><h2>'.Forms::e($f['title']).'</h2><p>'.(int)$f['total'].' envíos · <a href="/md-forms/manage/'.$f['id'].'">Ver envíos y editar</a> · <a href="/md-forms/form/'.Forms::e($f['slug']).'">Abrir formulario</a></p><code>[musedock-form slug="'.Forms::e($f['slug']).'"]</code></section>';
        $body.='<h2>Crear formulario</h2>'.$this->editor();return Forms::page('MuseDock Forms',$body.'<script src="/assets/modules/musedock-forms/builder.js" defer></script>');
    }
    private function editor(?array $f=null): string {
        return '<form method="post" action="/md-forms/save">'.csrf_field().'<input type="hidden" name="id" value="'.(int)($f['id']??0).'"><label>Título<input name="title" required value="'.Forms::e($f['title']??'').'"></label><label>Slug<input name="slug" required pattern="[a-z0-9-]+" value="'.Forms::e($f['slug']??'').'"></label><label>Descripción<textarea name="description">'.Forms::e($f['description']??'').'</textarea></label><label>Campos (editor avanzado JSON)<textarea name="fields" rows="16" required>'.Forms::e($f['fields']??'[{"key":"nombre","type":"text","label":"Nombre","required":true},{"key":"email","type":"email","label":"Email","required":true}]').'</textarea></label><p>Tipos: text, email, tel, url, date, number, textarea, select, checkbox, checkboxes. Opciones en options. Los cambios no borran los envíos ni las definiciones originales importadas.</p><button>Guardar formulario</button></form>';
    }
    public function detail($id): string {
        Forms::admin();$f=Forms::query('SELECT * FROM md_forms WHERE id=? AND tenant_id=?',[(int)$id,Forms::tenant()])->fetch(PDO::FETCH_ASSOC);if(!$f)return Forms::page('No encontrado','',404);
        $body='<p><a href="/md-forms/manage">Formularios</a></p>'.$this->editor($f).'<h2>Solicitudes</h2><p><a href="/md-forms/export/'.(int)$f['id'].'">Exportar CSV privado</a></p>';
        $page=max(1,(int)($_GET['page']??1));$offset=($page-1)*30;
        $rows=Forms::query('SELECT * FROM md_form_submissions WHERE form_id=? AND tenant_id=? ORDER BY submitted_at DESC,id DESC LIMIT 30 OFFSET '.$offset,[$f['id'],Forms::tenant()])->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $s)$body.='<details><summary>#'.(int)$s['id'].' · '.Forms::e($s['submitted_at']).' · '.Forms::e($s['source']).'</summary><pre>'.Forms::e(json_encode(json_decode($s['data'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre></details>';
        $body.='<p><a href="?page='.max(1,$page-1).'">Anterior</a> · <a href="?page='.($page+1).'">Siguiente</a></p>';
        return Forms::page($f['title'],$body.'<script src="/assets/modules/musedock-forms/builder.js" defer></script>');
    }
    public function export($id): string {
        Forms::admin();$f=Forms::query('SELECT * FROM md_forms WHERE id=? AND tenant_id=?',[(int)$id,Forms::tenant()])->fetch(PDO::FETCH_ASSOC);if(!$f)return Forms::page('No encontrado','',404);
        $fields=json_decode($f['fields'],true)?:[];$keys=array_column($fields,'key');$labels=array_column($fields,'label');
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="form-'.(int)$f['id'].'-submissions.csv"');
        $stream=fopen('php://output','wb');fwrite($stream,"\xEF\xBB\xBF");$safe=static function($v){$s=is_scalar($v)?(string)$v:json_encode($v,JSON_UNESCAPED_UNICODE);return preg_match('/^[\s]*[=+@-]/u',$s)?"'".$s:$s;};
        fputcsv($stream,array_merge(['ID','Fecha','Origen','ID origen'],$labels),',','"','');
        $q=Forms::query('SELECT * FROM md_form_submissions WHERE tenant_id=? AND form_id=? ORDER BY id',[Forms::tenant(),$f['id']]);while($s=$q->fetch(PDO::FETCH_ASSOC)){$data=json_decode($s['data'],true)?:[];$row=[$s['id'],$s['submitted_at'],$s['source'],$s['source_id']];foreach($keys as $k)$row[]=$data[$k]??'';fputcsv($stream,array_map($safe,$row),',','"','');}fclose($stream);return '';
    }
    public function save(): string {
        Forms::admin();Forms::csrf();$id=(int)($_POST['id']??0);$slug=(string)($_POST['slug']??'');$title=trim((string)($_POST['title']??''));
        if(!preg_match('/^[a-z0-9][a-z0-9-]{0,100}$/',$slug)||$title===''||strlen($title)>255)return Forms::page('Datos inválidos','',422);
        try{$fields=Forms::schema(json_decode((string)($_POST['fields']??''),true,64,JSON_THROW_ON_ERROR));}catch(\Throwable $e){return Forms::page('Campos inválidos','<p>'.Forms::e($e->getMessage()).'</p>',422);}
        if($id)Forms::query('UPDATE md_forms SET slug=?,title=?,description=?,fields=? WHERE id=? AND tenant_id=?',[$slug,$title,substr((string)($_POST['description']??''),0,5000),json_encode($fields),$id,Forms::tenant()]);
        else Forms::query('INSERT INTO md_forms (tenant_id,slug,title,description,fields,active) VALUES (?,?,?,?,?,1)',[Forms::tenant(),$slug,$title,substr((string)($_POST['description']??''),0,5000),json_encode($fields)]);
        header('Location: /md-forms/manage',true,303);return '';
    }
}
