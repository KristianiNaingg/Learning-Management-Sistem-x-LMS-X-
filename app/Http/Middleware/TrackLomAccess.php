<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LomUserLog;

class TrackLomAccess
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Cek kalau user tidak login atau bukan student
        if (!Auth::check() || Auth::user()->id_role !== 3) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route?->getName();

        if (!$routeName) {
            return $response;
        }

        if ($routeName === 'student.url.redirect') {
        return $response;
    }

        /**
         * Detect LOM Type from route name
         * Misalnya nama route:
         * - student.page.show → "page"
         * - student.url.open → "url"
         */
        $lomType = match (true) {
            str_contains($routeName, 'page') => 'page',
            str_contains($routeName, 'assignment') => 'assignment',
            str_contains($routeName, 'quiz') => 'quiz',
            str_contains($routeName, 'label') => 'label',
            str_contains($routeName, 'folder') => 'folder',
            str_contains($routeName, 'file') => 'file',
            str_contains($routeName, 'lesson') => 'lesson',
            str_contains($routeName, 'forum') => 'forum',
            str_contains($routeName, 'url') => 'url', // URL HTML RESOURCE
            str_contains($routeName, 'infographic') => 'infographic',
            default => null,
        };

        if (!$lomType) {
            return $response;
        }

        // Cari parameter ID dari route
        $routeParameters = $route->parameters();
        $lomId = null;

        foreach ($routeParameters as $key => $value) {
            if (str_contains($key, 'id')) {
                $lomId = $value;
                break;
            }
        }

        if (!$lomId) {
            return $response;
        }

        // Simpan log
        LomUserLog::create([
            'user_id' => Auth::id(),
            'lom_id' => $lomId,
            'lom_type' => $lomType,
            'action' => 'view',
            'accessed_at' => now(),
        ]);

        return $response;
    }
}
