<?php

namespace App\Console\Commands;

use App\Support\LegacySnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportMemberSubscriptions extends Command
{
    protected $signature = 'qetg:import-member-subscriptions {--dry-run : Validate without writing}';

    protected $description = 'Import member subscriptions from an isolated legacy snapshot';

    public function handle(LegacySnapshot $snapshot): int
    {
        if (($error = $snapshot->configurationError()) !== null) {
            $this->error($error);

            return self::FAILURE;
        }
        $subscriptions = DB::connection('legacy')->table('quiz_subscription_membre')
            ->select('idUser', 'idUserAbonnement', 'dateAjout')->orderBy('idAbonnement')->get();
        $users = DB::table('users')->whereNotNull('legacy_id')->pluck('id', 'legacy_id');
        $seen = [];
        foreach ($subscriptions as $subscription) {
            $key = $subscription->idUser.':'.$subscription->idUserAbonnement;
            if (isset($seen[$key])) {
                $this->error('Duplicate legacy subscriptions found. No subscriptions were imported.');

                return self::FAILURE;
            }
            if (! $users->has($subscription->idUser) || ! $users->has($subscription->idUserAbonnement)) {
                $this->error('A subscription references a member not present in the new application. No subscriptions were imported.');

                return self::FAILURE;
            }
            $seen[$key] = true;
        }
        $created = 0;
        if (! $this->option('dry-run')) {
            DB::transaction(function () use ($subscriptions, $users, &$created) {
                foreach ($subscriptions as $subscription) {
                    $followerId = $users->get($subscription->idUser);
                    $followedId = $users->get($subscription->idUserAbonnement);
                    if (DB::table('member_subscriptions')->where('follower_id', $followerId)->where('followed_id', $followedId)->exists()) {
                        continue;
                    }
                    DB::table('member_subscriptions')->insert([
                        'follower_id' => $followerId,
                        'followed_id' => $followedId,
                        'active' => true,
                        'legacy_date' => $subscription->dateAjout,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
            });
        }

        $this->info(($this->option('dry-run') ? 'Subscriptions available: '.$subscriptions->count() : 'Subscriptions imported: '.$created).'.');

        return self::SUCCESS;
    }
}
