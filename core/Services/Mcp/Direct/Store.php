<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use PDO;
use Throwable;
use RuntimeException;

/** CMS-owned storage. Never loads Panel configuration or its OAuth databases. */
final class Store
{
    public PDO $db;
    private bool $postgres=false;
    public const TABLES=['resources','clients','grants','codes','tokens','requests','events','approval_jobs','article_workflows','limits'];
    public function __construct(?PDO $db = null)
    {
        if ($db === null && \Screenart\Musedock\Env::get('MCP_CMS_STATE_DRIVER','sqlite')==='pgsql') {
            $config=require APP_ROOT.'/config/config.php';
            $driver=\Screenart\Musedock\Database::getDriver();
            $db=new PDO($driver->getDSN($config['db']['host']??'localhost',$config['db']['name']??'', $config['db']['port']??null),$config['db']['user']??'',$config['db']['pass']??'');
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='pgsql') throw new RuntimeException('El estado compartido MCP requiere PostgreSQL.');
        }
        if ($db === null) {
            $directory = (string) \Screenart\Musedock\Env::get('MCP_CMS_PRIVATE_DIRECTORY', dirname(APP_ROOT) . '/.musedock-cms');
            if (!str_starts_with($directory,'/')) throw new RuntimeException('El almacenamiento MCP debe usar una ruta privada absoluta.');
            if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
                throw new RuntimeException('No se pudo crear el almacenamiento privado MCP del CMS.');
            }
            $directory=realpath($directory);
            $root=realpath(APP_ROOT);
            if (!$directory || ($root && ($directory===$root || str_starts_with($directory,$root.'/')))) throw new RuntimeException('El almacenamiento MCP debe estar fuera de httpdocs.');
            chmod($directory,0700);
            $file = $directory . '/mcp.sqlite';
            if (is_link($file)) throw new RuntimeException('Almacenamiento MCP no válido.');
            $db = new PDO('sqlite:' . $file);
            chmod($file, 0600);
        }
        $this->db = $db;
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->postgres=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql';
        if (!$this->postgres) { $db->exec('PRAGMA busy_timeout=5000'); $db->exec('PRAGMA foreign_keys=ON'); }
        $schema="CREATE TABLE IF NOT EXISTS resources (id TEXT PRIMARY KEY, data TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS clients (id TEXT PRIMARY KEY, resource TEXT NOT NULL, data TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS grants (id TEXT PRIMARY KEY, client TEXT NOT NULL, resource TEXT NOT NULL, data TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS codes (hash TEXT PRIMARY KEY, data TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS tokens (hash TEXT PRIMARY KEY, type TEXT NOT NULL, family TEXT NOT NULL, data TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS requests (id TEXT PRIMARY KEY, resource TEXT NOT NULL, client TEXT NOT NULL, status TEXT NOT NULL, expires INTEGER NOT NULL, data TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY AUTOINCREMENT, resource TEXT NOT NULL, action TEXT NOT NULL, actor INTEGER NOT NULL, detail TEXT NOT NULL, created INTEGER NOT NULL);
            CREATE TABLE IF NOT EXISTS approval_jobs (request_id TEXT PRIMARY KEY, resource TEXT NOT NULL, actor INTEGER NOT NULL, status TEXT NOT NULL, created INTEGER NOT NULL, started INTEGER, error TEXT);
            CREATE TABLE IF NOT EXISTS article_workflows (id TEXT PRIMARY KEY, resource TEXT NOT NULL, client TEXT NOT NULL, status TEXT NOT NULL, data TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS limits (id TEXT PRIMARY KEY, started INTEGER NOT NULL, count INTEGER NOT NULL);";
        if ($this->postgres) $schema=str_replace('INTEGER PRIMARY KEY AUTOINCREMENT','BIGSERIAL PRIMARY KEY',$schema);
        $db->exec($this->sql($schema));
    }
    private function sql(string $sql): string
    {
        if (!$this->postgres) return $sql;
        $sql=str_replace("json_extract(data,'$.grant')","(data::jsonb->>'grant')",$sql);
        $sql=str_replace('rowid',"(data::jsonb->>'created')::bigint",$sql);
        if (str_contains($sql,'INSERT OR IGNORE')) $sql=str_replace('INSERT OR IGNORE','INSERT',$sql).' ON CONFLICT DO NOTHING';
        return preg_replace('/\b('.implode('|',self::TABLES).')\b/','mcp_state_$1',$sql);
    }
    public function one(string $sql, array $args = []): ?array
    {
        $s = $this->db->prepare($this->sql($sql)); $s->execute($args);
        return $s->fetch() ?: null;
    }
    public function all(string $sql, array $args = []): array
    {
        $s = $this->db->prepare($this->sql($sql)); $s->execute($args); return $s->fetchAll();
    }
    public function run(string $sql, array $args = []): int
    {
        $s = $this->db->prepare($this->sql($sql)); $s->execute($args); return $s->rowCount();
    }
    public function atomic(callable $fn)
    {
        $this->db->exec($this->postgres?'BEGIN':'BEGIN IMMEDIATE');
        if ($this->postgres) $this->db->exec("SELECT pg_advisory_xact_lock(hashtext('musedock-cms-mcp-state'))");
        try { $result = $fn(); $this->db->exec('COMMIT'); return $result; }
        catch (Throwable $e) { $this->db->exec('ROLLBACK'); throw $e; }
    }
    public function resource(string $id): ?array { return $this->decode($this->one('SELECT data FROM resources WHERE id = ?', [$id])); }
    public function client(string $id): ?array { return $this->decode($this->one('SELECT data FROM clients WHERE id = ?', [$id])); }
    public function grant(string $id): ?array { return $this->decode($this->one('SELECT data FROM grants WHERE id = ?', [$id])); }
    public function decode(?array $row): ?array { return $row ? json_decode($row['data'], true, 64, JSON_THROW_ON_ERROR) : null; }
    public function putResource(array $r): void
    {
        $this->run('INSERT INTO resources(id,data) VALUES(?,?) ON CONFLICT(id) DO UPDATE SET data=excluded.data', [$r['id'], self::json($r)]);
    }
    public function putClient(array $c): void
    {
        $this->run('INSERT INTO clients(id,resource,data) VALUES(?,?,?) ON CONFLICT(id) DO UPDATE SET data=excluded.data', [$c['id'], $c['resource'], self::json($c)]);
    }
    public function putGrant(array $g): void
    {
        $this->run('INSERT INTO grants(id,client,resource,data) VALUES(?,?,?,?) ON CONFLICT(id) DO UPDATE SET data=excluded.data', [$g['id'], $g['client'], $g['resource'], self::json($g)]);
    }
    public function event(string $resource, string $action, int $actor, array $detail = []): void
    {
        // Only identifiers and outcomes. Never record tokens, secrets or article bodies.
        $this->run('INSERT INTO events(resource,action,actor,detail,created) VALUES(?,?,?,?,?)', [$resource, $action, $actor, self::json($detail), time()]);
    }
    public function limit(string $id, int $maximum = 60, int $seconds = 60): void
    {
        $this->atomic(function () use ($id, $maximum, $seconds) {
            $r = $this->one('SELECT * FROM limits WHERE id=?', [$id]);
            if (!$r || $r['started'] <= time() - $seconds) {
                $this->run('INSERT INTO limits(id,started,count) VALUES(?,?,1) ON CONFLICT(id) DO UPDATE SET started=excluded.started,count=1', [$id,time()]);
            } elseif ($r['count'] >= $maximum) throw new RuntimeException('Límite de peticiones alcanzado.');
            else $this->run('UPDATE limits SET count=count+1 WHERE id=?', [$id]);
        });
    }
    public static function json(array $data): string { return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
}
