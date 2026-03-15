<?php

namespace CrossPublisherAdmin\Controllers;

use CrossPublisherAdmin\Models\Queue;
use CrossPublisherAdmin\Models\Relation;
use CrossPublisherAdmin\Models\Log;
use CrossPublisherAdmin\Models\DomainGroup;
use Screenart\Musedock\View;

class DashboardController
{
    public function index()
    {
        $queueCounts = Queue::countByStatus();
        $totalRelations = Relation::count();
        $tokensToday = Log::tokensToday();
        $publishedToday = Log::publishedToday();
        $recentLogs = Log::all([], 20);
        $groups = DomainGroup::allWithCounts();

        return View::renderSuperadmin('plugins.cross-publisher.dashboard', [
            'queueCounts' => $queueCounts,
            'totalRelations' => $totalRelations,
            'tokensToday' => $tokensToday,
            'publishedToday' => $publishedToday,
            'recentLogs' => $recentLogs,
            'groups' => $groups,
            'pendingCount' => $queueCounts['pending'] ?? 0,
            'processingCount' => $queueCounts['processing'] ?? 0,
            'completedCount' => $queueCounts['completed'] ?? 0,
            'failedCount' => $queueCounts['failed'] ?? 0,
        ]);
    }
}
