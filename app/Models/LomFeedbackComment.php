<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LomFeedbackComment extends Model
{
    use HasFactory;
    protected $table = 'lom_assignfeedback_comments';
    protected $primaryKey = 'id';

    // Timestamps dinonaktifkan
    public $timestamps = true;

    // Kolom yang bisa diisi (mass assignment)
    protected $fillable = [
        'submission_id',
        'user_id',
        'comment',
       
    ];



    public function submission()
    {
        return $this->belongsTo(LomAssignSubmission::class, 'submission_id', 'id');
    }

     public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
     public function assignment()
{
    return $this->hasOneThrough(
        LomAssign::class,              // Model tujuan
        LomAssignSubmission::class,    // Model perantara
        'id',                          // Foreign key di LomAssignSubmission (ke assignment)
        'id',                          // Primary key di LomAssign
        'submission_id',               // Foreign key di LomFeedbackComment (ke submission)
        'assign_id'                    // Foreign key di LomAssignSubmission (ke assignment)
    );
}


}
