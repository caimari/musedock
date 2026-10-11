<?php
require __DIR__.'/bootstrap.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store, Authorization, Policy};
$store=new Store(new PDO('sqlite::memory:'));
$resource=['id'=>'main','url'=>'https://cms.example/mcp','enabled'=>true,'revision'=>1];
$store->putResource($resource);
$oauth=new Authorization($store);
$new=$oauth->createClient('main','Rotation test','https://client.example/callback',[Policy::READ]);
$id=$new['client']['id'];
$basic=fn($secret)=>'Basic '.base64_encode($id.':'.$secret);
$verifier=str_repeat('a',43);
$pending=$oauth->validateRequest('main',['client_id'=>$id,'redirect_uri'=>'https://client.example/callback','response_type'=>'code','code_challenge_method'=>'S256','code_challenge'=>Policy::challenge($verifier),'resource'=>$resource['url'],'scope'=>Policy::READ]);
$code=$oauth->consent($pending,7);
$tokens=$oauth->exchange($new['client'],['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>'https://client.example/callback','code_verifier'=>$verifier,'resource'=>$resource['url']]);
$secret=$oauth->rotateSecret('main',$id);
$oauth->authenticateClient('main',$basic($new['secret']));
$client=$oauth->authenticateClient('main',$basic($secret));
if($client['revision']!==$new['client']['revision'])throw new RuntimeException('Client revision changed.');
$oauth->access('main',$tokens['access_token']);
$refreshed=$oauth->exchange($client,['grant_type'=>'refresh_token','refresh_token'=>$tokens['refresh_token'],'resource'=>$resource['url']]);
function mustDeny(callable $callback):void {
 try{$callback();}catch(RuntimeException $e){return;}
 throw new RuntimeException('Unexpected authorization.');
}
mustDeny(fn()=>$oauth->rotateSecret('tenant:1',$id));
mustDeny(fn()=>$oauth->rotateSecret('main',$id));
$oauth->finishSecretRotation('main',$id);
mustDeny(fn()=>$oauth->authenticateClient('main',$basic($new['secret'])));
$oauth->authenticateClient('main',$basic($secret));
$oauth->access('main',$refreshed['access_token']);
$second=$oauth->rotateSecret('main',$id);
$client=$store->client($id);$client['previous_secret_expires']=time()-1;$store->putClient($client);
mustDeny(fn()=>$oauth->authenticateClient('main',$basic($secret)));
$oauth->authenticateClient('main',$basic($second));
$client=$store->client($id);$client['enabled']=false;$store->putClient($client);
mustDeny(fn()=>$oauth->rotateSecret('main',$id));
echo "PASS: overlapping secrets, retained grants/access/refresh, resource isolation, rotation completion, expiry and revoked client.\n";
