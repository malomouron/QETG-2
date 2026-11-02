<?php

namespace App\Console\Commands;

use App\Support\LegacySnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOfficialResults extends Command
{
    protected $signature = 'qetg:import-official-results {--dry-run : Validate without writing}';

    protected $description = 'Import official quiz results and favorites from a legacy database snapshot';

    public function handle(LegacySnapshot $snapshot): int
    {
        if (($error = $snapshot->configurationError()) !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        $orphaned = DB::connection('legacy')->table('quiz_statistique as stat')
            ->leftJoin('quiz', 'stat.id_quiz', '=', 'quiz.id_quiz')
            ->whereNull('quiz.id_quiz')->exists();
        if ($orphaned) {
            $this->error('The snapshot contains results referencing a missing quiz. No results were imported.');

            return self::FAILURE;
        }

        $results = DB::connection('legacy')->table('quiz_statistique as stat')
            ->join('quiz', 'stat.id_quiz', '=', 'quiz.id_quiz')
            ->where('quiz.id_quiz_user', 0)
            ->select('stat.id_user', 'stat.id_quiz', 'stat.date', 'stat.note', 'stat.favoris', 'stat.quiz_utilisateur')
            ->get();
        $users = DB::table('users')->whereNotNull('legacy_id')->pluck('id', 'legacy_id');
        $quizIds = DB::table('official_quizzes')->pluck('legacy_id')->mapWithKeys(fn ($id) => [(string) $id => true]);
        foreach ($results as $result) {
            if (! $users->has($result->id_user) || ! $quizIds->has((string) $result->id_quiz)) {
                $this->error('A result references a member or official quiz that has not been imported. No results were imported.');

                return self::FAILURE;
            }
        }

        $created = 0;
        if (! $this->option('dry-run')) {
            DB::transaction(function () use ($results, $users, &$created) {
                foreach ($results as $result) {
                    $userId = $users->get($result->id_user);
                    if (DB::table('official_quiz_results')->where('user_id', $userId)->where('quiz_id', $result->id_quiz)->exists()) {
                        continue;
                    }
                    DB::table('official_quiz_results')->insert([
                        'user_id' => $userId,
                        'quiz_id' => $result->id_quiz,
                        'favorite' => (int) $result->favoris === 1,
                        'legacy_date' => $result->date,
                        'legacy_score' => $result->note,
                        'legacy_favorite' => $result->favoris,
                        'legacy_quiz_utilisateur' => $result->quiz_utilisateur,
                    ]);
                    $created++;
                }
            });
        }

        $this->info(($this->option('dry-run') ? 'Results available: '.$results->count() : 'Results imported: '.$created).'.');

        return self::SUCCESS;
    }
}
