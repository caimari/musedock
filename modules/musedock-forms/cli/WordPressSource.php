<?php
namespace MuseDockForms\Import;
use PDO;
/** CLI-only connection. Parses literal config values; never boots WordPress. */
final class WordPressSource
{
    public PDO $db;
    public string $prefix;
    public function __construct(string $configPath){
        if(PHP_SAPI!=='cli')throw new \RuntimeException('Solo CLI');
        $config=file_get_contents($configPath);$v=[];
        foreach(['DB_NAME','DB_USER','DB_PASSWORD','DB_HOST'] as $key){if(!preg_match('/define\s*\(\s*[\'"]'.preg_quote($key,'/').'[\'"]\s*,\s*([\'"])(.*?)\1\s*\)/s',$config,$m))throw new \RuntimeException('Configuración no literal o incompleta');$v[$key]=$m[2];}
        if(!preg_match('/\$table_prefix\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/',$config,$m))throw new \RuntimeException('Prefijo inválido');$this->prefix=$m[1];
        $host=$v['DB_HOST'];$dsn='mysql:host='.$host.';dbname='.$v['DB_NAME'].';charset=utf8mb4';
        if(preg_match('/^([^:]+):(\d+)$/',$host,$h))$dsn='mysql:host='.$h[1].';port='.$h[2].';dbname='.$v['DB_NAME'].';charset=utf8mb4';
        $this->db=new PDO($dsn,$v['DB_USER'],$v['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $this->db->exec('SET SESSION TRANSACTION READ ONLY');$this->db->beginTransaction();
    }
    public function rows(string $table,string $where='1=1'): array {
        if(!preg_match('/^[a-z0-9_]+$/i',$table))throw new \InvalidArgumentException('Tabla inválida');
        return $this->db->query('SELECT * FROM `'.$this->prefix.$table.'` WHERE '.$where)->fetchAll();
    }
    public function backup(string $dir,array $tables): array {
        $dir=rtrim($dir,'/');if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new \RuntimeException('No se pudo crear copia privada');
        $real=realpath($dir);if(!$real||str_starts_with($real,APP_ROOT.'/public')||str_starts_with($real,APP_ROOT.'/storage/tenants'))throw new \RuntimeException('La copia debe estar fuera de los directorios públicos y del tenant');chmod($real,0700);
        $snapshot=[];foreach($tables as $table=>$where){$rows=$this->rows($table,$where);$path=$real.'/'.$table.'.json';if(file_exists($path))throw new \RuntimeException('La copia ya existe; usa un directorio nuevo');file_put_contents($path,json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));chmod($path,0600);$snapshot[$table]=['rows'=>count($rows),'sha256'=>hash_file('sha256',$path)];}
        $path=$real.'/manifest.json';file_put_contents($path,json_encode($snapshot,JSON_PRETTY_PRINT));chmod($path,0600);return $snapshot;
    }
    public static function decode(string $s){return @unserialize($s,['allowed_classes'=>false]);}
    public function __destruct(){if($this->db->inTransaction())$this->db->rollBack();}
}
