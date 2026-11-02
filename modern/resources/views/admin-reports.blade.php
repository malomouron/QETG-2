@extends('layouts.app')

@section('title', 'Modération des signalements')

@section('content')
    <h1>Signalements à examiner</h1>
    <p>Les signalements historiques ne contiennent pas de motif ; vérifiez la situation avant de les classer.</p>
    @if (session('status')) <p class="notice" role="status">{{ session('status') }}</p> @endif
    @forelse ($reports as $report)
        <section class="panel">
            <p><a href="{{ route('members.show', $report->reporter_id) }}">{{ $report->reporter_name }}</a> a signalé <a href="{{ route('members.show', $report->reported_id) }}">{{ $report->reported_name }}</a>.</p>
            <p>Enregistré le {{ $report->created_at }}</p>
            <form action="{{ route('admin.reports.resolve', $report->id) }}" method="post">
                @csrf
                <button type="submit">Classer ce signalement</button>
            </form>
        </section>
    @empty
        <p>Aucun signalement en attente.</p>
    @endforelse
    {{ $reports->links('pagination.simple') }}
@endsection
