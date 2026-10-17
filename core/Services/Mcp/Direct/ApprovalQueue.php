<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

final class ApprovalQueue
{
    public function __construct(private Store $store, private Content $content) {}
    public function run(): int
    {
        $count=0;
        // Interrupted jobs are never retried: the provider may already have charged.
        $stale=$this->store->all("SELECT request_id FROM approval_jobs WHERE status='executing' AND started<?",[time()-600]);
        foreach ($stale as $job) {
            $this->store->run("UPDATE approval_jobs SET status='failed',error=? WHERE request_id=? AND status='executing'",['Ejecución interrumpida; comprueba los medios antes de solicitar otra generación.',$job['request_id']]);
            $this->store->run("UPDATE requests SET status='failed' WHERE id=? AND status='executing'",[$job['request_id']]);
        }
        foreach ($this->store->all("SELECT * FROM approval_jobs WHERE status='queued' ORDER BY created LIMIT 5") as $job) {
            $claimed=$this->store->run("UPDATE approval_jobs SET status='executing',started=? WHERE request_id=? AND status='queued'",[time(),$job['request_id']]);
            if (!$claimed) continue;
            try {
                $GLOBALS['mcp_request_id']=$job['request_id'];
                if (!Endpoint::installed($job['resource'])) throw new \RuntimeException('El plugin ya no está activo.');
                (new Tools($this->store,$this->content))->decide($job['resource'],$job['request_id'],(int)$job['actor'],true);
                $this->store->run("UPDATE approval_jobs SET status='completed' WHERE request_id=?",[$job['request_id']]);
            } catch (\Throwable $e) {
                $message=$e instanceof \PDOException?'No se pudo guardar la operación en el CMS.':$e->getMessage();
                $this->store->run("UPDATE approval_jobs SET status='failed',error=? WHERE request_id=?",[mb_substr($message,0,500),$job['request_id']]);
            }
            unset($GLOBALS['mcp_request_id']);
            $count++;
        }
        return $count+(new ArticleWorkflow($this->store,$this->content))->run();
    }
}
