<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use RuntimeException;

final class Tools
{
    public const MAP = [
        'list_posts'=>Policy::READ,'get_post'=>Policy::READ,'list_categories'=>Policy::READ,'list_tags'=>Policy::READ,
        'create_draft'=>Policy::CREATE,'update_draft'=>Policy::UPDATE,
        'get_post_details'=>Policy::READ,'list_comments'=>Policy::COMMENTS,'moderate_comment'=>Policy::COMMENTS,
        'list_translations'=>Policy::TRANSLATIONS,'save_translation'=>Policy::TRANSLATIONS,
        'list_media'=>Policy::MEDIA,'list_image_options'=>Policy::MEDIA,'list_image_prompts'=>Policy::MEDIA,'upload_image'=>Policy::MEDIA,'generate_image'=>Policy::MEDIA,
        'create_category'=>Policy::TAXONOMY,'update_category'=>Policy::TAXONOMY,'create_tag'=>Policy::TAXONOMY,'update_tag'=>Policy::TAXONOMY,
        'create_post'=>Policy::EDITOR,'update_post'=>Policy::EDITOR,'publish_post'=>Policy::POSTS,'schedule_post'=>Policy::POSTS,
        'get_request_status'=>Policy::READ,'list_requests'=>Policy::APPROVE,'approve_request'=>Policy::APPROVE,'reject_request'=>Policy::APPROVE,
        'list_sites'=>Policy::READ,'copy_draft'=>Policy::COPY,'list_news_items'=>Policy::READ,'import_news_draft'=>Policy::IMPORT,
    ];
    public function __construct(public Store $store, public Content $content) {}
    public function catalog(array $auth): array
    {
        $out=[]; $r=$auth['resource']; $c=$auth['client']; $g=$auth['grant'];
        foreach (self::MAP as $tool=>$scope) {
            if (!$this->permitted($tool,$auth)) continue;
            $properties=[]; $required=[];
            if ($tool!=='get_post' && $tool!=='list_sites') {
                $properties['site_id']=['type'=>'integer','minimum'=>0,'description'=>'ID devuelto por list_sites; el CMS principal usa 0.'];
                if (Policy::site($r['id'])===null) $required[]='site_id';
            }
            if (in_array($tool,['get_post','update_draft','copy_draft'],true)) { $properties['post_id']=['type'=>'integer','minimum'=>1]; $required[]='post_id'; }
            if ($tool==='import_news_draft') { $properties['item_id']=['type'=>'integer','minimum'=>1]; $required[]='item_id'; }
            if (in_array($tool,['list_posts','list_categories','list_tags','list_news_items'],true)) {
                $properties['limit']=['type'=>'integer','minimum'=>1,'maximum'=>50];
                $properties['offset']=['type'=>'integer','minimum'=>0];
                if ($tool==='list_posts') $properties['status']=['type'=>'string','enum'=>['draft','published']];
            }
            if (in_array($tool,['create_draft','update_draft'],true)) {
                foreach (['title','content','excerpt','seo_title','seo_description','base_locale'] as $f) $properties[$f]=['type'=>'string'];
                if ($tool==='create_draft') $required=array_merge($required,['title','content']);
            }
            if (isset(Editor::MAP[$tool])) { $properties=Editor::schema($tool); $required=Editor::required($tool); if (Policy::site($r['id'])===null) $required[]='site_id'; }
            if (in_array($tool,['get_request_status','list_requests','approve_request','reject_request'],true)) { $properties=$tool==='list_requests'?[]:['request_id'=>['type'=>'string','maxLength'=>48,'minLength'=>48]]; $required=$tool==='list_requests'?[]:['request_id']; }
            $write=!self::readOnly($tool) && !in_array($tool,['approve_request','reject_request'],true);
            if ($write) {
                foreach (in_array($tool,['create_draft','update_draft','copy_draft','import_news_draft','create_post','update_post'],true)?['category_ids','tag_ids']:[] as $f) $properties[$f]=['type'=>'array','items'=>['type'=>'integer','minimum'=>1],'maxItems'=>50];
                $properties['apply']=['type'=>'boolean','default'=>false,'description'=>'false: preparar plan; true: solicitar aprobación en el CMS, sin ejecutar.'];
            }
            $out[]=['name'=>$tool,'description'=>$tool==='generate_image'?'Consulta primero list_image_options para el sitio. quality=hd no es universal: MiniMax usa standard. La aprobación crea una imagen en medios; para mostrarla en un artículo, asigna featured_image_id mediante update_post.':($tool==='list_image_prompts'?'Consulta prompts originales, proveedor, parámetros y resolución real de las imágenes del sitio autorizado para mantener un estilo coherente.':(in_array($tool,['approve_request','reject_request'],true)?'Aprueba o rechaza una solicitud propia. Requiere autorización explícita del CMS; aprobar ejecuta cambios.':($write?'Prepara cambios. apply=true crea una solicitud; aprueba después en el CMS o con approve_request si tienes permiso.':'Consulta exclusivamente el alcance y permisos autorizados.'))),
                'inputSchema'=>['type'=>'object','properties'=>(object)$properties,'required'=>$required,'additionalProperties'=>false],
                'annotations'=>['readOnlyHint'=>self::readOnly($tool),'destructiveHint'=>in_array($tool,['approve_request','moderate_comment'],true),'openWorldHint'=>false,'idempotentHint'=>self::readOnly($tool)],
                '_meta'=>['securitySchemes'=>[['type'=>'oauth2','scopes'=>array_values(array_unique([Policy::READ,$scope]))]]]];
        }
        return $out;
    }
    public function permitted(string $tool,array $auth): bool
    {
        $scope=self::MAP[$tool]??null; if (!$scope) return false;
        $r=$auth['resource'];
        if (!self::readOnly($tool) && empty($r['allow_drafts'])) return false;
        if ($scope===Policy::POSTS && empty($r['allow_publishing'])) return false;
        if ($scope===Policy::APPROVE && empty($r['allow_mcp_approval'])) return false;
        if ($tool==='list_sites' && Policy::site($r['id'])!==null) return false;
        if ($tool==='copy_draft' && $r['id']!=='plugin:cross-publisher') return false;
        if (in_array($tool,['list_news_items','import_news_draft'],true) && $r['id']!=='plugin:news-aggregator') return false;
        return Policy::active($r,$auth['client'],$auth['grant']) && Policy::allows($auth['client'],$auth['grant'],$scope)
            && $this->content->actorActive($r['id'],$auth['grant']['actor'],$scope);
    }
    public static function readOnly(string $tool): bool { return str_starts_with($tool,'list_') || in_array($tool,['get_post','get_post_details','get_request_status'],true); }
    public function validate(string $tool,array $auth,array $args,bool $stored=false): void
    {
        $def=null; foreach ($this->catalog($auth) as $t) if ($t['name']===$tool) $def=$t;
        if (!$def) throw new RuntimeException('Herramienta o permiso no autorizado.');
        $props=(array)$def['inputSchema']['properties'];
        if ($stored && isset($args['password_hash']) && in_array($tool,['create_post','update_post'],true)) { if ((password_get_info($args['password_hash'])['algoName']??'unknown')==='unknown') throw new RuntimeException('Contraseña almacenada no válida.'); $props['password_hash']=['type'=>'string','maxLength'=>200]; }
        if (array_diff(array_keys($args),array_keys($props)) || array_diff($def['inputSchema']['required'],array_keys($args))) throw new RuntimeException('Argumentos no válidos.');
        if (strlen(Store::json($args))>($tool==='upload_image'?3000000:250000)) throw new RuntimeException('Petición demasiado grande.');
        foreach ($args as $key=>$v) {
            $p=$props[$key]; $type=$p['type'];
            if (($type==='integer' && !is_int($v)) || ($type==='string' && !is_string($v)) || ($type==='boolean' && !is_bool($v)) || ($type==='array' && (!is_array($v)||!array_is_list($v)))) throw new RuntimeException('Tipo de argumento no válido.');
            if ($type==='string' && (strlen($v)>($p['maxLength']??200000) || strlen($v)<($p['minLength']??0))) throw new RuntimeException('Longitud no válida.');
            if (isset($p['minimum']) && $v<$p['minimum'] || isset($p['maximum']) && $v>$p['maximum'] || isset($p['enum']) && !in_array($v,$p['enum'],true)) throw new RuntimeException('Valor no válido.');
            if ($type==='array') { if (count($v)>50) throw new RuntimeException('Demasiados identificadores.'); foreach ($v as $id) if (!is_int($id)||$id<=0) throw new RuntimeException('Identificador no válido.'); }
        }
    }
    public function call(string $tool,array $auth,array $args): array
    {
        $this->validate($tool,$auth,$args); $r=$auth['resource']; $c=$auth['client']; $g=$auth['grant'];
        if ($tool==='get_request_status') {
            $row=$this->store->one('SELECT * FROM requests WHERE id=? AND resource=? AND client=?',[$args['request_id'],$r['id'],$c['id']]);
            $payload=$this->store->decode($row);
            if (!$row || ($payload['grant']??'')!==$g['id']) throw new RuntimeException('Solicitud ajena o no disponible.');
            $job=$this->store->one('SELECT status,error FROM approval_jobs WHERE request_id=?',[$row['id']]);
            return ['request_id'=>$row['id'],'status'=>$job['status']??$row['status'],'result'=>$payload['result']??null,'error'=>$job['error']??null];
        }
        if (self::MAP[$tool]===Policy::APPROVE) return $this->remoteDecision($tool,$auth,$args);
        if (self::readOnly($tool)) {
            $result=$this->content->read($tool,$r,$c,$args);
            $this->store->event($r['id'],'tool.read',$g['actor'],['client'=>$c['id'],'tool'=>$tool]); return $result;
        }
        if (empty($r['allow_drafts'])) throw new RuntimeException('Solicitudes de borradores desactivadas en este recurso.');
        if (isset($args['password']) && in_array($tool,['create_post','update_post'],true)) { if (strlen($args['password'])<8 || strlen($args['password'])>200) throw new RuntimeException('La contraseña debe tener entre 8 y 200 caracteres.'); $args['password_hash']=password_hash($args['password'],PASSWORD_DEFAULT);unset($args['password']); }
        $plan=$this->content->plan($tool,$r,$c,$args);
        $this->assertPublishing($tool,$plan,$auth,$g['actor']);
        if (empty($args['apply'])) return ['status'=>'plan','plan'=>$plan];
        unset($args['apply']); $id=bin2hex(random_bytes(24));
        $payload=['grant'=>$g['id'],'args'=>$args,'plan'=>$plan,'fingerprint'=>Policy::fingerprint($plan),'created'=>time()];
        $this->store->atomic(function () use ($id,$r,$c,$g,$payload) {
            $count=$this->store->one("SELECT COUNT(*) AS n FROM requests WHERE client=? AND status='pending' AND expires>?",[$c['id'],time()]);
            if ($count['n']>=20) throw new RuntimeException('Máximo 20 solicitudes pendientes por cliente.');
            $this->store->run("INSERT INTO requests(id,resource,client,status,expires,data) VALUES(?,?,?,'pending',?,?)",[$id,$r['id'],$c['id'],time()+600,Store::json($payload)]);
            $this->store->event($r['id'],'request.created',$g['actor'],['client'=>$c['id'],'request'=>$id,'tool'=>$payload['plan']['tool']]);
        });
        return ['status'=>'pending_approval','request_id'=>$id,'expires_in'=>600,'plan'=>$plan,'next'=>'Aprueba o rechaza en (MCP) Conexiones IA del CMS.'];
    }
    private function assertPublishing(string $tool,array $plan,array $auth,int $actor): void
    {
        $live=in_array($tool,['publish_post','schedule_post'],true) || (in_array($tool,['create_post','update_post'],true) && (($plan['fields']['status']??'')==='published' || ($plan['source']['status']??'')==='published')) || ($tool==='save_translation' && ($plan['source']['post']['status']??'')==='published');
        if ($live && (empty($auth['resource']['allow_publishing']) || !Policy::allows($auth['client'],$auth['grant'],Policy::POSTS) || !$this->content->actorActive($auth['resource']['id'],$actor,Policy::POSTS))) throw new RuntimeException('Publicación no autorizada. Habilita el recurso y concede el permiso de publicación al cliente.');
    }
    private function remoteDecision(string $tool,array $auth,array $args): array
    {
        $rows=$this->store->all("SELECT * FROM requests WHERE resource=? AND client=? AND status='pending' AND expires>? ORDER BY expires LIMIT 20",[$auth['resource']['id'],$auth['client']['id'],time()]);
        $own=array_filter($rows,fn($row)=>($this->store->decode($row)['grant']??'')===$auth['grant']['id']);
        if ($tool==='list_requests') return array_values(array_map(fn($row)=>['request_id'=>$row['id'],'expires'=>$row['expires'],'plan'=>$this->store->decode($row)['plan']],$own));
        $found=null; foreach ($own as $row) if ($row['id']===$args['request_id']) $found=$row;
        if (!$found) throw new RuntimeException('Solicitud ajena, caducada o no disponible.');
        return $this->decide($auth['resource']['id'],$found['id'],$auth['grant']['actor'],$tool==='approve_request',$tool==='approve_request' && ($this->store->decode($found)['plan']['tool']??'')==='generate_image');
    }
    public function decide(string $resource,string $id,int $actor,bool $approve,bool $enqueue=false): array
    {
        $row=$this->store->one('SELECT * FROM requests WHERE id=? AND resource=?',[$id,$resource]);
        if (!$row || $row['status']!=='pending' || $row['expires']<=time()) throw new RuntimeException('Solicitud caducada o ya procesada.');
        if (!$this->content->actorActive($resource,$actor,self::MAP[$this->store->decode($row)['plan']['tool']]??Policy::CREATE)) throw new RuntimeException('No tienes permiso para aprobar.');
        $p=$this->store->decode($row);
        if (!$approve) {
            $n=$this->store->run("UPDATE requests SET status='rejected' WHERE id=? AND status='pending'",[$id]);
            if ($n!==1) throw new RuntimeException('Solicitud ya procesada.');
            $this->store->event($resource,'request.rejected',$actor,['request'=>$id]); return ['status'=>'rejected'];
        }
        $r=$this->store->resource($resource); $c=$this->store->client($row['client']); $g=$this->store->grant($p['grant']);
        if (!$r || !$c || !$g || empty($r['allow_drafts'])) throw new RuntimeException('Autorización revocada.');
        $auth=['resource'=>$r,'client'=>$c,'grant'=>$g]; $tool=$p['plan']['tool'];
        $this->validate($tool,$auth,$p['args'],true);
        $plan=$this->content->plan($tool,$r,$c,$p['args']);
        if (!hash_equals($p['fingerprint'],Policy::fingerprint($plan))) throw new RuntimeException('El plan ha cambiado. Solicita uno nuevo.');
        $this->assertPublishing($tool,$plan,$auth,$actor);
        if ($enqueue) {
            $this->store->run("INSERT INTO approval_jobs(request_id,resource,actor,status,created) VALUES(?,?,?,'queued',?) ON CONFLICT(request_id) DO NOTHING",[$id,$resource,$actor,time()]);
            return ['status'=>'queued','request_id'=>$id];
        }
        // Durable consumption before any CMS mutation. No retry after partial failure.
        $n=$this->store->run("UPDATE requests SET status='executing' WHERE id=? AND resource=? AND status='pending' AND expires>?",[$id,$resource,time()]);
        if ($n!==1) throw new RuntimeException('Solicitud caducada o ya procesada.');
        register_shutdown_function(function () use ($id, $resource) {
            $error=error_get_last();
            if (!$error || !in_array($error['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)) return;
            try {
                // Recover from an open transaction before recording the interrupted operation.
                try { $this->store->db->exec('ROLLBACK'); } catch (\Throwable $ignored) {}
                $this->store->run("UPDATE requests SET status='failed' WHERE id=? AND resource=? AND status='executing'",[$id,$resource]);
            } catch (\Throwable $ignored) {}
        });
        $this->store->event($resource,'request.approved',$actor,['request'=>$id]);
        try {
            $result=$this->store->atomic(function () use ($resource,$row,$p,$tool,$plan,$actor) {
                $r=$this->store->resource($resource); $c=$this->store->client($row['client']); $g=$this->store->grant($p['grant']);
                if (!$r || !$c || !$g || empty($r['allow_drafts'])) throw new RuntimeException('Autorización revocada antes de ejecutar.');
                $auth=['resource'=>$r,'client'=>$c,'grant'=>$g]; $this->validate($tool,$auth,$p['args'],true);
                $this->assertPublishing($tool,$plan,$auth,$actor);
                return $this->content->execute($plan,$actor,$auth,$p['args']);
            });
            if ($tool==='schedule_post') (new Scheduler($this->store,$this->content))->register($auth,$result,$actor);
            $p['result']=$result; $p['approved_by']=$actor;
            $this->store->run("UPDATE requests SET status='completed',data=? WHERE id=?",[Store::json($p),$id]);
            $this->store->event($resource,'request.completed',$actor,['request'=>$id,'object_id'=>$result['post_id']??$result['id']??null]); return $result;
        } catch (\Throwable $e) {
            $this->store->run("UPDATE requests SET status='failed' WHERE id=?",[$id]);
            $this->store->event($resource,'request.failed',$actor,['request'=>$id]); throw $e;
        }
    }
}
