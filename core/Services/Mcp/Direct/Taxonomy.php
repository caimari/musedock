<?php
namespace Screenart\Musedock\Services\Mcp\Direct;
use PDO;
use RuntimeException;

/** Site-local term resolution; creation is locked and shares the editor transaction. */
final class Taxonomy
{
    public const DEFAULTS=['allow_categories'=>true,'allow_tags'=>true,'max_categories'=>3,'max_tags'=>8,'allowed_categories'=>[],'allowed_tags'=>[],'aliases'=>[]];
    public function __construct(private Content $content) {}
    public static function normalize(string $name): string
    {
        $name=mb_strtolower(html_entity_decode(trim(strip_tags($name)),ENT_QUOTES,'UTF-8'));
        $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);
        return trim(preg_replace('/[^a-z0-9]+/',' ',strtolower($ascii===false?$name:$ascii)));
    }
    public function policy(int $site): array
    {
        $r=$this->content->query('SELECT data FROM mcp_taxonomy_policies WHERE site_id=?',[$site])[0]??null;
        return array_replace(self::DEFAULTS,$r?json_decode($r['data'],true)??[]:[]);
    }
    private function canonical(string $name,array $policy): string
    {
        $name=\Screenart\Musedock\SafeHtml::plain($name);
        foreach ($policy['aliases'] as $alias=>$target) if (self::normalize($alias)===self::normalize($name)) return \Screenart\Musedock\SafeHtml::plain($target);
        return trim($name);
    }
    public function find(int $site,string $kind,string $name): ?array
    {
        $policy=$this->policy($site);$key=self::normalize($this->canonical($name,$policy));
        foreach ($this->content->query("SELECT id,name,slug FROM blog_{$kind} WHERE COALESCE(tenant_id,0)=? AND deleted_at IS NULL ORDER BY id",[$site]) as $row) {
            if (self::normalize($row['name'])===$key) return $row;
        }
        return null;
    }
    public function prepare(int $site,array $args): array
    {
        $policy=$this->policy($site);$out=[];
        foreach (['categories'=>['category_ids','category_names'],'tags'=>['tag_ids','tag_names']] as $kind=>[$ids,$names]) {
            if (!isset($args[$ids]) && !isset($args[$names])) continue;
            $out[$ids]=array_values(array_unique($args[$ids]??[]));$seen=[];$new=0;
            foreach ($args[$names]??[] as $name) {
                $name=$this->canonical($name,$policy);$key=self::normalize($name);
                if (!$key || mb_strlen($name)>($kind==='tags'?100:255)) throw new RuntimeException('Nombre de taxonomía no válido.');
                if (isset($seen[$key])) continue;$seen[$key]=true;
                if (!$this->find($site,$kind,$name)) {
                    if (empty($args['create_missing_terms'])) throw new RuntimeException('No existe el término: '.$name.'. Usa create_missing_terms para crearlo.');
                    if (!$policy['allow_'.$kind] || ++$new>$policy['max_'.$kind]) throw new RuntimeException('La política editorial limita la creación de '.$kind.'.');
                    $allowed=$policy['allowed_'.$kind];
                    if ($allowed && !in_array($key,array_map([self::class,'normalize'],$allowed),true)) throw new RuntimeException('Término fuera de la lista editorial permitida: '.$name);
                }
                // Stable before/after another request creates the term; no read-time CMS writes.
                $out[$ids][]=['name'=>$name];
            }
        }
        return $out;
    }
    public function missing(int $site,array $tax): bool
    {
        foreach ($tax as $key=>$terms) foreach ($terms as $term) if (is_array($term) && !$this->find($site,$key==='category_ids'?'categories':'tags',$term['name'])) return true;
        return false;
    }
    public function lock(int $site): void
    {
        $db=$this->content->db;$mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $sql=$mysql?'INSERT IGNORE INTO mcp_taxonomy_policies(site_id,data) VALUES(?,?)':'INSERT INTO mcp_taxonomy_policies(site_id,data) VALUES(?,?) ON CONFLICT(site_id) DO NOTHING';
        $db->prepare($sql)->execute([$site,'{}']);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite') $this->content->query('SELECT site_id FROM mcp_taxonomy_policies WHERE site_id=? FOR UPDATE',[$site]);
    }
    public function resolve(int $site,array $tax,array $auth): array
    {
        if (!$tax) return [];
        $this->lock($site);$out=[];
        $check=['create_missing_terms'=>true];
        foreach ($tax as $key=>$terms) foreach ($terms as $term) if (is_array($term)) $check[$key==='category_ids'?'category_names':'tag_names'][]=$term['name'];
        $this->prepare($site,$check);
        foreach ($tax as $key=>$terms) {
            $kind=$key==='category_ids'?'categories':'tags';$out[$key]=[];
            foreach ($terms as $term) {
                if (is_int($term)) { $out[$key][]=$term;continue; }
                $existing=$this->find($site,$kind,$term['name']);
                if (!$existing) {
                    if (!Policy::allows($auth['client'],$auth['grant'],Policy::TAXONOMY) || !$this->content->actorActive($auth['resource']['id'],$auth['grant']['actor'],Policy::TAXONOMY)) throw new RuntimeException('Crear términos requiere permiso de taxonomías.');
                    // Inline insert within the SAME editor transaction, not a nested editorial operation.
                    $slug=str_replace(' ','-',self::normalize($term['name']));
                    $max=$kind==='tags'?150:180; if (strlen($slug)>$max) $slug=substr($slug,0,$max-9).'-'.substr(hash('sha256',$slug),0,8);$now=date('Y-m-d H:i:s');$db=$this->content->db;
                    if ($this->content->query("SELECT id FROM blog_{$kind} WHERE COALESCE(tenant_id,0)=? AND slug=? AND deleted_at IS NULL",[$site,$slug])) throw new RuntimeException('El slug de este término ya pertenece a otro nombre; configura una equivalencia editorial.');
                    $sql="INSERT INTO blog_{$kind}(tenant_id,name,slug,created_at,updated_at) VALUES(?,?,?,?,?)".($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql'?' RETURNING id':'');
                    $q=$db->prepare($sql);$q->execute([$site?:null,$term['name'],$slug,$now,$now]);
                    $existing=['id'=>$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql'?(int)$q->fetchColumn():(int)$db->lastInsertId()];
                }
                $out[$key][]=(int)$existing['id'];
            }
            $out[$key]=array_values(array_unique($out[$key]));
        }
        return $out;
    }
    public function assigned(int $site,int $post): array
    {
        $out=[];
        foreach (['categories'=>'category_id','tags'=>'tag_id'] as $kind=>$column) $out[$kind]=$this->content->query("SELECT t.id,t.name,t.slug FROM blog_{$kind} t JOIN blog_post_{$kind} p ON p.{$column}=t.id WHERE p.post_id=? AND COALESCE(t.tenant_id,0)=? ORDER BY t.id",[$post,$site]);
        return $out;
    }
}
