<?php

namespace App\Http\Controllers\Lom;



use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\LomQuiz;
use App\Models\LomQuizAnswer;
use App\Models\LomQuizQuestion;
use App\Models\LomQuizAttempt;
use App\Models\LomQuizGrade;

use App\Models\Subtopic;
use App\Models\LearningDimension;
use App\Models\LearningStyleOption;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
class QuizQuestionController extends Controller
{
   /**
     * Show the form for creating a new question for a quiz.
     */
    public function create($quiz_id)
    {
        $quiz = LomQuiz::findOrFail($quiz_id);
        $menu = 'menu.v_menu_admin';
        return view('lom.quizzes.question.create', compact('quiz','menu'));
    }

    /**
     * Store questions for a specific quiz.
     */
    public function store(Request $request, $quiz_id)
    {
        
        
        $quiz = LomQuiz::findOrFail($quiz_id);

        $validated = $request->validate([
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.poin' => 'required|integer|min:0',
            'questions.*.options_a' => 'required|string',
            'questions.*.options_b' => 'required|string',
            'questions.*.options_c' => 'required|string',
            'questions.*.options_d' => 'required|string',
            'questions.*.correct_answer' => 'required|in:A,B,C,D',
        ]);

        try {
            // Update quiz title if provided
            if ($request->has('quiz_title')) {
                $quiz->name = $request->input('quiz_title');
                $quiz->save();
            }

            foreach ($validated['questions'] as $questionData) {
                LomQuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question_text' => $questionData['question_text'],
                    'poin' => $questionData['poin'],
                    'options_a' => $questionData['options_a'],
                    'options_b' => $questionData['options_b'],
                    'options_c' => $questionData['options_c'],
                    'options_d' => $questionData['options_d'],
                    'correct_answer' => $questionData['correct_answer'],
                ]);
            }

            return redirect()->route('quizs.show', $quiz->id)->with('success', 'Questions added successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to add questions: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to add questions: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show the form for editing a question.
     */
    public function edit($quiz_id, $question_id)
    {
        $quiz = LomQuiz::findOrFail($quiz_id);
        $menu = 'menu.v_menu_admin';
        $question = LomQuizQuestion::where('quiz_id', $quiz_id)->findOrFail($question_id);
        return view('lom.quizzes.question.edit', compact('quiz', 'question','menu'));
    }

    /**
     * Update a specific question.
     */
    public function update(Request $request, $quiz_id, $question_id)
    {
        $quiz = LomQuiz::findOrFail($quiz_id);
        $question = LomQuizQuestion::where('quiz_id', $quiz_id)->findOrFail($question_id);

        $validated = $request->validate([
            'question_text' => 'required|string',
            'poin' => 'required|integer|min:0',
            'options_a' => 'required|string',
            'options_b' => 'required|string',
            'options_c' => 'required|string',
            'options_d' => 'required|string',
            'correct_answer' => 'required|in:A,B,C,D',
        ]);

        try {
            $question->update($validated);
            return redirect()->route('quizs.show', $quiz->id)->with('success', 'Question updated successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to update question: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update question: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Delete a specific question.
     */
    public function destroy($quiz_id, $question_id)
    {
        $quiz = LomQuiz::findOrFail($quiz_id);
        $question = LomQuizQuestion::where('quiz_id', $quiz_id)->findOrFail($question_id);

        try {
            $question->delete();
            return redirect()->route('quizs.show', $quiz->id)->with('success', 'Question deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to delete question: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to delete question: ' . $e->getMessage()]);
        }
    }
}
