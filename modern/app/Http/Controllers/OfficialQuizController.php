<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OfficialQuizController extends Controller
{
    public function show(int $quiz): View
    {
        $progress = null;
        if (Auth::check()) {
            $progress = DB::table('official_quiz_progress as progress')
                ->join('official_questions as question', 'progress.question_id', '=', 'question.legacy_id')
                ->where('progress.user_id', Auth::id())->where('progress.quiz_id', $quiz)
                ->where('question.quiz_id', $quiz)->first(['question.number']);
        }

        return view('official-quiz', [
            'quiz' => $this->quiz($quiz),
            'questionCount' => DB::table('official_questions')->where('quiz_id', $quiz)->count(),
            'progress' => $progress,
        ]);
    }

    public function start(Request $request, int $quiz): RedirectResponse
    {
        $quizRecord = $this->quiz($quiz);
        $questions = $this->playableQuestions($quiz);
        $this->saveProgress($quiz, $questions->first()->legacy_id);
        $request->session()->put($this->sessionKey($quiz), [
            'status' => 'answering',
            'question_id' => $questions->first()->legacy_id,
            'content_hash' => $quizRecord->content_hash,
            'media_hash' => $quizRecord->media_hash,
        ]);

        return redirect()->route('official-quiz.question', $quiz);
    }

    public function resume(Request $request, int $quiz): RedirectResponse
    {
        $quizRecord = $this->quiz($quiz);
        $questions = $this->playableQuestions($quiz);
        $progress = DB::table('official_quiz_progress')->where('user_id', $request->user()->id)
            ->where('quiz_id', $quiz)->first();
        if ($progress === null || $progress->question_id === null ||
            ! $questions->contains('legacy_id', $progress->question_id)) {
            return back()->withErrors(['quiz' => 'La progression ne peut plus être reprise. Recommencez le quiz.']);
        }

        $request->session()->put($this->sessionKey($quiz), [
            'status' => 'answering',
            'question_id' => $progress->question_id,
            'content_hash' => $quizRecord->content_hash,
            'media_hash' => $quizRecord->media_hash,
        ]);

        return redirect()->route('official-quiz.question', $quiz);
    }

    public function question(Request $request, int $quiz): View
    {
        $this->quiz($quiz);
        $state = $this->state($request, $quiz, 'answering');
        $question = $this->currentQuestion($quiz, $state['question_id']);

        return view('official-question', [
            'quizId' => $quiz,
            'question' => $question,
            'questionCount' => DB::table('official_questions')->where('quiz_id', $quiz)->count(),
            'answers' => DB::table('official_answers')->where('question_id', $question->legacy_id)
                ->orderBy('number')->orderBy('legacy_id')->get(['legacy_id', 'text']),
        ]);
    }

    public function answer(Request $request, int $quiz): RedirectResponse
    {
        $this->quiz($quiz);
        $state = $this->state($request, $quiz, 'answering');
        $validated = $request->validate(['answer_id' => ['required', 'integer']]);
        $result = DB::transaction(function () use ($quiz, $state, $validated) {
            $lockedQuiz = DB::table('official_quizzes')->where('legacy_id', $quiz)->lockForUpdate()->first();
            abort_if($lockedQuiz === null || ($state['content_hash'] ?? null) !== $lockedQuiz->content_hash ||
                ($state['media_hash'] ?? null) !== $lockedQuiz->media_hash, 409, 'Le quiz a changé ; recommencez depuis sa présentation.');
            $question = $this->currentQuestion($quiz, $state['question_id']);
            $answer = DB::table('official_answers')->where('question_id', $question->legacy_id)
                ->where('legacy_id', $validated['answer_id'])->first();
            if ($answer === null) {
                throw ValidationException::withMessages(['answer_id' => 'Choisissez une réponse à cette question.']);
            }

            $nextQuestion = null;
            $resultRecorded = false;
            if ($answer->correct) {
                $nextQuestion = DB::table('official_questions')->where('quiz_id', $quiz)
                    ->where('number', $question->number + 1)->first();
                if ($nextQuestion === null && Auth::check()) {
                    DB::table('official_quiz_results')->upsert([[
                        'user_id' => Auth::id(),
                        'quiz_id' => $quiz,
                        'completed_at' => now(),
                    ]], ['user_id', 'quiz_id'], ['quiz_id']);
                    DB::table('official_quiz_progress')->where('user_id', Auth::id())->where('quiz_id', $quiz)->delete();
                    $resultRecorded = true;
                }
            } elseif (Auth::check()) {
                $firstQuestion = DB::table('official_questions')->where('quiz_id', $quiz)->where('number', 1)->first();
                abort_if($firstQuestion === null, 409, 'Le quiz a changé ; recommencez depuis sa présentation.');
                $this->saveProgress($quiz, $firstQuestion->legacy_id);
            }

            return [
                'question_id' => $question->legacy_id,
                'answer_id' => $answer->legacy_id,
                'next_question_id' => $nextQuestion->legacy_id ?? null,
                'result_recorded' => $resultRecorded,
            ];
        });
        $request->session()->put($this->sessionKey($quiz), [
            'status' => 'result',
            'question_id' => $result['question_id'],
            'answer_id' => $result['answer_id'],
            'next_question_id' => $result['next_question_id'],
            'content_hash' => $state['content_hash'],
            'media_hash' => $state['media_hash'] ?? null,
            'result_recorded' => $result['result_recorded'],
        ]);

        return redirect()->route('official-quiz.result', $quiz);
    }

    public function result(Request $request, int $quiz): View
    {
        $this->quiz($quiz);
        $state = $this->state($request, $quiz, 'result');
        $question = $this->currentQuestion($quiz, $state['question_id']);
        $answer = DB::table('official_answers')->where('question_id', $question->legacy_id)
            ->where('legacy_id', $state['answer_id'])->first();
        abort_if($answer === null, 409, 'Le quiz a changé ; recommencez depuis sa présentation.');

        return view('official-result', [
            'quizId' => $quiz,
            'answer' => $answer,
            'hasNext' => $state['next_question_id'] !== null,
            'resultRecorded' => $state['result_recorded'] ?? false,
        ]);
    }

    public function next(Request $request, int $quiz): RedirectResponse
    {
        $this->quiz($quiz);
        $state = $this->state($request, $quiz, 'result');
        $answer = DB::table('official_answers')->where('question_id', $state['question_id'])
            ->where('legacy_id', $state['answer_id'])->first();
        abort_if($answer === null || ! $answer->correct || $state['next_question_id'] === null, 409);
        $this->currentQuestion($quiz, $state['next_question_id']);
        $this->saveProgress($quiz, $state['next_question_id']);
        $request->session()->put($this->sessionKey($quiz), [
            'status' => 'answering',
            'question_id' => $state['next_question_id'],
            'content_hash' => $state['content_hash'],
            'media_hash' => $state['media_hash'] ?? null,
        ]);

        return redirect()->route('official-quiz.question', $quiz);
    }

    private function quiz(int $id): object
    {
        $quiz = DB::table('official_quizzes')->where('legacy_id', $id)->first();
        abort_if($quiz === null, 404);

        return $quiz;
    }

    private function playableQuestions(int $quiz): Collection
    {
        $questions = DB::table('official_questions')->where('quiz_id', $quiz)->orderBy('number')->get();
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['quiz' => 'Ce quiz ne contient pas encore de questions importées.']);
        }

        $answers = DB::table('official_answers')->whereIn('question_id', $questions->pluck('legacy_id'))->get()->groupBy('question_id');
        foreach ($questions as $index => $question) {
            $questionAnswers = $answers->get($question->legacy_id, collect());
            if ((int) $question->number !== $index + 1 || $questionAnswers->isEmpty() || ! $questionAnswers->contains('correct', 1)) {
                throw ValidationException::withMessages(['quiz' => 'Ce quiz est incomplet dans la copie importée et ne peut pas encore être joué.']);
            }
        }

        return $questions;
    }

    private function saveProgress(int $quiz, int $question): void
    {
        if (Auth::check()) {
            DB::table('official_quiz_progress')->upsert([[
                'user_id' => Auth::id(),
                'quiz_id' => $quiz,
                'question_id' => $question,
                'updated_at' => now(),
            ]], ['user_id', 'quiz_id'], ['question_id', 'updated_at']);
        }
    }

    private function currentQuestion(int $quiz, int $question): object
    {
        $row = DB::table('official_questions')->where('quiz_id', $quiz)->where('legacy_id', $question)->first();
        abort_if($row === null, 409, 'Le quiz a changé ; recommencez depuis sa présentation.');

        return $row;
    }

    private function state(Request $request, int $quiz, string $status): array
    {
        $state = $request->session()->get($this->sessionKey($quiz));
        abort_if(! is_array($state) || ($state['status'] ?? null) !== $status, 409, 'Commencez ou reprenez le quiz depuis sa présentation.');
        $quizRecord = $this->quiz($quiz);
        abort_if(($state['content_hash'] ?? null) !== $quizRecord->content_hash ||
            ($state['media_hash'] ?? null) !== $quizRecord->media_hash, 409, 'Le quiz a changé ; recommencez depuis sa présentation.');

        return $state;
    }

    private function sessionKey(int $quiz): string
    {
        return 'official_quizzes.'.$quiz;
    }
}
