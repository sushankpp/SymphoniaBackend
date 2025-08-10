<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\RoleChangeRequest;

class CheckUserAccounts extends Command
{
    protected $signature = 'app:check-user-accounts';
    protected $description = 'Check all user accounts and their roles';

    public function handle()
    {
        $this->info("=== User Accounts ===");
        
        $users = User::all();
        foreach ($users as $user) {
            $this->line("ID: {$user->id} | Name: {$user->name} | Email: {$user->email} | Role: {$user->role}");
        }
        
        $this->info("\n=== Role Change Requests ===");
        $requests = RoleChangeRequest::with(['user', 'reviewer'])->get();
        foreach ($requests as $request) {
            $this->line("Request ID: {$request->id}");
            $this->line("  User: {$request->user->name} (ID: {$request->user_id})");
            $this->line("  From: {$request->current_role} → To: {$request->requested_role}");
            $this->line("  Status: {$request->status}");
            if ($request->reviewer) {
                $this->line("  Reviewed by: {$request->reviewer->name} (ID: {$request->reviewed_by})");
            }
            $this->line("  Reason: {$request->reason}");
            $this->line("  Admin Notes: {$request->admin_notes}");
            $this->line("---");
        }
        
        return Command::SUCCESS;
    }
}
