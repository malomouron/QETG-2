<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetMemberAdmin extends Command
{
    protected $signature = 'qetg:set-admin {user : New application user ID} {state : grant or revoke} {--yes : Confirm the permission change}';

    protected $description = 'Explicitly grant or revoke administration permission for a member';

    public function handle(): int
    {
        $id = $this->argument('user');
        $state = $this->argument('state');
        if (! ctype_digit($id) || ! in_array($state, ['grant', 'revoke'], true)) {
            $this->error('Provide a numeric user ID and grant or revoke.');

            return self::FAILURE;
        }
        if (! $this->option('yes') && ! $this->confirm('Change the administration permission for user '.$id.'?')) {
            $this->error('Permission was not changed.');

            return self::FAILURE;
        }
        $user = DB::table('users')->where('id', $id)->first(['id']);
        if ($user === null) {
            $this->error('Member not found.');

            return self::FAILURE;
        }
        DB::table('users')->where('id', $id)->update(['is_admin' => $state === 'grant', 'updated_at' => now()]);
        $this->info('Permission updated for user '.$id.'.');

        return self::SUCCESS;
    }
}
