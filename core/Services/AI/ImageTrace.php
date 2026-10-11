<?php
namespace Screenart\Musedock\Services\AI;

final class ImageTrace
{
    public static array $context=[];
    public static function path(): string
    {
        $directory=(string)\Screenart\Musedock\Env::get('MCP_CMS_PRIVATE_DIRECTORY',dirname(APP_ROOT).'/.musedock-cms');
        $root=realpath(APP_ROOT); $real=realpath($directory);
        if (!$real || !$root || $real===$root || str_starts_with($real,$root.'/')) throw new \RuntimeException('Registro IA requiere almacenamiento privado fuera de la web.');
        return $real.'/image-generation.jsonl';
    }
    public static function record(string $stage,array $details=[]): void
    {
        try {
            $allowed=['request_id','tenant_id','provider_id','provider_type','model','duration_ms','http_status','curl_errno','provider_status','error_type'];
            $data=array_intersect_key(self::$context+$details,array_flip($allowed));
            $data=['time'=>gmdate('c'),'stage'=>$stage]+$data;
            $path=self::path();
            if (is_link($path)) return;
            file_put_contents($path,json_encode($data,JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);
            chmod($path,0600);
        } catch (\Throwable $ignored) {}
    }
    public static function recent(): array
    {
        try {
            $path=self::path();
            if (!is_file($path) || is_link($path)) return [];
            $f=fopen($path,'rb');if (!$f) return [];
            fseek($f,max(0,filesize($path)-131072));$data=stream_get_contents($f);fclose($f);
            $rows=[];
            foreach(explode("\n",$data) as $line) { $row=json_decode($line,true);if (is_array($row)) $rows[]=$row; }
            return array_reverse(array_slice($rows,-100));
        } catch (\Throwable $ignored) { return []; }
    }
}
