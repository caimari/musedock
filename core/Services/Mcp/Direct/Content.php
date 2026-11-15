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
    /** Connecting does not imply access to blog content; tool-specific checks still apply. */
    public function actorCanConnect(string $resource,int $actor): bool
    {
        return $this->actorActive($resource,$actor,Policy::READ)||$this->actorActive($resource,$actor,Policy::PAGES_READ);
    }
    public function actorActive(string $resource, int $actor, string $scope=Policy::READ): bool
    {
        $site=Policy::site($resource);
        if (Policy::news($resource) && !Endpoint::installed($resource)) return false;
        if ($site===null || $site===0) return (bool)$this->query("SELECT id FROM super_admins WHERE id=? AND role='superadmin'",[$actor]);
        $table=$actor>0?'admins':'users';
        if ($actor > 0) {
            $account = (new \Screenart\Musedock\Services\Panel\PanelAccounts($this->db))->find($site,$actor);
            if (!$account || !\Screenart\Musedock\Services\Panel\PanelAccounts::active($account)) return false;
        }
        if (!$this->query("SELECT id FROM {$table} WHERE id=? AND tenant_id=?",[abs($actor),$site])) return false;
        $permissions=\Screenart\Musedock\Helpers\PermissionHelper::class;
        if (!($actor>0 && $permissions::isTenantRootAdmin($actor,$site))
            && !$permissions::userCan(abs($actor),'mcp.connect',$site,$actor>0?'admin':'user')
            && !$permissions::userCan(abs($actor),'settings.edit',$site,$actor>0?'admin':'user')) return false;
        if($scope===Policy::APPROVE)return $this->actorCanConnect($resource,$actor);
        [$section,$level]=match($scope) { Policy::PAGES_ARCHIVE=>['pages','delete'],Policy::COLLECTIONS_ARCHIVE=>['collections','delete'],Policy::PAGES_READ=>['pages','read'], Policy::PAGES_EDIT=>['pages','write'], Policy::PAGES_PUBLISH=>['pages','publish'], Policy::COLLECTIONS_READ=>['collections','read'], Policy::COLLECTIONS_EDIT=>['collections','write'], Policy::COLLECTIONS_PUBLISH=>['collections','publish'], Policy::POSTS=>['posts','publish'], Policy::TAXONOMY=>['categories','write'], Policy::COMMENTS=>['posts','write'], Policy::TRANSLATIONS=>['posts','write'], Policy::MEDIA=>['media','write'], default=>['posts',$scope===Policy::READ?'read':'write'] };
        return McpPermissions::userCanGrant($actor,$site,$section,$level) && ($scope!==Policy::TAXONOMY || McpPermissions::userCanGrant($actor,$site,'tags','write'));
    }
    public function sites(array $r, array $c): array
    {
        $fixed=Policy::site($r['id']);
        if ($fixed!==null) return !Policy::news($r['id']) || Endpoint::tenantPluginActive($fixed) ? [$fixed] : [];
        // Resource-owned sites and client sites are separate, explicit allowlists.
        $ids=array_values(array_intersect($r['owned_sites']??[], $c['sites']??[]));
        if (!$ids) return [];
        $rows=$this->query('SELECT t.id,t.group_id FROM tenants t INNER JOIN domain_groups g ON g.id=t.group_id WHERE t.status=? AND t.id IN ('.implode(',',array_fill(0,count($ids),'?')).')',array_merge(['active'],$ids));
        return array_values(array_map(fn($t)=>(int)$t['id'],array_filter($rows,fn($t)=>in_array((int)$t['group_id'],$c['groups']??[],true) && (!Policy::news($r['id']) || Endpoint::tenantPluginActive((int)$t['id'])))));
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
        $sites=$this->query('SELECT id,name,domain,group_id FROM tenants WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids);
        $aliases=$this->query("SELECT tenant_id,domain,include_www FROM domain_aliases WHERE status='active' AND tenant_id IN (".implode(',',array_fill(0,count($ids),'?')).') ORDER BY domain',$ids);
        foreach ($sites as &$site) {
            $site['url']='https://'.$site['domain'];
            $site['aliases']=[];
            foreach ($aliases as $alias) {
                if ((int)$alias['tenant_id']!==(int)$site['id']) continue;
                $site['aliases'][]=['domain'=>$alias['domain'],'url'=>'https://'.$alias['domain'],'include_www'=>(bool)$alias['include_www']];
            }
            $site['public_urls']=array_merge([$site['url']],array_column($site['aliases'],'url'));
        }
        unset($site);
        return $sites;
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
        if (isset(Editorial::MAP[$tool])) return (new Editorial($this))->read($tool,$r,$c,$args);
        if (in_array($tool,NewsReader::TOOLS,true)) { if (!Policy::news($r['id'])) throw new RuntimeException('Consulta exclusiva de News Aggregator.'); return (new NewsReader($this))->read($tool,$r,$c,$args); }
        if (isset(Editor::MAP[$tool])) return (new Editor($this))->read($tool,$r,$c,$args);
        if ($tool==='list_sites') return $this->listSites($r,$c);
        if ($tool==='get_post') return $this->post($r,$c,(int)$args['post_id']);
        $site=$this->siteArg($r,$c,$args);
        if ($tool==='get_editorial_brief') {
            if ($r['id']!=='plugin:cross-publisher') throw new RuntimeException('Consulta exclusiva de Cross Publisher.');
            return $this->summaryBrief($site,$args);
        }
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
        if ($tool==='list_news_items' && Policy::news($r['id'])) {
            $where='tenant_id=?'; $params=[$site];
            if (isset($args['status'])) { $where.=' AND status=?'; $params[]=$args['status']; }
            if (isset($args['source_id'])) { $where.=' AND source_id=?'; $params[]=$args['source_id']; }
            return $this->query("SELECT id,tenant_id,source_id,original_title,original_url,status,created_post_id,CASE WHEN created_post_id IS NULL THEN 0 ELSE 1 END AS has_linked_article FROM news_aggregator_items WHERE {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",$params);
        }
        throw new RuntimeException('Herramienta no autorizada.');
    }
    /** «Permitir comentarios» inicial de una entrada nueva según el ajuste blog_comments_mode del sitio (1 si no se puede leer). */
    private function defaultAllowComments(?int $site): int
    {
        try {
            if (!class_exists('Blog\Models\BlogComment', false)) {
                require_once dirname(__DIR__, 4) . '/modules/blog/models/BlogComment.php';
            }
            return \Blog\Models\BlogComment::defaultAllowForNewPosts($site);
        } catch (\Throwable $e) {
            return 1;
        }
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
        if (isset(Editorial::MAP[$tool])) return (new Editorial($this))->plan($tool,$r,$c,$args);
        if (in_array($tool,NewsActions::TOOLS,true)) return (new NewsActions($this))->plan($tool,$r,$c,$args);
        if ($tool==='import_news_draft') throw new RuntimeException('Escribe un artículo propio con create_draft y enlázalo con link_news_item_to_post.');
        if (isset(Editor::MAP[$tool])) return (new Editor($this))->plan($tool,$r,$c,$args);
        $site=$this->siteArg($r,$c,$args);
        if (in_array($tool,['queue_editorial_summary','create_editorial_summary'],true)) {
            if ($r['id']!=='plugin:cross-publisher') throw new RuntimeException('Operación no autorizada.');
            return $tool==='queue_editorial_summary' ? $this->summaryPlan($site,$args) : $this->summaryClientPlan($site,$args);
        }
        $fields=$this->fields($args); $snapshot=null;
        if ($tool==='update_draft') {
            $snapshot=$this->post($r,$c,$args['post_id']);
            if ((int)($snapshot['tenant_id']??0)!==$site || $snapshot['status']!=='draft') throw new RuntimeException('Solo se pueden modificar borradores del sitio elegido.');
            if (!$fields && !isset($args['category_ids'],$args['tag_ids']) && !isset($args['category_ids']) && !isset($args['tag_ids'])) throw new RuntimeException('No hay cambios.');
        } elseif ($tool==='copy_draft' && $r['id']==='plugin:cross-publisher') {
            $snapshot=$this->post($r,$c,$args['post_id']);
            if ((int)$snapshot['tenant_id']===$site) throw new RuntimeException('El destino debe ser otro sitio.');
            // Red editorial: la entrevista completa solo vive en la web del festival.
            if ($this->isInterview((int)$snapshot['id'],(string)$snapshot['content'])) throw new RuntimeException('Las entrevistas no se copian enteras: usa queue_editorial_summary (resumen editorial con enlace al original).');
            // Explicit copy of text: no automatic AI, source images or synchronization.
            if ($this->query('SELECT id FROM cross_publish_relations WHERE source_post_id=? AND source_tenant_id=? AND target_tenant_id=?',[$snapshot['id'],(int)$snapshot['tenant_id'],$site])) throw new RuntimeException('Ya existe una copia vinculada en este destino.');
            $fields=$this->fields(array_filter(array_intersect_key($snapshot,array_flip(['title','content','excerpt','base_locale','seo_title','seo_description'])),fn($v)=>$v!==null));
        } elseif ($tool!=='create_draft') throw new RuntimeException('Operación no autorizada.');
        if ($tool!=='update_draft' && (empty($fields['title']) || empty($fields['content']))) throw new RuntimeException('Indica título y contenido.');
        return ['tool'=>$tool,'site_id'=>$site,'fields'=>$fields,'taxonomy'=>$this->taxonomy($site,$args),'source'=>$snapshot,'result_status'=>'draft'];
    }
    public function execute(array $plan,int $actor,?array $auth=null, array $args=[]): array
    {
        if ($plan['tool']==='import_news_draft') throw new RuntimeException('La importación literal está retirada. Crea un borrador propio y enlaza el aviso.');
        if (isset(Editorial::MAP[$plan['tool']])) return (new Editorial($this))->execute($plan,$actor,$auth,$args);
        if (in_array($plan['tool'],NewsActions::TOOLS,true)) return (new NewsActions($this))->execute($plan,$actor,$auth,$args);
        if (isset(Editor::MAP[$plan['tool']])) return (new Editor($this))->execute($plan,$actor,$auth,$args);
        if ($plan['tool']==='queue_editorial_summary') return $this->executeSummary($plan,$actor,$auth,$args);
        if ($plan['tool']==='create_editorial_summary') return $this->executeClientSummary($plan,$actor,$auth,$args);
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
                $row=$f+['tenant_id'=>$site?:null,'user_id'=>abs($actor),'user_type'=>($auth && !str_starts_with($auth['resource']['id'],'tenant:')) || $site===0 ? 'superadmin' : ($actor>0?'admin':'user'),'slug'=>$slug,'status'=>'draft','visibility'=>'public','published_at'=>null,'allow_comments'=>$this->defaultAllowComments($site?:null),'created_at'=>$now,'updated_at'=>$now];
                $driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
                $insert=$this->db->prepare('INSERT INTO blog_posts('.implode(',',array_keys($row)).') VALUES('.implode(',',array_fill(0,count($row),'?')).')'.($driver==='pgsql'?' RETURNING id':''));
                $insert->execute(array_values($row));
                $id=$driver==='pgsql'?(int)$insert->fetchColumn():(int)$this->db->lastInsertId();
                $prefix=$site===0?(function_exists('setting')?setting('blog_url_prefix','blog'):'blog'):$this->tenantPrefix($site);
                $this->db->prepare('INSERT INTO slugs(module,reference_id,slug,tenant_id,prefix) VALUES(?,?,?,?,?)')->execute(['blog',$id,$slug,$site?:null,$prefix?:null]);
                if ($plan['tool']==='copy_draft') {
                    $this->db->prepare('INSERT INTO cross_publish_relations(source_post_id,source_tenant_id,target_post_id,target_tenant_id,sync_enabled,created_at) VALUES(?,?,?,?,?,?)')->execute([$plan['source']['id'],(int)$plan['source']['tenant_id'],$id,$site,0,$now]);
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
    /** Misma regla que Cross-Publisher (EditorialSummaryService::isInterview). */
    private function isInterview(int $postId,string $content): bool
    {
        if (str_contains($content,'class="interview"')) return true;
        return (bool)$this->query("SELECT pc.post_id FROM blog_post_categories pc INNER JOIN blog_categories c ON c.id=pc.category_id WHERE pc.post_id=? AND LOWER(c.slug) IN ('interviews','entrevistas','interviste','entretiens','interview','entrevista')",[$postId]);
    }
    private function hasColumn(string $table,string $column): bool
    {
        static $cache=[]; $key=$table.'.'.$column;
        if (!isset($cache[$key])) {
            // Sin $this->query: dentro de una transacción añadiría FOR UPDATE a information_schema.
            $schema=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql'?'current_schema()':'DATABASE()';
            $s=$this->db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema={$schema} AND table_name=? AND column_name=?");
            $s->execute([$table,$column]); $cache[$key]=(bool)$s->fetchColumn();
        }
        return $cache[$key];
    }
    /** Artículo original publicado por post_id o por su URL pública (dominio del tenant o alias + slug). */
    private function summarySource(array $args): array
    {
        $cols='id,tenant_id,title,slug,content,status,published_at';
        if (isset($args['post_id'])) {
            $row=$this->query("SELECT {$cols} FROM blog_posts WHERE id=? AND deleted_at IS NULL",[$args['post_id']])[0]??null;
        } elseif (isset($args['source_url'])) {
            $host=strtolower((string)parse_url($args['source_url'],PHP_URL_HOST)); $host=preg_replace('/^www\./','',$host);
            $path=trim((string)parse_url($args['source_url'],PHP_URL_PATH),'/'); $slug=$path===''?'':basename($path);
            if ($host==='' || $slug==='') throw new RuntimeException('source_url no válida: indica la URL completa del artículo.');
            $tenant=$this->query("SELECT id FROM tenants WHERE status='active' AND (domain=? OR domain=?)",[$host,'www.'.$host])[0]['id']
                ?? $this->query("SELECT tenant_id AS id FROM domain_aliases WHERE status='active' AND (domain=? OR domain=?)",[$host,'www.'.$host])[0]['id'] ?? null;
            if (!$tenant) throw new RuntimeException('El dominio de source_url no pertenece a la red.');
            $row=$this->query("SELECT {$cols} FROM blog_posts WHERE tenant_id=? AND slug=? AND deleted_at IS NULL",[(int)$tenant,$slug])[0]??null;
        } else throw new RuntimeException('Indica post_id o source_url del artículo original.');
        if (!$row || $row['status']!=='published' || empty($row['tenant_id'])) throw new RuntimeException('Artículo original no encontrado o no publicado.');
        if (!$this->query("SELECT id FROM tenants WHERE id=? AND status='active'",[(int)$row['tenant_id']])) throw new RuntimeException('El sitio del original no está activo.');
        return $row;
    }
    /**
     * Comprobaciones comunes de un resumen editorial: original, destino de un grupo, sin encargos
     * repetidos, idiomas activos del medio. $languages: códigos pedidos (o ['all']).
     */
    private function summaryTarget(int $site,array $args,array $languages,bool $checkQueue=true): array
    {
        $src=$this->summarySource($args); $from=(int)$src['tenant_id'];
        if ($from===$site) throw new RuntimeException('El destino debe ser otro sitio.');
        if (!$this->query("SELECT id FROM tenants WHERE id=? AND status='active' AND group_id IS NOT NULL",[$site])) throw new RuntimeException('El destino debe ser un medio activo de un grupo editorial.');
        if ($checkQueue && $this->query("SELECT id FROM cross_publish_queue WHERE source_post_id=? AND source_tenant_id=? AND target_tenant_id=? AND status IN ('pending','processing')",[(int)$src['id'],$from,$site])) throw new RuntimeException('Ya hay un encargo en cola para ese medio.');
        if ($this->query('SELECT id FROM cross_publish_relations WHERE source_post_id=? AND source_tenant_id=? AND target_tenant_id=?',[(int)$src['id'],$from,$site])) throw new RuntimeException('Ese medio ya tiene una versión de este artículo.');
        $skip=!empty($args['skip_delay']);
        if ($skip && !$this->hasColumn('cross_publish_queue','skip_delay')) throw new RuntimeException('«Sin espera» no disponible: falta la migración 2026_11_11_140000.');
        $driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME); $key=$driver==='mysql'?'`key`':'"key"';
        $lang=strtolower((string)($this->query("SELECT value FROM tenant_settings WHERE tenant_id=? AND {$key}=?",[$site,'default_lang'])[0]['value']??'') ?: 'es');
        // Sitio con el idioma fijado (force_lang): solo existe esa versión pública, sin traducciones visibles
        $forced=strtolower(trim((string)($this->query("SELECT value FROM tenant_settings WHERE tenant_id=? AND {$key}=?",[$site,'force_lang'])[0]['value']??'')));
        if (!preg_match('/^[a-z]{2}$/',$forced)) $forced='';
        if ($forced!=='') $lang=$forced;
        // Idiomas: el principal siempre; los demás, solo si están activos en el medio (y no está fijado)
        $others=[];
        if ($forced==='') foreach ($this->query('SELECT code FROM languages WHERE tenant_id=? AND active=1 ORDER BY order_position, code',[$site]) as $l) { $c=strtolower(trim((string)$l['code'])); if ($c!=='' && $c!==$lang && !in_array($c,$others,true)) $others[]=$c; }
        $extra=[];
        foreach ($languages as $code) {
            $code=strtolower(trim((string)$code));
            if ($code==='all') { $extra=$others; break; }
            if ($code===$lang) continue;
            if ($forced!=='') throw new RuntimeException("Este medio tiene el idioma fijado en «{$forced}» (ajuste «forzar idioma»): solo publica en ese idioma y una versión en «{$code}» no se vería. Quita languages/translations, o quita el idioma fijado en el sitio.");
            if (!in_array($code,$others,true)) throw new RuntimeException("El medio no tiene activo el idioma «{$code}». Idiomas del medio: ".implode(', ',array_merge([$lang],$others)).'.');
            if (!in_array($code,$extra,true)) $extra[]=$code;
        }
        if ($extra && !$this->hasColumn('cross_publish_queue','extra_languages')) throw new RuntimeException('Varios idiomas no disponible: falta la migración 2026_11_11_200000.');
        $publishedAt=$src['published_at']?strtotime((string)$src['published_at']):0;
        return ['src'=>$src,'from'=>$from,'lang'=>$lang,'others'=>$others,'extra'=>$extra,'skip'=>$skip,'ready_at'=>$publishedAt+48*3600];
    }
    /** Carga las clases del plugin Cross-Publisher (el cron de aprobaciones MCP no carga plugins). */
    private function crossPublisher(): void
    {
        if (class_exists('\\CrossPublisherAdmin\\Services\\EditorialSummaryService')) return;
        $row=$this->db->prepare("SELECT path,namespace FROM superadmin_plugins WHERE slug='cross-publisher' AND is_active=1 AND is_installed=1");
        $row->execute(); $p=$row->fetch(PDO::FETCH_ASSOC);
        if (!$p || !is_dir((string)$p['path'])) throw new RuntimeException('El plugin Cross-Publisher no está activo.');
        $base=rtrim((string)$p['path'],'/').'/'; $prefix=trim((string)($p['namespace']?:'CrossPublisherAdmin'),'\\').'\\';
        spl_autoload_register(function ($class) use ($base,$prefix) {
            if (strncmp($prefix,$class,strlen($prefix))!==0) return;
            $file=$base.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
            if (is_file($file)) require $file;
        });
        if (!class_exists('\\CrossPublisherAdmin\\Services\\EditorialSummaryService')) throw new RuntimeException('No se pudo cargar el Cross-Publisher.');
    }
    private function sourceObject(int $postId): object
    {
        $s=$this->db->prepare('SELECT * FROM blog_posts WHERE id=?'); $s->execute([$postId]);
        $post=$s->fetch(PDO::FETCH_OBJ); if (!$post) throw new RuntimeException('Artículo original no encontrado.');
        return $post;
    }
    /** get_editorial_brief: lo que necesita la IA conectada para escribir ella el resumen. */
    private function summaryBrief(int $site,array $args): array
    {
        $t=$this->summaryTarget($site,$args,(array)($args['languages']??[]));
        $this->crossPublisher();
        $brief=(new \CrossPublisherAdmin\Services\EditorialSummaryService())->brief($this->sourceObject((int)$t['src']['id']),$site,$t['extra']);
        $brief['timing']=$t['ready_at']>time()
            ? 'El original se publicó hace menos de 48 h: create_editorial_summary exige skip_delay=true, y solo si el usuario confirma que Google ya lo tiene indexado; si no, espera hasta '.gmdate('Y-m-d H:i',$t['ready_at']).' UTC.'
            : 'Listo: han pasado 48 h desde el original.';
        return $brief;
    }
    /** create_editorial_summary: comprueba los textos de la IA conectada antes de pedir aprobación. */
    private function summaryClientPlan(int $site,array $args): array
    {
        $given=$args['translations']??[];
        if (!is_array($given) || ($given && array_is_list($given))) throw new RuntimeException('translations debe ser un objeto {"<código de idioma>": {title, excerpt, content, seo_title, seo_description, seo_keywords}}.');
        if (count($given)>10) throw new RuntimeException('Demasiadas traducciones.');
        $t=$this->summaryTarget($site,$args,array_keys($given));
        if ($t['ready_at']>time() && !$t['skip']) throw new RuntimeException('El original se publicó hace menos de 48 h (listo a partir del '.gmdate('Y-m-d H:i',$t['ready_at']).' UTC). Si el usuario confirma que Google ya lo tiene indexado, repite con skip_delay=true.');
        $texts=['translations'=>[]];
        foreach (['title','excerpt','content','seo_title','seo_description','seo_keywords','category'] as $f) $texts[$f]=(string)($args[$f]??'');
        $texts['tags']=array_values(array_map('strval',(array)($args['tags']??[])));
        $texts['links']=array_values(array_map('strval',(array)($args['links']??[])));
        if (array_key_exists('source_links',$args)) $texts['source_links']=(bool)$args['source_links'];
        foreach ($given as $code=>$tr) {
            $code=strtolower((string)$code); if ($code===$t['lang']) continue;
            if (!is_array($tr)) throw new RuntimeException("La traducción «{$code}» no es válida.");
            foreach (['title','excerpt','content','seo_title','seo_description','seo_keywords'] as $f) { $v=$tr[$f]??''; if (!is_string($v) || strlen($v)>200000) throw new RuntimeException("Campo {$f} no válido en «{$code}»."); $texts['translations'][$code][$f]=$v; }
        }
        // Mismas comprobaciones que al guardar (citas, longitud, enlaces, alfabeto), sin escribir nada
        $this->crossPublisher();
        $svc=new \CrossPublisherAdmin\Services\EditorialSummaryService();
        $svc->checkClientTexts($this->sourceObject((int)$t['src']['id']),$site,$t['lang'],$t['extra'],$texts);
        return ['tool'=>'create_editorial_summary','site_id'=>$site,'fields'=>[],'taxonomy'=>[],
            'source'=>['id'=>(int)$t['src']['id'],'tenant_id'=>$t['from'],'title'=>$t['src']['title'],'published_at'=>$t['src']['published_at'],'interview'=>$this->isInterview((int)$t['src']['id'],(string)$t['src']['content'])],
            'summary'=>['writer'=>'IA conectada por MCP (sin gasto de API del CMS)','target_language'=>$t['lang'],'translations'=>$t['extra'],'skip_delay'=>$t['skip'],'publish'=>!empty($args['publish']),'checks'=>'citas, longitud, enlaces y alfabeto: correctos','texts'=>$texts],
            'result_status'=>!empty($args['publish'])?'published':'draft'];
    }
    private function executeClientSummary(array $plan,int $actor,?array $auth,array $args): array
    {
        $this->crossPublisher();
        $this->db->beginTransaction();
        try {
            if ($auth) {
                if (!$this->actorActive($auth['resource']['id'],$actor,Tools::MAP[$plan['tool']])) throw new RuntimeException('Permiso de aprobación revocado.');
                $currentPlan=$this->plan($plan['tool'],$auth['resource'],$auth['client'],$args);
                if (!hash_equals(Policy::fingerprint($plan),Policy::fingerprint($currentPlan))) throw new RuntimeException('El contenido ha cambiado antes de ejecutar.');
            }
            $s=$plan['summary'];
            [$queueId,$postId]=(new \CrossPublisherAdmin\Services\CrossPublishService())->createFromClient(
                (int)$plan['source']['id'],(int)$plan['source']['tenant_id'],(int)$plan['site_id'],$s['target_language'],$s['translations'],!empty($s['skip_delay']),abs($actor),$s['texts'],!empty($s['publish']));
            $this->db->commit();
            if (!empty($s['publish'])) \CrossPublisherAdmin\Services\EditorialSummaryService::invalidateCache((int)$plan['site_id']);
            return ['post_id'=>$postId,'queue_id'=>$queueId,'site_id'=>$plan['site_id'],'status'=>!empty($s['publish'])?'published':'draft','languages'=>array_merge([$s['target_language']],$s['translations']),
                'next'=>!empty($s['publish'])?'Publicado en el medio (con sus traducciones).':'Borrador creado en el medio (con sus traducciones). Revísalo y publícalo desde su blog.'];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    private function summaryPlan(int $site,array $args): array
    {
        $t=$this->summaryTarget($site,$args,(array)($args['languages']??[]));
        $src=$t['src']; $from=$t['from']; $lang=$t['lang']; $others=$t['others']; $extra=$t['extra']; $skip=$t['skip'];
        $angle=trim(SafeHtml::plain((string)($args['angle']??'')));
        $hours=48; $publishedAt=$src['published_at']?strtotime((string)$src['published_at']):0;
        return ['tool'=>'queue_editorial_summary','site_id'=>$site,'fields'=>[],'taxonomy'=>[],
            'source'=>['id'=>(int)$src['id'],'tenant_id'=>$from,'title'=>$src['title'],'published_at'=>$src['published_at'],'interview'=>$this->isInterview((int)$src['id'],(string)$src['content'])],
            'summary'=>['target_language'=>$lang,'translations'=>$extra,'available_languages'=>array_merge([$lang],$others),'quotes'=>'fieles: literales si el medio escribe en el idioma del original; si no, traducidas palabra por palabra con nota «traducido del …»','angle'=>$angle,'skip_delay'=>$skip,'publish'=>!empty($args['publish']),
                'generates'=>$skip?'en 1–2 minutos (siguiente pasada del cron)':'a partir del '.gmdate('Y-m-d H:i',$publishedAt+$hours*3600).' UTC (48 h tras el original)'],
            'result_status'=>!empty($args['publish'])?'published':'draft'];
    }
    private function executeSummary(array $plan,int $actor,?array $auth,array $args): array
    {
        $this->db->beginTransaction();
        try {
            if ($auth) {
                if (!$this->actorActive($auth['resource']['id'],$actor,Tools::MAP[$plan['tool']])) throw new RuntimeException('Permiso de aprobación revocado.');
                $currentPlan=$this->plan($plan['tool'],$auth['resource'],$auth['client'],$args);
                if (!hash_equals(Policy::fingerprint($plan),Policy::fingerprint($currentPlan))) throw new RuntimeException('El contenido ha cambiado antes de ejecutar.');
            }
            $s=$plan['summary']; $skip=!empty($s['skip_delay']);
            $row=['source_tenant_id'=>$plan['source']['tenant_id'],'source_post_id'=>$plan['source']['id'],'target_tenant_id'=>$plan['site_id'],'status'=>'pending',
                'translate'=>false,'adapt'=>true,'target_language'=>$s['target_language'],'custom_prompt'=>$s['angle']!==''?$s['angle']:null,'target_status'=>!empty($s['publish'])?'published':'draft','created_by'=>abs($actor)];
            if ($skip) $row['skip_delay']=true;
            if (!empty($s['translations'])) $row['extra_languages']=implode(',',$s['translations']);
            $driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver!=='pgsql') foreach (['translate','adapt','skip_delay'] as $k) if (isset($row[$k])) $row[$k]=$row[$k]?1:0;
            $insert=$this->db->prepare('INSERT INTO cross_publish_queue('.implode(',',array_keys($row)).') VALUES('.implode(',',array_fill(0,count($row),'?')).')'.($driver==='pgsql'?' RETURNING id':''));
            foreach (array_values($row) as $i=>$v) $insert->bindValue($i+1,$v,is_bool($v)?PDO::PARAM_BOOL:(is_int($v)?PDO::PARAM_INT:(is_null($v)?PDO::PARAM_NULL:PDO::PARAM_STR)));
            $insert->execute();
            $id=$driver==='pgsql'?(int)$insert->fetchColumn():(int)$this->db->lastInsertId();
            $this->db->commit();
            return ['queue_id'=>$id,'site_id'=>$plan['site_id'],'status'=>'queued','result_status'=>!empty($s['publish'])?'published':'draft','generates'=>$s['generates'],
                'next'=>!empty($s['publish'])?'El Cross-Publisher lo escribirá y lo publicará directamente en el medio. Estado en Cross-Publisher → Cola.':'El Cross-Publisher creará el borrador en el medio; revísalo y publícalo desde su blog. Estado en Cross-Publisher → Cola.'];
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
