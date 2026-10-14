<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use Screenart\Musedock\Database;
use Screenart\Musedock\Env;
use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;

final class Admin
{
    public function handle(): void
    {
        SessionSecurity::startSession();
        $tenant=!empty($GLOBALS['tenant']['id']);
        $id=$tenant?'tenant:'.(int)$GLOBALS['tenant']['id']:(string)($_GET['resource']??$_POST['resource']??'main');
        if (!$tenant && !in_array($id,['main','plugin:cross-publisher','plugin:news-aggregator'],true)) { http_response_code(404); return; }
        if (str_starts_with($id,'plugin:') && !Endpoint::installed($id)) { http_response_code(404); echo 'La conexión de este plugin no está disponible.'; return; }
        $ep=new Endpoint($id); $actor=$ep->actor(); $content=new Content(Database::connect());
        if (!$actor || !$content->actorActive($id,$actor) || ($tenant && !userCan('settings.view'))) { http_response_code(403); echo 'Acceso denegado.'; return; }
        $origin=$tenant?'https://'.$GLOBALS['tenant']['domain']:rtrim((string)Env::get('APP_URL',''),'/');
        $parts=parse_url($origin);
        if (!$parts || ($parts['scheme']??'')!=='https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) { http_response_code(500); echo 'Configura APP_URL con el origen HTTPS canónico del CMS.'; return; }
        $origin='https://'.$parts['host'];
        $url=$origin.'/mcp'.(str_starts_with($id,'plugin:')?'/'.substr($id,7):'');
        $r=$ep->store->resource($id)??['id'=>$id,'name'=>$id==='main'?'MuseDock — dominio principal':($tenant?$GLOBALS['tenant']['name']:substr($id,7)),
            'url'=>$url,'enabled'=>false,'allow_drafts'=>false,'owned_sites'=>[],'revision'=>1];
        $base=$ep->adminBase().($tenant?'/mcp/direct':'/settings/mcp');
        $back=$base.($tenant?'':'?resource='.urlencode($id)); $error=null; $ajax=($_SERVER['HTTP_X_MCP_PROGRESS']??'')==='1';
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            try {
                if ($tenant && !userCan('settings.edit')) throw new \RuntimeException('No tienes permiso para modificar ajustes.');
                if (empty($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'],(string)($_POST['_token']??$_POST['_csrf']??''))) throw new \RuntimeException('CSRF no válido.');
                $action=$_POST['action']??'';
                if ($action==='operation_status') {
                    $row=$ep->store->one('SELECT id,status,data FROM requests WHERE id=? AND resource=?',[(string)($_POST['request']??''),$id]);
                    if (!$row) throw new \RuntimeException('Solicitud no encontrada.');
                    $job=$ep->store->one('SELECT status,error FROM approval_jobs WHERE request_id=? AND resource=?',[$row['id'],$id]);
                    $payload=$ep->store->decode($row);
                    header('Content-Type: application/json'); header('Cache-Control: no-store');
                    echo json_encode(['ok'=>true,'status'=>$job['status']??$row['status'],'error'=>$job['error']??null,'result'=>$payload['result']??null]); return;
                }
                if ($action==='deployment') {
                    if ($tenant || !\Screenart\Musedock\Helpers\PermissionHelper::isSuperAdminRoot($actor)) throw new \RuntimeException('Solo el administrador principal puede activar el servicio global.');
                    Deployment::setEnabled(isset($_POST['deployment_enabled']));
                    $ep->store->event($id,'deployment.configured',$actor,['enabled'=>isset($_POST['deployment_enabled'])]);
                    flash('success','Servicio global MCP actualizado.'); header('Location: '.$back); return;
                }
                if ($action==='configure') {
                    $r['managed']=$r['managed']??false;
                    $r['url']=$url; $r['enabled']=isset($_POST['enabled']); $r['allow_drafts']=isset($_POST['allow_drafts']); $r['allow_publishing']=isset($_POST['allow_publishing']); $r['require_featured_image']=isset($_POST['require_featured_image']); $r['allow_mcp_approval']=isset($_POST['allow_mcp_approval']); $r['revision']++;
                    if (str_starts_with($id,'plugin:')) {
                        $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['owned_sites']??[])),fn($n)=>$n>0)));
                        foreach ($ids as $site) {
                            $s=Database::connect()->prepare("SELECT t.id FROM tenants t INNER JOIN domain_groups g ON g.id=t.group_id WHERE t.id=? AND t.status='active'"); $s->execute([$site]);
                            if (!$s->fetchColumn()) throw new \RuntimeException('El sitio debe estar activo y pertenecer a un grupo editorial del plugin.');
                        }
                        $r['owned_sites']=$ids;
                    }
                    $ep->store->putResource($r);
                    $ep->store->run("UPDATE requests SET status='revoked' WHERE resource=? AND status='pending'",[$id]);
                    $ep->store->event($id,'resource.configured',$actor);
                } elseif ($action==='create_client') {
                    if (!$ep->store->resource($id)) $ep->store->putResource($r);
                    $sites=[]; $groups=[];
                    if (Policy::site($id)===null) {
                        $sites=array_values(array_unique(array_map('intval',(array)($_POST['sites']??[]))));
                        $groups=array_values(array_unique(array_map('intval',(array)($_POST['groups']??[]))));
                        if (!$sites || !$groups || array_diff($sites,$r['owned_sites'])) throw new \RuntimeException('Selecciona grupos y sitios propios autorizados.');
                        foreach ($sites as $site) {
                            $s=Database::connect()->prepare("SELECT g.id FROM tenants t INNER JOIN domain_groups g ON g.id=t.group_id WHERE t.id=? AND t.status='active'"); $s->execute([$site]);
                            $group=$s->fetchColumn();
                            if ($group===false || !in_array((int)$group,$groups,true)) throw new \RuntimeException('El sitio no pertenece a los grupos seleccionados.');
                        }
                    }
                    $callback=Policy::clientCallback((string)($_POST['provider']??'custom'),(string)($_POST['callback']??''));
                    $new=$ep->oauth->createClient($id,(string)($_POST['name']??''),$callback,(array)($_POST['scopes']??[]),$sites,$groups,(string)($_POST['networks']??''));
                    $_SESSION['cms_mcp_new'][$id]=['id'=>$new['client']['id'],'secret'=>$new['secret']];
                    $ep->store->event($id,'client.created',$actor,['client'=>$new['client']['id']]);
                    $saved=$ep->store->client($new['client']['id']);
                    $saved['auto_approve_posts']=isset($_POST['auto_approve_posts']);
                    $saved['auto_approve_images']=isset($_POST['auto_approve_images']);
                    $ep->store->putClient($saved);
                } elseif ($action==='rotate_secret' || $action==='finish_secret_rotation') {
                    $clientId=(string)($_POST['client']??'');
                    if ($action==='rotate_secret') {
                        $secret=$ep->oauth->rotateSecret($id,$clientId);
                        $_SESSION['cms_mcp_new'][$id]=['id'=>$clientId,'secret'=>$secret,'rotated'=>true];
                    } else $ep->oauth->finishSecretRotation($id,$clientId);
                    $ep->store->event($id,$action,$actor,['client'=>$clientId]);
                } elseif ($action==='client_permissions' || $action==='revoke_client') {
                    $client=$ep->store->client((string)($_POST['client']??''));
                    if (!$client || $client['resource']!==$id) throw new \RuntimeException('Cliente no encontrado.');
                    $scopes=$action==='revoke_client'?$client['scopes']:(array)($_POST['scopes']??[]);
                    $ep->oauth->reviseClient($id,$client['id'],$scopes,$action!=='revoke_client');
                    if ($action==='client_permissions') {
                        $saved=$ep->store->client($client['id']);
                        $saved['auto_approve_posts']=isset($_POST['auto_approve_posts']);
                        $saved['auto_approve_images']=isset($_POST['auto_approve_images']);
                        $ep->store->putClient($saved);
                        $ep->store->event($id,'client.auto_approval.updated',$actor,['client'=>$client['id'],'posts'=>$saved['auto_approve_posts'],'images'=>$saved['auto_approve_images']]);
                    }
                    $ep->store->event($id,$action,$actor,['client'=>$client['id']]);
                } elseif ($action==='revoke_grant') {
                    $g=$ep->store->grant((string)($_POST['grant']??''));
                    if (!$g || $g['resource']!==$id) throw new \RuntimeException('Consentimiento no encontrado.');
                    $g['active']=false; $ep->store->putGrant($g); $ep->store->event($id,'grant.revoked',$actor,['client'=>$g['client']]);
                } elseif ($action==='cancel_schedule') {
                    if (!$content->actorActive($id,$actor,Policy::POSTS)) throw new \RuntimeException('No tienes permiso para cancelar publicaciones.');
                    (new Scheduler($ep->store,$content));
                    $ep->store->run("UPDATE schedules SET status='cancelled' WHERE id=? AND resource=? AND status='scheduled'",[(string)($_POST['schedule']??''),$id]);
                    $ep->store->event($id,'publication.cancelled',$actor);
                } elseif ($action==='approve' || $action==='reject') {
                    if (function_exists('set_time_limit')) set_time_limit(240);
                    $result=(new Tools($ep->store,$content))->decide($id,(string)($_POST['request']??''),$actor,$action==='approve',$action==='approve');
                    if ($ajax) { header('Content-Type: application/json'); header('Cache-Control: no-store'); echo json_encode(['ok'=>true,'result'=>$result]); return; }
                } else throw new \RuntimeException('Acción desconocida.');
                flash('success','Configuración MCP actualizada.'); header('Location: '.$back); return;
            } catch (\Throwable $e) { $error=$e instanceof \PDOException?'No se pudo completar la operación.':$e->getMessage();
                if ($ajax) { http_response_code(400); header('Content-Type: application/json'); header('Cache-Control: no-store'); echo json_encode(['ok'=>false,'error'=>$error]); return; } }
        }
        $clients=[];
        foreach ($ep->store->all('SELECT data FROM clients WHERE resource=?',[$id]) as $row) {
            $c=$ep->store->decode($row); unset($c['secret_hash'],$c['previous_secret_hash']); $clients[]=$c;
        }
        $grants=[]; foreach ($ep->store->all('SELECT data FROM grants WHERE resource=?',[$id]) as $row) $grants[]=$ep->store->decode($row);
        $requests=$ep->store->all("SELECT * FROM requests WHERE resource=? AND status='pending' AND expires>? AND NOT EXISTS (SELECT 1 FROM approval_jobs j WHERE j.request_id=requests.id) ORDER BY expires DESC",[$id,time()]);
        foreach ($requests as &$request) $request['payload']=$ep->store->decode($request); unset($request);
        $recentOperations=$ep->store->all("SELECT id,status,data FROM requests WHERE resource=? AND (status IN ('executing','failed','completed') OR EXISTS (SELECT 1 FROM approval_jobs j WHERE j.request_id=requests.id)) ORDER BY rowid DESC LIMIT 15",[$id]);
        foreach ($recentOperations as &$operation) { $payload=$ep->store->decode($operation); $operation['tool']=$payload['plan']['tool']??''; $operation['error']=$payload['failure_reason']??null; $job=$ep->store->one('SELECT status,error FROM approval_jobs WHERE request_id=?',[$operation['id']]); if ($job) { $operation['status']=$job['status']; $operation['error']=$job['error']; } unset($operation['data']); } unset($operation);
        $new=$_SESSION['cms_mcp_new'][$id]??null; unset($_SESSION['cms_mcp_new'][$id]);
        $sites=[]; $groups=[];
        if (Policy::site($id)===null) {
            $sites=Database::connect()->query("SELECT t.id,t.name,t.domain,t.group_id FROM tenants t INNER JOIN domain_groups g ON g.id=t.group_id WHERE t.status='active' ORDER BY g.name,t.name")->fetchAll(\PDO::FETCH_ASSOC);
            $groups=Database::connect()->query('SELECT id,name FROM domain_groups ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC);
        }
        $fixedSite=Policy::site($id);
        $imageSites=$fixedSite!==null?[['id'=>$fixedSite,'name'=>$r['name'],'domain'=>$parts['host']]]:array_values(array_filter($sites,fn($site)=>in_array((int)$site['id'],$r['owned_sites']??[],true)));
        $imageSite=(int)($_GET['image_site']??($imageSites[0]['id']??0));
        $imageOptions=null; $imagePrompts=[]; $imageError=null;
        if ($imageSites && in_array($imageSite,array_map(fn($site)=>(int)$site['id'],$imageSites),true)) {
            try { $media=new Media($content); $imageOptions=$media->options($imageSite); $imagePrompts=$media->listing($imageSite,20,0); }
            catch (\Throwable $e) { $imageError=$e instanceof \PDOException?'No se pudo consultar el historial de imágenes.':$e->getMessage(); }
        } else $imageError='Primero autoriza los sitios disponibles en Servicio y sitios.';
        $imageDiagnostics=[];
        foreach (\Screenart\Musedock\Services\AI\ImageTrace::recent() as $trace) {
            if (empty($trace['request_id'])) continue;
            if (!$ep->store->one('SELECT id FROM requests WHERE id=? AND resource=?',[$trace['request_id'],$id])) continue;
            $imageDiagnostics[]=$trace;
            if (count($imageDiagnostics)>=30) break;
        }
        $resourceOptions=['main'=>'Dominio principal'];
        foreach (['cross-publisher'=>'Cross Publisher','news-aggregator'=>'News Aggregator'] as $slug=>$label) if (Endpoint::installed('plugin:'.$slug)) $resourceOptions['plugin:'.$slug]=$label;
        (new Scheduler($ep->store,$content));
        $data=['imageSites'=>$imageSites,'imageSite'=>$imageSite,'imageOptions'=>$imageOptions,'imagePrompts'=>$imagePrompts,'imageError'=>$imageError,'imageDiagnostics'=>$imageDiagnostics,'recentOperations'=>$recentOperations,'resourceOptions'=>$resourceOptions,'schedules'=>$ep->store->all("SELECT * FROM schedules WHERE resource=? AND status='scheduled' ORDER BY due",[$id]),'title'=>'(MCP) Conexiones IA','resource'=>$r,'clients'=>$clients,'grants'=>$grants,'requests'=>$requests,'newClient'=>$new,'error'=>$error,
            'base'=>$base,'back'=>$back,'tenantMode'=>$tenant,'scopes'=>Policy::scopes($id),'sites'=>$sites,'groups'=>$groups,
            'canManageDeployment'=>!$tenant && \Screenart\Musedock\Helpers\PermissionHelper::isSuperAdminRoot($actor),
            'deploymentEnabled'=>filter_var(Env::get('MCP_CMS_DIRECT_ENABLED',false),FILTER_VALIDATE_BOOLEAN) && filter_var(Env::get('MCP_ENABLED',true),FILTER_VALIDATE_BOOLEAN),'pluginInstalled'=>Endpoint::installed($id),
            'events'=>$ep->store->all('SELECT * FROM events WHERE resource=? ORDER BY id DESC LIMIT 30',[$id])];
        echo $tenant?View::renderTenantAdmin('mcp.direct',$data):View::renderSuperadmin('mcp.direct',$data);
    }
}
