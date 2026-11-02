<?php

namespace App\Console\Commands;

use App\Support\LegacyPassword;
use App\Support\LegacySnapshot;
use App\Support\MemberIdentity;
use App\Support\MemberIdentityConflict;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportLegacyMembers extends Command
{
    protected $signature = 'qetg:import-members {--dry-run : Validate without writing}';

    protected $description = 'Import members from a separate legacy database snapshot without overwriting existing accounts';

    public function handle(LegacySnapshot $snapshot, LegacyPassword $passwords, MemberIdentity $identities): int
    {
        if (($error = $snapshot->configurationError()) !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        $members = DB::connection('legacy')->table('quiz_membre')
            ->select('id', 'login', 'pass_md5', 'email', 'nom', 'prenom', 'admin', 'image_profil', 'newletter', 'ip', 'date')
            ->orderBy('id')->get();
        $existing = DB::table('users')->whereNotNull('legacy_id')->pluck('legacy_id')->mapWithKeys(fn ($id) => [(string) $id => true]);
        $deleted = DB::table('deleted_legacy_members')->pluck('legacy_id')->mapWithKeys(fn ($id) => [(string) $id => true]);
        $newMembers = $members->reject(fn ($member) => $existing->has((string) $member->id) || $deleted->has((string) $member->id));
        foreach ($newMembers as $member) {
            if (! $passwords->supported($member->pass_md5) || strlen($member->pass_md5) > 255) {
                $this->error('Unsupported password hash for legacy member '.$member->id.'. No members were imported.');

                return self::FAILURE;
            }
            $login = LegacySnapshot::text($member->login);
            $email = LegacySnapshot::text($member->email);
            if ($identities->conflictsWithUsers($login, $email, true) || $identities->conflictsWithPending($login, $email)) {
                $this->error('Legacy member '.$member->id.' conflicts with a new or pending account. No members were imported.');

                return self::FAILURE;
            }
        }

        if (! $this->option('dry-run')) {
            try {
                DB::transaction(function () use ($newMembers, $identities) {
                    $identities->lock();
                    foreach ($newMembers as $member) {
                        if (DB::table('deleted_legacy_members')->where('legacy_id', $member->id)->exists()) {
                            throw new MemberIdentityConflict('A legacy member was deleted during the import. Retry the import.');
                        }
                        $login = LegacySnapshot::text($member->login);
                        $email = LegacySnapshot::text($member->email);
                        if ($identities->conflictsWithUsers($login, $email, true) || $identities->conflictsWithPending($login, $email)) {
                            throw new MemberIdentityConflict('Legacy member '.$member->id.' conflicts with a new or pending account. No members were imported.');
                        }
                        DB::table('users')->insert([
                            'name' => mb_substr($login, 0, 255),
                            'email' => $email,
                            'password' => $member->pass_md5,
                            'legacy_id' => $member->id,
                            'legacy_login' => $login,
                            'legacy_last_name' => LegacySnapshot::text($member->nom),
                            'legacy_first_name' => LegacySnapshot::text($member->prenom),
                            'legacy_admin' => $member->admin,
                            'legacy_avatar' => $member->image_profil,
                            'legacy_newsletter' => $member->newletter,
                            'legacy_ip' => $member->ip,
                            'legacy_registration_date' => $member->date,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
            } catch (MemberIdentityConflict $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            } catch (QueryException $exception) {
                Log::error('Legacy member import failed; the destination transaction was rolled back.', ['code' => $exception->getCode()]);
                $this->error('Member import failed; no members were imported. Check destination database configuration.');

                return self::FAILURE;
            }
        }

        $this->info(($this->option('dry-run') ? 'Members available: ' : 'Members imported: ').$newMembers->count().'; existing members left unchanged: '.$members->filter(fn ($member) => $existing->has((string) $member->id))->count().'.');
        $this->info('Previously deleted legacy members excluded: '.$members->filter(fn ($member) => $deleted->has((string) $member->id))->count().'.');

        return self::SUCCESS;
    }
}
