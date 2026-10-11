<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

final class Network
{
    public static function parse(string $text): array
    {
        $entries=array_values(array_unique(preg_split('/[\s,]+/',trim($text),-1,PREG_SPLIT_NO_EMPTY)));
        if (count($entries)>512) throw new \RuntimeException('Demasiados rangos de red.');
        foreach ($entries as $entry) {
            $parts=explode('/',$entry); $ip=@inet_pton($parts[0]);
            if (!$ip || count($parts)>2 || isset($parts[1]) && (!ctype_digit($parts[1]) || (int)$parts[1]>strlen($ip)*8)) throw new \RuntimeException('IP o CIDR no válido.');
        }
        return $entries;
    }
    public static function allows(array $entries,string $address): bool
    {
        if (!$entries) return true;
        $ip=@inet_pton($address); if (!$ip) return false;
        foreach ($entries as $entry) {
            $p=explode('/',$entry); $net=@inet_pton($p[0]); if (!$net || strlen($ip)!==strlen($net)) continue;
            $bits=isset($p[1])?(int)$p[1]:strlen($net)*8; $bytes=intdiv($bits,8); $remainder=$bits%8;
            if (substr($net,0,$bytes)!==substr($ip,0,$bytes)) continue;
            if (!$remainder || (ord($net[$bytes]) & (255 << (8-$remainder)))===(ord($ip[$bytes]) & (255 << (8-$remainder)))) return true;
        }
        return false;
    }
}
