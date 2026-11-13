@props(['user'])
@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" {{ $attributes->class('shrink-0 rounded-full object-cover') }}>
@else
    <span {{ $attributes->class('grid shrink-0 place-items-center rounded-full bg-brand-soft font-semibold text-brand-strong') }}>{{ $user->initials() }}</span>
@endif
