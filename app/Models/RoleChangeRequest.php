<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_role',
        'requested_role',
        'reason',
        'status',
        'reviewed_by',
        'admin_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function approve($adminId, $notes = null)
    {
        \DB::transaction(function () use ($adminId, $notes) {
            // Update the role change request
            $this->update([
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'admin_notes' => $notes,
                'reviewed_at' => now(),
            ]);

            // Update user role with error handling
            if ($this->user) {
                $updated = $this->user->update(['role' => $this->requested_role]);
                if (!$updated) {
                    throw new \Exception('Failed to update user role');
                }
                \Log::info('User role updated successfully', [
                    'user_id' => $this->user_id,
                    'old_role' => $this->current_role,
                    'new_role' => $this->requested_role
                ]);
            } else {
                throw new \Exception('User not found for role change request');
            }
        });
    }

    public function reject($adminId, $notes = null)
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $adminId,
            'admin_notes' => $notes,
            'reviewed_at' => now(),
        ]);
    }
}