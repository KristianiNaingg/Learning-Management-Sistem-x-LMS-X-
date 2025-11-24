<?php

namespace App\Http\Controllers;

use App\Models\LomUserLog;
use App\Models\LomUrl;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LomUserLogController extends Controller
{
    /**
     * Daftar mahasiswa & total durasi belajar
     */
    public function index()
    {
        $students = User::where('id_role', 3)
            ->leftJoin('lom_user_logs', 'users.id', '=', 'lom_user_logs.user_id')
            ->select(
                'users.id',
                'users.name',
                DB::raw("
                    SUM(
                        CASE 
                            WHEN lom_user_logs.lom_type IN ('label','file','lesson','infographic') THEN COALESCE(lom_user_logs.views,0)
                            ELSE 1
                        END
                    ) as total_access
                "),
                DB::raw("COALESCE(SUM(lom_user_logs.duration),0) as total_duration")
            )
            ->groupBy('users.id','users.name')
            ->orderByDesc('total_duration')
            ->get();

        return view('layouts.v_template', [
            'menu'      => 'menu.v_menu_admin',
            'content'   => 'admin.user.users_log',
            'students'  => $students,
        ]);
    }

    /**
     * Detail aktivitas LOM per mahasiswa
     */
    public function show($id)
    {
        $student = User::findOrFail($id);

        $logs = LomUserLog::where('user_id', $id)
            ->select(
                'lom_id',
                'lom_type',
                DB::raw("
                    SUM(
                        CASE 
                            WHEN lom_type IN ('label','file','lesson','infographic') THEN COALESCE(views,0)
                            ELSE 1
                        END
                    ) as total_access
                "),
                DB::raw('SUM(duration) as total_duration'),
                DB::raw('MAX(accessed_at) as last_access')
            )
            ->groupBy('lom_id','lom_type')
            ->orderByDesc('total_access')
            ->get();

        return view('layouts.v_template', [
            'menu'     => 'menu.v_menu_admin',
            'content'  => 'admin.user.user_log_detail',
            'student'  => $student,
            'logs'     => $logs,
        ]);
    }

    /**
     * Update views & duration untuk LOM tertentu
     * LOM: label, file, lesson, infographic, page, folder, forum, url
     */
    public function updateViewsAndDuration(Request $request)
    {
        if (!auth()->check() || auth()->user()->id_role !== 3) {
            return response()->json(['message' => 'Unauthorized'],403);
        }

        $validated = $request->validate([
            'lom_id'   => 'required|integer',
            'lom_type' => 'required|string',
            'duration' => 'required|numeric|min:0',
            'views'    => 'required|integer|min:0',
        ]);

        if (in_array($validated['lom_type'], ['quiz','assignment'])) {
            return response()->json(['message'=>'Use updateDuration for Quiz/Assignment'],400);
        }

        $userId = auth()->id();

        $log = LomUserLog::firstOrNew([
            'user_id' => $userId,
            'lom_id'  => $validated['lom_id'],
            'lom_type'=> $validated['lom_type']
        ]);

        $log->duration = ($log->duration ?? 0) + $validated['duration'];
        $log->views    = ($log->views ?? 0) + $validated['views'];
        $log->accessed_at = now();
        $log->save();

        return response()->json(['message'=>'Views & duration recorded successfully']);
    }

    /**
     * Update duration untuk Quiz / Assignment (per interaksi nyata)
     */
    public function updateDuration(Request $request)
    {
        if (!auth()->check() || auth()->user()->id_role !== 3) {
            return response()->json(['message' => 'Unauthorized'],403);
        }

        $validated = $request->validate([
            'lom_id'   => 'required|integer',
            'lom_type' => 'required|string',
            'duration' => 'required|numeric|min:0',
        ]);

        if (in_array($validated['lom_type'], ['label','file','lesson','infographic','page','folder','forum','url'])) {
            return response()->json(['message'=>'Use updateViewsAndDuration for this LOM type'],400);
        }

        $userId = auth()->id();

        $log = LomUserLog::firstOrNew([
            'user_id' => $userId,
            'lom_id'  => $validated['lom_id'],
            'lom_type'=> $validated['lom_type']
        ]);

        $log->duration = ($log->duration ?? 0) + $validated['duration'];
        $log->accessed_at = now();
        $log->save();

        return response()->json(['message'=>'Duration recorded successfully']);
    }

    /**
     * Redirect ke URL & track
     */
    public function redirect($id)
    {
        $url = LomUrl::findOrFail($id);

        LomUserLog::create([
            'user_id' => Auth::id(),
            'lom_id' => $url->id,
            'lom_type' => 'url',
            'action' => 'view',
            'accessed_at' => now(),
        ]);

        return redirect()->away($url->url_link);
    }

    // Dummy / placeholder methods
    public function create() {}
    public function store(Request $request) {}
    public function edit(LomUserLog $lomUserLog) {}
    public function update(Request $request, LomUserLog $lomUserLog) {}
    public function destroy(LomUserLog $lomUserLog) {}
}
