@props(['tone' => 'mute'])
<span {{ $attributes->class(['chip', 'chip-'.$tone]) }}>{{ $slot }}</span>
