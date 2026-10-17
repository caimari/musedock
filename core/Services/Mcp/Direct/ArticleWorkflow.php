<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use RuntimeException;

/** Coordinates the existing media/editor services; never retries an uncertain provider call. */
final class ArticleWorkflow
{
    public function __construct(private Store $store, private Content $content) {}
    private function auth(array $job): array
    {
        if (str_starts_with($job['resource'],'plugin:') && !Endpoint::installed($job['resource'])) throw new RuntimeException('El plugin ya no está activo.');
        $r=$this->store->resource($job['resource']); $c=$this->store->client($job['client']); $g=$this->store->grant($job['grant']);
        $auth=['resource'=>$r,'client'=>$c,'grant'=>$g];
        if (!$r || !$c || !$g || !Policy::active($r,$c,$g) || empty($c['auto_approve_posts']) || empty($c['auto_approve_images'])) throw new RuntimeException('Este flujo requiere aprobación automática de artículos e imágenes y consentimiento vigente.');
        $tools=new Tools($this->store,$this->content);
        foreach (['generate_image','set_featured_image','publish_post'] as $tool) if (!$tools->permitted($tool,$auth)) throw new RuntimeException('Permisos insuficientes para completar el flujo.');
        $this->content->siteArg($r,$c,['site_id'=>$job['site']]);
        return $auth;
    }
    public function call(array $auth,array $args): array
    {
        if (!empty($args['prompt'])) $args['prompt'].=' Sin texto, letras, números, logotipos ni marcas de agua.';
        $site=$this->content->siteArg($auth['resource'],$auth['client'],$args);
        $key=hash('sha256',$auth['resource']['id'].'|'.$auth['client']['id'].'|'.$site.'|'.$args['post_id']);
        $row=$this->store->one('SELECT data FROM article_workflows WHERE id=?',[$key]);
        $job=$row?json_decode($row['data'],true):null;
        if (!$job) {
            $job=['id'=>$key,'resource'=>$auth['resource']['id'],'client'=>$auth['client']['id'],'grant'=>$auth['grant']['id'],'site'=>$site,'post'=>(int)$args['post_id'],'args'=>$args,'stage'=>'queued','status'=>'queued','created'=>time()];
            $this->auth($job);
        }
        if (($args['action']??'start')==='status') {
            if (!$row) throw new RuntimeException('Trabajo no encontrado.');
            return $this->result($this->reconcile($job));
        }
        if (empty($args['apply'])) return ['status'=>'plan','site_id'=>$site,'post_id'=>$args['post_id'],'next'=>'apply=true inicia la cola persistente; action=status consulta el resultado.'];
        if (!$row) {
            if (empty($args['prompt'])) throw new RuntimeException('Indica el prompt de la portada.');
            // Validate post and provider before persisting, without a generation or publication.
            $this->content->read('get_post_details',$auth['resource'],$auth['client'],['site_id'=>$site,'post_id'=>$job['post']]);
            (new Media($this->content))->plan('generate_image',$site,$args);
            $this->store->run('INSERT OR IGNORE INTO article_workflows(id,resource,client,status,data) VALUES(?,?,?,?,?)',[$key,$job['resource'],$job['client'],'queued',Store::json($job)]);
        } elseif (($args['action']??'start')==='resume' && $job['status']==='failed') {
            $job=$this->reconcile($job);
            if ($job['status']==='completed') return $this->result($job);
            $job['grant']=$auth['grant']['id']; $this->auth($job);
            if ($job['stage']==='generating' && empty($job['media_id'])) {
                if (empty($args['media_id'])) throw new RuntimeException('Generación incierta: revisa medios y proporciona media_id para recuperar sin otro cargo.');
                (new Media($this->content))->one((int)$args['media_id'],$site);
                $job['media_id']=(int)$args['media_id']; $job['stage']='image_saved';
            }
            $job['status']='queued'; unset($job['error']); $this->save($job);
        }
        $stored=$this->store->one('SELECT data FROM article_workflows WHERE id=?',[$key]);
        return $this->result(json_decode($stored['data'],true));
    }
    private function save(array $job): void
    {
        $job['updated']=time();
        $this->store->run('UPDATE article_workflows SET status=?,data=? WHERE id=?',[$job['status'],Store::json($job),$job['id']]);
    }
    private function result(array $job): array
    {
        return ['job_id'=>$job['id'],'site_id'=>$job['site'],'post_id'=>$job['post']]+array_intersect_key($job,array_flip(['id','status','stage','site','post','media_id','public_url','error']));
    }
    /** Read-only CMS evidence reconciles lost checkpoints; never calls a provider or publishes. */
    private function reconcile(array $job): array
    {
        if (!in_array($job['status'],['failed','executing'],true)) return $job;
        $media=null;
        if (!empty($job['media_id'])) {
            $media=(new Media($this->content))->one((int)$job['media_id'],$job['site']);
        } else {
            $rows=$this->content->query('SELECT id,metadata FROM media WHERE COALESCE(tenant_id,0)=? AND created_at>=? ORDER BY id DESC',[$job['site'],gmdate('Y-m-d H:i:s',($job['created']??0)-60)]);
            foreach ($rows as $row) {
                $metadata=json_decode($row['metadata']??'{}',true);
                if (($metadata['ai_generation']['request_id']??null)!==$job['id']) continue;
                $media=(new Media($this->content))->one((int)$row['id'],$job['site']); break;
            }
        }
        if (!$media) return $job;
        $job['media_id']=(int)$media['id'];
        if ($job['stage']==='generating') $job['stage']='image_saved';
        $post=$this->content->query('SELECT slug,status,featured_image FROM blog_posts WHERE id=? AND COALESCE(tenant_id,0)=? AND deleted_at IS NULL',[$job['post'],$job['site']])[0]??null;
        if ($post && $post['status']==='published' && ($post['featured_image']??null)===$media['url']) {
            $route=$this->content->query("SELECT slug,prefix FROM slugs WHERE module='blog' AND reference_id=? AND COALESCE(tenant_id,0)=?",[$job['post'],$job['site']])[0]??null;
            $domain=$job['site']?$this->content->query('SELECT domain FROM tenants WHERE id=?',[$job['site']])[0]['domain']:parse_url(\Screenart\Musedock\Env::get('APP_URL',''),PHP_URL_HOST);
            $path=($route && !empty($route['prefix'])?trim($route['prefix'],'/').'/':'').($route['slug']??$post['slug']);
            $job['public_url']='https://'.$domain.'/'.$path;
            $job['status']='completed'; $job['stage']='published'; unset($job['error']);
            $this->store->event($job['resource'],'article.workflow.reconciled',0,['job'=>$job['id'],'media_id'=>$job['media_id']]);
        }
        $this->save($job);
        return $job;
    }
    public function run(): int
    {
        foreach ($this->store->all("SELECT data FROM article_workflows WHERE status='executing'") as $row) {
            $j=json_decode($row['data'],true); $j['status']='failed'; $j['error']='Trabajo interrumpido; reanuda para continuar desde la última etapa confirmada.'; $this->save($j);
        }
        foreach ($this->store->all("SELECT data FROM article_workflows WHERE status='failed' LIMIT 100") as $row) $this->reconcile(json_decode($row['data'],true));
        $count=0;
        foreach ($this->store->all("SELECT data FROM article_workflows WHERE status='queued' LIMIT 3") as $row) {
            $j=json_decode($row['data'],true);
            if (!$this->store->run("UPDATE article_workflows SET status='executing' WHERE id=? AND status='queued'",[$j['id']])) continue;
            $j['status']='executing';
            try {
                $auth=$this->auth($j); $actor=(int)$auth['grant']['actor'];
                if (empty($j['media_id'])) {
                    $j['stage']='generating'; $this->save($j);
                    $args=$j['args'];
                    $GLOBALS['mcp_request_id']=$j['id'];
                    $image=(new Media($this->content))->execute('generate_image',$j['site'],$actor,$args);
                    $j['media_id']=$image['media']['id']; $j['stage']='image_saved'; $this->save($j);
                }
                $auth=$this->auth($j);
                if ($j['stage']!=='image_assigned') {
                $args=['site_id'=>$j['site'],'post_id'=>$j['post'],'media_id'=>$j['media_id']];
                $plan=$this->content->plan('set_featured_image',$auth['resource'],$auth['client'],$args);
                $this->content->execute($plan,$actor,$auth,$args);
                $j['stage']='image_assigned'; $this->save($j);
                }
                $auth=$this->auth($j);
                $args=['site_id'=>$j['site'],'post_id'=>$j['post']];
                $plan=$this->content->plan('publish_post',$auth['resource'],$auth['client'],$args);
                if (($plan['source']['status']??'')!=='published') $this->content->execute($plan,$actor,$auth,$args);
                $post=$this->content->query('SELECT slug,status,featured_image FROM blog_posts WHERE id=? AND COALESCE(tenant_id,0)=?',[$j['post'],$j['site']])[0];
                if ($post['status']!=='published' || empty($post['featured_image'])) throw new RuntimeException('La comprobación final no confirma portada y publicación.');
                $route=$this->content->query("SELECT slug,prefix FROM slugs WHERE module='blog' AND reference_id=? AND COALESCE(tenant_id,0)=?",[$j['post'],$j['site']])[0]??null;
                $path=($route && !empty($route['prefix'])?trim($route['prefix'],'/').'/':'').($route['slug']??$post['slug']);
                $domain=$j['site']?$this->content->query('SELECT domain FROM tenants WHERE id=?',[$j['site']])[0]['domain']:parse_url(\Screenart\Musedock\Env::get('APP_URL',''),PHP_URL_HOST);
                $j['public_url']='https://'.$domain.'/'.$path; $j['stage']='published'; $j['status']='completed'; $this->save($j);
            } catch (\Throwable $e) {
                $j['status']='failed'; $j['error']=$e instanceof \PDOException?'No se pudo guardar la etapa en el CMS.':mb_substr($e->getMessage(),0,500); $this->save($j);
            }
            unset($GLOBALS['mcp_request_id']);
            $this->store->event($j['resource'],'article.workflow.'.$j['status'],(int)($actor??0),['job'=>$j['id'],'stage'=>$j['stage']]); $count++;
        }
        return $count;
    }
}
