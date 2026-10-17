<?php
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/modules/search-console/services/PropertyService.php';
require dirname(__DIR__).'/modules/search-console/services/SitemapDispatchService.php';
use Modules\SearchConsole\Services\SitemapDispatchService;
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$time=1800000000;$calls=[];$fail=false;$xml=[];
$make=fn($date,$path='one')=>'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://a.example/'.$path.'</loc><lastmod>'.gmdate('c',$date).'</lastmod></url></urlset>';
$xml['a.example']=$make($time);$xml['b.example']=$make($time-86400);
// Closure fetch by reference to observe subsequent updates.
$service=new SitemapDispatchService($db,function($host)use(&$xml){return $xml[$host];},function($host)use(&$calls,&$fail){$calls[]=$host;return ['ok'=>!$fail,'message'=>'temporary error'];});
$check=function($yes,$message){if(!$yes)throw new RuntimeException($message);};
$check($service->scan('a.example',$time)['status']==='sent','recent publication is sent');
$check($service->scan('b.example',$time)['status']==='baseline','idle domain establishes baseline without submission');
for($i=1;$i<=10;$i++){$xml['a.example']=$make($time+$i,'article-'.$i);$check($service->scan('a.example',$time+$i)['status']==='not_due','ten changes are grouped');}
$check(count($calls)===1,'no per-article Google calls');
$check($service->scan('a.example',$time+28800)['status']==='sent' && count($calls)===2,'one grouped send after eight hours');
$check($service->scan('a.example',$time+57600)['status']==='unchanged' && count($calls)===2,'unchanged sitemap never resent');
$check($service->scan('b.example',$time+3*365*86400)['status']==='unchanged' && count($calls)===2,'three idle years do not trigger Google calls');
$xml['a.example']=$make($time+60000,'new-page');$fail=true;
$check($service->scan('a.example',$time+86400)['status']==='failed','failure stored');
$fail=false;$check($service->scan('a.example',$time+115200)['status']==='sent','failed changes remain pending for next interval');
$xml['a.example']='<invalid>';$check($service->scan('a.example',$time+144000)['status']==='failed','invalid XML never submitted');
$check(SitemapDispatchService::signature($make($time))['hash']===SitemapDispatchService::signature($make($time))['hash'],'stable fingerprint');
echo "PASS: per-domain isolation, 8-hour grouping, new material, idle sites, XML validation and retry without real Google requests.\n";
