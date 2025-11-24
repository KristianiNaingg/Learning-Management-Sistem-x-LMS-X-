<?php

namespace App\Http\Controllers\Lom;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\LomAssign;
use App\Models\LomAssignSubmission;
use App\Models\LomFeedbackComment;
use Illuminate\Support\Facades\Auth;

use App\Models\LomAssignGrade;
use App\Models\Subtopic;
use App\Models\LearningDimension;
use App\Models\LearningStyleOption;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AssignGradeController extends Controller
{
   /**
     * Simpan atau update nilai (grade) untuk submission.
     */
    public function store(Request $request, $submissionId)
{
    $request->validate([
        'grade' => 'required|numeric|min:0|max:100',
        'feedback' => 'nullable|string|max:500',
    ]);

    $submission = LomAssignSubmission::findOrFail($submissionId);

    // 🔹 Simpan atau perbarui grade
    $grade = LomAssignGrade::updateOrCreate(
        ['submission_id' => $submission->id],
        [
            'assign_id' => $submission->assign_id,
            'user_id' => $submission->user_id,
            'graded_by' => Auth::id(),
            'grade' => $request->grade,
            'feedback' => $request->feedback,
        ]
    );

    // 🔹 Tambahkan atau perbarui komentar otomatis dari feedback
    if (!empty($request->feedback)) {
        LomFeedbackComment::updateOrCreate(
            [
                'submission_id' => $submission->id,
                'user_id' => Auth::id(), // dosen yang beri feedback
            ],
            [
                'assign_id' => $submission->assign_id,
                'comment' => $request->feedback,
            ]
        );
    }

    return redirect()->back()->with('success', 'Grade and feedback saved successfully!');
}

}
