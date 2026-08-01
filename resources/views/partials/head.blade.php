<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

{{-- The subscribe endpoints are session-guarded POSTs; web push reads the VAPID
     public key to mint a browser subscription (#35). The key is public by design. --}}
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Atelier') : config('app.name', 'Atelier') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
