<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LomAssignSubmission extends Model
{
    use HasFactory;

    protected $table = 'lom_assign_submissions';

    protected $fillable = [
        'assign_id',
        'user_id',
        'file_path',
        'status',
        'submitted_at',
    ];


    protected $casts = [
        'file_path' => 'array',
        'submitted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getFilesAttribute()
    {
        if (is_array($this->file_path)) {
            return collect($this->file_path);
        }

        try {
            return collect(json_decode($this->file_path, true));
        } catch (\Exception $e) {
            return collect();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function assignment()
    {
        return $this->belongsTo(LomAssign::class, 'assign_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function grade()
    {
        return $this->hasOne(LomAssignGrade::class, 'submission_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(LomFeedbackComment::class, 'submission_id', 'id');
    }

    // Ambil komentar terbaru (untuk tampilan ringkas di table)
    public function latestComment()
    {
        return $this->hasOne(LomFeedbackComment::class, 'submission_id', 'id')->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public function isGraded()
    {
        return $this->grade !== null;
    }

    public function gradeValue()
    {
        return optional($this->grade)->grade ?? '-';
    }

    public function feedback()
    {
        return optional($this->grade)->feedback;
    }
}
