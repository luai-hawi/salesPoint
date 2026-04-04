<?php
// hisba-analytics.php
// Drop this file into your Laravel public/ folder or use as a standalone PHP page.
// No backend calls — all SQL parsing and calculations happen in the browser (JavaScript).
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حسبة — لوحة التحليلات الشاملة</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js">
    </script>

    <style>
        :root {
            --bg: #0a0e1a;
            --bg2: #111827;
            --bg3: #1a2235;
            --bg4: #1f2d44;
            --surface: #243352;
            --border: #2e3f5c;
            --border2: #3d5278;
            --text: #e8edf5;
            --text2: #8fa3c4;
            --text3: #5a7299;
            --gold: #f0b429;
            --gold2: #e09a10;
            --teal: #1dd9b0;
            --teal2: #0fa880;
            --coral: #f26b5b;
            --coral2: #d94f3d;
            --lavender: #9b8ff7;
            --lavender2: #7a6dd6;
            --sky: #5bc4f5;
            --green: #56d48a;
            --amber: #f5a623;
            --r: 10px;
            --r2: 16px;
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            --font: 'IBM Plex Sans Arabic', sans-serif;
            --mono: 'JetBrains Mono', monospace;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            font-size: 14px;
            line-height: 1.6;
        }

        /* ─── BACKGROUND ─── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 40% at 20% 10%, rgba(155, 143, 247, 0.07) 0%, transparent 60%),
                radial-gradient(ellipse 60% 30% at 80% 80%, rgba(29, 217, 176, 0.06) 0%, transparent 55%),
                radial-gradient(ellipse 40% 50% at 50% 50%, rgba(240, 180, 41, 0.03) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* ─── UPLOAD SCREEN ─── */
        #upload-screen {
            position: fixed;
            inset: 0;
            z-index: 100;
            background: var(--bg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 32px;
            padding: 24px;
        }

        .upload-logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .upload-logo-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--gold), var(--coral));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            box-shadow: 0 0 40px rgba(240, 180, 41, 0.3);
        }

        .upload-logo-text {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(90deg, var(--gold), var(--teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .upload-subtitle {
            color: var(--text2);
            font-size: 15px;
            text-align: center;
            max-width: 500px;
        }

        .upload-zone {
            width: 100%;
            max-width: 560px;
            border: 2px dashed var(--border2);
            border-radius: var(--r2);
            padding: 52px 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--bg2);
            position: relative;
            overflow: hidden;
        }

        .upload-zone::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(155, 143, 247, 0.04), rgba(29, 217, 176, 0.04));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .upload-zone:hover,
        .upload-zone.drag-over {
            border-color: var(--teal);
            box-shadow: 0 0 40px rgba(29, 217, 176, 0.15);
        }

        .upload-zone:hover::before,
        .upload-zone.drag-over::before {
            opacity: 1;
        }

        .upload-icon {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        .upload-text-main {
            font-size: 17px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .upload-text-sub {
            font-size: 13px;
            color: var(--text3);
        }

        .upload-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 32px;
            background: linear-gradient(135deg, var(--teal2), var(--teal));
            color: #0a1a14;
            font-weight: 700;
            font-size: 14px;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            font-family: var(--font);
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(29, 217, 176, 0.3);
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(29, 217, 176, 0.4);
        }

        #file-input {
            display: none;
        }

        /* ─── PROGRESS ─── */
        #progress-screen {
            position: fixed;
            inset: 0;
            z-index: 99;
            background: var(--bg);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 24px;
        }

        .progress-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--teal);
        }

        .progress-bar-track {
            width: 400px;
            height: 6px;
            background: var(--bg3);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--teal), var(--lavender));
            border-radius: 3px;
            transition: width 0.3s;
            width: 0%;
        }

        .progress-step {
            font-size: 13px;
            color: var(--text2);
            font-family: var(--mono);
        }

        /* ─── MAIN LAYOUT ─── */
        #dashboard {
            display: none;
            position: relative;
            z-index: 1;
        }

        /* ─── TOP NAV ─── */
        .topnav {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(10, 14, 26, 0.92);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(90deg, var(--gold), var(--teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--gold), var(--coral));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .nav-links {
            display: flex;
            gap: 4px;
            list-style: none;
        }

        .nav-links a {
            color: var(--text2);
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s;
        }

        .nav-links a:hover {
            color: var(--text);
            background: var(--bg3);
        }

        .nav-badge {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text3);
            padding: 2px 8px;
            border: 1px solid var(--border);
            border-radius: 20px;
        }

        /* ─── SECTIONS ─── */
        .section {
            padding: 48px 32px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 16px;
        }

        .section-title-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .section-eyebrow {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--text3);
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
        }

        .section-title span {
            background: linear-gradient(90deg, var(--gold), var(--teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-desc {
            font-size: 13px;
            color: var(--text2);
            max-width: 500px;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, var(--border), transparent);
            margin: 0 32px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ─── KPI CARDS ─── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .kpi-card {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: var(--r2);
            padding: 24px 20px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, border-color 0.2s;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: var(--r2) var(--r2) 0 0;
        }

        .kpi-card.gold::before {
            background: linear-gradient(90deg, var(--gold), var(--amber));
        }

        .kpi-card.teal::before {
            background: linear-gradient(90deg, var(--teal), var(--sky));
        }

        .kpi-card.coral::before {
            background: linear-gradient(90deg, var(--coral), var(--lavender));
        }

        .kpi-card.lav::before {
            background: linear-gradient(90deg, var(--lavender), var(--sky));
        }

        .kpi-card.green::before {
            background: linear-gradient(90deg, var(--green), var(--teal));
        }

        .kpi-card.sky::before {
            background: linear-gradient(90deg, var(--sky), var(--lavender));
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            border-color: var(--border2);
        }

        .kpi-icon {
            font-size: 28px;
            margin-bottom: 12px;
            display: block;
        }

        .kpi-value {
            font-family: var(--mono);
            font-size: 26px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
            line-height: 1;
        }

        .kpi-label {
            font-size: 12px;
            color: var(--text2);
            line-height: 1.4;
        }

        .kpi-sub {
            font-size: 11px;
            color: var(--text3);
            margin-top: 6px;
            font-family: var(--mono);
        }

        /* ─── CHART CARDS ─── */
        .chart-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .chart-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }

        .chart-grid-1-2 {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }

        .chart-grid-2-1 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        @media (max-width: 1100px) {

            .chart-grid-2,
            .chart-grid-3,
            .chart-grid-1-2,
            .chart-grid-2-1 {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: var(--r2);
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        .chart-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 12px;
        }

        .chart-card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
        }

        .chart-card-sub {
            font-size: 12px;
            color: var(--text3);
            margin-top: 3px;
        }

        .chart-badge {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
            font-family: var(--mono);
            white-space: nowrap;
            flex-shrink: 0;
        }

        .badge-gold {
            background: rgba(240, 180, 41, 0.15);
            color: var(--gold);
            border: 1px solid rgba(240, 180, 41, 0.3);
        }

        .badge-teal {
            background: rgba(29, 217, 176, 0.12);
            color: var(--teal);
            border: 1px solid rgba(29, 217, 176, 0.25);
        }

        .badge-coral {
            background: rgba(242, 107, 91, 0.12);
            color: var(--coral);
            border: 1px solid rgba(242, 107, 91, 0.25);
        }

        .badge-lav {
            background: rgba(155, 143, 247, 0.12);
            color: var(--lavender);
            border: 1px solid rgba(155, 143, 247, 0.25);
        }

        .badge-green {
            background: rgba(86, 212, 138, 0.12);
            color: var(--green);
            border: 1px solid rgba(86, 212, 138, 0.25);
        }

        .badge-sky {
            background: rgba(91, 196, 245, 0.12);
            color: var(--sky);
            border: 1px solid rgba(91, 196, 245, 0.25);
        }

        .chart-wrap {
            position: relative;
            width: 100%;
        }

        .chart-wrap canvas {
            max-width: 100%;
        }

        /* ─── CONTROLS ─── */
        .ctrl-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 16px;
        }

        .ctrl-label {
            font-size: 12px;
            color: var(--text3);
        }

        .ctrl-select {
            background: var(--bg3);
            border: 1px solid var(--border2);
            color: var(--text);
            border-radius: 8px;
            padding: 6px 12px;
            font-family: var(--font);
            font-size: 13px;
            cursor: pointer;
            outline: none;
            transition: border-color 0.2s;
        }

        .ctrl-select:hover,
        .ctrl-select:focus {
            border-color: var(--teal);
        }

        .ctrl-btn {
            background: var(--bg3);
            border: 1px solid var(--border2);
            color: var(--text2);
            border-radius: 8px;
            padding: 6px 14px;
            font-family: var(--font);
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .ctrl-btn:hover,
        .ctrl-btn.active {
            background: var(--surface);
            border-color: var(--teal);
            color: var(--teal);
        }

        /* ─── TABLE ─── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .data-table th {
            text-align: right;
            padding: 10px 14px;
            color: var(--text3);
            font-weight: 500;
            font-size: 11px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid rgba(46, 63, 92, 0.5);
            color: var(--text);
            vertical-align: middle;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .rank-num {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text3);
            width: 28px;
            display: inline-block;
            text-align: center;
        }

        .rank-num.top1 {
            color: var(--gold);
        }

        .rank-num.top2 {
            color: var(--text2);
        }

        .rank-num.top3 {
            color: var(--amber);
        }

        .bar-inline {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bar-fill {
            height: 6px;
            border-radius: 3px;
            background: linear-gradient(90deg, var(--teal2), var(--teal));
            min-width: 4px;
            transition: width 0.5s;
        }

        .mono-val {
            font-family: var(--mono);
            font-size: 12px;
            color: var(--teal);
        }

        /* ─── HEATMAP ─── */
        .heatmap-grid {
            display: grid;
            grid-template-columns: 60px repeat(12, 1fr);
            gap: 3px;
            font-size: 11px;
        }

        .heatmap-cell {
            aspect-ratio: 1;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--mono);
            font-size: 10px;
            transition: transform 0.1s;
            cursor: default;
            position: relative;
        }

        .heatmap-cell:hover {
            transform: scale(1.15);
            z-index: 2;
        }

        .heatmap-label {
            display: flex;
            align-items: center;
            font-size: 11px;
            color: var(--text3);
            font-family: var(--mono);
            padding-left: 4px;
        }

        .heatmap-month-header {
            text-align: center;
            color: var(--text3);
            font-size: 10px;
            padding-bottom: 4px;
            font-family: var(--mono);
        }

        /* ─── SCATTER ─── */
        .scatter-legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .scatter-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text2);
        }

        .scatter-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* ─── GAUGE / METER ─── */
        .gauge-row {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .gauge-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .gauge-header {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }

        .gauge-name {
            color: var(--text2);
        }

        .gauge-val {
            font-family: var(--mono);
            color: var(--text);
        }

        .gauge-track {
            height: 8px;
            background: var(--bg3);
            border-radius: 4px;
            overflow: hidden;
        }

        .gauge-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 1s cubic-bezier(0.23, 1, 0.32, 1);
        }

        /* ─── EMPTY STATES ─── */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text3);
            font-size: 13px;
        }

        .empty-state-icon {
            font-size: 36px;
            margin-bottom: 10px;
            display: block;
        }

        /* ─── TICKER / STATS BAR ─── */
        .stats-ticker {
            background: var(--bg2);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 12px 32px;
            display: flex;
            gap: 40px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .stats-ticker::-webkit-scrollbar {
            display: none;
        }

        .ticker-item {
            display: flex;
            gap: 8px;
            align-items: baseline;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .ticker-label {
            font-size: 11px;
            color: var(--text3);
        }

        .ticker-val {
            font-family: var(--mono);
            font-size: 14px;
            font-weight: 600;
            color: var(--teal);
        }

        /* ─── TOOLTIP OVERRIDE ─── */
        .chartjs-tooltip {
            background: var(--bg3) !important;
            border: 1px solid var(--border2) !important;
            border-radius: 8px !important;
            font-family: var(--font) !important;
        }

        /* ─── SCROLL FADE-IN ─── */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s, transform 0.5s;
        }

        .fade-in.visible {
            opacity: 1;
            transform: none;
        }

        /* ─── ANNOTATION ─── */
        .annotation-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(29, 217, 176, 0.06);
            border: 1px solid rgba(29, 217, 176, 0.2);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 12px;
            color: var(--text2);
            margin-top: 12px;
        }

        .annotation-icon {
            font-size: 16px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ─── FOOTER ─── */
        .footer {
            padding: 32px;
            text-align: center;
            color: var(--text3);
            font-size: 12px;
            border-top: 1px solid var(--border);
            margin-top: 48px;
        }
    </style>
</head>

<body>

    <!-- ══════════════════════════════════════════════════════════════════
     UPLOAD SCREEN
══════════════════════════════════════════════════════════════════ -->
    <div id="upload-screen">
        <div class="upload-logo">
            <div class="upload-logo-icon">🌿</div>
            <div class="upload-logo-text">حسبة · Analytics</div>
        </div>
        <p class="upload-subtitle">
            ارفع ملف تصدير قاعدة البيانات (.sql) لتوليد لوحة تحليلات شاملة — كل العمليات تتم محلياً في المتصفح بدون أي
            خادم
        </p>
        <div class="upload-zone" id="drop-zone">
            <span class="upload-icon">📁</span>
            <div class="upload-text-main">اسحب ملف SQL هنا</div>
            <div class="upload-text-sub">أو اضغط لاختيار الملف</div>
            <button class="upload-btn" onclick="document.getElementById('file-input').click()">
                اختر ملف .sql
            </button>
            <input type="file" id="file-input" accept=".sql,.txt">
        </div>
        <p style="font-size:12px;color:var(--text3);text-align:center">
            يدعم MySQL dump (mysqldump) — جميع البيانات تُعالَج داخل المتصفح فقط ولا تُرسل لأي خادم
        </p>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
     PROGRESS SCREEN
══════════════════════════════════════════════════════════════════ -->
    <div id="progress-screen">
        <div class="progress-title">⚙️ جاري تحليل البيانات...</div>
        <div class="progress-bar-track">
            <div class="progress-bar-fill" id="prog-fill"></div>
        </div>
        <div class="progress-step" id="prog-step">تهيئة المحلل...</div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
     DASHBOARD
══════════════════════════════════════════════════════════════════ -->
    <div id="dashboard">

        <!-- Top Nav -->
        <nav class="topnav">
            <div class="nav-logo">
                <div class="nav-logo-icon" style="font-size:14px">🌿</div>
                حسبة Analytics
            </div>
            <ul class="nav-links">
                <li><a href="#sec-kpi">نظرة عامة</a></li>
                <li><a href="#sec-prices">الأسعار</a></li>
                <li><a href="#sec-farmers">المزارعون</a></li>
                <li><a href="#sec-traders">التجار</a></li>
                <li><a href="#sec-products">المنتجات</a></li>
                <li><a href="#sec-finance">المالية</a></li>
                <li><a href="#sec-seasonal">الموسمية</a></li>
                <li><a href="#sec-agents">الوكلاء</a></li>
            </ul>
            <div class="nav-badge" id="db-info-badge">—</div>
        </nav>

        <!-- Stats Ticker -->
        <div class="stats-ticker" id="stats-ticker">
            <div class="ticker-item"><span class="ticker-label">إجمالي الفواتير</span><span class="ticker-val"
                    id="tk-bills">—</span></div>
            <div class="ticker-item"><span class="ticker-label">إجمالي أوزان البضاعة</span><span class="ticker-val"
                    id="tk-weight">—</span></div>
            <div class="ticker-item"><span class="ticker-label">متوسط سعر الكيلو</span><span class="ticker-val"
                    id="tk-avgprice">—</span></div>
            <div class="ticker-item"><span class="ticker-label">إجمالي العمولات</span><span class="ticker-val"
                    id="tk-coms">—</span></div>
            <div class="ticker-item"><span class="ticker-label">عدد المزارعين النشطين</span><span class="ticker-val"
                    id="tk-farmers">—</span></div>
            <div class="ticker-item"><span class="ticker-label">عدد التجار النشطين</span><span class="ticker-val"
                    id="tk-traders">—</span></div>
            <div class="ticker-item"><span class="ticker-label">أنواع المنتجات</span><span class="ticker-val"
                    id="tk-prods">—</span></div>
            <div class="ticker-item"><span class="ticker-label">الفترة الزمنية</span><span class="ticker-val"
                    id="tk-period">—</span></div>
        </div>

        <!-- ── SECTION 1: KPIs ── -->
        <section class="section fade-in" id="sec-kpi">
            <div class="section-header">
                <div class="section-title-group">
                    <div class="section-eyebrow">نظرة عامة</div>
                    <div class="section-title">مؤشرات <span>الأداء الرئيسية</span></div>
                    <div class="section-desc">ملخص شامل لجميع النشاطات المسجّلة في قاعدة البيانات</div>
                </div>
            </div>
            <div class="kpi-grid" id="kpi-grid">
                <div class="kpi-card gold"><span class="kpi-icon">📊</span>
                    <div class="kpi-value" id="kpi-total-orders">—</div>
                    <div class="kpi-label">إجمالي الطلبيات</div>
                    <div class="kpi-sub" id="kpi-total-orders-sub">—</div>
                </div>
                <div class="kpi-card teal"><span class="kpi-icon">⚖️</span>
                    <div class="kpi-value" id="kpi-total-weight">—</div>
                    <div class="kpi-label">إجمالي الأوزان (كجم)</div>
                    <div class="kpi-sub" id="kpi-total-weight-sub">—</div>
                </div>
                <div class="kpi-card coral"><span class="kpi-icon">💰</span>
                    <div class="kpi-value" id="kpi-avg-price">—</div>
                    <div class="kpi-label">متوسط السعر / كجم (₪)</div>
                    <div class="kpi-sub" id="kpi-avg-price-sub">—</div>
                </div>
                <div class="kpi-card lav"><span class="kpi-icon">🤝</span>
                    <div class="kpi-value" id="kpi-total-coms">—</div>
                    <div class="kpi-label">إجمالي العمولات (₪)</div>
                    <div class="kpi-sub">6% من قيمة البضاعة</div>
                </div>
                <div class="kpi-card green"><span class="kpi-icon">🌾</span>
                    <div class="kpi-value" id="kpi-farmers">—</div>
                    <div class="kpi-label">المزارعون النشطون</div>
                    <div class="kpi-sub" id="kpi-farmers-sub">—</div>
                </div>
                <div class="kpi-card sky"><span class="kpi-icon">🏪</span>
                    <div class="kpi-value" id="kpi-traders">—</div>
                    <div class="kpi-label">التجار النشطون</div>
                    <div class="kpi-sub" id="kpi-traders-sub">—</div>
                </div>
                <div class="kpi-card gold"><span class="kpi-icon">🥦</span>
                    <div class="kpi-value" id="kpi-products">—</div>
                    <div class="kpi-label">أنواع المنتجات</div>
                    <div class="kpi-sub" id="kpi-products-sub">—</div>
                </div>
                <div class="kpi-card teal"><span class="kpi-icon">📦</span>
                    <div class="kpi-value" id="kpi-crates">—</div>
                    <div class="kpi-label">إجمالي الأطباق المتداولة</div>
                    <div class="kpi-sub" id="kpi-crates-sub">—</div>
                </div>
            </div>
        </section>

        <div class="divider"></div>

        <!-- ── SECTION 2: PRICES OVER YEARS ── -->
        <section class="section fade-in" id="sec-prices">
            <div class="section-header">
                <div class="section-title-group">
                    <div class="section-eyebrow">تحليل الأسعار</div>
                    <div class="section-title">متوسط سعر <span>المنتجات عبر السنوات</span></div>
                    <div class="section-desc">تطور متوسط سعر الكيلو لكل منتج — اختر المنتجات التي تريد مقارنتها</div>
                </div>
            </div>
            <div class="chart-card">
                <div class="ctrl-row">
                    <span class="ctrl-label">المنتجات:</span>
                    <select class="ctrl-select" id="price-prod-select" multiple size="1"
                        style="min-width:200px"></select>
                    <button class="ctrl-btn active" id="price-top5-btn" onclick="priceShowTop5()">أعلى 5
                        منتجات</button>
                    <button class="ctrl-btn" id="price-all-btn" onclick="priceShowAll()">كل المنتجات</button>
                    <select class="ctrl-select" id="price-view-select" onchange="renderPriceChart()">
                        <option value="avg">متوسط السعر</option>
                        <option value="max">أعلى سعر</option>
                        <option value="min">أدنى سعر</option>
                    </select>
                </div>
                <div class="chart-wrap" style="height:360px"><canvas id="chart-prices"></canvas></div>
                <div class="annotation-note">
                    <span class="annotation-icon">💡</span>
                    <span>يُحسب متوسط السعر من (إجمالي قيمة الفاتورة ÷ الوزن الكلي) لكل منتج في كل سنة. السعر بالشيكل
                        الإسرائيلي (₪) لكل كيلوغرام.</span>
                </div>
            </div>
        </section>

        <div class="divider"></div>

        <!-- ── SECTION 3: FARMER PERFORMANCE ── -->
        <section class="section fade-in" id="sec-farmers">
            <div class="section-header">
                <div class="section-title-group">
                    <div class="section-eyebrow">تحليل المزارعين</div>
                    <div class="section-title">أداء <span>المزارعين عبر السنوات</span></div>
                    <div class="section-desc">مقارنة نشاط المزارعين من حيث الحجم والقيمة والمنتجات</div>
                </div>
            </div>
            <div class="chart-grid-2">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">منحنى أداء مزارع مختار</div>
                            <div class="chart-card-sub">الوزن المباع سنة بسنة</div>
                        </div>
                        <span class="chart-badge badge-teal">خط زمني</span>
                    </div>
                    <div class="ctrl-row">
                        <select class="ctrl-select" id="farmer-select" style="min-width:220px"
                            onchange="renderFarmerChart()">
                            <option>— اختر مزارعاً —</option>
                        </select>
                        <select class="ctrl-select" id="farmer-metric" onchange="renderFarmerChart()">
                            <option value="weight">الوزن (كجم)</option>
                            <option value="revenue">الإيرادات (₪)</option>
                            <option value="orders">عدد الفواتير</option>
                        </select>
                    </div>
                    <div class="chart-wrap" style="height:280px"><canvas id="chart-farmer-timeline"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">تصنيف أفضل المزارعين</div>
                            <div class="chart-card-sub">إجمالي الوزن المباع على مدى الفترة الكاملة</div>
                        </div>
                        <span class="chart-badge badge-gold">تصنيف</span>
                    </div>
                    <div class="chart-wrap" style="height:300px"><canvas id="chart-farmer-rank"></canvas></div>
                </div>
            </div>
            <div style="margin-top:20px">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">توزيع المنتجات لكل مزارع (أعلى 10)</div>
                            <div class="chart-card-sub">ما هي المنتجات التي يبيعها كل مزارع بشكل رئيسي</div>
                        </div>
                        <span class="chart-badge badge-lav">توزيع</span>
                    </div>
                    <div class="chart-wrap" style="height:280px"><canvas id="chart-farmer-products"></canvas></div>
                </div>
            </div>
            <div style="margin-top:20px">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">جدول أداء المزارعين التفصيلي</div>
                            <div class="chart-card-sub">أعلى 20 مزارعاً من حيث الوزن الكلي</div>
                        </div>
                        <span class="chart-badge badge-green">جدول</span>
                    </div>
                    <div id="farmer-table-wrap" style="overflow-x:auto;max-height:380px;overflow-y:auto"></div>
                </div>
            </div>
        </section>

        <div class="divider"></div>

        <!-- ── SECTION 4: TOP PRODUCTS ── -->
        <section class="section fade-in" id="sec-products">
            <div class="section-header">
                <div class="section-title-group">
                    <div class="section-eyebrow">تحليل المنتجات</div>
                    <div class="section-title">أفضل المنتجات <span>أداءً عبر السنوات</span></div>
                    <div class="section-desc">منحنيات متعددة تظهر أداء كل منتج بمعيار الوزن أو القيمة أو عدد الصفقات
                    </div>
                </div>
            </div>
            <div class="chart-card">
                <div class="ctrl-row">
                    <span class="ctrl-label">المعيار:</span>
                    <select class="ctrl-select" id="prod-metric-select" onchange="renderTopProdsChart()">
                        <option value="weight">الوزن الكلي (كجم)</option>
                        <option value="revenue">إجمالي الإيرادات (₪)</option>
                        <option value="orders">عدد الصفقات</option>
                        <option value="avgprice">متوسط السعر/كجم</option>
                    </select>
                    <span class="ctrl-label">أعلى:</span>
                    <select class="ctrl-select" id="prod-topn-select" onchange="renderTopProdsChart()">
                        <option value="5">5 منتجات</option>
                        <option value="8" selected>8 منتجات</option>
                        <option value="10">10 منتجات</option>
                        <option value="15">15 منتج</option>
                    </select>
                </div>
                <div class="chart-wrap" style="height:380px"><canvas id="chart-top-products"></canvas></div>
            </div>
            <div style="margin-top:20px" class="chart-grid-2">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">حصة كل منتج من الحجم الكلي</div>
                            <div class="chart-card-sub">دونات تفاعلي للحصص السوقية بالوزن</div>
                        </div>
                        <span class="chart-badge badge-gold">دونات</span>
                    </div>
                    <div class="chart-wrap" style="height:280px"><canvas id="chart-prod-share"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">تصنيف الأسعار — أعلى وأدنى</div>
                            <div class="chart-card-sub">متوسط السعر / كجم لكل منتج (أعلى 20)</div>
                        </div>
                        <span class="chart-badge badge-coral">أسعار</span>
                    </div>
                    <div class="chart-wrap" style="height:280px"><canvas id="chart-prod-price-rank"></canvas></div>
                </div>
            </div>
        </section>

        <div class="divider"></div>

        <!-- ── SECTION 5: TRADERS ── -->
        <section class="section fade-in" id="sec-traders">
            <div class="section-header">
                <div class="section-title-group">
                    <div class="section-eyebrow">تحليل التجار</div>
                    <div class="section-title">نشاط <span>التجار والمشترين</span></div>
                </div>
            </div>
            <div class="chart-grid-2">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">أعلى 15 تاجراً — حجم الشراء</div>
                            <div class="chart-card-sub">إجمالي الوزن المشترى لكل تاجر</div>
                        </div>
                        <span class="chart-badge badge-sky">تصنيف</span>
                    </div>
                    <div class="chart-wrap" style="height:320px"><canvas id="chart-trader-rank"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">منحنى نشاط تاجر مختار</div>
                            <div class="chart-card-sub">عدد الفواتير والحجم سنة بسنة</div>
                        </div>
                        <span class="chart-badge badge-lav">خط زمني</span>
                    </div>
                    <div class="ctrl-row">
                        <select class="ctrl-select" id="trader-select" style="min-width:220px"
                            onchange="renderTraderChart()">
                            <option>— اختر تاجراً —</option>
                        </select>
                    </div>
                    <div class="chart-wrap" style="height:280px"><canvas id="chart-trader-timeline"></canvas></div>
                </div>
            </div>
            <div style="margin-top:20px">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">جدول التجار التفصيلي</div>
                            <div class="chart-card-sub">أعلى 20 تاجراً</div>
                        </div>
                    </div>
                    <div id="trader-table-wrap" style="overflow-x:auto"></div>
                </div>
            </div>
        </section>

        <div class="divider"></div>

        <!-- ── SECTION 6: FINANCIAL ── -->
        <section class="section fade-in" id="sec-finance">
            <div class="section-header">
                <div class="section-title-group">
                    <div class="section-eyebrow">التحليل المالي</div>
                    <div class="section-title">توزيع <span>الرسوم والعمولات</span></div>
                    <div class="section-desc">تفصيل كامل للعمولات والنقل والبلدية والأطباق وإيجار الأطباق</div>
                </div>
            </div>
            <div class="chart-grid-1-2">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">توزيع أنواع الرسوم</div>
                            <div class="chart-card-sub">نسبة كل نوع رسوم من الإجمالي</div>
                        </div>
                        <span class="chart-badge badge-gold">دونات</span>
                    </div>
                    <div class="chart-wrap" style="height:280px"><canvas id="chart-fees-donut"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">تطور العمولات والرسوم سنوياً</div>
                            <div class="chart-card-sub">منحنى تراكمي لكل نوع رسوم عبر السنوات</div>
                        </div>
                        <span class="chart-badge badge-teal">منطقة</span>
                    </div>
                    <div class="chart-wrap" style="height:280px"><canvas id="chart-fees-yearly"></canvas></div>
                </div>
            </div>
            <div style="margin-top:20px" class="chart-grid-2">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">حركة الأطباق (Crates) سنوياً</div>
                            <div class="chart-card-sub">عدد الأطباق المتداولة كل سنة</div>
                        </div>
                        <span class="chart-badge badge-coral">أطباق</span>
                    </div>
                    <div class="chart-wrap" style="height:260px"><canvas id="chart-crates-yearly"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">مؤشرات الرسوم لكل كيلو</div>
                            <div class="chart-card-sub">متوسط الرسوم لكل كيلوغرام مباع</div>
                        </div>
                        <span class="chart-badge badge-lav">مؤشرات</span>
                    </div>
                    <div class="gauge-row" style="padding:10px 0" id="fees-gauge-row">
                        <div class="empty-state"><span class="empty-state-icon">⏳</span>جاري التحليل...</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="divider"></div>

        <!-- ── SECTION 7: SEASONAL / HEATMAP ── -->
        <section class="section fade-in" id="sec-seasonal">
            <div class="section-header">
                <div class="section-title-group">
                    <div class="section-eyebrow">التحليل الموسمي</div>
                    <div class="section-title">الأنماط <span>الموسمية والشهرية</span></div>
                    <div class="section-desc">خريطة حرارية تظهر كثافة النشاط التجاري شهراً بشهر وسنة بسنة</div>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <div class="chart-card-title">خريطة حرارية — عدد الفواتير الشهرية</div>
                        <div class="chart-card-sub">كل خلية = شهر × سنة — اللون الأغمق يعني نشاطاً أعلى</div>
                    </div>
                    <span class="chart-badge badge-teal">Heatmap</span>
                </div>
                <div id="heatmap-container" style="overflow-x:auto;padding-bottom:8px"></div>
            </div>
            <div style="margin-top:20px" class="chart-grid-2">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">متوسط النشاط الشهري</div>
                            <div class="chart-card-sub">أي الأشهر الأكثر نشاطاً؟ (متوسط كل السنوات)</div>
                        </div>
                        <span class="chart-badge badge-gold">شريطي</span>
                    </div>
                    <div class="chart-wrap" style="height:260px"><canvas id="chart-monthly-avg"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">نمو الحجم السنوي</div>
                            <div class="chart-card-sub">إجمالي الوزن المباع كل سنة — رصد النمو أو التراجع</div>
                        </div>
                        <span class="chart-badge badge-teal">نمو</span>
                    </div>
                    <div class="chart-wrap" style="height:260px"><canvas id="chart-yearly-growth"></canvas></div>
                </div>
            </div>
        </section>

        <div class="divider"></div>

        <!-- ── SECTION 8: AGENTS / COMMONS ── -->
        <section class="section fade-in" id="sec-agents">
            <div class="section-header">
                <div class="section-title-group">
                    <div class="section-eyebrow">الوكلاء والعملاء</div>
                    <div class="section-title">أداء <span>الوكلاء (Commons)</span></div>
                    <div class="section-desc">تصنيف الوكلاء وتحليل حجم النشاط عبر شبكة العملاء</div>
                </div>
            </div>
            <div class="chart-grid-2">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">أعلى الوكلاء — عدد المزارعين المرتبطين</div>
                            <div class="chart-card-sub">من يمثل أكثر المزارعين؟</div>
                        </div>
                        <span class="chart-badge badge-lav">شريطي</span>
                    </div>
                    <div class="chart-wrap" style="height:300px"><canvas id="chart-agents-farmers"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">نشاط السوق حسب العميل (Client)</div>
                            <div class="chart-card-sub">مقارنة أداء كل سوق/مشغّل في المنصة</div>
                        </div>
                        <span class="chart-badge badge-coral">مقارنة</span>
                    </div>
                    <div class="chart-wrap" style="height:300px"><canvas id="chart-clients-compare"></canvas></div>
                </div>
            </div>
            <div style="margin-top:20px">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <div>
                            <div class="chart-card-title">معدل العمولة مقابل حجم النشاط</div>
                            <div class="chart-card-sub">مخطط تشتت — الوكلاء الأعلى عمولةً مقابل الأكثر نشاطاً</div>
                        </div>
                        <span class="chart-badge badge-sky">Scatter</span>
                    </div>
                    <div class="chart-wrap" style="height:280px"><canvas id="chart-agents-scatter"></canvas></div>
                </div>
            </div>
        </section>

        <footer class="footer">
            حسبة Analytics — جميع العمليات تتم محلياً في المتصفح | بيانات السوق المركزي للخضار
        </footer>

    </div><!-- end #dashboard -->

    <!-- ══════════════════════════════════════════════════════════════════
     JAVASCRIPT ENGINE
══════════════════════════════════════════════════════════════════ -->
    <script>
        'use strict';

        // ─────────────────────────────────────────────────────────────────
        // 1. STATE
        // ─────────────────────────────────────────────────────────────────
        const DB = {
            dailybills: [],
            dailyorders: [],
            farmers: [],
            traders: [],
            products: [],
            commons: [],
            commonfarmers: [],
            collections: [],
            collectiontraders: [],
            clients: [],
            taxes: [],
        };

        // Lookup maps built after parsing
        const MAPS = {
            farmers: {}, // id -> name
            traders: {}, // id -> name
            products: {}, // id -> name
            commons: {}, // id -> name
            clients: {}, // id -> fname
        };

        // Chart instances (destroyed before re-render)
        const CHARTS = {};

        // Derived datasets (computed once)
        let DERIVED = {};

        const MONTHS_AR = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر',
            'نوفمبر', 'ديسمبر'
        ];

        const PALETTE = [
            '#1dd9b0', '#f0b429', '#f26b5b', '#9b8ff7', '#5bc4f5', '#56d48a',
            '#f5a623', '#e879f9', '#38bdf8', '#a3e635', '#fb923c', '#f472b6',
            '#818cf8', '#34d399', '#fbbf24', '#60a5fa', '#c084fc', '#4ade80',
        ];

        // ─────────────────────────────────────────────────────────────────
        // 2. UPLOAD HANDLING
        // ─────────────────────────────────────────────────────────────────
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');

        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', e => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
        });
        fileInput.addEventListener('change', e => {
            if (e.target.files[0]) handleFile(e.target.files[0]);
        });

        function handleFile(file) {
            const reader = new FileReader();
            reader.onload = e => processSQL(e.target.result, file.name);
            reader.readAsText(file, 'utf-8');
        }

        // ─────────────────────────────────────────────────────────────────
        // 3. SQL PARSER
        // ─────────────────────────────────────────────────────────────────
        function setProgress(pct, msg) {
            document.getElementById('prog-fill').style.width = pct + '%';
            document.getElementById('prog-step').textContent = msg;
        }

        function processSQL(sql, filename) {
            document.getElementById('upload-screen').style.display = 'none';
            const ps = document.getElementById('progress-screen');
            ps.style.display = 'flex';

            // Yield to let the browser paint
            setTimeout(() => {
                try {
                    setProgress(5, 'تحليل ملف SQL...');
                    parseSQLDump(sql);

                    setProgress(40, 'بناء فهارس البيانات...');
                    buildMaps();

                    setProgress(60, 'حساب الإحصاءات...');
                    computeDerived();

                    setProgress(80, 'رسم المخططات...');
                    ps.style.display = 'none';
                    document.getElementById('dashboard').style.display = 'block';

                    setTimeout(() => {
                        populateKPIs();
                        populateTicker();
                        populateSelectors();
                        renderAllCharts();
                        initScrollFadeIn();
                        document.getElementById('db-info-badge').textContent = filename;
                        setProgress(100, 'اكتمل التحميل');
                    }, 100);
                } catch (err) {
                    ps.style.display = 'none';
                    document.getElementById('upload-screen').style.display = 'flex';
                    alert('خطأ في تحليل الملف: ' + err.message + '\n\nتأكد أن الملف هو MySQL dump صحيح.');
                    console.error(err);
                }
            }, 50);
        }

        function parseSQLDump(sql) {
            // Remove comments
            sql = sql.replace(/\/\*.*?\*\//gs, '');
            sql = sql.replace(/^--.*$/gm, '');

            // Extract INSERT statements
            const insertRe = /INSERT\s+INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s*VALUES\s*([\s\S]+?)(?=INSERT\s+INTO|$)/gi;
            let match;

            while ((match = insertRe.exec(sql)) !== null) {
                const tableName = match[1].toLowerCase();
                const colsPart = match[2];
                const valsPart = match[3];

                if (!Object.prototype.hasOwnProperty.call(DB, tableName)) continue;

                const cols = colsPart.split(',').map(c => c.trim().replace(/`/g, '').trim());

            // Parse value tuples
            const rows = parseValueTuples(valsPart);
            for (const row of rows) {
                if (row.length === 0) continue;
                const obj = {};
                for (let i = 0; i < cols.length; i++) {
                    let v = row[i] !== undefined ? row[i] : null;
                    // Convert numeric strings
                    if (v !== null && v !== 'NULL' && !isNaN(v) && v !== '') v = parseFloat(v);
                    obj[cols[i]] = v === 'NULL' ? null : v;
                }
                DB[tableName].push(obj);
            }
        }
    }

    function parseValueTuples(valsPart) {
        const rows = [];
        // Find each (...) tuple
        let i = 0;
        const len = valsPart.length;

        while (i < len) {
            // Skip to '('
            while (i < len && valsPart[i] !== '(') i++;
            if (i >= len) break;
            i++; // skip '('

            const row = [];
            let cell = '';
            let inStr = false;
            let strChar = '';
            let escaped = false;

            while (i < len) {
                const ch = valsPart[i];

                if (escaped) {
                    cell += ch;
                    escaped = false;
                    i++;
                    continue;
                }
                if (ch === '\\' && inStr) {
                    escaped = true;
                    cell += ch;
                    i++;
                    continue;
                }

                if (!inStr && (ch === "'" || ch === '"')) {
                    inStr = true;
                    strChar = ch;
                    i++;
                    continue;
                }
                if (inStr && ch === strChar) {
                    inStr = false;
                    i++;
                    continue;
                }
                if (inStr) {
                    cell += ch;
                    i++;
                    continue;
                }

                if (ch === ',') {
                    row.push(cell.trim());
                    cell = '';
                    i++;
                    continue;
                }
                if (ch === ')') {
                    row.push(cell.trim());
                    i++;
                    break;
                }
                cell += ch;
                i++;
            }

            if (row.length > 0) rows.push(row);
        }

        return rows;
    }

    // ─────────────────────────────────────────────────────────────────
    // 4. BUILD MAPS & DERIVED DATA
    // ─────────────────────────────────────────────────────────────────
    function buildMaps() {
        DB.farmers.forEach(f => {
            MAPS.farmers[f.id] = f.name || ('مزارع #' + f.id);
        });
        DB.traders.forEach(t => {
            MAPS.traders[t.id] = t.name || ('تاجر #' + t.id);
        });
        DB.products.forEach(p => {
            MAPS.products[p.id] = p.prodName || ('منتج #' + p.id);
        });
        DB.commons.forEach(c => {
            MAPS.commons[c.id] = c.name || ('وكيل #' + c.id);
        });
        DB.clients.forEach(c => {
            MAPS.clients[c.id] = (c.fname || '') + ' ' + (c.lname || '');
        });
    }

    function computeDerived() {
        // Join dailyorders with dailybills to get year, farmerID, traderID
        const billMap = {};
        DB.dailybills.forEach(b => {
            billMap[b.id] = b;
        });

        // For each order, compute price per kg = (itemPrice * prodNum * prodWheight) / (prodNum * prodWheight) = itemPrice per unit weight
        // The itemPrice in dailyorders is price per unit (طبق/piece) — total value = itemPrice * prodNum
        // Weight per item = prodWheight, so total weight = prodNum * prodWheight (kg? or kg per طبق)
        // We'll treat: totalValue = itemPrice * prodNum, totalWeight = prodNum * prodWheight
        const enriched = [];

        for (const ord of DB.dailyorders) {
            const bill = billMap[ord.billID];
            if (!bill) continue;
            const dateStr = bill.dateInvoice;
            if (!dateStr) continue;
            const year = parseInt(String(dateStr).substring(0, 4));
            const month = parseInt(String(dateStr).substring(5, 7));
            if (isNaN(year) || isNaN(month)) continue;

            const totalWeight = (ord.prodNum || 0) * (ord.prodWheight || 0);
            const totalValue = (ord.itemPrice || 0) * (ord.prodNum || 0);
            const pricePerKg = totalWeight > 0 ? totalValue / totalWeight : 0;

            enriched.push({
                billID: ord.billID,
                orderID: ord.id,
                prodID: ord.prodID,
                farmerID: bill.farmerID,
                traderID: bill.traderID,
                year,
                month,
                prodNum: ord.prodNum || 0,
                prodWheight: ord.prodWheight || 0,
                itemPrice: ord.itemPrice || 0,
                totalWeight,
                totalValue,
                pricePerKg,
                comision: ord.comision || 0,
                municipality: ord.municipality || 0,
                empty: ord.empty || 0,
                transport: ord.transport || 0,
                emptyRent: ord.emptyRent || 0,
                totalTrans: ord.totalTrans || 0,
                emptyReturned: ord.emptyReturend || 0,
                clientID: ord.client_ID || bill.client_ID,
            });
        }

        DERIVED.enriched = enriched;

        // Years
        DERIVED.years = [...new Set(enriched.map(e => e.year))].sort();

        // ── Price per product per year
        const priceByProdYear = {}; // prodID -> year -> {totalVal, totalWt}
        for (const e of enriched) {
            if (!e.prodID || e.totalWeight <= 0) continue;
            if (!priceByProdYear[e.prodID]) priceByProdYear[e.prodID] = {};
            if (!priceByProdYear[e.prodID][e.year]) priceByProdYear[e.prodID][e.year] = {
                totalVal: 0,
                totalWt: 0,
                count: 0,
                maxP: 0,
                minP: Infinity
            };
            const b = priceByProdYear[e.prodID][e.year];
            b.totalVal += e.totalValue;
            b.totalWt += e.totalWeight;
            b.count++;
            if (e.pricePerKg > b.maxP) b.maxP = e.pricePerKg;
            if (e.pricePerKg < b.minP) b.minP = e.pricePerKg;
        }
        DERIVED.priceByProdYear = priceByProdYear;

        // ── Totals per product
        const prodTotals = {}; // prodID -> {weight, value, orders, avgPrice}
        for (const e of enriched) {
            if (!e.prodID) continue;
            if (!prodTotals[e.prodID]) prodTotals[e.prodID] = {
                weight: 0,
                value: 0,
                orders: 0
            };
            prodTotals[e.prodID].weight += e.totalWeight;
            prodTotals[e.prodID].value += e.totalValue;
            prodTotals[e.prodID].orders++;
        }
        for (const pid in prodTotals) {
            const p = prodTotals[pid];
            p.avgPrice = p.weight > 0 ? p.value / p.weight : 0;
        }
        DERIVED.prodTotals = prodTotals;

        // ── Totals per farmer
        const farmerTotals = {}; // farmerID -> {weight, value, orders, years:{}}
        for (const e of enriched) {
            if (!e.farmerID) continue;
            if (!farmerTotals[e.farmerID]) farmerTotals[e.farmerID] = {
                weight: 0,
                value: 0,
                orders: 0,
                years: {}
            };
            const f = farmerTotals[e.farmerID];
            f.weight += e.totalWeight;
            f.value += e.totalValue;
            f.orders++;
            if (!f.years[e.year]) f.years[e.year] = {
                weight: 0,
                value: 0,
                orders: 0
            };
            f.years[e.year].weight += e.totalWeight;
            f.years[e.year].value += e.totalValue;
            f.years[e.year].orders++;
        }
        DERIVED.farmerTotals = farmerTotals;

        // ── Farmer product distribution (top farmers, what products they sell)
        const farmerProds = {}; // farmerID -> prodID -> weight
        for (const e of enriched) {
            if (!e.farmerID || !e.prodID) continue;
            if (!farmerProds[e.farmerID]) farmerProds[e.farmerID] = {};
            farmerProds[e.farmerID][e.prodID] = (farmerProds[e.farmerID][e.prodID] || 0) + e.totalWeight;
        }
        DERIVED.farmerProds = farmerProds;

        // ── Totals per trader
        const traderTotals = {}; // traderID -> {weight, value, orders, years:{}}
        for (const e of enriched) {
            if (!e.traderID) continue;
            if (!traderTotals[e.traderID]) traderTotals[e.traderID] = {
                weight: 0,
                value: 0,
                orders: 0,
                years: {}
            };
            const t = traderTotals[e.traderID];
            t.weight += e.totalWeight;
            t.value += e.totalValue;
            t.orders++;
            if (!t.years[e.year]) t.years[e.year] = {
                weight: 0,
                value: 0,
                orders: 0
            };
            t.years[e.year].weight += e.totalWeight;
            t.years[e.year].value += e.totalValue;
            t.years[e.year].orders++;
        }
        DERIVED.traderTotals = traderTotals;

        // ── Monthly activity heatmap
        const monthlyActivity = {}; // year -> month -> {bills, weight}
        for (const e of enriched) {
            if (!monthlyActivity[e.year]) monthlyActivity[e.year] = {};
            if (!monthlyActivity[e.year][e.month]) monthlyActivity[e.year][e.month] = {
                bills: new Set(),
                weight: 0
            };
            monthlyActivity[e.year][e.month].bills.add(e.billID);
            monthlyActivity[e.year][e.month].weight += e.totalWeight;
        }
        // Convert sets to counts
        for (const yr in monthlyActivity)
            for (const mo in monthlyActivity[yr])
                monthlyActivity[yr][mo].bills = monthlyActivity[yr][mo].bills.size;
        DERIVED.monthlyActivity = monthlyActivity;

        // ── Fees totals
        const fees = {
            comision: 0,
            municipality: 0,
            empty: 0,
            transport: 0,
            emptyRent: 0
        };
        let totalWeight = 0;
        const feesByYear = {}; // year -> fees
        for (const e of enriched) {
            fees.comision += e.comision;
            fees.municipality += e.municipality;
            fees.empty += e.empty;
            fees.transport += e.transport;
            fees.emptyRent += e.emptyRent;
            totalWeight += e.totalWeight;
            if (!feesByYear[e.year]) feesByYear[e.year] = {
                comision: 0,
                municipality: 0,
                empty: 0,
                transport: 0,
                emptyRent: 0
            };
            feesByYear[e.year].comision += e.comision;
            feesByYear[e.year].municipality += e.municipality;
            feesByYear[e.year].empty += e.empty;
            feesByYear[e.year].transport += e.transport;
            feesByYear[e.year].emptyRent += e.emptyRent;
        }
        DERIVED.fees = fees;
        DERIVED.feesByYear = feesByYear;
        DERIVED.totalWeight = totalWeight;
        DERIVED.totalValue = enriched.reduce((s, e) => s + e.totalValue, 0);

        // ── Crates per year
        const cratesByYear = {};
        for (const e of enriched) {
            cratesByYear[e.year] = (cratesByYear[e.year] || 0) + e.prodNum;
        }
        DERIVED.cratesByYear = cratesByYear;

        // ── Clients activity
        const clientActivity = {}; // clientID -> {weight, orders}
        for (const e of enriched) {
            if (!clientActivity[e.clientID]) clientActivity[e.clientID] = {
                weight: 0,
                orders: 0,
                value: 0
            };
            clientActivity[e.clientID].weight += e.totalWeight;
            clientActivity[e.clientID].orders++;
            clientActivity[e.clientID].value += e.totalValue;
        }
        DERIVED.clientActivity = clientActivity;

        // ── Top products by year (for multi-line chart)
        const prodByYear = {}; // prodID -> year -> {weight, value, orders}
        for (const e of enriched) {
            if (!e.prodID) continue;
            if (!prodByYear[e.prodID]) prodByYear[e.prodID] = {};
            if (!prodByYear[e.prodID][e.year]) prodByYear[e.prodID][e.year] = {
                weight: 0,
                value: 0,
                orders: 0
            };
            prodByYear[e.prodID][e.year].weight += e.totalWeight;
            prodByYear[e.prodID][e.year].value += e.totalValue;
            prodByYear[e.prodID][e.year].orders++;
        }
        DERIVED.prodByYear = prodByYear;
    }

    // ─────────────────────────────────────────────────────────────────
    // 5. POPULATE UI
    // ─────────────────────────────────────────────────────────────────
    function fmt(n, digits = 0) {
        if (n === undefined || n === null || isNaN(n)) return '—';
        return n.toLocaleString('ar-EG', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        });
    }

    function populateKPIs() {
        const e = DERIVED.enriched;
        const totalOrders = e.length;
        const totalWeight = DERIVED.totalWeight;
        const totalValue = DERIVED.totalValue;
        const avgPrice = totalWeight > 0 ? totalValue / totalWeight : 0;
        const totalComs = DERIVED.fees.comision;
        const activeFarmers = Object.keys(DERIVED.farmerTotals).length;
        const activeTraders = Object.keys(DERIVED.traderTotals).length;
        const totalProds = Object.keys(DERIVED.prodTotals).length;
        const totalCrates = e.reduce((s, r) => s + r.prodNum, 0);

        const years = DERIVED.years;
        const period = years.length > 0 ? `${years[0]} — ${years[years.length-1]}` : '—';

        setText('kpi-total-orders', fmt(totalOrders));
        setText('kpi-total-orders-sub', period);
        setText('kpi-total-weight', fmt(totalWeight / 1000, 1) + ' طن');
        setText('kpi-total-weight-sub', fmt(totalWeight) + ' كجم إجمالي');
        setText('kpi-avg-price', fmt(avgPrice, 2) + ' ₪');
        setText('kpi-avg-price-sub', 'إجمالي الإيرادات: ' + fmt(totalValue, 0) + ' ₪');
        setText('kpi-total-coms', fmt(totalComs, 0) + ' ₪');
        setText('kpi-farmers', fmt(activeFarmers));
        setText('kpi-farmers-sub', 'من إجمالي ' + DB.farmers.length + ' مزارع مسجّل');
        setText('kpi-traders', fmt(activeTraders));
        setText('kpi-traders-sub', 'من إجمالي ' + DB.traders.length + ' تاجر مسجّل');
        setText('kpi-products', fmt(totalProds));
        setText('kpi-products-sub', 'من ' + DB.products.length + ' صنف في الكتالوج');
        setText('kpi-crates', fmt(totalCrates));
        setText('kpi-crates-sub', 'طبق/صندوق تداول');
    }

    function populateTicker() {
        const e = DERIVED.enriched;
        const yrs = DERIVED.years;
        setText('tk-bills', fmt(new Set(e.map(r => r.billID)).size));
        setText('tk-weight', fmt(DERIVED.totalWeight / 1000, 1) + ' طن');
        setText('tk-avgprice', fmt(DERIVED.totalWeight > 0 ? DERIVED.totalValue / DERIVED.totalWeight : 0, 2) +
            ' ₪/كجم');
        setText('tk-coms', fmt(DERIVED.fees.comision, 0) + ' ₪');
        setText('tk-farmers', fmt(Object.keys(DERIVED.farmerTotals).length));
        setText('tk-traders', fmt(Object.keys(DERIVED.traderTotals).length));
        setText('tk-prods', fmt(Object.keys(DERIVED.prodTotals).length));
        setText('tk-period', yrs.length > 0 ? yrs[0] + ' — ' + yrs[yrs.length - 1] : '—');
    }

    function populateSelectors() {
        // Farmer select
        const topFarmers = Object.entries(DERIVED.farmerTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 50);
        const fsel = document.getElementById('farmer-select');
        fsel.innerHTML = '<option value="">— اختر مزارعاً —</option>';
        topFarmers.forEach(([id]) => {
            const o = document.createElement('option');
            o.value = id;
            o.textContent = MAPS.farmers[id] || ('مزارع #' + id);
            fsel.appendChild(o);
        });

        // Trader select
        const topTraders = Object.entries(DERIVED.traderTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 50);
        const tsel = document.getElementById('trader-select');
        tsel.innerHTML = '<option value="">— اختر تاجراً —</option>';
        topTraders.forEach(([id]) => {
            const o = document.createElement('option');
            o.value = id;
            o.textContent = MAPS.traders[id] || ('تاجر #' + id);
            tsel.appendChild(o);
        });

        // Product select for price chart
        const topProds = Object.entries(DERIVED.prodTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 30);
        const psel = document.getElementById('price-prod-select');
        psel.innerHTML = '';
        topProds.forEach(([id]) => {
            const o = document.createElement('option');
            o.value = id;
            o.textContent = MAPS.products[id] || ('منتج #' + id);
            psel.appendChild(o);
        });
    }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    // ─────────────────────────────────────────────────────────────────
    // 6. CHART HELPERS
    // ─────────────────────────────────────────────────────────────────
    function mkChart(id, config) {
        if (CHARTS[id]) {
            CHARTS[id].destroy();
        }
        const canvas = document.getElementById(id);
        if (!canvas) return null;
        Chart.defaults.color = '#8fa3c4';
        Chart.defaults.font.family = "'IBM Plex Sans Arabic', sans-serif";
        Chart.defaults.font.size = 12;
        CHARTS[id] = new Chart(canvas, config);
        return CHARTS[id];
    }

    function gridOpts(alpha = 0.12) {
        return {
            color: `rgba(46,63,92,${alpha})`,
            lineWidth: 0.8
        };
    }

    function tickOpts(color = '#5a7299') {
        return {
            color,
            font: {
                size: 11
            }
        };
    }

    function tooltipOpts() {
        return {
            backgroundColor: '#1a2235',
            borderColor: '#3d5278',
            borderWidth: 1,
            titleColor: '#e8edf5',
            bodyColor: '#8fa3c4',
            padding: 10,
            cornerRadius: 8,
            titleFont: {
                family: "'IBM Plex Sans Arabic', sans-serif"
            },
            bodyFont: {
                family: "'IBM Plex Sans Arabic', sans-serif"
            },
        };
    }

    function legendOpts() {
        return {
            labels: {
                color: '#8fa3c4',
                font: {
                    size: 11
                },
                usePointStyle: true,
                pointStyleWidth: 10,
                padding: 16,
            }
        };
    }

    // ─────────────────────────────────────────────────────────────────
    // 7. RENDER ALL CHARTS
    // ─────────────────────────────────────────────────────────────────
    function renderAllCharts() {
        renderPriceChart();
        renderFarmerRankChart();
        renderFarmerProductsChart();
        renderFarmerTable();
        renderTopProdsChart();
        renderProdShareChart();
        renderProdPriceRankChart();
        renderTraderRankChart();
        renderTraderTable();
        renderFeesDonut();
        renderFeesYearly();
        renderCratesYearly();
        renderFeesGauges();
        renderHeatmap();
        renderMonthlyAvg();
        renderYearlyGrowth();
        renderAgentsFarmers();
        renderClientsCompare();
        renderAgentsScatter();
    }

    // ── PRICE OVER YEARS ──
    let priceSelectedProds = [];

    function priceShowTop5() {
        document.getElementById('price-top5-btn').classList.add('active');
        document.getElementById('price-all-btn').classList.remove('active');
        const top5 = Object.entries(DERIVED.prodTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 5)
            .map(([id]) => id);
        priceSelectedProds = top5;
        renderPriceChart();
    }

    function priceShowAll() {
        document.getElementById('price-top5-btn').classList.remove('active');
        document.getElementById('price-all-btn').classList.add('active');
        const sel = document.getElementById('price-prod-select');
        priceSelectedProds = Array.from(sel.options).map(o => o.value);
        renderPriceChart();
    }

    function renderPriceChart() {
        if (priceSelectedProds.length === 0) priceShowTop5();
        const years = DERIVED.years;
        const metric = document.getElementById('price-view-select')?.value || 'avg';
        if (years.length === 0) return;

        const datasets = priceSelectedProds.map((pid, idx) => {
            const byYear = DERIVED.priceByProdYear[pid] || {};
            const data = years.map(yr => {
                const d = byYear[yr];
                if (!d) return null;
                if (metric === 'avg') return d.totalWt > 0 ? +(d.totalVal / d.totalWt).toFixed(2) :
                null;
                if (metric === 'max') return d.maxP > 0 ? +d.maxP.toFixed(2) : null;
                if (metric === 'min') return d.minP < Infinity ? +d.minP.toFixed(2) : null;
                return null;
            });
            const color = PALETTE[idx % PALETTE.length];
            return {
                label: MAPS.products[pid] || ('منتج #' + pid),
                data,
                borderColor: color,
                backgroundColor: color + '22',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7,
                tension: 0.4,
                fill: false,
                spanGaps: true,
            };
        });

        mkChart('chart-prices', {
            type: 'line',
            data: {
                labels: years,
                datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: legendOpts(),
                    tooltip: tooltipOpts(),
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                    y: {
                        grid: gridOpts(),
                        ticks: {
                            ...tickOpts(),
                            callback: v => v + ' ₪'
                        },
                        title: {
                            display: true,
                            text: 'السعر (₪/كجم)',
                            color: '#5a7299',
                            font: {
                                size: 11
                            }
                        },
                    },
                },
            },
        });
    }

    // ── FARMER CHARTS ──
    function renderFarmerChart() {
        const id = document.getElementById('farmer-select')?.value;
        const met = document.getElementById('farmer-metric')?.value || 'weight';
        if (!id) return;
        const data = DERIVED.farmerTotals[id];
        if (!data) return;
        const years = DERIVED.years;
        const vals = years.map(yr => {
            const y = data.years[yr];
            if (!y) return 0;
            return met === 'weight' ? y.weight : met === 'revenue' ? y.value : y.orders;
        });
        const color = '#1dd9b0';
        const yLabel = met === 'weight' ? 'الوزن (كجم)' : met === 'revenue' ? 'الإيرادات (₪)' : 'عدد الفواتير';

        mkChart('chart-farmer-timeline', {
            type: 'bar',
            data: {
                labels: years,
                datasets: [{
                    label: MAPS.farmers[id] || ('مزارع #' + id),
                    data: vals,
                    backgroundColor: color + '44',
                    borderColor: color,
                    borderWidth: 2,
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: legendOpts(),
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                    y: {
                        grid: gridOpts(),
                        ticks: tickOpts(),
                        title: {
                            display: true,
                            text: yLabel,
                            color: '#5a7299',
                            font: {
                                size: 11
                            }
                        },
                    },
                },
            },
        });
    }

    function renderFarmerRankChart() {
        const top = Object.entries(DERIVED.farmerTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 15);
        const labels = top.map(([id]) => MAPS.farmers[id] || ('#' + id));
        const vals = top.map(([, d]) => Math.round(d.weight));

        mkChart('chart-farmer-rank', {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'الوزن الكلي (كجم)',
                    data: vals,
                    backgroundColor: PALETTE.map(c => c + '88'),
                    borderColor: PALETTE,
                    borderWidth: 1.5,
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            ...tickOpts(),
                            font: {
                                size: 11
                            }
                        }
                    },
                },
            },
        });
    }

    function renderFarmerProductsChart() {
        const topFarmers = Object.entries(DERIVED.farmerTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 10)
            .map(([id]) => id);

        // Get top 6 products overall
        const topProds = Object.entries(DERIVED.prodTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 6)
            .map(([id]) => id);

        const datasets = topProds.map((pid, idx) => ({
            label: MAPS.products[pid] || ('#' + pid),
            data: topFarmers.map(fid => {
                const fp = DERIVED.farmerProds[fid];
                return fp ? Math.round(fp[pid] || 0) : 0;
            }),
            backgroundColor: PALETTE[idx % PALETTE.length] + 'bb',
            borderColor: PALETTE[idx % PALETTE.length],
            borderWidth: 1,
            borderRadius: 4,
        }));

        mkChart('chart-farmer-products', {
            type: 'bar',
            data: {
                labels: topFarmers.map(id => MAPS.farmers[id] || ('#' + id)),
                datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: legendOpts(),
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: gridOpts(0.08),
                        ticks: {
                            ...tickOpts(),
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        stacked: true,
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                },
            },
        });
    }

    function renderFarmerTable() {
        const top = Object.entries(DERIVED.farmerTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 20);

        const maxW = top.length > 0 ? top[0][1].weight : 1;

        let html = `<table class="data-table">
        <thead><tr>
          <th>#</th>
          <th>اسم المزارع</th>
          <th>الوزن الكلي (كجم)</th>
          <th>التوزيع</th>
          <th>إجمالي الإيرادات (₪)</th>
          <th>عدد الفواتير</th>
          <th>متوسط السعر/كجم</th>
        </tr></thead><tbody>`;

        top.forEach(([id, d], i) => {
            const avg = d.weight > 0 ? (d.value / d.weight).toFixed(2) : '—';
            const barW = Math.round((d.weight / maxW) * 120);
            const rankCls = i === 0 ? 'top1' : i === 1 ? 'top2' : i === 2 ? 'top3' : '';
            html += `<tr>
          <td><span class="rank-num ${rankCls}">${i+1}</span></td>
          <td>${MAPS.farmers[id] || ('#' + id)}</td>
          <td><span class="mono-val">${fmt(d.weight)}</span></td>
          <td><div class="bar-inline"><div class="bar-fill" style="width:${barW}px"></div></div></td>
          <td><span class="mono-val">${fmt(d.value, 0)} ₪</span></td>
          <td>${fmt(d.orders)}</td>
          <td><span class="mono-val">${avg} ₪</span></td>
        </tr>`;
        });

        html += '</tbody></table>';
        document.getElementById('farmer-table-wrap').innerHTML = html;
    }

    // ── TOP PRODUCTS MULTI-LINE ──
    function renderTopProdsChart() {
        const metric = document.getElementById('prod-metric-select')?.value || 'weight';
        const topN = parseInt(document.getElementById('prod-topn-select')?.value || '8');
        const years = DERIVED.years;
        if (years.length === 0) return;

        // Pick top N products by total
        const sorted = Object.entries(DERIVED.prodTotals)
            .sort((a, b) => {
                const va = metric === 'weight' ? a[1].weight : metric === 'revenue' ? a[1].value : metric ===
                    'orders' ? a[1].orders : a[1].avgPrice;
                const vb = metric === 'weight' ? b[1].weight : metric === 'revenue' ? b[1].value : metric ===
                    'orders' ? b[1].orders : b[1].avgPrice;
                return vb - va;
            })
            .slice(0, topN)
            .map(([id]) => id);

        const datasets = sorted.map((pid, idx) => {
            const byYear = DERIVED.prodByYear[pid] || {};
            const data = years.map(yr => {
                const d = byYear[yr];
                if (!d) return null;
                if (metric === 'weight') return Math.round(d.weight);
                if (metric === 'revenue') return Math.round(d.value);
                if (metric === 'orders') return d.orders;
                if (metric === 'avgprice') return d.weight > 0 ? +(d.value / d.weight).toFixed(2) :
                null;
                return null;
            });
            const color = PALETTE[idx % PALETTE.length];
            return {
                label: MAPS.products[pid] || ('#' + pid),
                data,
                borderColor: color,
                backgroundColor: color + '18',
                borderWidth: 2.5,
                pointRadius: 4,
                tension: 0.35,
                fill: true,
                spanGaps: true,
            };
        });

        mkChart('chart-top-products', {
            type: 'line',
            data: {
                labels: years,
                datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: legendOpts(),
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                    y: {
                        grid: gridOpts(),
                        ticks: tickOpts(),
                        stacked: false
                    },
                },
            },
        });
    }

    function renderProdShareChart() {
        const top = Object.entries(DERIVED.prodTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 10);

        const labels = top.map(([id]) => MAPS.products[id] || ('#' + id));
        const vals = top.map(([, d]) => Math.round(d.weight));

        mkChart('chart-prod-share', {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: vals,
                    backgroundColor: PALETTE.map(c => c + 'cc'),
                    borderColor: PALETTE,
                    borderWidth: 1.5,
                    hoverOffset: 10,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: legendOpts(),
                    tooltip: tooltipOpts()
                },
                cutout: '60%',
            },
        });
    }

    function renderProdPriceRankChart() {
        const sorted = Object.entries(DERIVED.prodTotals)
            .filter(([, d]) => d.weight > 100)
            .sort((a, b) => b[1].avgPrice - a[1].avgPrice)
            .slice(0, 20);

        const labels = sorted.map(([id]) => MAPS.products[id] || ('#' + id));
        const vals = sorted.map(([, d]) => +d.avgPrice.toFixed(2));

        mkChart('chart-prod-price-rank', {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'متوسط السعر (₪/كجم)',
                    data: vals,
                    backgroundColor: sorted.map((_, i) => PALETTE[i % PALETTE.length] + '88'),
                    borderColor: sorted.map((_, i) => PALETTE[i % PALETTE.length]),
                    borderWidth: 1.5,
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: {
                            ...tickOpts(),
                            callback: v => v + ' ₪'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            ...tickOpts(),
                            font: {
                                size: 10
                            }
                        }
                    },
                },
            },
        });
    }

    // ── TRADERS ──
    function renderTraderChart() {
        const id = document.getElementById('trader-select')?.value;
        if (!id) return;
        const data = DERIVED.traderTotals[id];
        if (!data) return;
        const years = DERIVED.years;
        const weights = years.map(yr => data.years[yr] ? Math.round(data.years[yr].weight) : 0);
        const orders = years.map(yr => data.years[yr] ? data.years[yr].orders : 0);

        mkChart('chart-trader-timeline', {
            type: 'bar',
            data: {
                labels: years,
                datasets: [{
                        label: 'الوزن (كجم)',
                        data: weights,
                        backgroundColor: '#5bc4f522',
                        borderColor: '#5bc4f5',
                        borderWidth: 2,
                        borderRadius: 5,
                        yAxisID: 'y',
                    },
                    {
                        label: 'عدد الفواتير',
                        data: orders,
                        type: 'line',
                        borderColor: '#f0b429',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointRadius: 4,
                        tension: 0.4,
                        yAxisID: 'y2',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: legendOpts(),
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                    y: {
                        grid: gridOpts(),
                        ticks: tickOpts(),
                        position: 'right'
                    },
                    y2: {
                        grid: {
                            display: false
                        },
                        ticks: tickOpts(),
                        position: 'left'
                    },
                },
            },
        });
    }

    function renderTraderRankChart() {
        const top = Object.entries(DERIVED.traderTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 15);

        mkChart('chart-trader-rank', {
            type: 'bar',
            data: {
                labels: top.map(([id]) => MAPS.traders[id] || ('#' + id)),
                datasets: [{
                    label: 'الوزن المشترى (كجم)',
                    data: top.map(([, d]) => Math.round(d.weight)),
                    backgroundColor: PALETTE.map(c => c + '88'),
                    borderColor: PALETTE,
                    borderWidth: 1.5,
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            ...tickOpts(),
                            font: {
                                size: 10
                            }
                        }
                    },
                },
            },
        });
    }

    function renderTraderTable() {
        const top = Object.entries(DERIVED.traderTotals)
            .sort((a, b) => b[1].weight - a[1].weight)
            .slice(0, 20);
        const maxW = top.length > 0 ? top[0][1].weight : 1;

        let html = `<table class="data-table"><thead><tr>
        <th>#</th><th>اسم التاجر</th><th>الوزن الكلي (كجم)</th>
        <th>التوزيع</th><th>إجمالي المشتريات (₪)</th><th>عدد الفواتير</th>
      </tr></thead><tbody>`;

        top.forEach(([id, d], i) => {
            const rankCls = i === 0 ? 'top1' : i === 1 ? 'top2' : i === 2 ? 'top3' : '';
            const barW = Math.round((d.weight / maxW) * 120);
            html += `<tr>
          <td><span class="rank-num ${rankCls}">${i+1}</span></td>
          <td>${MAPS.traders[id] || ('#' + id)}</td>
          <td><span class="mono-val">${fmt(d.weight)}</span></td>
          <td><div class="bar-inline"><div class="bar-fill" style="width:${barW}px;background:linear-gradient(90deg,#5bc4f222,#5bc4f5)"></div></div></td>
          <td><span class="mono-val">${fmt(d.value, 0)} ₪</span></td>
          <td>${fmt(d.orders)}</td>
        </tr>`;
        });

        html += '</tbody></table>';
        document.getElementById('trader-table-wrap').innerHTML = html;
    }

    // ── FEES ──
    function renderFeesDonut() {
        const f = DERIVED.fees;
        const total = f.comision + f.municipality + f.empty + f.transport + f.emptyRent;
        if (total === 0) return;

        mkChart('chart-fees-donut', {
            type: 'doughnut',
            data: {
                labels: ['عمولة', 'بلدية', 'أطباق', 'نقل', 'إيجار أطباق'],
                datasets: [{
                    data: [f.comision, f.municipality, f.empty, f.transport, f.emptyRent].map(v => +v
                        .toFixed(0)),
                    backgroundColor: ['#1dd9b0cc', '#f0b429cc', '#f26b5bcc', '#9b8ff7cc', '#5bc4f5cc'],
                    borderColor: ['#1dd9b0', '#f0b429', '#f26b5b', '#9b8ff7', '#5bc4f5'],
                    borderWidth: 1.5,
                    hoverOffset: 12,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: legendOpts(),
                    tooltip: {
                        ...tooltipOpts(),
                        callbacks: {
                            label: ctx => {
                                const pct = ((ctx.parsed / total) * 100).toFixed(1);
                                return ` ${ctx.label}: ${fmt(ctx.parsed, 0)} ₪ (${pct}%)`;
                            },
                        },
                    },
                },
                cutout: '55%',
            },
        });
    }

    function renderFeesYearly() {
        const years = DERIVED.years;
        const fByY = DERIVED.feesByYear;

        const mkDs = (label, key, color) => ({
            label,
            data: years.map(yr => fByY[yr] ? +fByY[yr][key].toFixed(0) : 0),
            borderColor: color,
            backgroundColor: color + '22',
            borderWidth: 2,
            fill: true,
            tension: 0.35,
        });

        mkChart('chart-fees-yearly', {
            type: 'line',
            data: {
                labels: years,
                datasets: [
                    mkDs('عمولة', 'comision', '#1dd9b0'),
                    mkDs('بلدية', 'municipality', '#f0b429'),
                    mkDs('أطباق', 'empty', '#f26b5b'),
                    mkDs('نقل', 'transport', '#9b8ff7'),
                    mkDs('إيجار أطباق', 'emptyRent', '#5bc4f5'),
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: legendOpts(),
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                    y: {
                        grid: gridOpts(),
                        ticks: {
                            ...tickOpts(),
                            callback: v => fmt(v, 0) + ' ₪'
                        },
                        stacked: false
                    },
                },
            },
        });
    }

    function renderCratesYearly() {
        const years = DERIVED.years;
        const vals = years.map(yr => DERIVED.cratesByYear[yr] || 0);

        mkChart('chart-crates-yearly', {
            type: 'bar',
            data: {
                labels: years,
                datasets: [{
                    label: 'عدد الأطباق',
                    data: vals,
                    backgroundColor: '#f26b5b44',
                    borderColor: '#f26b5b',
                    borderWidth: 2,
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                    y: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                },
            },
        });
    }

    function renderFeesGauges() {
        const tw = DERIVED.totalWeight;
        if (tw === 0) return;
        const f = DERIVED.fees;
        const items = [{
                name: 'عمولة / كجم',
                val: f.comision / tw,
                color: '#1dd9b0',
                max: 0.15
            },
            {
                name: 'بلدية / كجم',
                val: f.municipality / tw,
                color: '#f0b429',
                max: 0.05
            },
            {
                name: 'أطباق / كجم',
                val: f.empty / tw,
                color: '#f26b5b',
                max: 0.20
            },
            {
                name: 'نقل / كجم',
                val: f.transport / tw,
                color: '#9b8ff7',
                max: 0.10
            },
            {
                name: 'إيجار أطباق / كجم',
                val: f.emptyRent / tw,
                color: '#5bc4f5',
                max: 0.05
            },
        ];

        const container = document.getElementById('fees-gauge-row');
        container.innerHTML = items.map(item => {
            const pct = Math.min(100, (item.val / item.max) * 100);
            return `
          <div class="gauge-item">
            <div class="gauge-header">
              <span class="gauge-name">${item.name}</span>
              <span class="gauge-val">${item.val.toFixed(4)} ₪</span>
            </div>
            <div class="gauge-track">
              <div class="gauge-fill" style="width:${pct.toFixed(1)}%;background:${item.color}"></div>
            </div>
          </div>`;
        }).join('');
    }

    // ── HEATMAP ──
    function renderHeatmap() {
        const years = DERIVED.years;
        if (years.length === 0) return;
        const activity = DERIVED.monthlyActivity;

        // Find max for color scale
        let maxBills = 0;
        for (const yr of years)
            for (let m = 1; m <= 12; m++) {
                const v = activity[yr]?.[m]?.bills || 0;
                if (v > maxBills) maxBills = v;
            }

        const shortM = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر',
            'نوفمبر', 'ديسمبر'
        ];
        const shortMa = ['ي', 'ف', 'م', 'أ', 'م', 'ي', 'ي', 'أ', 'س', 'أ', 'ن', 'د'];

        let html = `<div class="heatmap-grid" style="min-width:${60 + 12*36}px">`;
        // Header row
        html += `<div class="heatmap-label"></div>`;
        for (let m = 0; m < 12; m++) {
            html += `<div class="heatmap-month-header">${shortMa[m]}</div>`;
        }

        for (const yr of years) {
            html += `<div class="heatmap-label">${yr}</div>`;
            for (let m = 1; m <= 12; m++) {
                const bills = activity[yr]?.[m]?.bills || 0;
                const intensity = maxBills > 0 ? bills / maxBills : 0;
                const alpha = 0.08 + intensity * 0.85;
                const r = Math.round(29 + (29 * 0.8) * intensity);
                const g = Math.round(217 - 160 * intensity);
                const b = Math.round(176 - 100 * intensity);
                const bg = `rgba(${r},${g},${b},${alpha.toFixed(2)})`;
                const title = `${shortM[m-1]} ${yr}: ${bills} فاتورة`;
                html +=
                    `<div class="heatmap-cell" style="background:${bg}" title="${title}">${bills > 0 ? bills : ''}</div>`;
            }
        }

        html += '</div>';
        document.getElementById('heatmap-container').innerHTML = html;
    }

    function renderMonthlyAvg() {
        const years = DERIVED.years;
        const activity = DERIVED.monthlyActivity;
        const monthAvg = Array.from({
            length: 12
        }, (_, i) => {
            let total = 0,
                count = 0;
            for (const yr of years) {
                const v = activity[yr]?.[i + 1]?.bills || 0;
                total += v;
                count++;
            }
            return count > 0 ? +(total / count).toFixed(1) : 0;
        });

        mkChart('chart-monthly-avg', {
            type: 'bar',
            data: {
                labels: MONTHS_AR,
                datasets: [{
                    label: 'متوسط عدد الفواتير الشهرية',
                    data: monthAvg,
                    backgroundColor: monthAvg.map((v, i) => {
                        const mx = Math.max(...monthAvg);
                        const alpha = 0.3 + (v / (mx || 1)) * 0.6;
                        return `rgba(240,180,41,${alpha.toFixed(2)})`;
                    }),
                    borderColor: '#f0b429',
                    borderWidth: 1.5,
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: {
                            ...tickOpts(),
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                },
            },
        });
    }

    function renderYearlyGrowth() {
        const years = DERIVED.years;
        const activity = DERIVED.monthlyActivity;
        const yearlyWeight = years.map(yr => {
            let w = 0;
            for (let m = 1; m <= 12; m++) w += (DERIVED.monthlyActivity[yr]?.[m]?.weight || 0);
            return Math.round(w);
        });

        // Compute YoY growth %
        const growthPct = yearlyWeight.map((v, i) => {
            if (i === 0) return 0;
            const prev = yearlyWeight[i - 1];
            return prev > 0 ? +((v - prev) / prev * 100).toFixed(1) : 0;
        });

        mkChart('chart-yearly-growth', {
            type: 'bar',
            data: {
                labels: years,
                datasets: [{
                        label: 'الوزن الكلي (كجم)',
                        data: yearlyWeight,
                        backgroundColor: '#1dd9b033',
                        borderColor: '#1dd9b0',
                        borderWidth: 2,
                        borderRadius: 5,
                        yAxisID: 'y',
                    },
                    {
                        label: 'نمو سنوي %',
                        type: 'line',
                        data: growthPct,
                        borderColor: '#f0b429',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointRadius: 5,
                        tension: 0.3,
                        yAxisID: 'y2',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: legendOpts(),
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                    y: {
                        grid: gridOpts(),
                        ticks: tickOpts(),
                        position: 'right'
                    },
                    y2: {
                        grid: {
                            display: false
                        },
                        position: 'left',
                        ticks: {
                            ...tickOpts(),
                            callback: v => v + '%'
                        },
                    },
                },
            },
        });
    }

    // ── AGENTS ──
    function renderAgentsFarmers() {
        const agentFarmerCount = {};
        DB.commonfarmers.forEach(cf => {
            agentFarmerCount[cf.commonID] = (agentFarmerCount[cf.commonID] || 0) + 1;
        });
        const top = Object.entries(agentFarmerCount)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 15);

        mkChart('chart-agents-farmers', {
            type: 'bar',
            data: {
                labels: top.map(([id]) => MAPS.commons[id] || ('#' + id)),
                datasets: [{
                    label: 'عدد المزارعين المرتبطين',
                    data: top.map(([, v]) => v),
                    backgroundColor: PALETTE.map(c => c + '88'),
                    borderColor: PALETTE,
                    borderWidth: 1.5,
                    borderRadius: 5,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: tickOpts()
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            ...tickOpts(),
                            font: {
                                size: 10
                            }
                        }
                    },
                },
            },
        });
    }

    function renderClientsCompare() {
        const ca = DERIVED.clientActivity;
        const sorted = Object.entries(ca).sort((a, b) => b[1].weight - a[1].weight);
        const labels = sorted.map(([id]) => MAPS.clients[id]?.trim() || ('سوق #' + id));
        const wVals = sorted.map(([, d]) => Math.round(d.weight));
        const oVals = sorted.map(([, d]) => d.orders);

        mkChart('chart-clients-compare', {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                        label: 'الوزن (كجم)',
                        data: wVals,
                        backgroundColor: '#f26b5b55',
                        borderColor: '#f26b5b',
                        borderWidth: 2,
                        borderRadius: 6,
                        yAxisID: 'y',
                    },
                    {
                        label: 'عدد الطلبيات',
                        type: 'line',
                        data: oVals,
                        borderColor: '#9b8ff7',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointRadius: 5,
                        tension: 0.3,
                        yAxisID: 'y2',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: legendOpts(),
                    tooltip: tooltipOpts()
                },
                scales: {
                    x: {
                        grid: gridOpts(),
                        ticks: {
                            ...tickOpts(),
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        grid: gridOpts(),
                        ticks: tickOpts(),
                        position: 'right'
                    },
                    y2: {
                        grid: {
                            display: false
                        },
                        ticks: tickOpts(),
                        position: 'left'
                    },
                },
            },
        });
    }

    function renderAgentsScatter() {
        const pts = DB.commons.map(c => {
            const farmerCount = DB.commonfarmers.filter(cf => cf.commonID == c.id).length;
            return {
                x: farmerCount,
                y: +(c.comision || 0.06) * 100,
                label: c.name || ('#' + c.id),
                r: Math.min(20, Math.sqrt(farmerCount + 1) * 3),
            };
        }).filter(p => p.x > 0 || p.y > 0);

        mkChart('chart-agents-scatter', {
            type: 'bubble',
            data: {
                datasets: [{
                    label: 'الوكلاء',
                    data: pts,
                    backgroundColor: pts.map((_, i) => PALETTE[i % PALETTE.length] + 'aa'),
                    borderColor: pts.map((_, i) => PALETTE[i % PALETTE.length]),
                    borderWidth: 1.5,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        ...tooltipOpts(),
                        callbacks: {
                            label: ctx => {
                                const pt = ctx.raw;
                                return ` ${pt.label} — مزارعين: ${pt.x}، عمولة: ${pt.y.toFixed(1)}%`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: gridOpts(),
                            ticks: tickOpts(),
                            title: {
                                display: true,
                                text: 'عدد المزارعين المرتبطين',
                                color: '#5a7299',
                                font: {
                                    size: 11
                                }
                            },
                        },
                        y: {
                            grid: gridOpts(),
                            ticks: {
                                ...tickOpts(),
                                callback: v => v + '%'
                            },
                            title: {
                                display: true,
                                text: 'نسبة العمولة (%)',
                                color: '#5a7299',
                                font: {
                                    size: 11
                                }
                            },
                        },
                    },
                },
            });
        }

        // ─────────────────────────────────────────────────────────────────
        // 8. SCROLL FADE-IN
        // ─────────────────────────────────────────────────────────────────
        function initScrollFadeIn() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(e => {
                    if (e.isIntersecting) e.target.classList.add('visible');
                });
            }, {
                threshold: 0.08
            });
            document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
        }

        // ─────────────────────────────────────────────────────────────────
        // 9. INITIAL STATE (demo if no file)
        // ─────────────────────────────────────────────────────────────────
        // Show upload screen on load (default)
        window.addEventListener('DOMContentLoaded', () => {
            // Initialize price chart selections
            priceSelectedProds = [];
        });
    </script>
</body>

</html>
