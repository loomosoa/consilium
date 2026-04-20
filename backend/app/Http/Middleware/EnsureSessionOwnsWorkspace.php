<?php

namespace App\Http\Middleware;

use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ensures the requested workspace/column/generation belongs to the current session.
 * Returns 403 if the session does not own the resource.
 */
class EnsureSessionOwnsWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        $sessionId = $request->session()->getId();

        // Check workspace route parameter
        if ($workspaceId = $request->route('workspaceId')) {
            $workspace = Workspace::find($workspaceId);

            if ($workspace === null) {
                return response()->json(['message' => 'Workspace not found'], 404);
            }

            if ($workspace->session_id !== $sessionId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        // Check column route parameter
        if ($columnId = $request->route('columnId')) {
            $column = ColumnConversation::with('workspace')->find($columnId);

            if ($column === null) {
                return response()->json(['message' => 'Column not found'], 404);
            }

            if ($column->workspace === null || $column->workspace->session_id !== $sessionId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        // Check generation route parameter
        if ($generationId = $request->route('generationId')) {
            $generation = Generation::with('column.workspace')->find($generationId);

            if ($generation === null) {
                return response()->json(['message' => 'Generation not found'], 404);
            }

            if ($generation->column === null || $generation->column->workspace === null) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            if ($generation->column->workspace->session_id !== $sessionId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        return $next($request);
    }
}
