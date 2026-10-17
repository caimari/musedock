<?php
namespace Modules\SearchConsole\Services;

use PDO;
use RuntimeException;

/** Master-controlled, change-only Google sitemap submissions, isolated by host. */
final class SitemapDispatchService
{
    public const INTERVAL=28800;
    private $fetch;
    private $submit;
    public function __construct(private PDO $pdo, ?callable $fetch=null, ?callable $submit=null)
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_sitemap_dispatch (host VARCHAR(253) PRIMARY KEY, checked_at BIGINT NOT NULL DEFAULT 0, sent_at BIGINT NOT NULL DEFAULT 0, accepted_hash VARCHAR(64) NOT NULL DEFAULT '', observed_hash VARCHAR(64) NOT NULL DEFAULT '', status VARCHAR(40) NOT NULL DEFAULT 'pending', error TEXT NULL)");
        $this->fetch=$fetch??[self::class,'fetch'];
        $this->submit=$submit??fn($host)=>(new SitemapService())->submitForDomain($this->pdo,new CredentialStore($this->pdo),$host);
    }
    public static function signature(string $xml): array
    {
        if (stripos($xml,'<!DOCTYPE')!==false) throw new RuntimeException('Sitemap con DTD no permitido.');
        $old=libxml_use_internal_errors(true);
        try { $root=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NONET); }
        finally { libxml_clear_errors();libxml_use_internal_errors($old); }
        if (!$root || $root->getName()!=='urlset') throw new RuntimeException('Sitemap XML no válido.');
        $items=[];$latest=0;
        foreach ($root->xpath('/*[local-name()="urlset"]/*[local-name()="url"]') as $url) {
            $locations=$url->xpath('./*[local-name()="loc"]');$loc=trim((string)($locations[0]??''));
            if (!$loc) continue;
            $mods=$url->xpath('./*[local-name()="lastmod"]');$mod=trim((string)($mods[0]??''));
            $latest=max($latest,strtotime($mod)?:0);
            $extras=[];
            foreach ($url->xpath('./*[local-name()="image"]/*[local-name()="loc"] | ./*[local-name()="link"]') as $extra) $extras[]=$extra->asXML();
            sort($extras);$items[$loc]=[$mod,$extras];
        }
        if (!$items) throw new RuntimeException('Sitemap vacío; se conserva el estado anterior.');
        ksort($items);
        return ['hash'=>hash('sha256',json_encode($items,JSON_UNESCAPED_SLASHES)),'latest'=>$latest];
    }
    public function scan(string $host, ?int $now=null): array
    {
        $host=PropertyService::normalizeHost($host);$now=$now??time();
        $q=$this->pdo->prepare('SELECT * FROM gsc_sitemap_dispatch WHERE host=?');$q->execute([$host]);$old=$q->fetch(PDO::FETCH_ASSOC);
        if ($old && (int)$old['checked_at']>$now-self::INTERVAL) return ['status'=>'not_due','host'=>$host];
        if (!$old) $this->pdo->prepare('INSERT INTO gsc_sitemap_dispatch(host) VALUES(?)')->execute([$host]);
        $status='failed';$error=null;$accepted=$old['accepted_hash']??'';$observed=$old['observed_hash']??'';$sent=(int)($old['sent_at']??0);
        try {
            $signature=self::signature(($this->fetch)($host));$observed=$signature['hash'];
            // Existing idle sites establish a baseline, without resubmitting old material.
            if (!$old && $signature['latest']<$now-self::INTERVAL) { $accepted=$observed;$status='baseline'; }
            elseif ($accepted===$observed) $status='unchanged';
            else {
                $result=($this->submit)($host);
                if (($result['ok']??false)!==true) throw new RuntimeException($result['message']??'Envío no confirmado.');
                $accepted=$observed;$sent=$now;$status='sent';
            }
        } catch (\Throwable $e) { $error=mb_substr($e->getMessage(),0,500); }
        $this->pdo->prepare('UPDATE gsc_sitemap_dispatch SET checked_at=?,sent_at=?,accepted_hash=?,observed_hash=?,status=?,error=? WHERE host=?')->execute([$now,$sent,$accepted,$observed,$status,$error,$host]);
        return ['host'=>$host,'status'=>$status,'error'=>$error];
    }
    public function run(int $limit=8): array
    {
        $cut=time()-self::INTERVAL;
        $q=$this->pdo->prepare("SELECT DISTINCT p.domain FROM gsc_properties p JOIN gsc_credentials c ON c.id=p.credential_id LEFT JOIN gsc_sitemap_dispatch d ON d.host=p.domain WHERE p.status='active' AND c.owner_type='platform' AND (d.host IS NULL OR d.checked_at<=?) ORDER BY p.domain");$q->execute([$cut]);
        $out=[];foreach(array_slice($q->fetchAll(PDO::FETCH_COLUMN),0,$limit) as $host) $out[]=$this->scan($host);
        return $out;
    }
    public static function fetch(string $host): string
    {
        $ch=curl_init('https://'.$host.'/sitemap.xml');$body='';
        curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'MuseDock-Sitemap/1.0',CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>10485760)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if ($ok===false || $code!==200) throw new RuntimeException('Sitemap no accesible (HTTP '.$code.').');
        return $body;
    }
}
