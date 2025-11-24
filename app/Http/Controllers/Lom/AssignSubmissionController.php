<?php

namespace App\Http\Controllers\Lom;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\LomAssignSubmission;
use App\Models\LomAssign;
use App\Models\CourseUser;


class AssignSubmissionController extends Controller
{
    public function index()
    {
       
    }

/**
     * Display the specified assignment detail.
     */
    public function show($id)
{
    try {
        $assignment = LomAssign::with([
            'subtopic.topic.course',
            'submissions.user',           // user yang mengumpulkan
            'submissions.grade',          // nilai per submission
            'submissions.comments.user',  // ✅ komentar dan user pemberi komentar
            'grades'                      // semua grade yang terkait
        ])->findOrFail($id);

        $subtopic = $assignment->subtopic;
        $topic = $subtopic->topic;
        $course = $topic->course;

        // jumlah peserta (student)
        $participants = CourseUser::where('course_id', $course->id)
            ->where('participant_role', 'Student')
            ->count();

        // Hitung jumlah submission
        $submitted = $assignment->submissions->count();

        return view('layouts.v_template', [
            'menu' => 'menu.v_menu_admin',
            'content' => 'lom.assignments.showAssignmentDosen',
            'title' => $assignment->name,
            'assignment' => $assignment,
            'subtopic' => $subtopic,
            'topic' => $topic,
            'course' => $course,
            'participants' => $participants,
            'submitted' => $submitted,
            'submitted_at' => now(),
            'submissions' => $assignment->submissions
        ]);

    } catch (\Exception $e) {
        return redirect()->back()
            ->withErrors(['error' => 'Gagal menampilkan halaman: ' . $e->getMessage()]);
    }
}
    public function cancel(Request $request)
    {
        $submission = LomAssignSubmission::find($request->submission_id);

        if (!$submission || $submission->user_id !== auth()->id()) {
            return redirect()->back()->with('error', 'Tidak dapat membatalkan submission ini.');
        }

        // Hapus file jika ada
        if ($submission->file_path && is_array($submission->file_path)) {
            foreach ($submission->file_path as $file) {
                if (file_exists(public_path($file))) {
                    unlink(public_path($file));
                }
            }
        }

        $submission->delete();

        return redirect()->back()->with('success', 'Submission berhasil dibatalkan. Silakan unggah ulang jika diperlukan.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'assign_id' => 'required|exists:lom_assigns,id',
            'file_path.*' => 'required|file|max:10240' // contoh validasi max 10MB
        ]);

        $files = [];
        if ($request->hasFile('file_path')) {
            foreach ($request->file('file_path') as $file) {
                $path = $file->store('uploads/submissions', 'public');
                $files[] = $path;
            }
        }

        LomAssignSubmission::create([
            'assign_id' => $request->assign_id,
            'user_id' => auth()->id(),
            'file_path' => $files,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Submission berhasil diunggah.');
    }








    
}

