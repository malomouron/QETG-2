<?php

namespace App\Console\Commands;

use App\Support\LegacySnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOfficialProgress extends Command
{
    protected $signature = 'qetg:import-official-progress {--dry-run : Validate without writing}';

    protected $description = 'Import saved official quiz progress from a legacy database snapshot';

    public function handle(LegacySnapshot $snapshot): int
    {
        if (($error = $snapshot->configurationError()) !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        $orphaned = DB::connection('legacy')->table('quiz_progression as progress')
            ->leftJoin('quiz_question as question', 'progress.id_question', '=', 'question.id_question')
            ->leftJoin('quiz', 'question.id_quiz', '=', 'quiz.id_quiz')
            ->where(function ($query) {
                $query->whereNull('question.id_question')->orWhereNull('quiz.id_quiz');
            })->exists();
        if ($orphaned) {
            $this->error('The snapshot contains progress referencing a missing question or quiz. No progress was imported.');

            return self::FAILURE;
        }

        $progress = DB::connection('legacy')->table('quiz_progression as progress')
            ->join('quiz_question as question', 'progress.id_question', '=', 'question.id_question')
            ->join('quiz', 'question.id_quiz', '=', 'quiz.id_quiz')
            ->where('quiz.id_quiz_user', 0)
            ->select('progress.id_user', 'progress.id_question', 'question.id_quiz')
            ->get();
        $users = DB::table('users')->whereNotNull('legacy_id')->pluck('id', 'legacy_id');
        $questions = DB::table('official_questions')->pluck('quiz_id', 'legacy_id');
        $seen = [];
        foreach ($progress as $item) {
            $key = $item->id_user.':'.$item->id_quiz;
            if (isset($seen[$key])) {
                $this->error('The snapshot contains multiple progress records for one member and quiz. No progress was imported.');

                return self::FAILURE;
            }
            $seen[$key] = true;
            if (! $users->has($item->id_user) || ! $questions->has($item->id_question) ||
                (int) $questions->get($item->id_question) !== (int) $item->id_quiz) {
                $this->error('Progress references a member or question that has not been imported. No progress was imported.');

                return self::FAILURE;
            }
        }

        $created = 0;
        if (! $this->option('dry-run')) {
            DB::transaction(function () use ($progress, $users, &$created) {
                foreach ($progress as $item) {
                    $userId = $users->get($item->id_user);
                    if (DB::table('official_quiz_progress')->where('user_id', $userId)->where('quiz_id', $item->id_quiz)->exists()) {
                        continue;
                    }
                    DB::table('official_quiz_progress')->insert([
                        'user_id' => $userId,
                        'quiz_id' => $item->id_quiz,
                        'question_id' => $item->id_question,
                        'legacy_question_id' => $item->id_question,
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
            });
        }

        $this->info(($this->option('dry-run') ? 'Progress records available: '.$progress->count() : 'Progress records imported: '.$created).'.');

        return self::SUCCESS;
    }
}
