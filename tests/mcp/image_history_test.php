<?php
require __DIR__.'/bootstrap.php';
use Screenart\Musedock\Services\AI\AIImageService;
use Screenart\Musedock\Services\Mcp\Direct\{Content,Media};
$db=fixture();
$db->exec("CREATE TABLE ai_settings(setting_key TEXT,setting_value TEXT,tenant_id INTEGER)");
$payload=new ReflectionMethod(AIImageService::class,'miniMaxPayload');
$actual=$payload->invoke(null,'Editorial style',['model'=>'image-01'],['size'=>'1792x1024','quality'=>'standard']);
if($actual['width']!==1792||$actual['height']!==1024||isset($actual['aspect_ratio']))throw new RuntimeException('Exact MiniMax dimensions were lost.');
foreach([['size'=>'1792x1024','quality'=>'hd'],['size'=>'513x1024'],['size'=>'2049x1024'],['size'=>'1200x630']] as $bad){
 try{$payload->invoke(null,'Test',['model'=>'image-01'],$bad);}catch(InvalidArgumentException $e){continue;}
 throw new RuntimeException('Invalid MiniMax option accepted.');
}
$persist=new ReflectionMethod(AIImageService::class,'saveGenerationMetadata');
$data=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfWQAAAAASUVORK5CYII=');
$db->exec("UPDATE media SET metadata='{\"thumbnail\":{\"width\":420}}' WHERE id=1");
$metadata=['ai_generation'=>['prompt'=>'Blue editorial style','provider_type'=>'minimax','model'=>'image-01','requested_size'=>'1792x1024','quality'=>'standard']];
$persist->invoke(null,1,1,$data,$metadata);
$media=new Media(new Content($db));
$images=$media->prompts(1,20,0);
if(count($images)!==1||$images[0]['generation']['prompt']!=='Blue editorial style'||$images[0]['resolution']!==['width'=>1,'height'=>1])throw new RuntimeException('Generation provenance missing.');
if($media->prompts(3,20,0)!==[])throw new RuntimeException('Cross-tenant history leak.');
$saved=json_decode($db->query('SELECT metadata FROM media WHERE id=1')->fetchColumn(),true);
if($saved['thumbnail']['width']!==420)throw new RuntimeException('Existing metadata lost.');
$db->exec("INSERT INTO ai_settings VALUES('ai_log_all_prompts','false',1)");
$metadata['ai_generation']['prompt']=str_repeat('a',200);$persist->invoke(null,1,1,$data,$metadata);
$images=$media->prompts(1,20,0);
if(strlen($images[0]['generation']['prompt'])!==100||!$images[0]['generation']['prompt_truncated'])throw new RuntimeException('Prompt retention setting ignored.');
echo "PASS: exact MiniMax dimensions, invalid quality/size rejection, saved prompt and actual resolution, metadata preservation, tenant isolation and retention setting. No provider calls.\n";
