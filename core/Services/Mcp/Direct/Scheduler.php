<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use RuntimeException;
use Screenart\Musedock\Env;

/** Posts stay draft until a due, still-authorized job succeeds. */
final class Scheduler
{
    public function __construct(private Store $store,private Content $content)
    {
        $store->db->exec('CREATE TABLE IF NOT EXISTS schedules(id TEXT PRIMARY KEY, resource TEXT NOT NULL, client TEXT NOT NULL, post_id INTEGER NOT NULL, due INTEGER NOT NULL, status TEXT NOT NULL, data TEXT NOT NULL)');
    }
    public function register(array $auth,array $result,int $actor): void
    {
        $post=(new Editor($this->content))->post($result['post_id'],$result['site_id']);
        $this->store->atomic(function () use($auth,$result,$actor,$post) {
            $this->store->run("UPDATE schedules SET status='replaced' WHERE resource=? AND post_id=? AND status='scheduled'",[$auth['resource']['id'],$result['post_id']]);
            $id=bin2hex(random_bytes(24));
            $data=['grant'=>$auth['grant']['id'],'actor'=>$actor,'site_id'=>$result['site_id'],'snapshot'=>Policy::fingerprint($post),'timezone'=>date_default_timezone_get()];
            $this->store->run('INSERT INTO schedules(id,resource,client,post_id,due,status,data) VALUES(?,?,?,?,?,\'scheduled\',?)',[$id,$auth['resource']['id'],$auth['client']['id'],$result['post_id'],strtotime($post['published_at']),Store::json($data)]);
            $this->store->event($auth['resource']['id'],'publication.scheduled',$actor,['post_id'=>$result['post_id'],'schedule'=>$id]);
        });
    }
    public function run(?int $now=null): array
    {
        if (!filter_var(Env::get('MCP_CMS_DIRECT_ENABLED',false),FILTER_VALIDATE_BOOLEAN) || !filter_var(Env::get('MCP_ENABLED',true),FILTER_VALIDATE_BOOLEAN)) return ['published'=>0,'cancelled'=>0];
        $now=$now??time();$out=['published'=>0,'cancelled'=>0];
        foreach ($this->store->all("SELECT * FROM schedules WHERE status='scheduled' AND due<=? ORDER BY due LIMIT 50",[$now]) as $job) {
            $this->store->atomic(function () use($job,$now,&$out) {
                if ($this->store->run("UPDATE schedules SET status='executing' WHERE id=? AND status='scheduled'",[$job['id']])!==1) return;
                $data=$this->store->decode($job);$oldZone=date_default_timezone_get();
                try {
                    date_default_timezone_set($data['timezone']);
                    $r=$this->store->resource($job['resource']);$c=$this->store->client($job['client']);$g=$this->store->grant($data['grant']);
                    if (!$r || !$c || !$g || !Policy::active($r,$c,$g) || empty($r['allow_publishing']) || empty($r['allow_drafts']) || !Policy::allows($c,$g,Policy::POSTS) || !Endpoint::installed($r['id'])) throw new RuntimeException('Programación revocada.');
                    $this->content->assertSite($r,$c,$data['site_id']);
                    $post=(new Editor($this->content))->post($job['post_id'],$data['site_id']);
                    if ($post['status']!=='draft' || !hash_equals($data['snapshot'],Policy::fingerprint($post))) throw new RuntimeException('Artículo modificado después de programarlo.');
                    $args=['post_id'=>(int)$job['post_id'],'site_id'=>$data['site_id']];$auth=['resource'=>$r,'client'=>$c,'grant'=>$g];
                    $plan=$this->content->plan('publish_post',$r,$c,$args);
                    $this->content->execute($plan,$data['actor'],$auth,$args);
                    $this->store->run("UPDATE schedules SET status='published' WHERE id=?",[$job['id']]);$out['published']++;
                    $this->store->event($r['id'],'publication.completed',$data['actor'],['post_id'=>$job['post_id'],'schedule'=>$job['id']]);
                } catch (\Throwable $e) {
                    $this->store->run("UPDATE schedules SET status='cancelled' WHERE id=?",[$job['id']]);$out['cancelled']++;
                    $this->store->event($job['resource'],'publication.cancelled',$data['actor']??0,['post_id'=>$job['post_id'],'schedule'=>$job['id']]);
                } finally { date_default_timezone_set($oldZone); }
            });
        }
        return $out;
    }
}
