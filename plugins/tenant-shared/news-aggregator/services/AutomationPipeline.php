<?php

namespace NewsAggregator\Services;

use NewsAggregator\Models\Source;
use NewsAggregator\Models\Item;
use NewsAggregator\Models\Settings;
use NewsAggregator\Models\Log;

/**
 * Pipeline de automatización del News Aggregator
 *
 * Ejecuta el flujo completo según la configuración:
 * 1. Captura (fetch de fuentes vencidas)
 * 2. Reescritura IA (si auto_rewrite está activo)
 * 3. Aprobación automática (si auto_approve está activo)
 * 4. Publicación automática (si auto_publish está activo)
 */
class AutomationPipeline
{
    private int $tenantId;
    private array $settings;
    private array $log = [];

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->settings = Settings::getWithDefaults($tenantId);
    }

    /**
     * Ejecutar el pipeline completo
     */
    public function run(int $maxFetchSources = 10, int $maxRewriteItems = 10, int $maxPublishItems = 10): array
    {
        $results = [
            'fetched' => 0,
            'rewritten' => 0,
            'approved' => 0,
            'published' => 0,
            'errors' => []
        ];

        // Verificar que el plugin está activo
        if (!$this->settings['enabled']) {
            $this->log('Plugin desactivado para tenant ' . $this->tenantId);
            return $results;
        }

        // Paso 1: Captura de fuentes vencidas
        $results['fetched'] = $this->stepFetch($maxFetchSources);

        // Paso 2: Reescritura automática (si está activa)
        if ($this->settings['auto_rewrite'] && $this->settings['ai_provider_id']) {
            $results['rewritten'] = $this->stepRewrite($maxRewriteItems);
        }

        // Paso 3: Aprobación automática (si está activa)
        if ($this->settings['auto_approve']) {
            $results['approved'] = $this->stepApprove();
        }

        // Paso 4: Publicación automática (si está activa)
        if ($this->settings['auto_publish']) {
            $results['published'] = $this->stepPublish($maxPublishItems);
        }

        $results['errors'] = $this->log;

        // Registrar ejecución del pipeline en los logs de BD
        $this->logPipelineExecution($results);

        return $results;
    }

    /**
     * Paso 1: Captura - Fetch de fuentes que les toca según su intervalo
     */
    private function stepFetch(int $maxSources): int
    {
        $totalFetched = 0;
        $dueSources = Source::getDueForFetch($this->tenantId);

        // Limitar cantidad de fuentes a procesar por ejecución
        $dueSources = array_slice($dueSources, 0, $maxSources);

        foreach ($dueSources as $source) {
            try {
                $result = FetcherFactory::fetch($this->tenantId, $source);

                if ($result['success']) {
                    $totalFetched += $result['count'];
                    $this->log("Fetch OK: {$source->name} - {$result['count']} nuevos items");
                } else {
                    $this->log("Fetch ERROR: {$source->name} - {$result['error']}");
                }
            } catch (\Exception $e) {
                $this->log("Fetch EXCEPTION: {$source->name} - {$e->getMessage()}");
            }

            // Pausa entre fuentes
            usleep(200000); // 0.2 segundos
        }

        return $totalFetched;
    }

    /**
     * Paso 2: Reescritura - Reescribir items pendientes con IA
     */
    private function stepRewrite(int $maxItems): int
    {
        $rewritten = 0;

        try {
            $rewriter = new AIRewriter($this->tenantId);
            $results = $rewriter->processQueue($maxItems);

            foreach ($results as $r) {
                if ($r['success']) {
                    $rewritten++;
                    $this->log("Rewrite OK: {$r['title']} ({$r['tokens']} tokens)");
                } else {
                    $this->log("Rewrite ERROR: {$r['title']} - {$r['error']}");
                }
            }
        } catch (\Exception $e) {
            $this->log("Rewrite EXCEPTION: {$e->getMessage()}");
        }

        return $rewritten;
    }

    /**
     * Paso 3: Aprobación automática - Aprobar todos los items en estado 'ready'
     */
    private function stepApprove(): int
    {
        $approved = 0;

        try {
            $readyItems = Item::getReady($this->tenantId, 100);

            foreach ($readyItems as $item) {
                Item::updateStatus($item->id, Item::STATUS_APPROVED);
                $approved++;

                Log::create([
                    'tenant_id' => $this->tenantId,
                    'item_id' => $item->id,
                    'action' => Log::ACTION_APPROVE,
                    'status' => 'success',
                    'metadata' => ['auto' => true]
                ]);
            }

            if ($approved > 0) {
                $this->log("Auto-aprobados: {$approved} items");
            }
        } catch (\Exception $e) {
            $this->log("Approve EXCEPTION: {$e->getMessage()}");
        }

        return $approved;
    }

    /**
     * Paso 4: Publicación automática - Publicar items aprobados como blog posts
     */
    private function stepPublish(int $maxItems): int
    {
        $published = 0;

        try {
            $publisher = new NewsPublisher($this->tenantId);
            $approvedItems = Item::getApproved($this->tenantId, $maxItems);

            foreach ($approvedItems as $item) {
                $result = $publisher->publish($item->id);

                if ($result['success']) {
                    $published++;
                    $this->log("Publish OK: Post #{$result['post_id']} creado desde item #{$item->id}");
                } else {
                    $this->log("Publish ERROR: Item #{$item->id} - {$result['error']}");
                }

                // Pausa entre publicaciones
                usleep(100000); // 0.1 segundos
            }
        } catch (\Exception $e) {
            $this->log("Publish EXCEPTION: {$e->getMessage()}");
        }

        return $published;
    }

    /**
     * Registrar ejecución del pipeline en BD para que sea visible en el admin
     */
    private function logPipelineExecution(array $results): void
    {
        $total = $results['fetched'] + $results['rewritten'] + $results['approved'] + $results['published'];
        $hasErrors = false;

        foreach ($this->log as $msg) {
            if (strpos($msg, 'ERROR') !== false || strpos($msg, 'EXCEPTION') !== false) {
                $hasErrors = true;
                break;
            }
        }

        $summary = "Capturados: {$results['fetched']}, Reescritos: {$results['rewritten']}, Aprobados: {$results['approved']}, Publicados: {$results['published']}";
        $isCron = (php_sapi_name() === 'cli');

        Log::create([
            'tenant_id' => $this->tenantId,
            'action' => Log::ACTION_PIPELINE,
            'status' => $hasErrors ? 'failed' : 'success',
            'items_count' => $total,
            'error_message' => $hasErrors ? implode(' | ', array_slice($this->log, -3)) : null,
            'metadata' => [
                'fetched' => $results['fetched'],
                'rewritten' => $results['rewritten'],
                'approved' => $results['approved'],
                'published' => $results['published'],
                'source' => $isCron ? 'cron' : 'manual',
                'log' => array_slice($this->log, -10),
            ]
        ]);
    }

    /**
     * Registrar mensaje de log
     */
    private function log(string $message): void
    {
        $this->log[] = '[' . date('H:i:s') . '] ' . $message;
    }

    /**
     * Obtener log de ejecución
     */
    public function getLog(): array
    {
        return $this->log;
    }
}
