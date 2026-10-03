@php
    // Self-contained on purpose: error pages must render even when sessions, the database or the asset build are unavailable.
    $code = (string) ($code ?? 500);
    $primary = in_array(app()->getLocale(), ['ar', 'en'], true) ? app()->getLocale() : 'ar';
    $secondary = $primary === 'ar' ? 'en' : 'ar';
    $tr = fn (string $key, string $locale) => __($key, [], $locale);
@endphp
<!DOCTYPE html>
<html lang="{{ $primary }}" dir="{{ $primary === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} - {{ $tr("errors.codes.$code.title", $primary) }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f4f6;color:#111827;font-family:system-ui,-apple-system,"Segoe UI",Tahoma,Arial,sans-serif;padding:24px}
        .card{max-width:520px;width:100%;background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(17,24,39,.08);padding:36px 32px;text-align:center}
        .brand{font-weight:700;color:#4f46e5;letter-spacing:.3px;margin-bottom:8px}
        .code{font-size:64px;line-height:1;font-weight:800;color:#e5e7eb;margin:8px 0}
        h1{font-size:22px;margin:10px 0 8px}
        p{margin:0 0 6px;color:#4b5563;line-height:1.7;font-size:15px}
        .secondary{margin-top:18px;padding-top:16px;border-top:1px solid #f3f4f6;color:#6b7280}
        .secondary h2{font-size:15px;margin:0 0 4px;color:#374151}
        .secondary p{font-size:13px}
        .actions{margin-top:24px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
        .btn{display:inline-block;border-radius:10px;padding:10px 18px;font-size:14px;font-weight:600;text-decoration:none;cursor:pointer;border:1px solid #d1d5db;background:#fff;color:#374151}
        .btn.primary{background:#4f46e5;border-color:#4f46e5;color:#fff}
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="brand">{{ $tr('errors.brand', $primary) }}</div>
        <div class="code" aria-hidden="true">{{ $code }}</div>
        <h1>{{ $tr("errors.codes.$code.title", $primary) }}</h1>
        <p>{{ $tr("errors.codes.$code.message", $primary) }}</p>

        <div class="secondary" lang="{{ $secondary }}" dir="{{ $secondary === 'ar' ? 'rtl' : 'ltr' }}">
            <h2>{{ $tr("errors.codes.$code.title", $secondary) }}</h2>
            <p>{{ $tr("errors.codes.$code.message", $secondary) }}</p>
        </div>

        <div class="actions">
            @if ($code === '419')
                <a class="btn primary" href="javascript:location.reload()">{{ $tr('errors.reload', $primary) }} / {{ $tr('errors.reload', $secondary) }}</a>
            @else
                <a class="btn" href="javascript:history.back()">{{ $tr('errors.back', $primary) }} / {{ $tr('errors.back', $secondary) }}</a>
            @endif
            <a class="btn primary" href="{{ url('/') }}">{{ $tr('errors.home', $primary) }} / {{ $tr('errors.home', $secondary) }}</a>
        </div>
    </main>
</body>
</html>
