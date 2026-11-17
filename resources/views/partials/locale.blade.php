@if (count(setting('locales', ['el', 'en'])) > 1)
    <form method="POST" action="{{ route('locale') }}" class="flex items-center rounded-xl bg-white/10 p-1 text-sm font-bold {{ $class ?? '' }}">
        @csrf
        @foreach (setting('locales', ['el', 'en']) as $l)
            <button name="locale" value="{{ $l }}" @class(['rounded-lg px-2.5 py-1 uppercase', 'bg-white text-ink' => app()->getLocale() === $l, 'opacity-70 hover:opacity-100' => app()->getLocale() !== $l])
                aria-label="{{ $l === 'el' ? 'Ελληνικά' : 'English' }}">{{ $l }}</button>
        @endforeach
    </form>
@endif
