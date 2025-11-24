<?php

namespace App\Http\Controllers\Lom;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\LomQuiz;

use App\Models\Subtopic;
use App\Models\LearningDimension;
use App\Models\LearningStyleOption;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;


class QuizController extends Controller
{
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

        return view('lom.quizzes.create', compact('selectedSubtopic', 'learningDimensions', 'subtopics', 'subtopicId', 'menu'));
    }

   /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate form inputs with custom error messages
            $validated = $request->validate([
                'sub_topic_id' => 'required|integer|exists:subtopics,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'learning_style_id' => 'required|integer|exists:learning_dimensions,id',
                'time_open' => 'nullable|date',
                'time_close' => 'nullable|date|after:time_open',
                'time_limit' => 'nullable|integer|min:0', // Input in seconds
                'max_attempts' => 'required|integer|min:1',
                'grade_to_pass' => 'required|numeric|min:0|max:100',
                'action' => 'required|in:save_return,save_display',
            ], [
                'sub_topic_id.exists' => 'The selected sub-topic is invalid.',
                'name.required' => 'Please provide a quiz name.',
                'name.max' => 'The quiz name cannot exceed 255 characters.',
                'learning_style_id.required' => 'Please select a learning dimension.',
                'learning_style_id.exists' => 'The selected dimension is invalid.',
                'time_close.after' => 'The close time must be after the open time.',
                'time_limit.min' => 'The time limit cannot be negative.',
                'max_attempts.required' => 'Please specify the number of attempts.',
                'max_attempts.min' => 'At least one attempt is required.',
                'grade_to_pass.required' => 'Please specify the grade to pass.',
                'grade_to_pass.min' => 'The grade to pass cannot be negative.',
                'grade_to_pass.max' => 'The grade to pass cannot exceed 100.',
                'action.in' => 'Invalid action selected.',
            ]);

           

            // Create quiz
            $quiz = LomQuiz::create([
                'subtopic_id' => $validated['sub_topic_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'time_open' => $validated['time_open'],
                'time_close' => $validated['time_close'],
                'time_limit' => $validated['time_limit'], // Already in seconds
                'max_attempts' => $validated['max_attempts'],
                'grade_to_pass' => $validated['grade_to_pass'],
                'learning_dimension_id' => $validated['learning_style_id'],
            ]);

            Log::info('Quiz created successfully', [
                'quiz_id' => $quiz->id,
                'subtopic_id' => $validated['sub_topic_id'],
                'name' => $validated['name'],
                'learning_dimension_id' => $validated['learning_style_id'],
                'time_limit_seconds' => $validated['time_limit'],
                'action' => $validated['action'],
            ]);

            $subtopic = Subtopic::findOrFail($validated['sub_topic_id']);
            $topic = $subtopic->topic;
            $course_id = $topic->course_id;
            $topic_id = $topic->id;

            $topic = $subtopic->topic;
            $course_id = $topic->course_id;
            $topic_id = $topic->id;

            // Redirect based on action
            if ($validated['action'] === 'save_return') {
                return redirect()->route('topics.show', [$course_id, $topic_id])
                    ->with('success', 'Quiz created successfully.');
            } else {
                return redirect()->route('quizs.show', $quiz->id)
                    ->with('success', 'Quiz created successfully.');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for quiz submission', [
                'errors' => $e->errors(),
                'input' => $request->except('_token'),
            ]);
            return redirect()->back()
                ->withErrors($e->errors())
                ->with('error', 'Please correct the errors below and try again.')
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Unexpected error during quiz submission', [
                'message' => $e->getMessage(),
                'input' => $request->except('_token'),
            ]);
            return redirect()->back()
                ->with('error', 'An unexpected error occurred while creating the quiz. Please try again or contact support.')
                ->withInput();
        }

    
    }
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */


   
public function show($id)
{
    try {
        // Log initial request details
        Log::info('Attempting to display quiz show page', [
            'quiz_id' => $id,
            'user_id' => Auth::id() ?? 'guest',
            'user_role' => Auth::user()->role ?? 'unknown',
            'request_path' => request()->path(),
            'referer' => request()->headers->get('referer') ?? 'none'
        ]);

        // Load quiz with relations: subtopic → topic → course
        Log::debug('Loading quiz data for ID: ' . $id);
        $quiz = LomQuiz::with(['subtopic.topic.course', 'attempts.user'])->findOrFail($id);

        Log::debug('Quiz loaded successfully', [
            'quiz_name' => $quiz->name,
            'subtopic_id' => $quiz->subtopic_id,
            'has_subtopic' => !is_null($quiz->subtopic)
        ]);

        // Extract related data
        $subtopic = $quiz->subtopic;
        $topic = $subtopic->topic ?? null;
        $course = $topic->course ?? null;

        if (is_null($subtopic) || is_null($topic) || is_null($course)) {
            Log::warning('Missing related data for quiz', [
                'quiz_id' => $id,
                'subtopic' => $subtopic?->id ?? 'null',
                'topic' => $topic?->id ?? 'null',
                'course' => $course?->id ?? 'null'
            ]);
        }

        // Process participant attempts
        Log::debug('Processing participant attempts');
        $participants = $quiz->attempts
            ->groupBy('user_id')
            ->map(function ($attempts, $userId) {
                $highestAttempt = $attempts->sortByDesc('score')->first();
                Log::debug('Processed participant attempt', [
                    'user_id' => $userId,
                    'highest_score' => $highestAttempt->score,
                    'attempt_number' => $highestAttempt->attempt_number
                ]);
                return [
                    'user' => $highestAttempt->user,
                    'highest_score' => $highestAttempt->score,
                    'attempt_number' => $highestAttempt->attempt_number,
                    'start_time' => $highestAttempt->start_time,
                    'end_time' => $highestAttempt->end_time,
                ];
            })->sortByDesc('highest_score')->values();

        Log::debug('Participants processed', ['participant_count' => $participants->count()]);

        // Prepare and return view
        Log::info('Rendering quiz show view', [
            'quiz_id' => $id,
            'view' => 'layouts.v_template',
            'content_view' => 'lom.quizzes.show'
        ]);

        $data = [
            'menu' => 'menu.v_menu_admin',
            'content' => 'lom.quizzes.show',
            'title' => $quiz->name,
            'quiz' => $quiz,
            'subtopic' => $subtopic,
            'topic' => $topic,
            'course' => $course,
            'options' => $quiz->options,
            'participants' => $participants,
        ];

        return view('layouts.v_template', $data);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        Log::error('Quiz not found', [
            'quiz_id' => $id,
            'user_id' => Auth::id() ?? 'guest',
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return redirect()->back()->withErrors(['error' => 'Quiz tidak ditemukan.']);
    } catch (\Exception $e) {
        Log::error('Failed to display quiz show page', [
            'quiz_id' => $id,
            'user_id' => Auth::id() ?? 'guest',
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_data' => request()->all()
        ]);
        return redirect()->back()->withErrors(['error' => 'Gagal menampilkan halaman: ' . $e->getMessage()]);
    }
}

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MDLQuiz  $mDLQuiz
     * @return \Illuminate\Http\Response
     */


     public function showMahasiswa($quizId)
{
    // Ambil kuis beserta pertanyaan dan percobaan user
    $quiz = LomQuiz::with([
        'questions',
        'attempts' => fn($query) => $query->where('user_id', Auth::id())
    ])->findOrFail($quizId);

    // Ambil subtopic, topic, dan course
    $subtopic = $quiz->subtopic;      // Subtopic
    $topic = $subtopic->topic;        // Topic
    $course = $topic->course;         // Course

    $now = now();

    // Cek apakah kuis tersedia berdasarkan waktu
    if (($quiz->time_open && $quiz->time_open > $now) || ($quiz->time_close && $quiz->time_close < $now)) {
        return redirect()->route('dashboard2')->with('error', 'This quiz is not available.');
    }

    // Hitung percobaan kuis
    $attemptCount = $quiz->attempts->count();
    $maxAttempts = $quiz->max_attempts ?? 0;
    $canAttempt = $maxAttempts === 0 || $attemptCount < $maxAttempts;

    // Jika sudah mencapai batas percobaan, arahkan ke hasil terakhir
    if (!$canAttempt) {
        $lastAttempt = $quiz->attempts->sortByDesc('created_at')->first();
        if ($lastAttempt) {
            return redirect()->route('quiz.result', [$quiz->id, $lastAttempt->id])
                ->with('status', 'You have reached the maximum number of attempts. Here are your results.');
        }
        return redirect()->route('dashboard2')->with('error', 'No attempts found for this quiz.');
    }

    // Return view dengan variabel yang sudah dirapikan
    return view('layouts.v_template', [
        'menu'         => 'menu.v_menu_admin',
        'content'      => 'lom.quizzes.show_mahasiswa',
        'quiz'         => $quiz,
        'course'       => $course,
        'topic'        => $topic,
        'subtopic'     => $subtopic,
        'attemptCount' => $attemptCount,
        'maxAttempts'  => $maxAttempts,
        'canAttempt'   => $canAttempt,
        'title'        => $quiz->name,
        'questions'    => $quiz->questions
    ]);
}





    }




