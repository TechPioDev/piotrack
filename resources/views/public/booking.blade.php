@extends('public.layout')

@section('title', $page->name)

@section('content')
    <h1>{{ $page->name }}</h1>
    <p class="lead">{{ $page->meeting_type }} · {{ $page->duration_minutes }} minutes</p>

    <form method="POST" action="{{ url('/b/'.$page->slug) }}">
        <label for="name">Name <span style="color:#b42318">*</span></label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required>
        @error('name')<div class="error">{{ $message }}</div>@enderror

        <label for="email">Email <span style="color:#b42318">*</span></label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
        @error('email')<div class="error">{{ $message }}</div>@enderror

        @if (count($slots) > 0)
            <label for="slot_id">Pick a time <span style="color:#b42318">*</span></label>
            <select id="slot_id" name="slot_id">
                <option value="">— choose an available time —</option>
                @foreach ($slots as $slot)
                    <option value="{{ $slot['id'] }}" @selected(old('slot_id') === $slot['id'])>{{ $slot['label'] }} (UTC)</option>
                @endforeach
            </select>
            @error('slot_id')<div class="error">{{ $message }}</div>@enderror

            <label for="scheduled_at">Or request another time</label>
            <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}">
        @else
            <label for="scheduled_at">Preferred time <span style="color:#b42318">*</span></label>
            <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" required>
        @endif
        @error('scheduled_at')<div class="error">{{ $message }}</div>@enderror

        @if ($page->assignment === 'territory')
            <label for="city">City or area</label>
            <input id="city" type="text" name="city" value="{{ old('city') }}" placeholder="e.g. Philadelphia">
            @error('city')<div class="error">{{ $message }}</div>@enderror
        @endif

        @foreach ($questions as $i => $question)
            <label for="answer_{{ $i }}">{{ $question['label'] }} @if ($question['required'] ?? false)<span style="color:#b42318">*</span>@endif</label>
            <input id="answer_{{ $i }}" type="text" name="answers[{{ $i }}]" value="{{ old('answers.'.$i) }}" @if ($question['required'] ?? false) required @endif>
            @error('answers.'.$i)<div class="error">{{ $message }}</div>@enderror
        @endforeach

        <label for="notes">Anything we should know?</label>
        <textarea id="notes" name="notes">{{ old('notes') }}</textarea>

        @foreach ($utm as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach

        <button type="submit">Book {{ $page->meeting_type }}</button>
    </form>
@endsection
