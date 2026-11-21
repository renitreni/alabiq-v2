<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Recent audit trail entries, optionally filtered by action.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->auditLogService->recent($request->query('action'), 100),
        ]);
    }
}
