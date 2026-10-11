<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use PDO;
use RuntimeException;
use Screenart\Musedock\SafeHtml;
use Screenart\Musedock\Services\Mcp\McpPermissions;

/** Content access is constrained here, independently of model-provided identifiers. */
final class Content
{
    public function __construct(public PDO $db) {}
    public function query(string $sql, array $params=[]): array
    {
        if ($this->db->inTransaction() && $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite' && str_starts_with(ltrim($sql),'SELECT') && !str_ends_with($sql,' FOR UPDATE')) $sql.=' FOR UPDATE';
        $s=$this->db->prepare($sql);
        foreach (array_values($params) as $i=>$value) $s->bindValue($i+1,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $s->execute(); return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function actorActive(string $resource, int $actor, string $scope=Policy::READ): bool
    {
        $site=Policy::site($resource);
        if ($site===null || $site===0) return (bool)$this->query("SELECT id FROM super_admins WHERE id=? AND role='superadmin'",[$actor]);
        $table=$actor>0?'admins':'users';
        if (!$this->query("SELECT id FROM {$table} WHERE id=? AND tenant_id=?",[abs($actor),$site])) return false;
        $permissions=\Screenart\Musedock\Helpers\PermissionHelper::class;
        if (!($actor>0 && $permissions::isTenantRootAdmin($actor,$site))
            && !$permissions::userCan(abs($actor),'mcp.connect',$site,$actor>0?'admin':'user')
            && !$permissions::userCan(abs($actor),'settings.edit',$site,$actor>0?'admin':'user')) return false;
        [$section,$level]=match($scope) { Policy::POSTS=>['posts','publish'], Policy::TAXONOMY=>['categories','write'], Policy::COMMENTS=>['posts','write'], Policy::TRANSLATIONS=>['posts','write'], Policy::MEDIA=>['media','write'], default=>['posts',$scope===Policy::READ?'read':'write'] };
        return McpPermissions::userCanGrant($actor,$site,$section,$level) && ($scope!==Policy::TAXONOMY || McpPermissions::userCanGrant($actor,$site,'tags','write'));
    }
    public function sites(array $r, array $c): array
    {
        $fixed=Policy::site($r['id']);
        if ($fixed!==null) return [$fixed];
        // Resource-owned sites and client sites are separate, explicit allowlists.
        $ids=array_values(array_intersect($r['owned_sites']??[], $c['sites']??[]));
        if (!$ids) return [];
        $rows=$this->query('SELECT id,group_id FROM tenants WHERE status=? AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')',array_merge(['active'],$ids));
        return array_values(array_map(fn($t)=>(int)$t['id'],array_filter($rows,fn($t)=>in_array((int)$t['group_id'],$c['groups']??[],true))));
    }
    public function assertSite(array $r, array $c, int $site): void
    {
        if (!in_array($site,$this->sites($r,$c),true)) throw new RuntimeException('Sitio fuera del alcance autorizado.');
    }
    public function listSites(array $r, array $c): array
    {
        $ids=$this->sites($r,$c);
        if (!$ids) return [];
        if ($ids===[0]) return [['id'=>0,'name'=>'Dominio principal','domain'=>parse_url($r['url'],PHP_URL_HOST)]];
        return $this->query('SELECT id,name,domain,group_id FROM tenants WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids);
    }
    public function siteArg(array $r, array $c, array $args): int
    {
        $site=Policy::site($r['id']);
        if ($site===null) {
            if (!isset($args['site_id']) || !is_int($args['site_id']) || $args['site_id']<=0) throw new RuntimeException('Indica un site_id autorizado.');
            $site=$args['site_id'];
        } elseif (isset($args['site_id']) && $args['site_id']!==$site) throw new RuntimeException('Esta conexión solo accede a su propio sitio.');
        $this->assertSite($r,$c,$site); return $site;
    }
    public function post(array $r, array $c, int $id): array
    {
        $row=$this->query('SELECT id,tenant_id,title,slug,content,excerpt,status,base_locale,seo_title,seo_description,updated_at FROM blog_posts WHERE id=? AND deleted_at IS NULL',[$id])[0]??null;
        if (!$row) throw new RuntimeException('Artículo no encontrado.');
        $this->assertSite($r,$c,(int)($row['tenant_id']??0)); return $row;
    }
    public function read(string $tool, array $r, array $c, array $args): array
    {
        if (isset(Editor::MAP[$tool])) return (new Editor($this))->read($tool,$r,$c,$args);
        if ($tool==='list_sites') return $this->listSites($r,$c);
        if ($tool==='get_post') return $this->post($r,$c,(int)$args['post_id']);
        $site=$this->siteArg($r,$c,$args);
        $limit=min(50,max(1,(int)($args['limit']??20))); $offset=max(0,(int)($args['offset']??0));
        if ($tool==='list_posts') {
            $where='COALESCE(tenant_id,0)=? AND deleted_at IS NULL'; $p=[$site];
            if (isset($args['status'])) { $where.=' AND status=?'; $p[]=$args['status']; }
            return $this->query("SELECT id,title,slug,excerpt,status,updated_at FROM blog_posts WHERE {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",$p);
        }
        if (in_array($tool,['list_categories','list_tags'],true)) {
            $table=$tool==='list_categories'?'blog_categories':'blog_tags';
            return $this->query("SELECT id,name,slug FROM {$table} WHERE COALESCE(tenant_id,0)=? ORDER BY name LIMIT {$limit} OFFSET {$offset}",[$site]);
        }
        if ($tool==='list_news_items' && $r['id']==='plugin:news-aggregator') {
            return $this->query("SELECT id,original_title,original_url,status,created_post_id FROM news_aggregator_items WHERE tenant_id=? ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",[$site]);
        }
        throw new RuntimeException('Herramienta no autorizada.');
    }
    private function fields(array $args): array
    {
        $data=[];
        foreach (['title','content','excerpt','seo_title','seo_description','base_locale'] as $field) {
            if (!array_key_exists($field,$args)) continue;
            if (!is_string($args[$field]) || strlen($args[$field])>200000) throw new RuntimeException('Contenido no válido.');
            $data[$field]=$field==='content'?SafeHtml::clean($args[$field]):SafeHtml::plain($args[$field]);
        }
        if (isset($data['title']) && (trim($data['title'])==='' || mb_strlen($data['title'])>255)) throw new RuntimeException('Título vacío o demasiado largo.');
        if (isset($data['base_locale']) && !preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/D',$data['base_locale'])) throw new RuntimeException('Idioma no válido.');
        return $data;
    }
    private function taxonomy(int $site,array $args): array
    {
        $result=[];
        foreach (['category_ids'=>'blog_categories','tag_ids'=>'blog_tags'] as $key=>$table) {
            if (!isset($args[$key])) continue;
            $ids=$args[$key];
            if (!is_array($ids) || count($ids)>50) throw new RuntimeException('Taxonomía no válida.');
            foreach ($ids as $id) {
                if (!is_int($id) || $id<=0 || !$this->query("SELECT id FROM {$table} WHERE id=? AND COALESCE(tenant_id,0)=?",[$id,$site])) throw new RuntimeException('Categoría o etiqueta de otro sitio.');
            }
            $result[$key]=array_values(array_unique($ids));
        }
        return $result;
    }
    public function plan(string $tool,array $r,array $c,array $args): array
    {
        if (isset(Editor::MAP[$tool])) return (new Editor($this))->plan($tool,$r,$c,$args);
        $site=$this->siteArg($r,$c,$args); $fields=$this->fields($args); $snapshot=null;
        if ($tool==='update_draft') {
            $snapshot=$this->post($r,$c,$args['post_id']);
            if ((int)($snapshot['tenant_id']??0)!==$site || $snapshot['status']!=='draft') throw new RuntimeException('Solo se pueden modificar borradores del sitio elegido.');
            if (!$fields && !isset($args['category_ids'],$args['tag_ids']) && !isset($args['category_ids']) && !isset($args['tag_ids'])) throw new RuntimeException('No hay cambios.');
        } elseif ($tool==='copy_draft' && $r['id']==='plugin:cross-publisher') {
            $snapshot=$this->post($r,$c,$args['post_id']);
            if ((int)$snapshot['tenant_id']===$site) throw new RuntimeException('El destino debe ser otro sitio.');
            // Explicit copy of text: no automatic AI, source images or synchronization.
            if ($this->query('SELECT id FROM cross_publish_relations WHERE source_post_id=? AND source_tenant_id=? AND target_tenant_id=?',[$snapshot['id'],(int)$snapshot['tenant_id'],$site])) throw new RuntimeException('Ya existe una copia vinculada en este destino.');
            $fields=$this->fields(array_filter(array_intersect_key($snapshot,array_flip(['title','content','excerpt','base_locale','seo_title','seo_description'])),fn($v)=>$v!==null));
        } elseif ($tool==='import_news_draft' && $r['id']==='plugin:news-aggregator') {
            $snapshot=$this->query('SELECT id,tenant_id,original_title,original_content,rewritten_title,rewritten_content,rewritten_excerpt,status,created_post_id FROM news_aggregator_items WHERE id=? AND tenant_id=?',[$args['item_id'],$site])[0]??null;
            if (!$snapshot || !empty($snapshot['created_post_id'])) throw new RuntimeException('Noticia no disponible o ya convertida.');
            $fields=$this->fields(['title'=>$snapshot['rewritten_title']?:$snapshot['original_title'], 'content'=>$snapshot['rewritten_content']?:($snapshot['original_content']??''),'excerpt'=>$snapshot['rewritten_excerpt']??'']);
        } elseif ($tool!=='create_draft') throw new RuntimeException('Operación no autorizada.');
        if ($tool!=='update_draft' && (empty($fields['title']) || empty($fields['content']))) throw new RuntimeException('Indica título y contenido.');
        return ['tool'=>$tool,'site_id'=>$site,'fields'=>$fields,'taxonomy'=>$this->taxonomy($site,$args),'source'=>$snapshot,'result_status'=>'draft'];
    }
    public function execute(array $plan,int $actor,?array $auth=null, array $args=[]): array
    {
        if (isset(Editor::MAP[$plan['tool']])) return (new Editor($this))->execute($plan,$actor,$auth,$args);
        $site=$plan['site_id']; $now=gmdate('Y-m-d H:i:s'); $f=$plan['fields'];
        $this->db->beginTransaction();
        try {
            if ($auth) {
                if (!$this->actorActive($auth['resource']['id'],$actor,Tools::MAP[$plan['tool']])) throw new RuntimeException('Permiso de aprobación revocado.');
                $currentPlan=$this->plan($plan['tool'],$auth['resource'],$auth['client'],$args);
                if (!hash_equals(Policy::fingerprint($plan),Policy::fingerprint($currentPlan))) throw new RuntimeException('El contenido ha cambiado antes de ejecutar.');
            }
            if ($plan['tool']==='update_draft') {
                $id=(int)$plan['source']['id'];
                // Guard row again inside the transaction (locks on MySQL/Postgres).
                $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE';
                $current=$this->query('SELECT id,tenant_id,title,slug,content,excerpt,status,base_locale,seo_title,seo_description,updated_at FROM blog_posts WHERE id=? AND deleted_at IS NULL'.$lock,[$id])[0]??null;
                if ($current!==$plan['source']) throw new RuntimeException('El borrador ha cambiado; solicita un plan nuevo.');
                $f['updated_at']=$now;
                $this->db->prepare('UPDATE blog_posts SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($f))).' WHERE id=? AND status=?')->execute(array_merge(array_values($f),[$id,'draft']));
            } else {
                $slug=$this->slug($f['title']); $base=$slug; $n=1;
                while ($this->query('SELECT id FROM blog_posts WHERE COALESCE(tenant_id,0)=? AND slug=?',[$site,$slug])) $slug=$base.'-'.++$n;
                $row=$f+['tenant_id'=>$site?:null,'user_id'=>abs($actor),'user_type'=>($auth && !str_starts_with($auth['resource']['id'],'tenant:')) || $site===0 ? 'superadmin' : ($actor>0?'admin':'user'),'slug'=>$slug,'status'=>'draft','visibility'=>'public','published_at'=>null,'allow_comments'=>1,'created_at'=>$now,'updated_at'=>$now];
                $driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
                $insert=$this->db->prepare('INSERT INTO blog_posts('.implode(',',array_keys($row)).') VALUES('.implode(',',array_fill(0,count($row),'?')).')'.($driver==='pgsql'?' RETURNING id':''));
                $insert->execute(array_values($row));
                $id=$driver==='pgsql'?(int)$insert->fetchColumn():(int)$this->db->lastInsertId();
                $prefix=$site===0?(function_exists('setting')?setting('blog_url_prefix','blog'):'blog'):$this->tenantPrefix($site);
                $this->db->prepare('INSERT INTO slugs(module,reference_id,slug,tenant_id,prefix) VALUES(?,?,?,?,?)')->execute(['blog',$id,$slug,$site?:null,$prefix?:null]);
                if ($plan['tool']==='copy_draft') {
                    $this->db->prepare('INSERT INTO cross_publish_relations(source_post_id,source_tenant_id,target_post_id,target_tenant_id,sync_enabled,created_at) VALUES(?,?,?,?,?,?)')->execute([$plan['source']['id'],(int)$plan['source']['tenant_id'],$id,$site,0,$now]);
                }
                if ($plan['tool']==='import_news_draft') {
                    $s=$this->db->prepare("UPDATE news_aggregator_items SET status='published', created_post_id=? WHERE id=? AND tenant_id=? AND created_post_id IS NULL");
                    $s->execute([$id,$plan['source']['id'],$site]);
                    if ($s->rowCount()!==1) throw new RuntimeException('La noticia ha cambiado o ya fue convertida.');
                }
            }
            foreach ($plan['taxonomy'] as $key=>$ids) {
                $kind=$key==='category_ids'?'categories':'tags'; $column=$kind==='categories'?'category_id':'tag_id';
                $old=$this->query("SELECT {$column} FROM blog_post_{$kind} WHERE post_id=?",[$id]);
                $this->db->prepare("DELETE FROM blog_post_{$kind} WHERE post_id=?")->execute([$id]);
                foreach ($ids as $taxId) $this->db->prepare("INSERT INTO blog_post_{$kind}(post_id,{$column}) VALUES(?,?)")->execute([$id,$taxId]);
                foreach (array_unique(array_merge($ids,array_column($old,$column))) as $taxId) {
                    $this->db->prepare("UPDATE blog_{$kind} SET post_count=(SELECT COUNT(*) FROM blog_post_{$kind} WHERE {$column}=?) WHERE id=?")->execute([$taxId,$taxId]);
                }
            }
            $this->db->commit(); return ['post_id'=>$id,'site_id'=>$site,'status'=>'draft'];
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }
    private function tenantPrefix(int $id): string
    {
        $driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME); $key=$driver==='mysql'?'`key`':'"key"';
        $r=$this->query("SELECT value FROM tenant_settings WHERE tenant_id=? AND {$key}=?",[$id,'blog_url_prefix']);
        return (string)($r[0]['value']??'blog');
    }
    private function slug(string $title): string
    {
        $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$title);
        return substr(trim(preg_replace('/[^a-z0-9]+/','-',strtolower($ascii?:$title)),'-'),0,180)?:'articulo';
    }
}
