<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use RuntimeException;

/** Authorization Code + S256, exact resource binding, rotating one-use refresh tokens. */
final class Authorization
{
    public function __construct(public Store $store) {}
    public function createClient(string $resource, string $name, string $callback, array $scopes, array $sites = [], array $groups = [], string $networks = ''): array
    {
        if (!$this->store->resource($resource) || !Policy::callback($callback) || trim($name) === '' || strlen($name) > 100) throw new RuntimeException('Datos del cliente no válidos.');
        $scopes = Policy::requestedScopes(implode(' ', $scopes), Policy::scopes($resource));
        $secret = 'cms_secret_' . bin2hex(random_bytes(32));
        $c = ['id'=>'cms_client_'.bin2hex(random_bytes(16)), 'resource'=>$resource, 'name'=>strip_tags($name),
            'callback'=>$callback, 'secret_hash'=>hash('sha256',$secret), 'scopes'=>$scopes, 'sites'=>array_values(array_unique(array_map('intval',$sites))),
            'groups'=>array_values(array_unique(array_map('intval',$groups))), 'networks'=>Network::parse($networks), 'revision'=>1, 'enabled'=>true, 'created'=>time()];
        $this->store->putClient($c);
        return ['client'=>$c,'secret'=>$secret];
    }
    public function rotateSecret(string $resource, string $id): string
    {
        return $this->store->atomic(function () use ($resource, $id) {
            $c=$this->store->client($id);
            if (!$c || !$c['enabled'] || $c['resource']!==$resource) throw new RuntimeException('Cliente no autorizado.');
            if (($c['previous_secret_expires']??0)>time()) throw new RuntimeException('Finaliza la renovación pendiente antes de generar otro secreto.');
            $secret='cms_secret_'.bin2hex(random_bytes(32));
            $c['previous_secret_hash']=$c['secret_hash'];
            $c['previous_secret_expires']=time()+86400;
            $c['secret_hash']=hash('sha256',$secret);
            $this->store->putClient($c);
            return $secret;
        });
    }
    public function finishSecretRotation(string $resource, string $id): void
    {
        $this->store->atomic(function () use ($resource, $id) {
            $c=$this->store->client($id);
            if (!$c || !$c['enabled'] || $c['resource']!==$resource) throw new RuntimeException('Cliente no autorizado.');
            unset($c['previous_secret_hash'], $c['previous_secret_expires']);
            $this->store->putClient($c);
        });
    }
    public function reviseClient(string $resource, string $id, array $scopes, bool $enabled): void
    {
        $c=$this->store->client($id);
        if (!$c || $c['resource']!==$resource) throw new RuntimeException('Cliente no encontrado.');
        $normalized=Policy::requestedScopes(implode(' ',$scopes),Policy::scopes($resource));
        $previous=$c['scopes']; $compare=$normalized; sort($previous); sort($compare);
        if ($previous===$compare && $c['enabled']===$enabled) return;
        $c['scopes']=$normalized;
        $c['enabled']=$enabled; $c['revision']++; $this->store->putClient($c);
        $this->invalidateClient($id);
    }
    public function invalidateClient(string $id): void
    {
        foreach ($this->store->all('SELECT data FROM grants WHERE client=?',[$id]) as $row) {
            $g=$this->store->decode($row); $g['active']=false; $this->store->putGrant($g);
        }
        $this->store->run("UPDATE requests SET status='revoked' WHERE client=? AND status='pending'",[$id]);
    }
    public function validateRequest(string $resource, array $p): array
    {
        $r=$this->store->resource($resource); $c=$this->store->client((string)($p['client_id']??''));
        if (!$r || !$r['enabled'] || !$c || !$c['enabled'] || $c['resource']!==$resource) throw new RuntimeException('Cliente o servicio desactivado.');
        if (($p['redirect_uri']??'')!==$c['callback'] || !Policy::callback($c['callback'])) throw new RuntimeException('Callback no autorizado.');
        if (($p['response_type']??'')!=='code' || ($p['code_challenge_method']??'')!=='S256'
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D',(string)($p['code_challenge']??''))) throw new RuntimeException('Se requiere PKCE S256.');
        if (($p['resource']??'')!==$r['url']) throw new RuntimeException('Recurso OAuth no autorizado.');
        $scopes=Policy::requestedScopes((string)($p['scope']??''),$c['scopes']);
        return ['resource'=>$resource,'client'=>$c['id'],'callback'=>$c['callback'],'challenge'=>$p['code_challenge'],
            'scopes'=>$scopes,'state'=>substr((string)($p['state']??''),0,2048),'revision'=>$c['revision'],'resource_revision'=>$r['revision'],'created'=>time()];
    }
    public function consent(array $pending, int $actor): string
    {
        $r=$this->store->resource($pending['resource']); $c=$this->store->client($pending['client']);
        if (!$r || !$c || !$r['enabled'] || !$c['enabled'] || $actor===0 || $pending['created']<time()-900
            || $c['revision']!==$pending['revision'] || $r['revision']!==$pending['resource_revision']) throw new RuntimeException('Consentimiento caducado.');
        $g=['id'=>bin2hex(random_bytes(24)),'client'=>$c['id'],'resource'=>$r['id'],'actor'=>$actor,'scopes'=>$pending['scopes'],
            'revision'=>$c['revision'],'resource_revision'=>$r['revision'],'active'=>true,'created'=>time()];
        $raw='cms_code_'.bin2hex(random_bytes(32));
        $code=$pending+['grant'=>$g['id'],'expires'=>time()+300];
        $this->store->atomic(function () use ($g,$raw,$code) {
            $this->store->putGrant($g);
            $this->store->run('INSERT INTO codes(hash,data) VALUES(?,?)',[hash('sha256',$raw),Store::json($code)]);
            $this->store->event($g['resource'],'consent.approved',$g['actor'],['client'=>$g['client']]);
        });
        return $raw;
    }
    public function authenticateClient(string $resource, string $authorization): array
    {
        if (!preg_match('/^Basic\s+(.+)$/i',$authorization,$m)) throw new RuntimeException('Se requiere client_secret_basic.');
        $decoded=base64_decode($m[1],true);
        if (!$decoded || !str_contains($decoded,':')) throw new RuntimeException('Cliente no autorizado.');
        [$id,$secret]=array_map('urldecode',explode(':',$decoded,2));
        $c=$this->store->client($id);
        $hash=hash('sha256',$secret);
        $valid=$c && (hash_equals($c['secret_hash'],$hash) || (($c['previous_secret_expires']??0)>time() && hash_equals($c['previous_secret_hash']??'',$hash)));
        if (!$c || !$c['enabled'] || $c['resource']!==$resource || !$valid) throw new RuntimeException('Cliente no autorizado.');
        return $c;
    }
    public function exchange(array $c, array $p): array
    {
        $r=$this->store->resource($c['resource']);
        if (!$r || !$r['enabled'] || ($p['resource']??'')!==$r['url']) throw new RuntimeException('Recurso OAuth no autorizado.');
        $failure=null;
        $result=$this->store->atomic(function () use ($c,$p,$r,&$failure) {
            if (($p['grant_type']??'')==='authorization_code') {
                $hash=hash('sha256',(string)($p['code']??''));
                $code=$this->store->decode($this->store->one('SELECT data FROM codes WHERE hash=?',[$hash]));
                if (!$code || $code['expires']<=time() || $code['client']!==$c['id'] || $code['resource']!==$r['id']
                    || ($p['redirect_uri']??'')!==$c['callback'] || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D',(string)($p['code_verifier']??''))
                    || !hash_equals($code['challenge'],Policy::challenge($p['code_verifier']))) throw new RuntimeException('Código o PKCE no válido.');
                $g=$this->store->grant($code['grant']);
                if (!$g || !Policy::active($r,$c,$g)) throw new RuntimeException('Consentimiento revocado.');
                $this->store->run('DELETE FROM codes WHERE hash=?',[$hash]);
                return $this->issue($g,bin2hex(random_bytes(24)),$r['url']);
            }
            if (($p['grant_type']??'')!=='refresh_token') throw new RuntimeException('Tipo de concesión no admitido.');
            $hash=hash('sha256',(string)($p['refresh_token']??''));
            $row=$this->store->one("SELECT * FROM tokens WHERE hash=? AND type='refresh'",[$hash]);
            $t=$this->store->decode($row);
            if (!$t || $t['client']!==$c['id'] || $t['resource']!==$r['url']) throw new RuntimeException('Refresh token no válido.');
            if (!empty($t['used'])) {
                $this->revokeFamily($row['family']); $failure='Refresh token reutilizado; familia revocada.'; return null;
            }
            $g=$this->store->grant($t['grant']);
            if ($t['expires']<=time() || !$g || !Policy::active($r,$c,$g)) throw new RuntimeException('Token caducado o revocado.');
            $t['used']=true; $this->store->run('UPDATE tokens SET data=? WHERE hash=?',[Store::json($t),$hash]);
            return $this->issue($g,$row['family'],$r['url']);
        });
        if ($failure) throw new RuntimeException($failure);
        return $result;
    }
    private function issue(array $g, string $family, string $url): array
    {
        $out=[];
        foreach (['access'=>3600,'refresh'=>30*86400] as $type=>$ttl) {
            $raw='cms_'.$type.'_'.bin2hex(random_bytes(32));
            $data=['grant'=>$g['id'],'client'=>$g['client'],'resource'=>$url,'expires'=>time()+$ttl,'used'=>false];
            $this->store->run('INSERT INTO tokens(hash,type,family,data) VALUES(?,?,?,?)',[hash('sha256',$raw),$type,$family,Store::json($data)]);
            $out[$type.'_token']=$raw;
        }
        return $out+['token_type'=>'Bearer','expires_in'=>3600,'scope'=>implode(' ',$g['scopes'])];
    }
    public function access(string $resource, string $raw): array
    {
        $r=$this->store->resource($resource);
        $row=$this->store->one("SELECT * FROM tokens WHERE hash=? AND type='access'",[hash('sha256',$raw)]);
        $t=$this->store->decode($row); $g=$t?$this->store->grant($t['grant']):null; $c=$g?$this->store->client($g['client']):null;
        if (!$r || !$t || $t['resource']!==$r['url'] || $t['expires']<=time() || $t['used'] || !$g || !$c || !Policy::active($r,$c,$g)) throw new RuntimeException('Token no válido para este recurso.');
        return ['resource'=>$r,'client'=>$c,'grant'=>$g,'family'=>$row['family']];
    }
    public function revoke(array $c, string $raw): void
    {
        $row=$this->store->one('SELECT * FROM tokens WHERE hash=?',[hash('sha256',$raw)]); $t=$this->store->decode($row);
        if ($t && $t['client']===$c['id']) $this->store->atomic(function () use ($row) { $this->revokeFamily($row['family']); });
    }
    private function revokeFamily(string $family): void
    {
        foreach ($this->store->all('SELECT hash,data FROM tokens WHERE family=?',[$family]) as $row) {
            $t=$this->store->decode($row); $t['used']=true;
            $g=$this->store->grant($t['grant']);
            if ($g) { $g['active']=false; $this->store->putGrant($g); }
            $this->store->run("UPDATE requests SET status='revoked' WHERE status='pending' AND json_extract(data,'$.grant')=?",[$t['grant']]);
            $this->store->run('UPDATE tokens SET data=? WHERE hash=?',[Store::json($t),$row['hash']]);
        }
    }
}
