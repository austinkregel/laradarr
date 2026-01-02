<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shared = [];
        
        if ($request->user()) {
            $request->user()->load('favorites');
            
            // Share unresolved manual import count
            $shared['unresolvedManualImportsCount'] = \App\Models\ManualImportFlag::where('resolved', false)->count();
        }

        // Share Plex server info for building URLs
        try {
            $plexService = app(\App\Contracts\PlexServiceContract::class);
            $shared['plexServerInfo'] = $plexService->getServerInfo();
        } catch (\Exception $e) {
            // Fail silently if Plex is not configured
            $shared['plexServerInfo'] = null;
        }
        
        return array_merge(parent::share($request), $shared);
    }
}
