<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use RuntimeException;

/** Coordinates the existing media/editor services; never retries an uncertain provider call. */
final class ArticleWorkflow
{
    public function __construct(private Store $store, private Content $content,private ?\Closure $imageGenerator=null) {}
    private function auth(array $job): array
    {
        if (str_starts_with($job['resource'],'plugin:') && !Endpoint::installed($job['resource'])) throw new RuntimeException('El plugin ya no está activo.');
        $r=$this->store->resource($job['resource']); $c=$this->store->client($job['client']); $g=$this->store->grant($job['grant']);
        $auth=['resource'=>$r,'client'=>$c,'grant'=>$g];
        if (!$r || !$c || !$g || !Policy::active($r,$c,$g) || empty($c['auto_approve_posts']) || empty($c['auto_approve_images'])) throw new RuntimeException('Este flujo requiere aprobación automática de artículos e imágenes y consentimiento vigente.');
        $tools=new Tools($this->store,$this->content);
        $required=['generate_image','set_featured_image'];
        if (!empty($job['create'])) $required[]='create_post';
        if (($job['mode']??'publish')==='publish') $required[]='publish_post';
        foreach ($required as $tool) if (!$tools->permitted($tool,$auth)) throw new RuntimeException('Permisos insuficientes para completar el flujo.');
        $this->content->siteArg($r,$c,['site_id'=>$job['site']]);
        if (!empty($job['create']) && empty($job['post'])) {
            $terms=new Taxonomy($this->content);$plan=$terms->prepare($job['site'],$job['args']);
            if ($terms->missing($job['site'],$plan) && (empty($c['auto_approve_taxonomy']) || !$tools->permitted('create_tag',$auth))) throw new RuntimeException('Crear términos automáticamente requiere permiso de taxonomías y su aprobación automática.');
        }
        return $auth;
    }
    public function call(array $auth,array $args,bool $create=false): array
    {
        if (!empty($args['prompt'])) $args['prompt'].=' Sin texto, letras, números, logotipos ni marcas de agua.';
        $site=$this->content->siteArg($auth['resource'],$auth['client'],$args);
        if ($create) {
            if (empty($args['job_id']) && empty($args['idempotency_key'])) throw new RuntimeException('Indica idempotency_key para iniciar o job_id para consultar.');
            $key=$args['job_id']??hash('sha256','create|'.$auth['resource']['id'].'|'.$auth['client']['id'].'|'.$site.'|'.$args['idempotency_key']);
        } else $key=hash('sha256',$auth['resource']['id'].'|'.$auth['client']['id'].'|'.$site.'|'.$args['post_id']);
        $row=$this->store->one('SELECT data FROM article_workflows WHERE id=?',[$key]);
        $job=$row?json_decode($row['data'],true):null;
        if ($job && ($job['client']!==$auth['client']['id'] || $job['resource']!==$auth['resource']['id'] || $job['site']!==$site)) throw new RuntimeException('Trabajo fuera del acceso autorizado.');
        if (!$job && isset($args['job_id'])) throw new RuntimeException('Trabajo no encontrado.');
        if (!$job) {
            $job=['id'=>$key,'resource'=>$auth['resource']['id'],'client'=>$auth['client']['id'],'grant'=>$auth['grant']['id'],'site'=>$site,'post'=>(int)($args['post_id']??0),'create'=>$create,'mode'=>$args['mode']??'publish','args'=>$args,'stage'=>'queued','status'=>'queued','created'=>time()];
            $this->auth($job);
        }
        if (($args['action']??'start')==='status') {
            if (!$row) throw new RuntimeException('Trabajo no encontrado.');
            return $this->result($this->reconcile($job));
        }
        if (empty($args['apply'])) return ['status'=>'plan','site_id'=>$site,'post_id'=>$job['post'],'next'=>'apply=true inicia la cola persistente; action=status consulta el resultado.'];
        if (!$row) {
            if (empty($args['prompt'])) throw new RuntimeException('Indica el prompt de la portada.');
            // Validate post and provider before persisting, without a generation or publication.
            if ($create) $this->content->plan('create_post',$auth['resource'],$auth['client'],$this->creationArgs($job));
            else $this->content->read('get_post_details',$auth['resource'],$auth['client'],['site_id'=>$site,'post_id'=>$job['post']]);
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
    private function creationArgs(array $job): array
    {
        $allowed=array_keys(Editor::schema('create_post'));
        $args=array_intersect_key($job['args'],array_flip(array_merge($allowed,['category_ids','tag_ids'])));
        $args['site_id']=$job['site']; $args['status']='draft';
        unset($args['published_at']);
        if (empty($args['title']) || empty($args['content'])) throw new RuntimeException('Indica título y contenido HTML.');
        $args['seo_title']=$args['seo_title']??mb_substr(strip_tags($args['title']),0,200);
        $args['seo_description']=$args['seo_description']??mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags($args['excerpt']??$args['content']))),0,160);
        return $args;
    }
    private function recoverCreation(array $job): array
    {
        if (empty($job['create']) || !empty($job['post'])) return $job;
        $receipt=$this->content->query('SELECT post_id FROM mcp_article_receipts WHERE job_id=? AND site_id=?',[$job['id'],$job['site']])[0]??null;
        if ($receipt) { $job['post']=(int)$receipt['post_id']; if (in_array($job['stage'],['creating','queued'],true)) $job['stage']='draft_created'; $this->save($job); }
        return $job;
    }
    private function save(array $job): void
    {
        $job['updated']=time();
        $this->store->run('UPDATE article_workflows SET status=?,data=? WHERE id=?',[$job['status'],Store::json($job),$job['id']]);
    }
    private function result(array $job): array
    {
        $terms=!empty($job['post'])?(new Taxonomy($this->content))->assigned($job['site'],$job['post']):[];
        return $terms+['job_id'=>$job['id'],'site_id'=>$job['site'],'post_id'=>$job['post']]+array_intersect_key($job,array_flip(['id','status','stage','mode','site','post','media_id','public_url','error','errors']));
    }
    /** Read-only CMS evidence reconciles lost checkpoints; never calls a provider or publishes. */
    private function reconcile(array $job): array
    {
        if (!in_array($job['status'],['failed','executing'],true)) return $job;
        $job=$this->recoverCreation($job);
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
        if (($job['mode']??'publish')==='draft' && $post && $post['status']==='draft' && ($post['featured_image']??null)===$media['url']) {
            $job['status']='completed'; $job['stage']='draft_ready'; unset($job['error'],$job['public_url']);
            $this->save($job); return $job;
        }
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
        foreach ($this->store->all("SELECT data FROM article_workflows WHERE status='failed' LIMIT 100") as $row) {
            try { $this->reconcile(json_decode($row['data'],true)); }
            catch (\Throwable $e) { /* An unavailable old medium must not block unrelated queued jobs. */ }
        }
        $count=0;
        foreach ($this->store->all("SELECT data FROM article_workflows WHERE status='queued' LIMIT 3") as $row) {
            $j=json_decode($row['data'],true);
            if (!$this->store->run("UPDATE article_workflows SET status='executing' WHERE id=? AND status='queued'",[$j['id']])) continue;
            $j['status']='executing';
            try {
                $auth=$this->auth($j); $actor=(int)$auth['grant']['actor'];
                if (!empty($j['create']) && empty($j['post'])) {
                    $j=$this->recoverCreation($j);
                    if (empty($j['post'])) {
                        $j['stage']='creating'; $this->save($j);
                        $args=$this->creationArgs($j);
                        $plan=$this->content->plan('create_post',$auth['resource'],$auth['client'],$args);
                        $created=(new Editor($this->content))->execute($plan,$actor,$auth,$args,function(int $post) use ($j) {
                            $this->content->db->prepare('INSERT INTO mcp_article_receipts(job_id,post_id,site_id) VALUES(?,?,?)')->execute([$j['id'],$post,$j['site']]);
                        });
                        $j['post']=(int)$created['post_id'];
                    }
                    $j['stage']='draft_created'; $this->save($j);
                }
                if (empty($j['media_id'])) {
                    $j['stage']='generating'; $this->save($j);
                    $args=$j['args'];
                    $GLOBALS['mcp_request_id']=$j['id'];
                    $image=$this->imageGenerator?($this->imageGenerator)($j['site'],$actor,$args):(new Media($this->content))->execute('generate_image',$j['site'],$actor,$args);
                    $j['media_id']=$image['media']['id']; $j['stage']='image_saved'; $this->save($j);
                }
                $auth=$this->auth($j);
                if ($j['stage']!=='image_assigned') {
                    $args=['site_id'=>$j['site'],'post_id'=>$j['post'],'media_id'=>$j['media_id']];
                    $plan=$this->content->plan('set_featured_image',$auth['resource'],$auth['client'],$args);
                    $this->content->execute($plan,$actor,$auth,$args);
                    $j['stage']='image_assigned'; $this->save($j);
                }
                if (($j['mode']??'publish')==='draft') {
                    $j['stage']='draft_ready'; $j['status']='completed'; $this->save($j);
                } else {
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
                }
            } catch (\Throwable $e) {
                $j['status']='failed'; $j['error']=$e instanceof \PDOException?'No se pudo guardar la etapa en el CMS.':mb_substr($e->getMessage(),0,500);
                $j['errors'][]=['stage'=>$j['stage'],'message'=>$j['error'],'time'=>time()];
                $j['errors']=array_slice($j['errors'],-20); $this->save($j);
            }
            unset($GLOBALS['mcp_request_id']);
            $this->store->event($j['resource'],'article.workflow.'.$j['status'],(int)($actor??0),['job'=>$j['id'],'stage'=>$j['stage'],'taxonomy'=>!empty($j['post'])?(new Taxonomy($this->content))->assigned($j['site'],$j['post']):[]]); $count++;
        }
        return $count;
    }
}
