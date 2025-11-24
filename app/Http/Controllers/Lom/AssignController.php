<?php

namespace App\Http\Controllers\Lom;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\LomAssign;
use App\Models\LomAssignSubmission;
use App\Models\LomFeedbackComment;


use App\Models\LomAssignGrade;
use App\Models\Subtopic;
use App\Models\LearningDimension;
use App\Models\LearningStyleOption;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AssignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $assignments = LomAssign::with('subtopic')->get();
        $menu = 'menu.v_menu_admin';
        return view('assignments.index', compact('assignments', 'menu'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $subtopicId = $request->query('sub_topic_id');
        $selectedSubtopic = $subtopicId ? Subtopic::findOrFail($subtopicId) : null;
        $learningDimensions = LearningDimension::with('options')->where('id', 1)->get();
        $subtopics = Subtopic::all();
        $menu = 'menu.v_menu_admin';

        return view('lom.assignments.create', compact('selectedSubtopic', 'learningDimensions', 'subtopics', 'subtopicId', 'menu'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
           
            $validated = $request->validate([
                'sub_topic_id' => 'required|exists:subtopics,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'content' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // Allow files up to 10MB
                'due_date' => 'required|date|after:now',
                'dimension' => 'nullable|exists:learning_dimensions,id',
                'dimension_options' => 'required_if:dimension,1|exists:learning_style_options,id',
            ], [
                'sub_topic_id.required' => 'Subtopik wajib dipilih.',
                'sub_topic_id.exists' => 'Subtopik yang dipilih tidak valid.',
                'name.required' => 'Nama tugas wajib diisi.',
                'content.file' => 'Konten harus berupa file.',
                'content.mimes' => 'File harus berupa PDF, DOC, DOCX, JPG, JPEG, atau PNG.',
                'content.max' => 'Ukuran file tidak boleh melebihi 10MB.',
                'due_date.required' => 'Tanggal tenggat wajib diisi.',
                'due_date.after' => 'Tanggal tenggat harus di masa depan.',
                'dimension_options.required_if' => 'Pilih salah satu opsi untuk dimensi pembelajaran.',
                'dimension_options.exists' => 'Opsi gaya belajar yang dipilih tidak valid.',
            ]);

            $subtopic = Subtopic::findOrFail($validated['sub_topic_id']);

            $assignment = DB::transaction(function () use ($validated, $request) {
                $contentPath = null;
                if ($request->hasFile('content')) {
                    $contentPath = $request->file('content')->store('assignments', 'public');
                }

                $assignment = LomAssign::create([
                    'subtopic_id' => $validated['sub_topic_id'],
                    'name' => $validated['name'],
                    'description' => $validated['description'],
                    'content' => $contentPath,
                    'due_date' => Carbon::parse($validated['due_date']),
                    'learning_style_option_id' => $validated['dimension_options'] ?? null,
                    'created_at' => now(),
                ]);
                return $assignment;
            });

            $topic = $subtopic->topic;
            $course_id = $topic->course_id;
            $topic_id = $topic->id;

            return redirect()->route('topics.show', [$course_id, $topic_id])
                ->with('success', 'Assg\ignment created successfully');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Gagal menyimpan tugas: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $assignment = LomAssign::with(['subtopic.topic.course', 'learningStyleOption', 'submissions.user', 'grades'])->findOrFail($id);
            $subtopic = $assignment->subtopic;
            $topic = $subtopic->topic;
            $course = $topic->course;
            $menu = 'menu.v_menu_admin';

            return view('assignments.show', compact('assignment', 'subtopic', 'topic', 'course', 'menu'));
        } catch (\Exception $e) {
            Log::error('Gagal menampilkan tugas: ' . $e->getMessage(), ['assignment_id' => $id]);
            return redirect()->back()->withErrors(['error' => 'Gagal menampilkan tugas.']);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        try {
            $assignment = LomAssign::with(['subtopic', 'learningStyleOption'])->findOrFail($id);
            $learningDimensions = LearningDimension::with('options')->where('id', 1)->get();
            $subtopic = $assignment->subtopic;
            $subtopics = Subtopic::all();
            $menu = 'menu.v_menu_admin';

            return view('assignments.edit', compact('assignment', 'learningDimensions', 'subtopic', 'subtopics', 'menu'));
        } catch (\Exception $e) {
            Log::error('Gagal memuat form edit tugas: ' . $e->getMessage(), ['assignment_id' => $id]);
            return redirect()->back()->withErrors(['error' => 'Gagal memuat form edit tugas.']);
        }
    }

    
    /**
     * Display the specified assignment.
     *
     * @param int $id
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showStudent($id)
    {
        try {
            Log::info('Accessing assignment show page', ['assignment_id' => $id]);

            // Retrieve assignment with relations
            $assignment = LomAssign::with([
                'subtopic.topic.course',
                'submissions' => fn($query) => $query->with(['user', 'grade']),
                'grades' => fn($query) => $query->where('user_id', auth()->id())
            ])->findOrFail($id);

            // Prepare view data
            $viewData = [
                'menu' => 'menu.v_menu_admin',
                'content' => 'lom.assignments.showAssignmentStudent',
                'title' => $assignment->name,
                'assignment' => $assignment,
                'subTopic' => $assignment->subtopic,
                'topic' => $assignment->subtopic->topic,
                'course' => $assignment->subtopic->topic->course,
                'submission' => $assignment->submissions->first(),
                'grade' => $assignment->grades->first()
            ];

    
            return view('layouts.v_template', $viewData);
        } catch (\Exception $e) {
            Log::error('Failed to display assignment page', [
                'assignment_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to display assignment: ' . $e->getMessage()]);
        }
    }

    public function submit(Request $request, LomAssign $assignment)
    {
        $request->validate([
            'file_path' => 'required|array|min:1',
            'file_path.*' => 'file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png,gif,bmp|max:51200',
            'confirm_submission' => 'required|accepted',
        ]);

        // Check due date
        if ($assignment->due_date && now()->greaterThan($assignment->due_date)) {
            return back()->withErrors(['error' => 'Batas waktu pengumpulan sudah lewat.']);
        }

        // Check existing submission
        $existing = LomAssignSubmission::where('assign_id', $assignment->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing && $existing->status === 'submitted') {
            return back()->withErrors(['error' => 'Anda sudah mengumpulkan tugas ini.']);
        }

        // Store files
        $paths = [];
        foreach ($request->file('file_path') as $file) {
            $path = $file->store('assignments', 'public');
            $paths[] = $path; // simpan path relatif
        }

        LomAssignSubmission::create([
            'assign_id' => $assignment->id,
            'user_id' => auth()->id(),
            'file_path' => $paths,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Tugas berhasil dikumpulkan!');
    }

    /**
     * Cancel submission
     */
    public function cancel(LomAssignSubmission $submission)
    {
        // Ownership check
        if ($submission->user_id !== auth()->id()) {
            return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk membatalkan tugas ini.']);
        }

        // Due date check
        if ($submission->assignment->due_date && now()->greaterThan($submission->assignment->due_date)) {
            return back()->withErrors(['error' => 'Batas waktu sudah lewat. Tidak bisa dibatalkan.']);
        }

        // Delete files
        foreach ($submission->files as $file) {
            $cleanPath = str_replace('storage/', '', $file);
            Storage::disk('public')->delete($cleanPath);
        }

        $submission->delete();

        return back()->with('success', 'Pengumpulan tugas dibatalkan.');
    }



    public function storeComment(Request $request, $submissionId)
{
    $request->validate([
        'comment' => 'required|string|max:500',
    ]);

    $submission = LomAssignSubmission::findOrFail($submissionId);

    LomFeedbackComment::create([
        'assign_id' => $submission->assign_id,
        'submission_id' => $submission->id,
        'user_id' => auth()->id(),
        'comment' => $request->comment,
    ]);

    return back()->with('success', 'Comment added successfully!');
}

}
