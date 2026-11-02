<?php

namespace App\Console\Commands;

use App\Support\LegacySnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOfficialQuizzes extends Command
{
    protected $signature = 'qetg:import-official-quizzes {--dry-run : Count records without writing}';

    protected $description = 'Import official quizzes from a separate legacy database snapshot';

    public function handle(LegacySnapshot $snapshot): int
    {
        if (($error = $snapshot->configurationError()) !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        $quizzes = DB::connection('legacy')->table('quiz')
            ->join('quiz_image', 'quiz.id_image_quiz', '=', 'quiz_image.id_image')
            ->where('quiz.id_quiz_user', 0)
            ->select('quiz.id_quiz', 'quiz.nom_quiz', 'quiz.bienvenue', 'quiz_image.src', 'quiz_image.alt')
            ->orderBy('quiz.id_quiz')
            ->get();

        $questions = DB::connection('legacy')->table('quiz_question as q')
            ->join('quiz as quiz', 'q.id_quiz', '=', 'quiz.id_quiz')
            ->join('quiz_image as quiz_image', 'quiz.id_image_quiz', '=', 'quiz_image.id_image')
            ->leftJoin('quiz_image as image', 'q.id_image_question', '=', 'image.id_image')
            ->leftJoin('quiz_difficulte as difficulty', 'q.id_difficulte', '=', 'difficulty.id_difficulte')
            ->where('quiz.id_quiz_user', 0)
            ->select('q.id_question', 'q.id_quiz', 'q.numero_question', 'q.texte_bienvenue', 'q.question_texte', 'difficulty.libelle', 'image.src', 'image.alt')
            ->orderBy('q.id_quiz')->orderBy('q.numero_question')->orderBy('q.id_question')
            ->get();

        $answers = DB::connection('legacy')->table('quiz_reponse as a')
            ->join('quiz_question as q', 'a.id_question', '=', 'q.id_question')
            ->join('quiz as quiz', 'q.id_quiz', '=', 'quiz.id_quiz')
            ->join('quiz_image as quiz_image', 'quiz.id_image_quiz', '=', 'quiz_image.id_image')
            ->leftJoin('quiz_image as image', 'a.id_image_reponse', '=', 'image.id_image')
            ->where('quiz.id_quiz_user', 0)
            ->select('a.id_reponse', 'a.id_question', 'a.num_reponse', 'a.choix_possible_quiz', 'a.reponseOK', 'a.texte_reponse_explicatif', 'image.src', 'image.alt')
            ->orderBy('a.id_reponse')
            ->get();

        foreach ($answers as $answer) {
            if (! in_array((string) $answer->reponseOK, ['0', '1'], true)) {
                $this->error('Invalid correctness value for legacy answer '.$answer->id_reponse.'.');

                return self::FAILURE;
            }
        }
        $answersByQuestion = $answers->groupBy('id_question');
        $questionsByQuiz = $questions->groupBy('id_quiz');

        if (! $this->option('dry-run')) {
            DB::transaction(function () use ($quizzes, $questions, $answers, $answersByQuestion, $questionsByQuiz) {
                foreach ($quizzes as $quiz) {
                    DB::table('official_quizzes')->updateOrInsert(
                        ['legacy_id' => $quiz->id_quiz],
                        [
                            'name' => LegacySnapshot::text($quiz->nom_quiz),
                            'description' => LegacySnapshot::text($quiz->bienvenue),
                            'image_path' => $quiz->src,
                            'image_alt' => LegacySnapshot::text($quiz->alt),
                        ],
                    );
                }

                foreach ($questions as $question) {
                    DB::table('official_questions')->updateOrInsert(
                        ['legacy_id' => $question->id_question],
                        [
                            'quiz_id' => $question->id_quiz,
                            'number' => $question->numero_question,
                            'introduction' => LegacySnapshot::text($question->texte_bienvenue),
                            'text' => LegacySnapshot::text($question->question_texte),
                            'difficulty' => LegacySnapshot::text($question->libelle),
                            'image_path' => $question->src,
                            'image_alt' => LegacySnapshot::text($question->alt),
                        ],
                    );
                }

                foreach ($answers as $answer) {
                    DB::table('official_answers')->updateOrInsert(
                        ['legacy_id' => $answer->id_reponse],
                        [
                            'question_id' => $answer->id_question,
                            'number' => $answer->num_reponse,
                            'text' => LegacySnapshot::text($answer->choix_possible_quiz),
                            'correct' => (int) $answer->reponseOK,
                            'explanation' => LegacySnapshot::text($answer->texte_reponse_explicatif),
                            'image_path' => $answer->src,
                            'image_alt' => LegacySnapshot::text($answer->alt),
                        ],
                    );
                }

                foreach ($questions as $question) {
                    $answerIds = $answersByQuestion->get($question->id_question, collect())->pluck('id_reponse');
                    DB::table('official_answers')->where('question_id', $question->id_question)
                        ->whereNotIn('legacy_id', $answerIds)->delete();
                }
                foreach ($quizzes as $quiz) {
                    $quizQuestions = $questionsByQuiz->get($quiz->id_quiz, collect());
                    $questionIds = $quizQuestions->pluck('id_question');
                    DB::table('official_questions')->where('quiz_id', $quiz->id_quiz)
                        ->whereNotIn('legacy_id', $questionIds)->delete();
                    $quizAnswers = $quizQuestions->flatMap(
                        fn ($question) => $answersByQuestion->get($question->id_question, collect())
                    )->values();
                    $contentHash = hash('sha256', json_encode(
                        [$quiz, $quizQuestions->values(), $quizAnswers],
                        JSON_THROW_ON_ERROR
                    ));
                    DB::table('official_quizzes')->where('legacy_id', $quiz->id_quiz)
                        ->update(['content_hash' => $contentHash]);
                }
            });
        }

        $this->info(($this->option('dry-run') ? 'Records available: ' : 'Records imported: ')
            .$quizzes->count().' quizzes, '.$questions->count().' questions, '.$answers->count().' answers.');

        return self::SUCCESS;
    }
}
