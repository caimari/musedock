<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use PDO;
use RuntimeException;
use Screenart\Musedock\SafeHtml;

/** Explicit editor operations. Every identifier is independently scoped to a site. */
final class Editor
{
    public const MAP = [
        'get_post_details'=>Policy::READ,
        'create_post'=>Policy::EDITOR,'update_post'=>Policy::EDITOR,'set_featured_image'=>Policy::EDITOR,'publish_post'=>Policy::POSTS,'schedule_post'=>Policy::POSTS,
        'create_category'=>Policy::TAXONOMY,'update_category'=>Policy::TAXONOMY,'create_tag'=>Policy::TAXONOMY,'update_tag'=>Policy::TAXONOMY,
        'list_comments'=>Policy::COMMENTS,'moderate_comment'=>Policy::COMMENTS,
        'list_translations'=>Policy::TRANSLATIONS,'save_translation'=>Policy::TRANSLATIONS,
        'list_media'=>Policy::MEDIA,'list_image_options'=>Policy::MEDIA,'list_image_prompts'=>Policy::MEDIA,'upload_image'=>Policy::MEDIA,'generate_image'=>Policy::MEDIA,
    ];
    public function __construct(private Content $content) {}
    public static function schema(string $tool): array
    {
        $s=['type'=>'string']; $id=['type'=>'integer','minimum'=>1];
        $p=['site_id'=>['type'=>'integer','minimum'=>0]];
        if (in_array($tool,['get_post_details','update_post','publish_post','schedule_post','list_translations','save_translation'],true)) $p['post_id']=$id;
        if (in_array($tool,['create_post','update_post'],true)) {
            foreach (['title','content','excerpt','slug','base_locale','password','featured_image','template','post_type','visibility','status','published_at','seo_title','seo_description','seo_keywords','canonical_url','robots_directive','seo_image','twitter_title','twitter_description','twitter_image','hero_image'] as $f) $p[$f]=$s;
            foreach (['hide_featured_image','featured','allow_comments','show_hero','hide_title'] as $f) $p[$f]=['type'=>'boolean'];
            $p['featured_image_id']=$id;
            $p['status']['enum']=['draft','published']; $p['visibility']['enum']=['public','private','password'];
            $p['post_type']['enum']=['post','brief','docs'];
            $p['template']['enum']=['template-sidebar-right','page','template-sidebar-left'];
            $p['robots_directive']['enum']=['index,follow','noindex,follow','index,nofollow','noindex,nofollow'];
        }
        if ($tool==='set_featured_image') { $p['post_id']=$id; $p['media_id']=$id; }
        if (in_array($tool,['publish_post','schedule_post'],true)) $p['published_at']=$s;
        if (str_contains($tool,'category') || str_contains($tool,'tag')) {
            foreach (['name','slug','description','color'] as $f) $p[$f]=$s;
            if (str_starts_with($tool,'update_')) $p['taxonomy_id']=$id;
            if (str_contains($tool,'category')) { $p['parent_id']=['type'=>'integer','minimum'=>0]; foreach (['seo_title','seo_description','seo_keywords','image'] as $f) $p[$f]=$s; $p['order']=['type'=>'integer','minimum'=>0,'maximum'=>100000]; }
        }
        if ($tool==='moderate_comment') { $p['comment_id']=$id; $p['status']=['type'=>'string','enum'=>['pending','approved','spam','delete']]; }
        if ($tool==='list_comments') { $p['post_id']=$id; $p['status']=['type'=>'string','enum'=>['pending','approved','spam']]; }
        if ($tool==='save_translation') { foreach (['locale','title','content','excerpt','seo_title','seo_description','seo_keywords','seo_image','canonical_url','robots_directive','twitter_title','twitter_description','twitter_image'] as $f) $p[$f]=$s; }
        if (in_array($tool,['list_comments','list_media','list_image_prompts'],true)) { $p['limit']=['type'=>'integer','minimum'=>1,'maximum'=>50]; $p['offset']=['type'=>'integer','minimum'=>0,'maximum'=>1000000]; }
        if ($tool==='upload_image') { $p['url']=$s; $p['base64']=['type'=>'string','maxLength'=>2800000]; $p['filename']=['type'=>'string','maxLength'=>100]; $p['alt_text']=['type'=>'string','maxLength'=>250]; }
        if ($tool==='generate_image') { $p['prompt']=['type'=>'string','minLength'=>1,'maxLength'=>4000]; $p['size']=['type'=>'string','maxLength'=>9,'pattern'=>'^[0-9]{3,4}x[0-9]{3,4}$','description'=>'Consulta list_image_options. MiniMax admite dimensiones explícitas entre 512 y 2048 px, múltiplos de 8. Otros proveedores tienen sus propios límites.']; $p['quality']=['type'=>'string','enum'=>['standard','hd']]; $p['alt_text']=['type'=>'string','maxLength'=>250]; }
        return $p;
    }
    public static function required(string $tool): array
    {
        return match($tool) {
            'create_post'=>['title','content'],
            'set_featured_image'=>['post_id','media_id'],
            'get_post_details','update_post','publish_post','list_translations'=>['post_id'],
            'schedule_post'=>['post_id','published_at'],
            'create_category','create_tag'=>['name'],
            'update_category','update_tag'=>['taxonomy_id'],
            'moderate_comment'=>['comment_id','status'],
            'save_translation'=>['post_id','locale','title','content'],
            'generate_image'=>['prompt'],default=>[],
        };
    }
    private function q(string $sql,array $p=[]): array { return $this->content->query($sql,$p); }
    private function object(string $table,int $id,int $site): array
    {
        $row=$this->q("SELECT * FROM {$table} WHERE id=? AND COALESCE(tenant_id,0)=?",[$id,$site])[0]??null;
        if (!$row || ($table==='blog_posts' && !empty($row['deleted_at'])) || (in_array($table,['blog_categories','blog_tags'],true) && !empty($row['deleted_at']))) throw new RuntimeException('Objeto no disponible en este sitio.');
        if ($table==='blog_posts') { $row['password_set']=!empty($row['password']); $row['password_revision']=hash('sha256',(string)($row['password']??'')); unset($row['password']); }
        return $row;
    }
    public function post(int $id,int $site): array { return $this->object('blog_posts',$id,$site); }
    public function read(string $tool,array $r,array $c,array $args): array
    {
        $site=$this->content->siteArg($r,$c,$args); $limit=$args['limit']??20; $offset=$args['offset']??0;
        if ($tool==='get_post_details') {
            $post=$this->post($args['post_id'],$site); unset($post['password_revision']);
            foreach (['categories'=>'category_id','tags'=>'tag_id'] as $kind=>$col) $post[$kind]=$this->q("SELECT {$col} FROM blog_post_{$kind} WHERE post_id=?",[$post['id']]);
            return $post;
        }
        if ($tool==='list_translations') { $this->post($args['post_id'],$site); return $this->q('SELECT locale,title,content,excerpt,seo_title,seo_description,seo_keywords,seo_image,canonical_url,robots_directive,twitter_title,twitter_description,twitter_image,updated_at FROM blog_post_translations WHERE post_id=? AND COALESCE(tenant_id,0)=?',[$args['post_id'],$site]); }
        if ($tool==='list_comments') {
            $where='COALESCE(tenant_id,0)=?'; $p=[$site];
            if (isset($args['post_id'])) { $this->post($args['post_id'],$site); $where.=' AND post_id=?';$p[]=$args['post_id']; }
            if (isset($args['status'])) { $where.=' AND status=?'; $p[]=$args['status']; }
            // Email, IP and user-agent are deliberately not needed for moderation.
            return $this->q("SELECT id,post_id,author_name,content,status,created_at,updated_at FROM blog_comments WHERE {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",$p);
        }
        if ($tool==='list_image_options') return (new Media($this->content))->options($site);
        if ($tool==='list_image_prompts') return (new Media($this->content))->prompts($site,$limit,$offset);
        if ($tool==='list_media') return (new Media($this->content))->listing($site,$limit,$offset);
        throw new RuntimeException('Consulta no autorizada.');
    }
    private function text(string $value,int $max=200000): string
    {
        if (mb_strlen($value)>$max) throw new RuntimeException('Texto demasiado largo.');
        return SafeHtml::plain($value);
    }
    private function url(string $value): string
    {
        if ($value==='') return '';
        if (strlen($value)>500) throw new RuntimeException('URL demasiado larga.');
        if (str_starts_with($value,'/') && !str_starts_with($value,'//') && !preg_match('/[\\\\\x00-\x20]/',$value) && !str_contains($value,'..')) return $value;
        $p=parse_url($value);
        if (!$p || !in_array($p['scheme']??'',['https','http'],true) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || preg_match('/[\x00-\x20\\\\]/',$value) || strlen($value)>500) throw new RuntimeException('URL no válida.');
        return $value;
    }
    private function slug(string $s): string
    {
        $v=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);return substr(trim(preg_replace('/[^a-z0-9]+/','-',strtolower($v?:$s)),'-'),0,180)?:'articulo';
    }
    private function locale(string $locale): string
    {
        if (!preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/D',$locale)) throw new RuntimeException('Idioma no válido.');
        if (!$this->q('SELECT code FROM languages WHERE code=? AND active=1',[$locale])) throw new RuntimeException('Idioma no habilitado en el CMS.');
        return $locale;
    }
    public static function date(string $date): string
    {
        // ISO 8601 includes the operator's intended timezone. Stored dates use CMS timezone.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})$/D',$date)) throw new RuntimeException('Indica una fecha ISO 8601 con zona horaria.');
        $d=new \DateTimeImmutable($date); $errors=\DateTimeImmutable::getLastErrors();
        if ($errors && ($errors['warning_count'] || $errors['error_count'])) throw new RuntimeException('Fecha no válida.');
        return $d->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }
    private function postFields(array $args,int $site,?array $source): array
    {
        $f=[];
        foreach (['title','excerpt','seo_title','seo_description','seo_keywords','twitter_title','twitter_description'] as $key) if (isset($args[$key])) $f[$key]=$this->text($args[$key],in_array($key,['title','seo_title','twitter_title'],true)?255:200000);
        if (isset($args['title']) && trim($f['title'])==='') throw new RuntimeException('El título no puede estar vacío.');
        if (isset($args['content'])) $f['content']=SafeHtml::clean($args['content']);
        foreach (['featured_image','seo_image','twitter_image','hero_image','canonical_url'] as $key) if (isset($args[$key])) $f[$key]=$this->url($args[$key]);
        foreach (['status'=>['draft','published'],'visibility'=>['public','private','password'],'post_type'=>['post','brief','docs'],'template'=>['template-sidebar-right','page','template-sidebar-left'],'robots_directive'=>['index,follow','noindex,follow','index,nofollow','noindex,nofollow']] as $key=>$values) if (isset($args[$key])) { if (!in_array($args[$key],$values,true)) throw new RuntimeException('Opción no válida.'); $f[$key]=$args[$key]; }
        foreach (['hide_featured_image','featured','allow_comments','show_hero','hide_title'] as $key) if (isset($args[$key])) $f[$key]=$args[$key]?1:0;
        if (isset($args['base_locale'])) $f['base_locale']=$this->locale($args['base_locale']);
        if (isset($args['slug'])) { if (!preg_match('/^[a-z0-9][a-z0-9-]{0,199}$/D',$args['slug'])) throw new RuntimeException('Slug no válido.'); $f['slug']=$args['slug']; }
        if (isset($args['published_at'])) $f['published_at']=$args['published_at']===''?null:self::date($args['published_at']);
        if (isset($args['password'])) { if (strlen($args['password'])<8 || strlen($args['password'])>200) throw new RuntimeException('La contraseña debe tener entre 8 y 200 caracteres.'); $f['password_change']=hash('sha256',$args['password']); }
        if (isset($args['password_hash'])) $f['password_change']=hash('sha256',$args['password_hash']);
        if (($f['visibility']??$source['visibility']??'public')==='password' && empty($f['password_change']) && empty($source['password_set'])) throw new RuntimeException('Indica una contraseña para proteger el artículo.');
        if (isset($args['featured_image_id'])) $f['featured_image']=(new Media($this->content))->one($args['featured_image_id'],$site)['url'];
        if (isset($args['featured_image_id'],$args['featured_image'])) throw new RuntimeException('Elige featured_image_id o featured_image, no ambos.');
        if (($f['status']??$source['status']??'draft')==='published' && !empty($f['published_at']) && strtotime($f['published_at'])>time()) throw new RuntimeException('Usa schedule_post para una publicación futura.');
        return $f;
    }
    private function taxonomy(int $site,array $args): array
    {
        $out=[];
        foreach (['category_ids'=>'blog_categories','tag_ids'=>'blog_tags'] as $key=>$table) if (isset($args[$key])) { foreach ($args[$key] as $id) $this->object($table,$id,$site); $out[$key]=array_values(array_unique($args[$key])); }
        return $out;
    }
    public function plan(string $tool,array $r,array $c,array $args): array
    {
        $site=$this->content->siteArg($r,$c,$args); $source=null; $fields=[]; $tax=[];
        if (in_array($tool,['create_post','update_post','set_featured_image','publish_post','schedule_post'],true)) {
            if ($tool!=='create_post') $source=$this->post($args['post_id'],$site);
            $fields=$tool==='set_featured_image'?['featured_image'=>(new Media($this->content))->one($args['media_id'],$site)['url']]:$this->postFields($args,$site,$source); $tax=$this->taxonomy($site,$args);
            if ($tool==='create_post' && (empty($fields['title']) || empty($fields['content']))) throw new RuntimeException('Indica título y contenido.');
            if ($tool==='publish_post') { $fields['status']='published'; $fields['published_at']=$fields['published_at']??$source['published_at']??null; if (!empty($fields['published_at']) && strtotime($fields['published_at'])>time()) throw new RuntimeException('Usa schedule_post.'); }
            if ($tool==='schedule_post') {
                $fields=['status'=>'draft','published_at'=>self::date($args['published_at'])];
                if (strtotime($fields['published_at'])<=time()+60) throw new RuntimeException('La programación debe ser al menos un minuto en el futuro.');
                if ($source['status']==='published') throw new RuntimeException('No puedes reprogramar un artículo ya publicado.');
            }
            if (($r['require_featured_image']??true) && ($tool==='schedule_post' || (($fields['status']??$source['status']??'draft')==='published' && ($source['status']??'draft')!=='published'))) (new Media($this->content))->assertPublicationImage($site,$fields['featured_image']??$source['featured_image']??null);
            if ($tool==='update_post' && !$fields && !$tax) throw new RuntimeException('No hay cambios.');
            if (isset($fields['slug']) && $this->q('SELECT id FROM blog_posts WHERE COALESCE(tenant_id,0)=? AND slug=? AND id<>?',[$site,$fields['slug'],$source['id']??0])) throw new RuntimeException('El slug ya está en uso.');
        } elseif (str_contains($tool,'category') || str_contains($tool,'tag')) {
            $table=str_contains($tool,'category')?'blog_categories':'blog_tags';
            if (str_starts_with($tool,'update_')) $source=$this->object($table,$args['taxonomy_id'],$site);
            foreach (['name','slug','description','color','seo_title','seo_description','seo_keywords','image','order','parent_id'] as $key) if (isset($args[$key])) {
                $max=match($key){'name'=>$table==='blog_tags'?100:255,'slug'=>$table==='blog_tags'?150:200,'seo_title'=>500,default=>200000};
                $fields[$key]=is_int($args[$key])?$args[$key]:$this->text($args[$key],$max);
            }
            if (isset($fields['name']) && trim($fields['name'])==='') throw new RuntimeException('Nombre vacío.');
            if (!$source) $fields['slug']=$fields['slug']??substr($this->slug($fields['name']),0,$table==='blog_tags'?150:180);
            if (isset($fields['slug']) && !preg_match('/^[a-z0-9][a-z0-9-]{0,199}$/D',$fields['slug'])) throw new RuntimeException('Slug no válido.');
            if (isset($fields['color']) && $fields['color']!=='' && !preg_match('/^#[a-f0-9]{6}$/iD',$fields['color'])) throw new RuntimeException('Color no válido.');
            if (isset($fields['image'])) $fields['image']=$this->url($fields['image']);
            if (isset($fields['parent_id'])) {
                $parent=$fields['parent_id']; $visited=[$source['id']??0];
                while ($parent) { if (in_array($parent,$visited,true)) throw new RuntimeException('Una categoría no puede ser su propia ascendiente.'); $visited[]=$parent; $parent=(int)($this->object('blog_categories',$parent,$site)['parent_id']??0); }
                $fields['parent_id']=$fields['parent_id']?:null;
            }
            if (isset($fields['slug']) && $this->q("SELECT id FROM {$table} WHERE COALESCE(tenant_id,0)=? AND slug=? AND id<>?",[$site,$fields['slug'],$source['id']??0])) throw new RuntimeException('Slug de taxonomía duplicado.');
            if (!$fields) throw new RuntimeException('No hay cambios.');
        } elseif ($tool==='moderate_comment') {
            $source=$this->object('blog_comments',$args['comment_id'],$site); $this->post((int)$source['post_id'],$site);
            unset($source['author_email'],$source['ip_address'],$source['user_agent']); $fields=['status'=>$args['status']];
        } elseif ($tool==='save_translation') {
            $post=$this->post($args['post_id'],$site);
            if ($post['status']==='published' && (empty($r['allow_publishing']) || !in_array(Policy::POSTS,$c['scopes'],true))) throw new RuntimeException('Editar una traducción publicada requiere permiso de publicación.');
            $locale=$this->locale($args['locale']); if ($locale===$post['base_locale']) throw new RuntimeException('Edita el artículo base para su idioma original.');
            $translation=$this->q('SELECT * FROM blog_post_translations WHERE post_id=? AND locale=? AND COALESCE(tenant_id,0)=?',[$post['id'],$locale,$site])[0]??null;
            $source=['post'=>$post,'translation'=>$translation]; $fields=['locale'=>$locale];
            foreach (['title','excerpt','seo_title','seo_description','seo_keywords'] as $key) if (isset($args[$key])) $fields[$key]=$this->text($args[$key],in_array($key,['title','seo_title'],true)?255:200000);
            foreach (['seo_image','canonical_url','twitter_image'] as $key) if (isset($args[$key])) $fields[$key]=$this->url($args[$key]);
            foreach (['twitter_title','twitter_description'] as $key) if (isset($args[$key])) $fields[$key]=$this->text($args[$key],$key==='twitter_title'?255:200000);
            if (isset($args['robots_directive'])) { if (!in_array($args['robots_directive'],['index,follow','noindex,follow','index,nofollow','noindex,nofollow'],true)) throw new RuntimeException('Directiva de robots no válida.'); $fields['robots_directive']=$args['robots_directive']; }
            $fields['content']=SafeHtml::clean($args['content']);
            if (trim($fields['title'])==='' || trim($fields['content'])==='') throw new RuntimeException('La traducción necesita título y contenido.');
        } elseif (in_array($tool,['upload_image','generate_image'],true)) $fields=(new Media($this->content))->plan($tool,$site,$args);
        else throw new RuntimeException('Operación no autorizada.');
        return ['tool'=>$tool,'site_id'=>$site,'fields'=>$fields,'taxonomy'=>$tax,'source'=>$source,'result_status'=>$tool==='schedule_post'?'scheduled':($fields['status']??'saved')];
    }
    private function quote(string $name): string { return $this->content->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'`'.$name.'`':'"'.$name.'"'; }
    private function insert(string $table,array $row): int
    {
        $p=$this->content->db; $pg=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql';
        $s=$p->prepare('INSERT INTO '.$table.'('.implode(',',array_map(fn($k)=>$this->quote($k),array_keys($row))).') VALUES('.implode(',',array_fill(0,count($row),'?')).')'.($pg?' RETURNING id':''));$s->execute(array_values($row));return $pg?(int)$s->fetchColumn():(int)$p->lastInsertId();
    }
    private function update(string $table,int $id,array $row): void
    {
        $this->content->db->prepare('UPDATE '.$table.' SET '.implode(',',array_map(fn($k)=>$this->quote($k).'=?',array_keys($row))).' WHERE id=?')->execute(array_merge(array_values($row),[$id]));
    }
    public function execute(array $plan,int $actor,?array $auth,array $args): array
    {
        if (!$auth) throw new RuntimeException('Consentimiento requerido.');
        $db=$this->content->db; $site=$plan['site_id']; $tool=$plan['tool']; $now=date('Y-m-d H:i:s');
        $db->beginTransaction();
        try {
            if (!$this->content->actorActive($auth['resource']['id'],$actor,self::MAP[$tool])) throw new RuntimeException('Permiso revocado.');
            $live=in_array($plan['tool'],['publish_post','schedule_post'],true) || (in_array($plan['tool'],['create_post','update_post','set_featured_image'],true) && (($plan['fields']['status']??'')==='published' || ($plan['source']['status']??'')==='published'));
            if ($live && (empty($auth['resource']['allow_publishing']) || !Policy::allows($auth['client'],$auth['grant'],Policy::POSTS) || !$this->content->actorActive($auth['resource']['id'],$actor,Policy::POSTS))) throw new RuntimeException('Publicación no autorizada.');
            if ($plan['tool']==='save_translation' && ($plan['source']['post']['status']??'')==='published' && (!Policy::allows($auth['client'],$auth['grant'],Policy::POSTS) || !$this->content->actorActive($auth['resource']['id'],$actor,Policy::POSTS))) throw new RuntimeException('Consentimiento de publicación requerido.');
            $current=$this->plan($tool,$auth['resource'],$auth['client'],$args);
            if (!hash_equals(Policy::fingerprint($plan),Policy::fingerprint($current))) throw new RuntimeException('El contenido ha cambiado; prepara otra solicitud.');
            $f=$plan['fields']; $source=$plan['source']; $id=0;
            if (in_array($tool,['create_post','update_post','set_featured_image','publish_post','schedule_post'],true)) {
                if (isset($f['password_change'])) { unset($f['password_change']);$f['password']=$args['password_hash']??password_hash($args['password'],PASSWORD_DEFAULT); }
                if (($f['visibility']??$source['visibility']??'public')!=='password') $f['password']=null;
                $f['updated_at']=$now;
                if (($f['status']??$source['status']??'draft')==='published' && ((array_key_exists('published_at',$f) && $f['published_at']===null) || (empty($f['published_at']) && empty($source['published_at'])))) $f['published_at']=$now;
                if ($source) { $id=(int)$source['id']; $this->update('blog_posts',$id,$f); }
                else {
                    $slug=$f['slug']??$this->slug($f['title']); $base=$slug; $n=1;
                    while ($this->q('SELECT id FROM blog_posts WHERE COALESCE(tenant_id,0)=? AND slug=?',[$site,$slug])) $slug=$base.'-'.++$n;
                    $f+=['slug'=>$slug,'tenant_id'=>$site?:null,'user_id'=>abs($actor),'user_type'=>str_starts_with($auth['resource']['id'],'tenant:')?($actor>0?'admin':'user'):'superadmin','status'=>'draft','visibility'=>'public','base_locale'=>'es','created_at'=>$now];
                    $id=$this->insert('blog_posts',$f);
                }
                if (!$source || isset($f['slug'])) {
                    $db->prepare("DELETE FROM slugs WHERE module='blog' AND reference_id=?")->execute([$id]);
                    $key=$this->quote('key'); $prefix=$site?($this->q("SELECT value FROM tenant_settings WHERE tenant_id=? AND {$key}=?",[$site,'blog_url_prefix'])[0]['value']??'blog'):(function_exists('setting')?setting('blog_url_prefix','blog'):'blog');
                    $this->insert('slugs',['module'=>'blog','reference_id'=>$id,'slug'=>$f['slug']??$source['slug'],'tenant_id'=>$site?:null,'prefix'=>$prefix?:null]);
                }
                foreach ($plan['taxonomy'] as $key=>$ids) {
                    $kind=$key==='category_ids'?'categories':'tags';$col=$kind==='categories'?'category_id':'tag_id';
                    $old=array_column($this->q("SELECT {$col} FROM blog_post_{$kind} WHERE post_id=?",[$id]),$col);
                    $db->prepare("DELETE FROM blog_post_{$kind} WHERE post_id=?")->execute([$id]);
                    foreach ($ids as $taxId) $db->prepare("INSERT INTO blog_post_{$kind}(post_id,{$col}) VALUES(?,?)")->execute([$id,$taxId]);
                    foreach (array_unique(array_merge($old,$ids)) as $taxId) $db->prepare("UPDATE blog_{$kind} SET post_count=(SELECT COUNT(*) FROM blog_post_{$kind} WHERE {$col}=?) WHERE id=?")->execute([$taxId,$taxId]);
                }
            } elseif (str_contains($tool,'category') || str_contains($tool,'tag')) {
                $table=str_contains($tool,'category')?'blog_categories':'blog_tags'; $f['updated_at']=$now;
                if ($source) { $id=(int)$source['id'];$this->update($table,$id,$f); }
                else $id=$this->insert($table,$f+['tenant_id'=>$site?:null,'created_at'=>$now]);
            } elseif ($tool==='moderate_comment') {
                $id=(int)$source['id'];
                if ($f['status']==='delete') $db->prepare('DELETE FROM blog_comments WHERE id=?')->execute([$id]);
                else { if ($f['status']==='approved') $f+=['approved_at'=>$now,'approved_by'=>abs($actor),'approved_by_type'=>str_starts_with($auth['resource']['id'],'tenant:')?($actor>0?'admin':'user'):'superadmin']; $this->update('blog_comments',$id,$f+['updated_at'=>$now]); }
                $db->prepare("UPDATE blog_posts SET comment_count=(SELECT COUNT(*) FROM blog_comments WHERE post_id=? AND status='approved') WHERE id=?")->execute([$source['post_id'],$source['post_id']]);
            } elseif ($tool==='save_translation') {
                $id=(int)$source['post']['id']; $f['updated_at']=$now;
                if ($source['translation']) $this->update('blog_post_translations',(int)$source['translation']['id'],$f);
                else $this->insert('blog_post_translations',$f+['post_id'=>$id,'tenant_id'=>$site?:null,'created_at'=>$now]);
            } else {
                // External work cannot be retried and is consumed before execution by Tools.
                $db->commit();
                return (new Media($this->content))->execute($tool,$site,$actor,$args);
            }
            $db->commit();
            $cache=\Screenart\Musedock\Cache\HtmlCache::class;
            {
                $cache::invalidateByTag('blog',$site?:null);$cache::invalidateByTag('blog-list',$site?:null);$cache::invalidate('/',$site?:null);
                if (in_array($tool,['create_post','update_post','set_featured_image','publish_post','schedule_post'],true)) { if ($source) $cache::onPostSaved($source,$site?:null,false); $cache::onPostSaved($this->post($id,$site),$site?:null,false); }
            }
            return ['id'=>$id,'post_id'=>in_array($tool,['create_post','update_post','set_featured_image','publish_post','schedule_post','save_translation'],true)?$id:null,'site_id'=>$site,'status'=>$tool==='schedule_post'?'scheduled':($f['status']??$source['status']??'saved')];
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
