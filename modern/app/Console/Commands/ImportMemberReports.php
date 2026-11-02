<?php

namespace App\Console\Commands;

use App\Support\LegacySnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportMemberReports extends Command
{
    protected $signature = 'qetg:import-member-reports {--dry-run : Validate without writing}';

    protected $description = 'Import member reports from an isolated legacy snapshot';

    public function handle(LegacySnapshot $snapshot): int
    {
        if (($error = $snapshot->configurationError()) !== null) {
            $this->error($error);

            return self::FAILURE;
        }
        $reports = DB::connection('legacy')->table('quiz_signalement')
            ->select('id_user_origine', 'id_user_cible')->orderBy('id_signalement')->get();
        $users = DB::table('users')->whereNotNull('legacy_id')->pluck('id', 'legacy_id');
        $seen = [];
        foreach ($reports as $report) {
            $key = $report->id_user_origine.':'.$report->id_user_cible;
            if (isset($seen[$key])) {
                $this->error('Duplicate legacy reports found. No reports were imported.');

                return self::FAILURE;
            }
            if (! $users->has($report->id_user_origine) || ! $users->has($report->id_user_cible)) {
                $this->error('A report references a member not present in the new application. No reports were imported.');

                return self::FAILURE;
            }
            $seen[$key] = true;
        }
        $created = 0;
        if (! $this->option('dry-run')) {
            DB::transaction(function () use ($reports, $users, &$created) {
                foreach ($reports as $report) {
                    $reporter = $users->get($report->id_user_origine);
                    $reported = $users->get($report->id_user_cible);
                    DB::table('users')->where('id', $reporter)->lockForUpdate()->firstOrFail();
                    if (DB::table('member_reports')->where('reporter_id', $reporter)->where('reported_id', $reported)->exists()) {
                        continue;
                    }
                    DB::table('member_reports')->insert([
                        'reporter_id' => $reporter, 'reported_id' => $reported,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $created++;
                }
            });
        }

        $this->info(($this->option('dry-run') ? 'Reports available: '.$reports->count() : 'Reports imported: '.$created).'.');

        return self::SUCCESS;
    }
}
