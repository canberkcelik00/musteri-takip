@props(['name', 'id'])

{{-- A customer's round avatar: decorative, so hidden from screen readers. --}}
<span {{ $attributes->class(['ui-avatar', 'ui-avatar-'.\App\Support\Avatar::colorNumber((int) $id)]) }} aria-hidden="true">{{ \App\Support\Avatar::initials($name) }}</span>
