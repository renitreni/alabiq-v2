<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    /**
     * Effective settings (DB overrides merged over config defaults).
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->settingsService->all()]);
    }

    /**
     * Bulk update settings; every accepted key is written and audited.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable',
        ]);

        $results = [];
        foreach ($validated['settings'] as $key => $value) {
            try {
                $results[$key] = $this->settingsService->set((string) $key, $value, Auth::user(), $request->ip());
            } catch (\DomainException $e) {
                return response()->json(['message' => $e->getMessage(), 'key' => $key], 422);
            }
        }

        return response()->json(['data' => $this->settingsService->all(), 'changes' => $results]);
    }
}
