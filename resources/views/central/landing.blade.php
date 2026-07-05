@php
    $landingView = match ($platformType ?? 'universal') {
        'tirta' => 'central.landing.platforms.tirta',
        'hotel' => 'central.landing.platforms.hotel',
        'resto' => 'central.landing.platforms.resto',
        'netbilling' => 'central.landing.platforms.netbilling',
        default => 'central.landing.platforms.universal',
    };
@endphp

@include($landingView, [
    'platformType' => $platformType ?? 'universal',
    'platformExperience' => $platformExperience ?? [],
])
