<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\SonarrServiceContract;
use App\Models\ManualImportFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ManualImportController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $flags = ManualImportFlag::query()
            ->where('resolved', false)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $unresolvedCount = ManualImportFlag::where('resolved', false)->count();

        return Inertia::render('ManualImport', [
            'flags' => $flags,
            'unresolvedCount' => $unresolvedCount,
        ]);
    }

    public function markResolved(Request $request, ManualImportFlag $flag): \Illuminate\Http\RedirectResponse
    {
        $flag->update([
            'resolved' => true,
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Manual import flag marked as resolved');
    }

    public function triggerSonarrImport(Request $request, ManualImportFlag $flag, SonarrServiceContract $sonarrService): \Illuminate\Http\RedirectResponse
    {
        if (empty($flag->content_path)) {
            return back()->withErrors(['message' => 'Content path is required for manual import']);
        }

        try {
            // Get manual import candidates from Sonarr
            $candidates = $sonarrService->getManualImportCandidates($flag->content_path, filterExistingFiles: true);

            if (empty($candidates)) {
                return back()->withErrors(['message' => 'No importable files found in the specified path']);
            }

            // Submit all candidates for import
            $result = $sonarrService->submitManualImport($candidates);

            // Mark flag as resolved
            $flag->update([
                'resolved' => true,
                'resolved_at' => now(),
            ]);

            Log::info('sonarr.manual_import.triggered', [
                'flag_id' => $flag->id,
                'content_path' => $flag->content_path,
                'candidates_count' => count($candidates),
            ]);

            return back()->with('success', 'Manual import triggered successfully. Sonarr is processing the files.');
        } catch (\Exception $e) {
            Log::error('sonarr.manual_import.failed', [
                'flag_id' => $flag->id,
                'content_path' => $flag->content_path,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['message' => 'Failed to trigger manual import: ' . $e->getMessage()]);
        }
    }
}

