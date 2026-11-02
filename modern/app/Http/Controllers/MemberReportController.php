<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberReportController extends Controller
{
    public function report(Request $request, int $member): RedirectResponse
    {
        if ((int) $request->user()->id === $member) {
            throw ValidationException::withMessages(['report' => 'Vous ne pouvez pas vous signaler vous-même.']);
        }
        abort_unless(DB::table('users')->where('id', $member)->exists(), 404);
        DB::transaction(function () use ($request, $member) {
            $reporter = $request->user()->id;
            DB::table('users')->where('id', $reporter)->lockForUpdate()->firstOrFail();
            if (! DB::table('member_reports')->where('reporter_id', $reporter)->where('reported_id', $member)->exists()) {
                DB::table('member_reports')->insert([
                    'reporter_id' => $reporter, 'reported_id' => $member,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Signalement enregistré.');
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);
        $reports = DB::table('member_reports as report')
            ->join('users as reporter', 'report.reporter_id', '=', 'reporter.id')
            ->join('users as reported', 'report.reported_id', '=', 'reported.id')
            ->whereNull('report.resolved_at')->orderBy('report.id')
            ->select('report.id', 'report.created_at', 'reporter.id as reporter_id', 'reporter.name as reporter_name',
                'reported.id as reported_id', 'reported.name as reported_name')->paginate(10);

        return view('admin-reports', ['reports' => $reports]);
    }

    public function resolve(Request $request, int $report): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $updated = DB::table('member_reports')->where('id', $report)->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'updated_at' => now()]);
        abort_if($updated === 0, 404);

        return back()->with('status', 'Signalement classé.');
    }
}
