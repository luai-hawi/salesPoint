<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="">
<meta name="theme-color" content="#4f46e5">
<link rel="manifest" href="{{ route('staff.manifest', $owner->staff_portal_key) }}">
<link rel="icon" href="{{ asset('images/logo.png') }}">
<style>
    :root {
        color-scheme: light;
        --bg: #f5f7fb;
        --card: #ffffff;
        --text: #0f172a;
        --muted: #64748b;
        --border: #dbe3f0;
        --primary: #4f46e5;
        --primary-dark: #4338ca;
        --success: #15803d;
        --success-bg: #dcfce7;
        --danger: #b91c1c;
        --danger-bg: #fee2e2;
        --warning: #a16207;
        --warning-bg: #fef3c7;
        --info: #1d4ed8;
        --info-bg: #dbeafe;
    }

    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background: var(--bg);
        color: var(--text);
    }
    a { color: inherit; }
    .shell { max-width: 760px; margin: 0 auto; padding: 1rem; }
    .topbar {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        margin-bottom: 1rem;
    }
    .brand { display: flex; align-items: center; gap: .75rem; }
    .brand img { width: 44px; height: 44px; border-radius: 12px; object-fit: cover; }
    .brand h1 { margin: 0; font-size: 1.125rem; }
    .brand p { margin: .15rem 0 0; color: var(--muted); font-size: .92rem; }
    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 20px;
        box-shadow: 0 8px 28px rgba(15, 23, 42, 0.06);
        padding: 1rem;
        margin-bottom: 1rem;
    }
    .stack { display: grid; gap: 1rem; }
    .grid-2 { display: grid; gap: .75rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .stat { border: 1px solid var(--border); border-radius: 16px; padding: .9rem; background: #f8fafc; }
    .stat .label { display: block; color: var(--muted); font-size: .85rem; margin-bottom: .25rem; }
    .stat .value { font-size: 1.15rem; font-weight: 700; }
    .actions, .toolbar { display: flex; flex-wrap: wrap; gap: .75rem; }
    .btn {
        appearance: none; border: 0; border-radius: 16px; padding: .95rem 1rem;
        font: inherit; font-weight: 700; cursor: pointer; text-decoration: none;
        transition: .15s ease;
    }
    .btn:disabled { opacity: .55; cursor: wait; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-primary:hover { background: var(--primary-dark); }
    .btn-secondary { background: white; border: 1px solid var(--border); color: var(--text); }
    .btn-danger { background: #dc2626; color: white; }
    .btn-ghost { background: transparent; color: var(--muted); }
    .btn-block { width: 100%; }
    .btn-big { padding-block: 1.15rem; font-size: 1.05rem; }
    .badge {
        display: inline-flex; align-items: center; gap: .4rem; border-radius: 999px;
        padding: .25rem .6rem; font-size: .8rem; font-weight: 700;
    }
    .badge-success { background: var(--success-bg); color: var(--success); }
    .badge-danger { background: var(--danger-bg); color: var(--danger); }
    .badge-warning { background: var(--warning-bg); color: var(--warning); }
    .badge-info { background: var(--info-bg); color: var(--info); }
    .muted { color: var(--muted); }
    .alert {
        border-radius: 16px; padding: .9rem 1rem; border: 1px solid var(--border);
        margin-bottom: 1rem; font-size: .95rem;
    }
    .alert-warning { background: var(--warning-bg); color: #854d0e; border-color: #fcd34d; }
    .alert-danger { background: var(--danger-bg); color: #991b1b; border-color: #fca5a5; }
    .alert-info { background: var(--info-bg); color: #1e40af; border-color: #93c5fd; }
    label { display: block; font-weight: 600; margin-bottom: .45rem; }
    input, textarea {
        width: 100%; border-radius: 14px; border: 1px solid var(--border); padding: .85rem .95rem;
        font: inherit; background: white;
    }
    textarea { min-height: 110px; resize: vertical; }
    .field { margin-bottom: .85rem; }
    .hidden { display: none !important; }
    .days { display: grid; gap: .75rem; }
    .day {
        padding: .9rem 1rem; border: 1px solid var(--border); border-radius: 16px; background: #fff;
    }
    .day-top {
        display: flex; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: .45rem;
    }
    .day-time { font-size: .92rem; color: var(--muted); }
    .split { display: flex; justify-content: space-between; gap: 1rem; align-items: center; }
    .rtl .split, [dir="rtl"] .split { flex-direction: row-reverse; }
    .small { font-size: .88rem; }
    .timer { font-weight: 800; font-size: 1.8rem; margin-top: .25rem; }
    .empty { padding: 1rem; border: 1px dashed var(--border); border-radius: 16px; text-align: center; color: var(--muted); }
    .hint-list { margin: 0; padding-inline-start: 1rem; color: var(--muted); }
    .locale-switch { display: flex; gap: .45rem; align-items: center; }
    .locale-switch a {
        display: inline-flex; padding: .45rem .75rem; border-radius: 999px; text-decoration: none; border: 1px solid var(--border);
        background: white; font-size: .9rem; font-weight: 700;
    }
    @media (min-width: 700px) {
        .shell { padding: 1.5rem; }
        .card { padding: 1.25rem; }
    }
</style>
