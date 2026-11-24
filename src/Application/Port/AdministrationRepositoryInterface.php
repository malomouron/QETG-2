<?php

declare(strict_types=1);

namespace QETG\Application\Port;

interface AdministrationRepositoryInterface
{
    /**
     * Caller must authorize dashboard access. Page numbers are one-based; each list has up to 20 items.
     *
     * @return array{
    *     stats: array{members: int, official: int, community: int, reports: int, completions: int},
    *     traffic: array{days: list<array{date: string, pageViews: int, visitorSessions: int}>, today: array{pageViews: int, visitorSessions: int}, sevenDayTotals: array{pageViews: int, visitorSessions: int}, migrationMissing: bool},
     *     quizzes: list<array{id: int, title: string, questions: int, public: bool}>,
     *     reports: list<array{target_id: int, target_login: string, target_admin: bool, report_count: int, reporters?: string}>,
     *     reportTargets: int
     * }
     */
    public function dashboard(int $quizPage, int $reportPage): array;

    /** Increment today's page-view count and, when true, today's unique-session count. */
    public function recordPageView(bool $newSessionToday): void;

    /**
     * @param list<array{text: string, difficulty: int, answers: list<string>, correct: int, explanation: string}> $questions
     * @throws \DomainException on invalid input or refused access
     */
    public function publishQuiz(int $actorId, string $title, string $description, int $xp, array $questions): int;

    /** @throws \DomainException if deletion is refused */
    public function deleteQuiz(int $actorId, int $quizId): void;

    /** @throws \DomainException if deletion is refused */
    public function deleteReportedMember(int $actorId, int $memberId): void;
}