<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RoleChangeRequest;

class ValidateUserArtistConsistency extends Command
{
    protected $signature = 'app:validate-user-artist-consistency {--fix : Automatically fix inconsistencies}';
    protected $description = 'Validate and optionally fix user-artist role inconsistencies';

    public function handle()
    {
        $this->info('Checking user-artist consistency...');
        
        $inconsistencies = RoleChangeRequest::validateUserArtistConsistency();
        
        if (empty($inconsistencies)) {
            $this->info('✅ No inconsistencies found! User-artist data is consistent.');
            return 0;
        }
        
        $this->warn('❌ Found ' . count($inconsistencies) . ' inconsistencies:');
        
        foreach ($inconsistencies as $inconsistency) {
            $this->line('- ' . $inconsistency['message']);
            if (isset($inconsistency['user_id'])) {
                $this->line('  User ID: ' . $inconsistency['user_id']);
            }
            if (isset($inconsistency['user_name'])) {
                $this->line('  User Name: ' . $inconsistency['user_name']);
            }
            if (isset($inconsistency['user_role'])) {
                $this->line('  User Role: ' . $inconsistency['user_role']);
            }
            $this->line('');
        }
        
        if ($this->option('fix')) {
            if ($this->confirm('Do you want to fix these inconsistencies?')) {
                $this->info('Fixing inconsistencies...');
                
                $fixed = RoleChangeRequest::fixUserArtistInconsistencies();
                
                if (empty($fixed)) {
                    $this->info('No fixes were applied.');
                } else {
                    $this->info('✅ Fixed ' . count($fixed) . ' inconsistencies:');
                    foreach ($fixed as $fix) {
                        $this->line('- ' . $fix['type'] . ': ' . json_encode($fix));
                    }
                }
                
                $this->info('Running validation again...');
                $remainingInconsistencies = RoleChangeRequest::validateUserArtistConsistency();
                
                if (empty($remainingInconsistencies)) {
                    $this->info('✅ All inconsistencies have been fixed!');
                } else {
                    $this->warn('⚠️  Some inconsistencies remain:');
                    foreach ($remainingInconsistencies as $inconsistency) {
                        $this->line('- ' . $inconsistency['message']);
                    }
                }
            }
        } else {
            $this->info('Run with --fix option to automatically fix these inconsistencies.');
        }
        
        return 0;
    }
}
