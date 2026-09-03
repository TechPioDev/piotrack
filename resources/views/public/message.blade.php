@extends('public.layout')

@section('title', $title)

@section('content')
    <h1>{{ $title }}</h1>
    <p class="lead">{{ $message }}</p>

    @isset($downloadUrl)
        {{-- WEB-022: the gated lead magnet, behind a signed expiring link. --}}
        <p><a href="{{ $downloadUrl }}" style="display:inline-block;padding:12px 22px;border-radius:10px;background:#0d7a6f;color:#fff;text-decoration:none;font-weight:600">Download {{ $downloadName ?? 'your file' }}</a></p>
        <p style="font-size:13px;color:#667">The link works for 7 days.</p>
    @endisset
@endsection
