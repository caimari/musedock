<?php
namespace MuseDockMembers;
/** Compatibility reader for imported hashes; all successful logins upgrade to PHP's native hash. */
final class Passwords
{
    public static function verify(string $password,string $hash): bool {
        if(strlen($password)>4096)return false;
        if(str_starts_with($hash,'$P$')||str_starts_with($hash,'$H$')){
            if(strlen($hash)!==34)return false;$alphabet='./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';$log=strpos($alphabet,$hash[3]);if($log===false||$log<7||$log>20)return false;
            $salt=substr($hash,4,8);$digest=md5($salt.$password,true);for($n=1<<$log;$n>0;--$n)$digest=md5($digest.$password,true);
            $encoded='';$acc=0;$bits=0;for($i=0;$i<16;$i++){$acc|=ord($digest[$i])<<$bits;$bits+=8;while($bits>=6){$encoded.=$alphabet[$acc&63];$acc>>=6;$bits-=6;}}if($bits)$encoded.=$alphabet[$acc&63];
            return hash_equals($hash,substr($hash,0,12).$encoded);
        }
        if(str_starts_with($hash,'$wp$'))return password_verify(base64_encode(hash_hmac('sha384',$password,'wp-sha384',true)),substr($hash,3));
        return password_verify($password,$hash);
    }
}
