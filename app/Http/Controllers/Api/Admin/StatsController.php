<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminStatsService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __construct(
        private AdminStatsService $statsService
    ) {}

    /**
     * Platform-wide statistics for the super admin dashboard.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->statsService->platformStatistics()]);
    }
}
