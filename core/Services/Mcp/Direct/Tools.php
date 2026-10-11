<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use RuntimeException;

final class Tools
{
    public const MAP = [
        'list_posts'=>Policy::READ,'get_post'=>Policy::READ,'list_categories'=>Policy::READ,'list_tags'=>Policy::READ,
        'create_draft'=>Policy::CREATE,'update_draft'=>Policy::UPDATE,
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
            $write=$scope!==Policy::READ;
            if ($write) {
                foreach (['category_ids','tag_ids'] as $f) $properties[$f]=['type'=>'array','items'=>['type'=>'integer','minimum'=>1],'maxItems'=>50];
                $properties['apply']=['type'=>'boolean','default'=>false,'description'=>'false: preparar plan; true: solicitar aprobación en el CMS, sin ejecutar.'];
            }
            $out[]=['name'=>$tool,'description'=>$write?'Prepara un borrador. apply=true solicita aprobación privada; no publica ni ejecuta desde el chat.':'Consulta contenido exclusivamente dentro del alcance autorizado.',
                'inputSchema'=>['type'=>'object','properties'=>(object)$properties,'required'=>$required,'additionalProperties'=>false],
                'annotations'=>['readOnlyHint'=>!$write,'destructiveHint'=>false,'openWorldHint'=>false,'idempotentHint'=>!$write],
                '_meta'=>['securitySchemes'=>[['type'=>'oauth2','scopes'=>array_values(array_unique([Policy::READ,$scope]))]]]];
        }
        return $out;
    }
    public function permitted(string $tool,array $auth): bool
    {
        $scope=self::MAP[$tool]??null; if (!$scope) return false;
        $r=$auth['resource'];
        if ($scope!==Policy::READ && empty($r['allow_drafts'])) return false;
        if ($tool==='list_sites' && Policy::site($r['id'])!==null) return false;
        if ($tool==='copy_draft' && $r['id']!=='plugin:cross-publisher') return false;
        if (in_array($tool,['list_news_items','import_news_draft'],true) && $r['id']!=='plugin:news-aggregator') return false;
        return Policy::active($r,$auth['client'],$auth['grant']) && Policy::allows($auth['client'],$auth['grant'],$scope)
            && $this->content->actorActive($r['id'],$auth['grant']['actor'],$scope);
    }
    public function validate(string $tool,array $auth,array $args): void
    {
        $def=null; foreach ($this->catalog($auth) as $t) if ($t['name']===$tool) $def=$t;
        if (!$def) throw new RuntimeException('Herramienta o permiso no autorizado.');
        $props=(array)$def['inputSchema']['properties'];
        if (array_diff(array_keys($args),array_keys($props)) || array_diff($def['inputSchema']['required'],array_keys($args))) throw new RuntimeException('Argumentos no válidos.');
        if (strlen(Store::json($args))>250000) throw new RuntimeException('Petición demasiado grande.');
        foreach ($args as $key=>$v) {
            $p=$props[$key]; $type=$p['type'];
            if (($type==='integer' && !is_int($v)) || ($type==='string' && !is_string($v)) || ($type==='boolean' && !is_bool($v)) || ($type==='array' && (!is_array($v)||!array_is_list($v)))) throw new RuntimeException('Tipo de argumento no válido.');
            if (isset($p['minimum']) && $v<$p['minimum'] || isset($p['maximum']) && $v>$p['maximum'] || isset($p['enum']) && !in_array($v,$p['enum'],true)) throw new RuntimeException('Valor no válido.');
            if ($type==='array') { if (count($v)>50) throw new RuntimeException('Demasiados identificadores.'); foreach ($v as $id) if (!is_int($id)||$id<=0) throw new RuntimeException('Identificador no válido.'); }
        }
    }
    public function call(string $tool,array $auth,array $args): array
    {
        $this->validate($tool,$auth,$args); $r=$auth['resource']; $c=$auth['client']; $g=$auth['grant'];
        if (self::MAP[$tool]===Policy::READ) {
            $result=$this->content->read($tool,$r,$c,$args);
            $this->store->event($r['id'],'tool.read',$g['actor'],['client'=>$c['id'],'tool'=>$tool]); return $result;
        }
        if (empty($r['allow_drafts'])) throw new RuntimeException('Solicitudes de borradores desactivadas en este recurso.');
        $plan=$this->content->plan($tool,$r,$c,$args);
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
    public function decide(string $resource,string $id,int $actor,bool $approve): array
    {
        $row=$this->store->one('SELECT * FROM requests WHERE id=? AND resource=?',[$id,$resource]);
        if (!$row || $row['status']!=='pending' || $row['expires']<=time()) throw new RuntimeException('Solicitud caducada o ya procesada.');
        if (!$this->content->actorActive($resource,$actor,Policy::CREATE)) throw new RuntimeException('No tienes permiso para aprobar.');
        $p=$this->store->decode($row);
        if (!$approve) {
            $n=$this->store->run("UPDATE requests SET status='rejected' WHERE id=? AND status='pending'",[$id]);
            if ($n!==1) throw new RuntimeException('Solicitud ya procesada.');
            $this->store->event($resource,'request.rejected',$actor,['request'=>$id]); return ['status'=>'rejected'];
        }
        $r=$this->store->resource($resource); $c=$this->store->client($row['client']); $g=$this->store->grant($p['grant']);
        if (!$r || !$c || !$g || empty($r['allow_drafts'])) throw new RuntimeException('Autorización revocada.');
        $auth=['resource'=>$r,'client'=>$c,'grant'=>$g]; $tool=$p['plan']['tool'];
        $this->validate($tool,$auth,$p['args']);
        $plan=$this->content->plan($tool,$r,$c,$p['args']);
        if (!hash_equals($p['fingerprint'],Policy::fingerprint($plan))) throw new RuntimeException('El plan ha cambiado. Solicita uno nuevo.');
        // Durable consumption before any CMS mutation. No retry after partial failure.
        $n=$this->store->run("UPDATE requests SET status='executing' WHERE id=? AND resource=? AND status='pending' AND expires>?",[$id,$resource,time()]);
        if ($n!==1) throw new RuntimeException('Solicitud caducada o ya procesada.');
        $this->store->event($resource,'request.approved',$actor,['request'=>$id]);
        try {
            $result=$this->store->atomic(function () use ($resource,$row,$p,$tool,$plan,$actor) {
                $r=$this->store->resource($resource); $c=$this->store->client($row['client']); $g=$this->store->grant($p['grant']);
                if (!$r || !$c || !$g || empty($r['allow_drafts'])) throw new RuntimeException('Autorización revocada antes de ejecutar.');
                $auth=['resource'=>$r,'client'=>$c,'grant'=>$g]; $this->validate($tool,$auth,$p['args']);
                return $this->content->execute($plan,$actor,$auth,$p['args']);
            });
            $p['result']=$result; $p['approved_by']=$actor;
            $this->store->run("UPDATE requests SET status='completed',data=? WHERE id=?",[Store::json($p),$id]);
            $this->store->event($resource,'request.completed',$actor,['request'=>$id,'post_id'=>$result['post_id']]); return $result;
        } catch (\Throwable $e) {
            $this->store->run("UPDATE requests SET status='failed' WHERE id=?",[$id]);
            $this->store->event($resource,'request.failed',$actor,['request'=>$id]); throw $e;
        }
    }
}
