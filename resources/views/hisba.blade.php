<!--
=======================================================================
  صفحة: لوحة إحصاءات الحسبة  |  Hisba Statistics Dashboard
=======================================================================
  الوصف:
    صفحة ذاتية الاكتفاء (standalone) — لا تعتمد على أي خادم.
    يقوم المستخدم برفع ملف SQL dump من قاعدة بيانات الحسبة،
    فتقرأ الصفحة الملف في المتصفح (FileReader API)، تحلله
    بالكتل (chunked)، تربط الجداول في الذاكرة، ثم تعرض
    الإحصاءات الكاملة والرسوم البيانية التفاعلية.

  الجداول المقروءة من ملف SQL:
    • commons      ← المشتركون (وكلاء المزارعين / الموردون)
    • traders      ← التجار (المشترون)
    • products     ← أنواع المنتجات (خضار وفاكهة)
    • dailybills   ← رأس الفاتورة (تاريخ + مشترك + تاجر)
    • dailyorders  ← سطور الفاتورة (منتج + كمية + سعر + رسوم)

  التبويبات الرئيسية:
    🏠 نظرة عامة   — KPIs + مخططات ملخص
    👥 المشتركون   — ترتيب وإحصاء كل مشترك
    🔍 تفاصيل مشترك — تحليل كامل + طباعة تقرير
    🏪 التجار      — ترتيب وإحصاء كل تاجر
    🔍 تفاصيل تاجر — تحليل كامل
    📦 المنتجات    — إحصاءات أسعار وكميات
    🔍 تفاصيل منتج — اتجاهات وتوزيعات
    📈 اتجاهات الأسعار — منحنى سعر منتج محدد
    💰 التحليل المالي — عمولات + بلدية + نقل + صافي
    🧠 إحصاءات متقدمة — أرقام قياسية + موسمية

  التقنيات المستخدمة:
    • Chart.js 4.4  — الرسوم البيانية
    • FileReader API — قراءة ملف SQL كتلةً كتلة (4 MB/chunk)
    • Vanilla JS    — بدون أي إطار عمل
    • CSS Grid/Flex — تصميم متجاوب (RTL)
=======================================================================
-->
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة إحصاءات الحسبة</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* =======================================================================
         *  CSS — لوحة إحصاءات الحسبة
         *  هيكل الأقسام:
         *   1.  CSS Reset          — إعادة التعيين العامة
         *   2.  Upload Screen      — شاشة رفع ملف SQL
         *   3.  Main App Layout    — التطبيق الرئيسي والهيدر
         *   4.  Navigation Tabs    — شريط التبويبات
         *   5.  Tab Content        — حاويات محتوى التبويبات
         *   6.  KPI Cards          — بطاقات مؤشرات الأداء
         *   7.  Section Cards      — البطاقات البيضاء المظللة
         *   8.  Charts             — حاويات Chart.js
         *   9.  Filter Rows        — صفوف فلاتر التاريخ والبحث
         *  10.  Buttons            — أزرار التنقل والإجراءات
         *  11.  Autocomplete       — قائمة الاقتراحات المنسدلة
         *  12.  Data Tables        — جداول البيانات
         *  13.  Badges             — شارات ملونة للأرقام
         *  14.  Scrollbar          — تنسيق شريط التمرير
         *  15.  Responsive         — استجابة الشاشات الصغيرة
         *  16.  Print Styles       — أنماط طباعة تقرير المشترك
         * ======================================================================= */

        /* ===== 1. CSS RESET — إعادة التعيين العامة ===== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #f0f4f8;
            color: #1a202c;
            direction: rtl;
        }

        /* ===== UPLOAD SCREEN ===== */
        #upload-screen {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a3a5c 0%, #2d6a4f 100%);
        }

        .upload-box {
            background: #fff;
            border-radius: 20px;
            padding: 60px 50px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 560px;
            width: 90%;
        }

        .upload-box h1 {
            font-size: 2rem;
            color: #1a3a5c;
            margin-bottom: 10px;
        }

        .upload-box p {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 1rem;
        }

        .upload-btn-label {
            display: inline-block;
            background: linear-gradient(135deg, #1a3a5c, #2d6a4f);
            color: #fff;
            padding: 16px 40px;
            border-radius: 12px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: opacity .2s;
            margin-bottom: 16px;
        }

        .upload-btn-label:hover {
            opacity: .88;
        }

        #sql-file-input {
            display: none;
        }

        .file-info {
            color: #64748b;
            font-size: .9rem;
        }

        #parse-btn {
            display: none;
            margin-top: 20px;
            background: #2d6a4f;
            color: #fff;
            border: none;
            padding: 14px 36px;
            border-radius: 10px;
            font-size: 1rem;
            cursor: pointer;
            transition: background .2s;
        }

        #parse-btn:hover {
            background: #1a4a35;
        }

        #progress-bar-wrap {
            margin-top: 20px;
            display: none;
        }

        #progress-bar-wrap p {
            margin-bottom: 8px;
            color: #1a3a5c;
            font-weight: 600;
        }

        .progress-bar {
            background: #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            height: 12px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #1a3a5c, #2d6a4f);
            width: 0%;
            transition: width .3s;
        }

        /* ===== MAIN APP ===== */
        #app {
            display: none;
        }

        header {
            background: linear-gradient(135deg, #1a3a5c, #2d6a4f);
            color: #fff;
            padding: 18px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        header h1 {
            font-size: 1.5rem;
        }

        header .meta {
            font-size: .85rem;
            opacity: .8;
        }

        /* ===== 4. NAVIGATION TABS — شريط التبويبات العلوي ===== */
        .nav-tabs {
            background: #1a3a5c;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 8px 20px;
        }

        .nav-tab {
            color: #cbd5e1;
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: .9rem;
            transition: all .2s;
            border: none;
            background: transparent;
        }

        .nav-tab:hover {
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }

        .nav-tab.active {
            background: #2d6a4f;
            color: #fff;
            font-weight: 600;
        }

        /* ===== 5. TAB CONTENT — محتوى التبويبات
         *   مخفي افتراضياً (display:none)، يظهر فقط عند إضافة كلاس .active
         * ===== */
        .tab-content {
            display: none;
            padding: 24px;
        }

        .tab-content.active {
            display: block;
        }

        /* ===== 6. KPI CARDS — بطاقات مؤشرات الأداء الرئيسية
         *   الألوان: الافتراضي=أزرق | .green=أخضر | .orange=برتقالي
         *           .red=أحمر       | .purple=بنفسجي
         * ===== */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            border-top: 4px solid #1a3a5c;
        }

        .kpi-card.green {
            border-top-color: #2d6a4f;
        }

        .kpi-card.orange {
            border-top-color: #d97706;
        }

        .kpi-card.red {
            border-top-color: #dc2626;
        }

        .kpi-card.purple {
            border-top-color: #7c3aed;
        }

        .kpi-card .val {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
        }

        .kpi-card .lbl {
            font-size: .8rem;
            color: #64748b;
            margin-top: 4px;
        }

        /* ===== 7. SECTION CARD (.sec) — البطاقة البيضاء الأساسية
         *   تُستخدم كحاوية لكل قسم فرعي داخل التبويبات
         * ===== */
        .sec {
            background: #fff;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            margin-bottom: 24px;
        }

        .sec h2 {
            font-size: 1.1rem;
            color: #1a3a5c;
            margin-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }

        /* ===== 8. CHART WRAPPER — حاوية رسوم Chart.js البيانية ===== */
        .chart-wrap {
            position: relative;
        }

        .chart-wrap canvas {
            max-height: 320px;
            /* حد أقصى للارتفاع لمنع التمدد الزائد */
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        /* ===== 9. FILTER ROW — صف فلاتر التاريخ والبحث
         *   يحتوي على: حقول التاريخ من/إلى + زر بحث + زر إظهار الكل
         * ===== */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 20px;
        }

        .filter-row label {
            font-size: .85rem;
            color: #475569;
            display: block;
            margin-bottom: 4px;
        }

        .filter-row input,
        .filter-row select {
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: .9rem;
            outline: none;
            transition: border-color .2s;
            direction: rtl;
        }

        .filter-row input:focus,
        .filter-row select:focus {
            border-color: #1a3a5c;
        }

        .btn-primary {
            background: #1a3a5c;
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-size: .9rem;
            transition: background .2s;
        }

        .btn-primary:hover {
            background: #163052;
        }

        .btn-green {
            background: #2d6a4f;
        }

        .btn-green:hover {
            background: #1d4a38;
        }

        /* ===== 11. AUTOCOMPLETE — حقل البحث مع قائمة الاقتراحات المنسدلة
         *   يُستخدم للبحث عن المشتركين / التجار / المنتجات بالاسم
         * ===== */
        .autocomplete-wrap {
            position: relative;
        }

        .autocomplete-wrap input {
            width: 100%;
            min-width: 220px;
        }

        .ac-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            left: 0;
            background: #fff;
            border: 1.5px solid #cbd5e1;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 220px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .1);
        }

        .ac-item {
            padding: 9px 14px;
            cursor: pointer;
            font-size: .88rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .ac-item:hover {
            background: #eff6ff;
            color: #1a3a5c;
            font-weight: 600;
        }

        /* ===== 12. DATA TABLES — جداول عرض البيانات
         *   .tbl-wrap: حاوية قابلة للتمرير أفقياً على الشاشات الضيقة
         * ===== */
        .tbl-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .88rem;
        }

        th {
            background: #1a3a5c;
            color: #fff;
            padding: 10px 12px;
            text-align: right;
            white-space: nowrap;
        }

        td {
            padding: 9px 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        tr:hover td {
            background: #f8fafc;
        }

        tr:nth-child(even) td {
            background: #fafafa;
        }

        /* ===== 13. BADGES — شارات ملونة للأرقام والتصنيفات
         *   .badge-blue   → عدد الصناديق / الكميات
         *   .badge-green  → الصافي / المبالغ الإيجابية
         *   .badge-orange → الرسوم / التكاليف
         *   .badge-red    → الخصومات / العمولات المقتطعة
         * ===== */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 600;
        }

        .badge-blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-green {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-orange {
            background: #fef3c7;
            color: #b45309;
        }

        .badge-red {
            background: #fee2e2;
            color: #dc2626;
        }

        /* ===== 14. SCROLLBAR — تنسيق شريط التمرير (Webkit/Chrome) ===== */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 3px;
        }

        /* ===== 15. RESPONSIVE — تكيّف الشاشات الصغيرة (< 600px)
         *   الرسوم البيانية: من عمودين → عمود واحد
         * ===== */
        @media (max-width: 600px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }

            .upload-box {
                padding: 36px 24px;
            }
        }

        .no-data {
            text-align: center;
            color: #94a3b8;
            padding: 30px;
            font-size: .95rem;
        }

        .loading {
            text-align: center;
            color: #1a3a5c;
            padding: 20px;
        }

        .btn-print {
            background: #0f766e;
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-size: .9rem;
            transition: background .2s;
            display: none;
        }

        .btn-print:hover {
            background: #0d5c55;
        }

        /* ===== 16. PRINT STYLES — أنماط الطباعة لتقرير المشترك
         *   عند الطباعة: إخفاء كل محتوى الصفحة وإظهار
         *   #print-common-overlay فقط (نافذة التقرير المطبوعة)
         * ===== */
        @media print {
            body * {
                visibility: hidden !important;
            }

            #print-common-overlay,
            #print-common-overlay * {
                visibility: visible !important;
            }

            #print-common-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: #fff;
                z-index: 99999;
                padding: 30px;
                overflow: auto;
            }
        }
    </style>
</head>

<body>

    <!-- ===================================================================
         شاشة الرفع (Upload Screen)
         الظهور: display:flex في البداية | تُخفى بعد تحليل الملف بنجاح
         المحتوى: زر اختيار ملف SQL + زر تحليل + شريط التقدم
    =================================================================== -->
    <!-- UPLOAD SCREEN -->
    <div id="upload-screen">
        <div class="upload-box">
            <div style="font-size:3.5rem;margin-bottom:14px;">📊</div>
            <h1>لوحة إحصاءات الحسبة</h1>
            <p>قم بتحميل ملف قاعدة البيانات SQL لعرض الإحصاءات والتحليلات المالية والزراعية الشاملة</p>
            <label class="upload-btn-label" for="sql-file-input">📂 اختر ملف SQL</label>
            <input type="file" id="sql-file-input" accept=".sql">
            <div class="file-info" id="file-info">لم يتم اختيار ملف</div>
            <button id="parse-btn">🚀 تحليل قاعدة البيانات</button>
            <div id="progress-bar-wrap">
                <p id="progress-label">جارٍ المعالجة...</p>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         التطبيق الرئيسي (Main App)
         الظهور: display:none في البداية | يظهر بعد اكتمال تحليل ملف SQL
         المحتوى: هيدر + شريط تبويبات + 10 تبويبات للتقارير
    =================================================================== -->
    <!-- MAIN APP -->
    <div id="app">
        <header>
            <h1>📊 لوحة إحصاءات الحسبة</h1>
            <div class="meta" id="db-meta"></div>
        </header>

        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showTab('overview',this)">🏠 نظرة عامة</button>
            <button class="nav-tab" onclick="showTab('commons',this)">👥 المشتركون</button>
            <button class="nav-tab" onclick="showTab('common-detail',this)">🔍 تفاصيل مشترك</button>
            <button class="nav-tab" onclick="showTab('traders',this)">🏪 التجار</button>
            <button class="nav-tab" onclick="showTab('trader-detail',this)">🔍 تفاصيل تاجر</button>
            <button class="nav-tab" onclick="showTab('products',this)">📦 المنتجات</button>
            <button class="nav-tab" onclick="showTab('product-detail',this)">🔍 تفاصيل منتج</button>
            <button class="nav-tab" onclick="showTab('price-trends',this)">📈 اتجاهات الأسعار</button>
            <button class="nav-tab" onclick="showTab('financial',this)">💰 التحليل المالي</button>
            <button class="nav-tab" onclick="showTab('advanced',this)">🧠 إحصاءات متقدمة</button>
        </div>

        <!-- OVERVIEW -->
        <div class="tab-content active" id="tab-overview">
            <div class="kpi-row" id="kpi-row"></div>
            <div class="chart-grid">
                <div class="sec">
                    <h2>📅 الإيرادات الشهرية (آخر 18 شهر)</h2>
                    <div class="chart-wrap"><canvas id="chart-monthly-revenue"></canvas></div>
                </div>
                <div class="sec">
                    <h2>📦 أكثر المنتجات تداولاً (حجم)</h2>
                    <div class="chart-wrap"><canvas id="chart-top-products"></canvas></div>
                </div>
                <div class="sec">
                    <h2>👥 أفضل 10 مشتركين (صناديق)</h2>
                    <div class="chart-wrap"><canvas id="chart-top-commons"></canvas></div>
                </div>
                <div class="sec">
                    <h2>🏪 أفضل 10 تجار (قيمة)</h2>
                    <div class="chart-wrap"><canvas id="chart-top-traders"></canvas></div>
                </div>
            </div>
            <div class="chart-grid">
                <div class="sec">
                    <h2>🍩 توزيع المنتجات (صناديق)</h2>
                    <div class="chart-wrap"><canvas id="chart-revenue-pie"></canvas></div>
                </div>
                <div class="sec">
                    <h2>🍩 توزيع الاقتطاعات</h2>
                    <div class="chart-wrap"><canvas id="chart-fees-donut"></canvas></div>
                </div>
            </div>
        </div>

        <!-- COMMONS LIST -->
        <div class="tab-content" id="tab-commons">
            <div class="sec">
                <h2>👥 إحصاءات جميع المشتركين</h2>
                <div class="filter-row">
                    <div><label>من تاريخ</label><input type="date" id="commons-date-from"></div>
                    <div><label>إلى تاريخ</label><input type="date" id="commons-date-to"></div>
                    <button class="btn-primary" onclick="renderCommonsList()">🔍 بحث</button>
                    <button class="btn-primary btn-green"
                        onclick="clearDatesAndRun('commons-date-from','commons-date-to',renderCommonsList)">↺
                        الكل</button>
                </div>
                <div class="tbl-wrap" id="commons-table-wrap">
                    <div class="loading">جارٍ التحميل...</div>
                </div>
            </div>
            <div class="chart-grid">
                <div class="sec">
                    <h2>أعلى 15 مشترك — الصناديق</h2>
                    <div class="chart-wrap"><canvas id="chart-commons-boxes"></canvas></div>
                </div>
                <div class="sec">
                    <h2>أعلى 15 مشترك — القيمة</h2>
                    <div class="chart-wrap"><canvas id="chart-commons-value"></canvas></div>
                </div>
            </div>
        </div>

        <!-- COMMON DETAIL -->
        <div class="tab-content" id="tab-common-detail">
            <div class="sec">
                <h2>🔍 تفاصيل مشترك محدد</h2>
                <div class="filter-row">
                    <div>
                        <label>اسم المشترك</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="cd-common-input" placeholder="ابحث عن مشترك..."
                                oninput="acSearch(this.value,'commons','cd-common-ac','cd-common-id','cd-common-input')"
                                onblur="hideAcDelayed('cd-common-ac')">
                            <div class="ac-dropdown" id="cd-common-ac"></div>
                        </div>
                        <input type="hidden" id="cd-common-id">
                    </div>
                    <div><label>من تاريخ</label><input type="date" id="cd-date-from"></div>
                    <div><label>إلى تاريخ</label><input type="date" id="cd-date-to"></div>
                    <button class="btn-primary" onclick="renderCommonDetail()">🔍 بحث</button>
                    <button class="btn-print" id="cd-print-btn" onclick="printCommonDetail()"> 🖨️ طباعة
                        التقرير</button>
                </div>
            </div>
            <div id="common-detail-content"></div>
        </div>

        <!-- TRADERS LIST -->
        <div class="tab-content" id="tab-traders">
            <div class="sec">
                <h2>🏪 إحصاءات جميع التجار</h2>
                <div class="filter-row">
                    <div><label>من تاريخ</label><input type="date" id="traders-date-from"></div>
                    <div><label>إلى تاريخ</label><input type="date" id="traders-date-to"></div>
                    <button class="btn-primary" onclick="renderTradersList()">🔍 بحث</button>
                    <button class="btn-primary btn-green"
                        onclick="clearDatesAndRun('traders-date-from','traders-date-to',renderTradersList)">↺
                        الكل</button>
                </div>
                <div class="tbl-wrap" id="traders-table-wrap">
                    <div class="loading">جارٍ التحميل...</div>
                </div>
            </div>
            <div class="chart-grid">
                <div class="sec">
                    <h2>أعلى 15 تاجر — الصناديق</h2>
                    <div class="chart-wrap"><canvas id="chart-traders-boxes"></canvas></div>
                </div>
                <div class="sec">
                    <h2>أعلى 15 تاجر — القيمة</h2>
                    <div class="chart-wrap"><canvas id="chart-traders-value"></canvas></div>
                </div>
            </div>
        </div>

        <!-- TRADER DETAIL -->
        <div class="tab-content" id="tab-trader-detail">
            <div class="sec">
                <h2>🔍 تفاصيل تاجر محدد</h2>
                <div class="filter-row">
                    <div>
                        <label>اسم التاجر</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="td-trader-input" placeholder="ابحث عن تاجر..."
                                oninput="acSearch(this.value,'traders','td-trader-ac','td-trader-id','td-trader-input')"
                                onblur="hideAcDelayed('td-trader-ac')">
                            <div class="ac-dropdown" id="td-trader-ac"></div>
                        </div>
                        <input type="hidden" id="td-trader-id">
                    </div>
                    <div><label>من تاريخ</label><input type="date" id="td-date-from"></div>
                    <div><label>إلى تاريخ</label><input type="date" id="td-date-to"></div>
                    <button class="btn-primary" onclick="renderTraderDetail()">🔍 بحث</button>
                </div>
            </div>
            <div id="trader-detail-content"></div>
        </div>

        <!-- PRODUCTS LIST -->
        <div class="tab-content" id="tab-products">
            <div class="sec">
                <h2>📦 إحصاءات جميع المنتجات</h2>
                <div class="filter-row">
                    <div><label>من تاريخ</label><input type="date" id="products-date-from"></div>
                    <div><label>إلى تاريخ</label><input type="date" id="products-date-to"></div>
                    <button class="btn-primary" onclick="renderProductsList()">🔍 بحث</button>
                    <button class="btn-primary btn-green"
                        onclick="clearDatesAndRun('products-date-from','products-date-to',renderProductsList)">↺
                        الكل</button>
                </div>
                <div class="tbl-wrap" id="products-table-wrap">
                    <div class="loading">جارٍ التحميل...</div>
                </div>
            </div>
            <div class="sec">
                <h2>📦 أعلى 15 منتج — الصناديق</h2>
                <div class="chart-wrap"><canvas id="chart-products-boxes"></canvas></div>
            </div>
        </div>

        <!-- PRODUCT DETAIL -->
        <div class="tab-content" id="tab-product-detail">
            <div class="sec">
                <h2>🔍 تفاصيل منتج محدد</h2>
                <div class="filter-row">
                    <div>
                        <label>اسم المنتج</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="pd-product-input" placeholder="ابحث عن منتج..."
                                oninput="acSearch(this.value,'products','pd-product-ac','pd-product-id','pd-product-input')"
                                onblur="hideAcDelayed('pd-product-ac')">
                            <div class="ac-dropdown" id="pd-product-ac"></div>
                        </div>
                        <input type="hidden" id="pd-product-id">
                    </div>
                    <div><label>من تاريخ</label><input type="date" id="pd-date-from"></div>
                    <div><label>إلى تاريخ</label><input type="date" id="pd-date-to"></div>
                    <button class="btn-primary" onclick="renderProductDetail()">🔍 بحث</button>
                </div>
            </div>
            <div id="product-detail-content"></div>
        </div>

        <!-- PRICE TRENDS -->
        <div class="tab-content" id="tab-price-trends">
            <div class="sec">
                <h2>📈 اتجاهات أسعار المنتجات عبر الزمن</h2>
                <div class="filter-row">
                    <div>
                        <label>المنتج</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="pt-product-input" placeholder="ابحث عن منتج..."
                                oninput="acSearch(this.value,'products','pt-product-ac','pt-product-id','pt-product-input')"
                                onblur="hideAcDelayed('pt-product-ac')">
                            <div class="ac-dropdown" id="pt-product-ac"></div>
                        </div>
                        <input type="hidden" id="pt-product-id">
                    </div>
                    <div><label>من تاريخ</label><input type="date" id="pt-date-from"></div>
                    <div><label>إلى تاريخ</label><input type="date" id="pt-date-to"></div>
                    <div>
                        <label>التجميع</label>
                        <select id="pt-groupby">
                            <option value="day">يومي</option>
                            <option value="week">أسبوعي</option>
                            <option value="month" selected>شهري</option>
                            <option value="year">سنوي</option>
                        </select>
                    </div>
                    <button class="btn-primary" onclick="renderPriceTrend()">📈 عرض</button>
                </div>
            </div>
            <div class="sec">
                <div class="chart-wrap"><canvas id="chart-price-trend" style="max-height:400px;"></canvas></div>
            </div>
            <div class="sec" id="price-trend-stats"></div>
        </div>

        <!-- FINANCIAL -->
        <div class="tab-content" id="tab-financial">
            <div class="sec">
                <h2>💰 التحليل المالي الشامل</h2>
                <div class="filter-row">
                    <div><label>من تاريخ</label><input type="date" id="fin-date-from"></div>
                    <div><label>إلى تاريخ</label><input type="date" id="fin-date-to"></div>
                    <button class="btn-primary" onclick="renderFinancial()">🔍 تحليل</button>
                    <button class="btn-primary btn-green"
                        onclick="clearDatesAndRun('fin-date-from','fin-date-to',renderFinancial)">↺ الكل</button>
                </div>
            </div>
            <div class="kpi-row" id="fin-kpi-row"></div>
            <div class="chart-grid">
                <div class="sec">
                    <h2>💰 الإيرادات الشهرية والرسوم</h2>
                    <div class="chart-wrap"><canvas id="chart-fin-monthly"></canvas></div>
                </div>
                <div class="sec">
                    <h2>📊 توزيع الاقتطاعات</h2>
                    <div class="chart-wrap"><canvas id="chart-fin-breakdown"></canvas></div>
                </div>
            </div>
            <div class="sec">
                <h2>📅 أفضل 30 يوم أداءً</h2>
                <div class="tbl-wrap" id="fin-daily-wrap"></div>
            </div>
        </div>

        <!-- ADVANCED STATS -->
        <div class="tab-content" id="tab-advanced">
            <div class="sec">
                <h2>🧠 إحصاءات متقدمة وأرقام قياسية</h2>
                <div class="filter-row">
                    <div><label>من تاريخ</label><input type="date" id="adv-date-from"></div>
                    <div><label>إلى تاريخ</label><input type="date" id="adv-date-to"></div>
                    <button class="btn-primary" onclick="renderAdvancedStats()">🔍 تحليل</button>
                    <button class="btn-primary btn-green"
                        onclick="clearDatesAndRun('adv-date-from','adv-date-to',renderAdvancedStats)">↺ الكل</button>
                </div>
            </div>
            <!-- Records KPIs -->
            <div class="kpi-row" id="adv-records-row"></div>
            <!-- Day of week + Seasonal -->
            <div class="chart-grid">
                <div class="sec">
                    <h2>📅 أنشط أيام الأسبوع (صناديق)</h2>
                    <div class="chart-wrap"><canvas id="chart-adv-weekday"></canvas></div>
                </div>
                <div class="sec">
                    <h2>🌱 الموسمية الشهرية السنوية (صناديق)</h2>
                    <div class="chart-wrap"><canvas id="chart-adv-seasonal"></canvas></div>
                </div>
            </div>
            <!-- Bill distribution + Price volatility -->
            <div class="chart-grid">
                <div class="sec">
                    <h2>💳 توزيع قيم الفواتير (نطاقات)</h2>
                    <div class="chart-wrap"><canvas id="chart-adv-bill-dist"></canvas></div>
                </div>
                <div class="sec">
                    <h2>📉 تذبذب أسعار المنتجات (نطاق السعر)</h2>
                    <div class="chart-wrap"><canvas id="chart-adv-price-range"></canvas></div>
                </div>
            </div>
            <!-- Bill KPIs -->
            <div class="sec">
                <h2>📊 إحصاءات الفواتير الفردية</h2>
                <div class="kpi-row" id="adv-bill-kpi"></div>
            </div>
            <!-- Top 20 days table -->
            <div class="sec">
                <h2>🏆 أفضل 20 يوم أداءً (قيمة)</h2>
                <div class="tbl-wrap" id="adv-top-days-wrap"></div>
            </div>
            <!-- Slowest days table -->
            <div class="sec">
                <h2>🐤 أضعف 20 يوم أداءً (قيمة)</h2>
                <div class="tbl-wrap" id="adv-slow-days-wrap"></div>
            </div>
        </div>
    </div>

    <script>
        /*
         * ================================================================
         *  قاعدة البيانات — هيكل الجداول الكامل (بناءً على ss.sql)
         *  DATABASE SCHEMA — Full Table Documentation
         * ================================================================
         *
         *  هذا النظام هو نظام حسبة لسوق الخضار والفواكه.
         *  This is a produce market (hisba) management system.
         *
         *  المفهوم الأساسي:
         *    المشترك (common) = وكيل/تاجر يُرسل بضاعة المزارع للسوق
         *    التاجر (trader)  = مشتري ينزل السوق ويشتري الصناديق
         *    كل يوم: مشترك يُورِّد منتجات → يشتريها تجار → تُسجَّل فواتير
         *
         * ─────────────────────────────────────────────────────────────
         *  جدول: commons — المشتركون (وكلاء المزارعين / الموردون)
         * ─────────────────────────────────────────────────────────────
         *  id          INT  PK   → المعرّف الأساسي
         *  commonNum   INT       → الرقم المرجعي للمشترك في النظام
         *  name        VARCHAR   → الاسم (مثال: "أحمد العطيات/إنتاج")
         *  address     VARCHAR   → العنوان
         *  phone       VARCHAR   → الهاتف
         *  transport   DOUBLE    → رسوم النقل لكل صندوق (افتراضي: 1.5 ₪)
         *  comision    DOUBLE    → نسبة عمولة الحسبة (افتراضي: 0.06 = 6%)
         *  totalAmount DOUBLE    → إجمالي المبالغ المتراكمة
         *  preBalance  DOUBLE    → الرصيد المرحَّل من الموسم السابق
         *  totalEmpty  INT       → إجمالي الصناديق الفارغة
         *  deleted     INT       → 0=نشط، 1=محذوف (soft delete)
         *  client_ID   INT FK    → معرّف المستأجر (نظام SaaS متعدد المستأجرين)
         *
         * ─────────────────────────────────────────────────────────────
         *  جدول: traders — التجار (المشترون)
         * ─────────────────────────────────────────────────────────────
         *  id          INT  PK   → المعرّف الأساسي
         *  traderNum   INT       → الرقم المرجعي للتاجر
         *  name        VARCHAR   → الاسم (مثال: "محمد العطيات")
         *  address     VARCHAR   → العنوان
         *  phone       VARCHAR   → الهاتف
         *  empty       DOUBLE    → تكلفة الصندوق الفارغ (افتراضي: 8 ₪)
         *  emptyRent   DOUBLE    → إيجار الصندوق الفارغ (افتراضي: 1 ₪)
         *  totalAmount DOUBLE    → إجمالي المبالغ المتراكمة
         *  preBalance  DOUBLE    → الرصيد المرحَّل
         *  totalEmpty  INT       → إجمالي الصناديق الفارغة
         *  deleted     INT       → 0=نشط، 1=محذوف
         *  client_ID   INT FK    → معرّف المستأجر
         *
         * ─────────────────────────────────────────────────────────────
         *  جدول: products — المنتجات (أنواع الخضار والفواكه)
         * ─────────────────────────────────────────────────────────────
         *  id          INT  PK   → المعرّف الأساسي
         *  prodName    VARCHAR   → اسم المنتج (مثال: "بندورة"، "خيار"، "تفاح")
         *  client_ID   INT FK    → معرّف المستأجر
         *
         * ─────────────────────────────────────────────────────────────
         *  جدول: dailybills — رأس الفاتورة اليومية (bill header)
         *  كل سطر = فاتورة واحدة = تاجر واحد يشتري من مشترك واحد في يوم واحد
         * ─────────────────────────────────────────────────────────────
         *  id           INT  PK  → المعرّف الأساسي
         *  traderID     INT  FK  → معرّف التاجر          → traders.id
         *  farmerID     INT  FK  → معرّف المشترك         → commons.id
         *               ⚠️ تنبيه: العمود اسمه farmerID لكنه يشير فعلياً
         *                  إلى جدول commons (وليس جدول farmers)
         *  dateInvoice  DATE     → تاريخ الفاتورة (YYYY-MM-DD)
         *  status       TINYINT  → 0=مفتوحة/جارية، 1=مغلقة/مكتملة
         *  pre_year     INT      → 0=الموسم الحالي، 1=من الموسم السابق
         *  bill_id      INT      → رقم الفاتورة التسلسلي داخل الموسم
         *  client_ID    INT FK   → معرّف المستأجر
         *
         * ─────────────────────────────────────────────────────────────
         *  جدول: dailyorders — سطور الفاتورة (bill line items)
         *  كل سطر = منتج واحد داخل فاتورة واحدة
         * ─────────────────────────────────────────────────────────────
         *  id               INT  PK  → المعرّف الأساسي
         *  prodID           INT  FK  → معرّف المنتج        → products.id
         *  prodNum          INT      → عدد الصناديق
         *  prodWheight      DOUBLE   → الوزن الإجمالي (كيلوغرام)
         *  itemPrice        DOUBLE   → السعر (لكل كيلو أو لكل صندوق)
         *  weightRate       DOUBLE   → معدّل تحويل الوزن (للحساب بالوزن)
         *  emptyReturend    INT      → صناديق فارغة مُرجَعة من المشترك
         *  emptyReturendTrader INT   → صناديق فارغة مُرجَعة من التاجر
         *  comision         DOUBLE   → نسبة عمولة الحسبة لهذا السطر (مثال: 0.06)
         *  municipality     DOUBLE   → رسوم البلدية لكل صندوق (بالشيكل)
         *  empty            DOUBLE   → تكلفة الصندوق الفارغ لكل صندوق
         *  transport        DOUBLE   → رسوم النقل لكل صندوق (بالشيكل)
         *  totalTrans       DECIMAL  → إجمالي النقل المحتسب مسبقاً (0 إذا لم يُحتسب)
         *  emptyRent        DOUBLE   → إيجار الصندوق الفارغ
         *  billID           INT  FK  → رابط برأس الفاتورة  → dailybills.id
         *  client_ID        INT FK   → معرّف المستأجر
         *
         *  ── معادلات الحساب المستخدمة في هذه الصفحة ──────────────────
         *  إجمالي الصف:
         *    إذا prodWheight > 0  →  total = prodWheight × itemPrice  (حساب بالوزن)
         *    وإلا                 →  total = prodNum × itemPrice      (حساب بالصندوق)
         *  العمولة:    commissionAmt = total × comision
         *  البلدية:    munAmt        = municipality × prodNum
         *  النقل:      transAmt      = totalTrans > 0 ? totalTrans : transport × prodNum
         *  الصافي:     net           = total − commissionAmt − munAmt − transAmt
         *
         * ─────────────────────────────────────────────────────────────
         *  جداول أخرى في النظام (غير مُقرأة في هذه الصفحة)
         * ─────────────────────────────────────────────────────────────
         *  clients          → المستأجرون (نظام SaaS متعدد المستأجرين)
         *  admins           → مستخدمو النظام مع أدوار (role)
         *  superadmins      → المسؤولون العامون للنظام
         *  farmers          → المزارعون الفعليون (يختلفون عن commons)
         *  commonfarmers    → رابط بين المشترك والمزارع (نسبة الربح rate)
         *  commonpartners   → رابط بين المشترك والشريك (نسبة الربح rate)
         *  partners         → الشركاء
         *  linkedcommons    → ربط مشترك رئيسي بمشترك فرعي (type: 0/1)
         *  collections      → ملخص تحصيل لكل فاتورة/مشترك (TotalComs, TotalBill...)
         *  collectiontraders→ ملخص تحصيل لكل فاتورة/تاجر
         *  cashbills        → سندات نقدية (دفع/قبض) | UserType+type+TypeID+price
         *  sheks            → سندات شيكات | ShekNum+ShekDate+bankName
         *  hwalas           → سندات حوالات بنكية | bankName+Alsanad
         *  emptybills       → سندات صناديق فارغة | emptyNum+storageName
         *  storemshtalbills → سندات مخازن/مشاتل | storeID+billID
         *  guarantees       → سندات ضمانات | storeID+billType
         *  banks            → بيانات البنوك | bankNum+name+address
         *  shops            → المتاجر | shopNum+name+address
         *  stores           → المخازن | storeNum+name+address
         *  mshtals          → المشاتل (حضانات النباتات) | mshtalNum+name
         *  employees        → الموظفون | emplyeeNum+name+salary
         *  salaries         → سجل رواتب الموظفين | salary+date+employee_ID
         *  taxes            → إعدادات الرسوم الافتراضية | comision+municipality+transport+empty+emptyRent
         *  permissions      → صلاحيات المستخدمين | showCommon+showFarmer
         *  groups           → مجموعات المستخدمين | user_id+admin_id
         *  phones           → أرقام هواتف إضافية
         *  customizes       → إعدادات تخصيص واجهة المستخدم
         *
         * ─────────────────────────────────────────────────────────────
         *  العلاقات الأساسية المستخدمة في ربط الجداول (JOIN):
         *    dailybills.farmerID  → commons.id   (المشترك الذي أرسل البضاعة)
         *    dailybills.traderID  → traders.id   (التاجر الذي اشترى)
         *    dailyorders.billID   → dailybills.id (الفاتورة التي ينتمي إليها السطر)
         *    dailyorders.prodID   → products.id   (نوع المنتج في السطر)
         * ================================================================
         */

        // ============================================================
        //  STATE — الحالة العامة للتطبيق (البيانات المُحمَّلة في الذاكرة)
        // ============================================================
        const DB = {
            commons: [], // ← بيانات جدول commons (id, name)
            traders: [], // ← بيانات جدول traders (id, name)
            products: [], // ← بيانات جدول products (id, name)
            dailybills: [], // ← بيانات جدول dailybills (id, traderID, commonID, dateInvoice)
            dailyorders: [], // ← بيانات جدول dailyorders (prodID, prodNum, itemPrice, comision...)
            enriched: [] // ← سطور مدمجة (join): كل سطر يربط order+bill+common+trader+product
        };
        const charts = {}; // ← مرجع لكائنات Chart.js لإتاحة إتلافها (destroy) قبل إعادة الرسم

        // ============================================================
        //  UPLOAD & PARSE — رفع وتحليل ملف SQL
        //
        //  الخطوات:
        //  1. يختار المستخدم ملف SQL dump  → يُظهر زر التحليل
        //  2. عند الضغط: يُقرأ الملف بكتل 4MB (chunked FileReader)
        //     لتجنب تجميد المتصفح مع الملفات الكبيرة
        //  3. كل كتلة: تُقسَّم إلى سطور → يتم تحليل كل INSERT
        //  4. بعد القراءة الكاملة: finalizeData() ترتبط الجداول
        // ============================================================
        document.getElementById('sql-file-input').addEventListener('change', function(e) {
            const f = e.target.files[0];
            if (!f) return;
            // عرض اسم الملف وحجمه للمستخدم
            document.getElementById('file-info').textContent = '✅ ' + f.name + ' — ' + (f.size / 1024 / 1024)
                .toFixed(1) + ' MB';
            document.getElementById('parse-btn').style.display = 'inline-block';
        });

        document.getElementById('parse-btn').addEventListener('click', () => {
            const f = document.getElementById('sql-file-input').files[0];
            if (!f) return;
            console.log('%c[Hisba] ▶ Starting parse', 'color:#1a3a5c;font-weight:bold;font-size:13px');
            console.log(
                `[Hisba] File: "${f.name}" | Size: ${(f.size/1024/1024).toFixed(2)} MB | Type: ${f.type||'unknown'}`
            );
            document.getElementById('progress-bar-wrap').style.display = 'block';
            document.getElementById('parse-btn').style.display = 'none';
            readAndParseSql(f);
        });

        function setProgress(pct, label) {
            document.getElementById('progress-fill').style.width = pct + '%';
            document.getElementById('progress-label').textContent = label;
        }

        /**
         * readAndParseSql — قراءة ملف SQL بالكتل وتحليل INSERT statements
         * يقرأ الملف كتلةً كتلة (4 MB لكل مرة) باستخدام FileReader
         * لكل كتلة: تُقسَّم إلى سطور ويُستدعى parseLine() لكل سطر
         * عند الانتهاء: يُستدعى finalizeData() لربط الجداول
         */
        function readAndParseSql(file) {
            const CHUNK = 4 * 1024 * 1024; // حجم الكتلة: 4 ميجابايت
            let offset = 0,
                leftover = ''; // باقي السطر من الكتلة السابقة (سطر غير مكتمل)
            const total = file.size;
            const totalMB = (total / 1024 / 1024).toFixed(2);
            // جداول مؤقتة لتجميع البيانات أثناء القراءة
            const tables = {
                commons: [],
                traders: [],
                products: [],
                dailybills: [],
                dailyorders: []
            };
            let chunkCount = 0;
            const t0 = performance.now(); // لقياس زمن التحليل الكلي
            console.log(`[Hisba] Chunk size: 4 MB | Total chunks estimated: ~${Math.ceil(total / (4*1024*1024))}`);
            console.group('[Hisba] Chunk reading progress');

            function readNext() {
                if (offset >= total) {
                    console.groupEnd();
                    if (leftover.trim()) parseLine(leftover.trim(), tables);
                    const elapsed = ((performance.now() - t0) / 1000).toFixed(2);
                    console.log(`%c[Hisba] ✅ Read complete in ${elapsed}s`, 'color:green;font-weight:bold');
                    console.log('[Hisba] Raw counts →', {
                        commons: tables.commons.length,
                        traders: tables.traders.length,
                        products: tables.products.length,
                        dailybills: tables.dailybills.length,
                        dailyorders: tables.dailyorders.length
                    });
                    finalizeData(tables);
                    return;
                }
                const slice = file.slice(offset, Math.min(offset + CHUNK, total));
                const reader = new FileReader();
                reader.onload = function(e) {
                    chunkCount++;
                    const text = e.target.result;
                    const lines = (leftover + text).split('\n');
                    leftover = lines.pop();
                    for (const line of lines) {
                        const t = line.trim();
                        if (t) parseLine(t, tables);
                    }
                    offset += CHUNK;
                    const pct = Math.min(90, Math.round(offset / total * 90));
                    const readMB = (offset / 1024 / 1024).toFixed(1);
                    const elapsed = ((performance.now() - t0) / 1000).toFixed(1);
                    console.log(
                        `[Hisba] Chunk #${chunkCount} | ${readMB} / ${totalMB} MB (${pct}%) | ` +
                        `commons:${tables.commons.length} traders:${tables.traders.length} ` +
                        `products:${tables.products.length} bills:${tables.dailybills.length} ` +
                        `orders:${tables.dailyorders.length} | ${elapsed}s elapsed`
                    );
                    setProgress(pct, 'جارٍ القراءة... ' + readMB + ' / ' + totalMB + ' MB');
                    setTimeout(readNext, 0);
                };
                reader.onerror = function() {
                    console.error(`[Hisba] ❌ FileReader error at offset ${offset}`);
                };
                reader.readAsText(slice, 'utf-8');
            }
            readNext();
        }

        function parseLine(line, tables) {
            if (line.indexOf('INSERT') !== 0) return;
            const m = line.match(/^INSERT INTO `(\w+)` VALUES\s*(.+)/i);
            if (!m) return;
            const tbl = m[1];
            if (!tables[tbl]) {
                // Only warn once per unknown table to avoid spam
                if (!parseLine._warned) parseLine._warned = {};
                if (!parseLine._warned[tbl]) {
                    console.warn(`[Hisba] Skipping unknown table: "${tbl}" (won't warn again)`);
                    parseLine._warned[tbl] = true;
                }
                return;
            }
            const valuesPart = m[2];
            const regex = /\(([^)]*(?:'(?:[^'\\]|\\.)*'[^)]*)*)\)/g;
            let match;
            while ((match = regex.exec(valuesPart)) !== null) {
                parseInsertRow(tbl, match[1], tables);
            }
        }

        /**
         * parseInsertRow — تحويل صف INSERT واحد إلى كائن JavaScript
         * row[N] يشير إلى العمود N (بترتيب تعريف الجدول في ss.sql)
         */
        function parseInsertRow(tbl, str, tables) {
            const row = splitRow(str); // تقسيم السطر إلى قيم مع دعم النصوص المقتبسة
            if (!row || !row.length) return;
            switch (tbl) {
                case 'commons':
                    // commons: id, commonNum, name, address, phone, transport, comision...
                    // row[0]=id | row[1]=commonNum | row[2]=name
                    if (row.length >= 3) tables.commons.push({
                        id: +row[0],
                        name: uq(row[2]) // اسم المشترك
                    });
                    break;
                case 'traders':
                    // traders: id, traderNum, name, address, phone, empty, emptyRent...
                    // row[0]=id | row[1]=traderNum | row[2]=name
                    if (row.length >= 3) tables.traders.push({
                        id: +row[0],
                        name: uq(row[2]) // اسم التاجر
                    });
                    break;
                case 'products':
                    // products: id, prodName, client_ID
                    // row[0]=id | row[1]=prodName
                    if (row.length >= 2) tables.products.push({
                        id: +row[0],
                        name: uq(row[1]) // اسم المنتج (مثال: "بندورة")
                    });
                    break;
                case 'dailybills':
                    // dailybills: id, traderID, farmerID(=commonID), dateInvoice, status, pre_year, bill_id, client_ID
                    // row[0]=id | row[1]=traderID | row[2]=farmerID(→commons) | row[3]=dateInvoice
                    // ملاحظة: العمود اسمه farmerID لكنه يشير إلى جدول commons
                    if (row.length >= 4) tables.dailybills.push({
                        id: +row[0],
                        traderID: +row[1], // → traders.id
                        commonID: +row[2], // → commons.id (مخزون كـ farmerID في DB)
                        dateInvoice: uq(row[3]) // تاريخ الفاتورة YYYY-MM-DD
                    });
                    break;
                case 'dailyorders':
                    // dailyorders: id[0], prodID[1], prodNum[2], prodWheight[3], itemPrice[4],
                    //   weightRate[5], emptyReturend[6], emptyReturendTrader[7],
                    //   comision[8], municipality[9], empty[10], transport[11],
                    //   totalTrans[12], emptyRent[13], billID[14], client_ID[15]
                    if (row.length >= 15) {
                        const prodNum = parseInt(row[2]) || 0; // عدد الصناديق
                        const prodWeight = parseFloat(row[3]) || 0; // الوزن الإجمالي (كغ)
                        const itemPrice = parseFloat(row[4]) || 0; // السعر (₪/كغ أو ₪/صندوق)
                        const comision = parseFloat(row[8]) || 0; // نسبة عمولة الحسبة (0.06 = 6%)
                        const mun = parseFloat(row[9]) || 0; // رسوم البلدية لكل صندوق (₪)
                        const transport = parseFloat(row[11]) || 0; // رسوم النقل لكل صندوق (₪)
                        const totalTrans = parseFloat(row[12]) || 0; // إجمالي النقل المحتسب مسبقاً

                        // حساب الإجمالي: إذا كان هناك وزن → حساب بالوزن، وإلا → حساب بالصناديق
                        const total = prodWeight > 0 ? prodWeight * itemPrice : prodNum * itemPrice;
                        // حساب النقل: استخدام totalTrans إذا موجود، وإلا احتساب transport × عدد الصناديق
                        const transAmt = totalTrans > 0 ? totalTrans : transport * prodNum;

                        tables.dailyorders.push({
                            prodID: +row[1], // → products.id
                            prodNum, // عدد الصناديق
                            prodWeight, // الوزن
                            itemPrice, // السعر
                            comision, // نسبة العمولة
                            mun, // رسوم البلدية/صندوق
                            transAmt, // إجمالي النقل المحتسب
                            billID: +row[14], // → dailybills.id
                            total, // = الوزن×السعر أو الصناديق×السعر
                            commissionAmt: total * comision, // = إجمالي × نسبة العمولة
                            munAmt: mun * prodNum // = رسوم البلدية × عدد الصناديق
                        });
                    }
                    break;
            }
        }

        /**
         * splitRow — تقسيم سطر SQL إلى مصفوفة من القيم
         * يتعامل مع النصوص المقتبسة بعلامات ' بشكل صحيح
         * (تجاهل الفاصلة داخل النصوص المقتبسة)
         * مثال: "1,'أحمد',0.06" → ['1', "'أحمد'", '0.06']
         */
        function splitRow(str) {
            const vals = [];
            let cur = '',
                inStr = false, // هل نحن داخل نص مقتبس؟
                esc = false; // هل الحرف التالي مُهرَّب (escaped)؟
            for (let i = 0; i < str.length; i++) {
                const c = str[i];
                if (esc) {
                    cur += c;
                    esc = false;
                    continue;
                }
                if (c === '\\') {
                    esc = true; // الحرف التالي مُهرَّب
                    cur += c;
                    continue;
                }
                if (c === "'" && !inStr) {
                    inStr = true; // بداية نص مقتبس
                    cur += c;
                    continue;
                }
                if (c === "'" && inStr) {
                    inStr = false; // نهاية نص مقتبس
                    cur += c;
                    continue;
                }
                if (c === ',' && !inStr) {
                    vals.push(cur.trim()); // فاصل بين القيم (خارج النصوص)
                    cur = '';
                    continue;
                }
                cur += c;
            }
            if (cur.trim()) vals.push(cur.trim()); // القيمة الأخيرة
            return vals;
        }

        /**
         * uq — إزالة علامات الاقتباس من قيمة نصية SQL
         * مثال: "'أحمد العطيات'" → "أحمد العطيات"
         * يتعامل أيضاً مع الهروب (\'  →  ')
         */
        function uq(s) {
            if (!s) return '';
            s = s.trim();
            if (s.startsWith("'") && s.endsWith("'")) s = s.slice(1, -1); // إزالة الاقتباس
            return s.replace(/\\'/g, "'").replace(/\\\\/g, '\\'); // إلغاء الهروب
        }

        /**
         * finalizeData — ربط جداول dailyorders + dailybills (JOIN في الذاكرة)
         *
         * المشكلة: dailyorders لا تحتوي على تاريخ/مشترك/تاجر مباشرةً،
         *          هذه المعلومات موجودة في dailybills عبر billID.
         *
         * الحل: بناء خريطة (billMap) من id → bill،
         *       ثم لكل order: إيجاد الـ bill المقابل ودمج البيانات
         *       في مصفوفة DB.enriched (السطور المدمجة الجاهزة للتحليل)
         *
         * النتيجة: كل عنصر في DB.enriched يحتوي على:
         *   date, commonID, traderID, prodID, boxes, weight,
         *   itemPrice, total, commissionAmt, munAmt, transAmt, billID
         */
        function finalizeData(tables) {
            setProgress(93, 'جارٍ ربط الجداول...');
            console.log('%c[Hisba] 🔗 Joining tables...', 'color:#2d6a4f;font-weight:bold');
            const t1 = performance.now();
            setTimeout(() => {
                // نقل البيانات إلى الكائن العام DB
                DB.commons = tables.commons;
                DB.traders = tables.traders;
                DB.products = tables.products;
                DB.dailybills = tables.dailybills;
                DB.dailyorders = tables.dailyorders;

                // بناء خريطة الفواتير: { billID → billObject } للبحث السريع O(1)
                const billMap = {};
                for (const b of DB.dailybills) billMap[b.id] = b;
                console.log(`[Hisba] Bill map built: ${Object.keys(billMap).length} bill IDs indexed`);

                DB.enriched = [];
                let skippedOrders = 0; // عداد السطور التي لا تجد فاتورة مقابلة
                for (const o of DB.dailyorders) {
                    const bill = billMap[o.billID]; // البحث عن الفاتورة بـ O(1)
                    if (!bill) {
                        // سطر يشير إلى فاتورة غير موجودة (بيانات غير مكتملة)
                        skippedOrders++;
                        continue;
                    }
                    // دمج بيانات الـ order مع بيانات الـ bill في سطر واحد
                    DB.enriched.push({
                        date: bill.dateInvoice, // التاريخ (من dailybills)
                        commonID: bill.commonID, // المشترك (من dailybills.farmerID)
                        traderID: bill.traderID, // التاجر (من dailybills)
                        prodID: o.prodID, // المنتج (من dailyorders)
                        boxes: o.prodNum, // عدد الصناديق
                        weight: o.prodWeight, // الوزن
                        itemPrice: o.itemPrice, // السعر
                        total: o.total, // الإجمالي (وزن×سعر أو صناديق×سعر)
                        commissionAmt: o.commissionAmt, // العمولة = total × comision
                        commissionRate: o.comision, // نسبة العمولة (للرجوع إليها)
                        munAmt: o.munAmt, // رسوم البلدية = municipality × prodNum
                        transAmt: o.transAmt, // إجمالي النقل
                        billID: o.billID // معرف الفاتورة (للتجميع وعد الفواتير)
                    });
                }

                const joinTime = ((performance.now() - t1) / 1000).toFixed(2);
                console.log(`%c[Hisba] ✅ Join complete in ${joinTime}s`, 'color:green;font-weight:bold');
                console.log(
                    `[Hisba] Enriched rows: ${DB.enriched.length} | Skipped orders (no bill): ${skippedOrders}`);
                if (skippedOrders > 0) console.warn(
                    `[Hisba] ⚠️ ${skippedOrders} orders had no matching bill — they are excluded from stats`);

                // Sanity checks
                const totalBoxes = DB.enriched.reduce((s, r) => s + r.boxes, 0);
                const totalValue = DB.enriched.reduce((s, r) => s + r.total, 0);
                console.log('%c[Hisba] 📊 Sanity check', 'color:#d97706;font-weight:bold', {
                    totalBoxes,
                    totalValueNIS: totalValue.toFixed(2),
                    uniqueCommons: new Set(DB.enriched.map(r => r.commonID)).size,
                    uniqueTraders: new Set(DB.enriched.map(r => r.traderID)).size,
                    uniqueProducts: new Set(DB.enriched.map(r => r.prodID)).size,
                    dateRange: [
                        DB.enriched.map(r => r.date).filter(Boolean).sort()[0],
                        DB.enriched.map(r => r.date).filter(Boolean).sort().at(-1)
                    ]
                });

                setProgress(100, 'اكتملت المعالجة بنجاح! ✅');
                console.log('%c[Hisba] 🚀 Rendering app...', 'color:#1a3a5c;font-weight:bold');
                setTimeout(() => {
                    document.getElementById('upload-screen').style.display = 'none';
                    document.getElementById('app').style.display = 'block';
                    initApp();
                }, 500);
            }, 50);
        }

        // ============================================================
        //  INIT — تهيئة التطبيق بعد اكتمال التحليل
        //
        //  يُظهر رقم المشتركين/التجار/المنتجات في الهيدر
        //  يستدعي كل دوال render* لتجهيز جميع التبويبات مسبقاً
        // ============================================================
        function initApp() {
            // عرض ملخص قاعدة البيانات في الهيدر
            document.getElementById('db-meta').textContent =
                'مشتركون: ' + DB.commons.length +
                ' | تجار: ' + DB.traders.length +
                ' | منتجات: ' + DB.products.length +
                ' | فواتير: ' + DB.dailybills.length +
                ' | سطور: ' + DB.dailyorders.length;
            console.log('%c[Hisba] 🖥️ initApp — rendering all sections...', 'color:#1a3a5c;font-weight:bold');
            console.time('[Hisba] Total render time');
            renderOverview();
            console.log('[Hisba]   ✔ Overview rendered');
            renderCommonsList();
            console.log('[Hisba]   ✔ Commons list rendered');
            renderTradersList();
            console.log('[Hisba]   ✔ Traders list rendered');
            renderProductsList();
            console.log('[Hisba]   ✔ Products list rendered');
            renderFinancial();
            console.log('[Hisba]   ✔ Financial rendered');
            renderAdvancedStats();
            console.log('[Hisba]   ✔ Advanced stats rendered');
            console.timeEnd('[Hisba] Total render time');
            console.log('%c[Hisba] ✅ App fully ready', 'color:green;font-weight:bold;font-size:14px');
        }

        // ============================================================
        //  HELPERS — دوال مساعدة مشتركة
        // ============================================================

        /** fmt — تنسيق رقم بالعربية (فاصلة آلاف) بدون عملة */
        function fmt(n) {
            return (+(n || 0)).toLocaleString('ar-EG', {
                maximumFractionDigits: 2
            });
        }

        /** fmtNIS — تنسيق مبلغ بالشيكل – يضيف رمز ₪ بعد الرقم */
        function fmtNIS(n) {
            return fmt(n) + ' ₪';
        }

        /** commonName — إرجاع اسم المشترك من DB.commons بواسطة id */
        function commonName(id) {
            const f = DB.commons.find(x => x.id === +id);
            return f ? f.name : '#' + id; // إذا لم يُعثر عليه: عرض المعرف الرقمي
        }

        /** traderName — إرجاع اسم التاجر من DB.traders بواسطة id */
        function traderName(id) {
            const t = DB.traders.find(x => x.id === +id);
            return t ? t.name : '#' + id;
        }

        /** productName — إرجاع اسم المنتج من DB.products بواسطة id */
        function productName(id) {
            const p = DB.products.find(x => x.id === +id);
            return p ? p.name : '#' + id;
        }

        /**
         * filterByDate — فلترة مصفوفة DB.enriched حسب نطاق تاريخ
         * يستخدم مقارنة نصية مباشرة (التواريخ بصيغة YYYY-MM-DD)
         */
        function filterByDate(arr, from, to) {
            return arr.filter(r => {
                if (!r.date) return false; // تجاهل سطور بدون تاريخ
                if (from && r.date < from) return false; // قبل تاريخ البداية
                if (to && r.date > to) return false; // بعد تاريخ النهاية
                return true;
            });
        }

        /** clearDatesAndRun — تفريغ حقلي التاريخ ثم تشغيل دالة render */
        function clearDatesAndRun(id1, id2, fn) {
            document.getElementById(id1).value = '';
            document.getElementById(id2).value = '';
            fn();
        }

        /** destroyChart — إتلاف مخطط Chart.js الموجود قبل إعادة رسمه
         *   ضروري لمنع تراكم المخططات عند تغيير الفلترة */
        function destroyChart(id) {
            if (charts[id]) {
                charts[id].destroy();
                delete charts[id];
            }
        }

        /**
         * makeBarChart — رسم مخطط أعمدة بسيط باستخدام Chart.js
         * @param id       معرف عنصر <canvas>
         * @param labels   تسميات المحور الأفقي
         * @param data     القيم العددية
         * @param label    عنوان المجموعة (dataset label)
         * @param color    لون الأعمدة (افتراضي: #1a3a5c)
         */
        function makeBarChart(id, labels, data, label, color) {
            destroyChart(id);
            const ctx = document.getElementById(id);
            if (!ctx) return;
            charts[id] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label,
                        data,
                        backgroundColor: color || '#1a3a5c',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                font: {
                                    size: 10
                                }
                            }
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        /**
         * makeLineChart — رسم مخطط خطوط متعدد المحاور (datasets)
         * يدعم عدة مجموعات في نفس المخطط (مثل: سعر متوسط + أعلى + أدنى)
         * يُظهر الأسطورة إذا كان هناك أكثر من dataset واحد
         */
        function makeLineChart(id, labels, datasets) {
            destroyChart(id);
            const ctx = document.getElementById(id);
            if (!ctx) return;
            charts[id] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: datasets.length > 1,
                            position: 'top'
                        }
                    }
                }
            });
        }

        /**
         * makePieChart — رسم مخطط دائري (doughnut) لعرض التوزيع النسبي
         * يُستخدم لتمثيل حصص المشتركين والتجار والمنتجات
         */
        function makePieChart(id, labels, data, colors) {
            destroyChart(id);
            const ctx = document.getElementById(id);
            if (!ctx) return;
            charts[id] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        /** COLORS — لوحة ألوان متسقة للمخططات الدائرية والمتعددة المحاور */
        const COLORS = ['#1a3a5c', '#2d6a4f', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#059669', '#ea580c', '#db2777',
            '#4338ca', '#65a30d', '#b45309', '#0f766e', '#be185d', '#1d4ed8'
        ];

        /** avg — حساب متوسط مصفوفة أرقام، يُرجع 0 للمصفوفة الفارغة */
        function avg(arr) {
            return arr.length ? arr.reduce((s, v) => s + v, 0) / arr.length : 0;
        }

        // ============================================================
        //  AUTOCOMPLETE — بحث تلقائي في حقول المشترك/التاجر/المنتج
        //
        //  acSearch: يبحث في DB[type] ويعرض قائمة منسدلة بالنتائج
        //  selectAc: يختار عنصراً ويضع الاسم في حقل النص والid في hidden input
        //  hideAcDelayed: يُخفي القائمة بتأخير 200ms (لإتاحة حدث onmousedown قبل onblur)
        // ============================================================
        function acSearch(val, type, dropId, hiddenId, inputId) {
            const q = val.trim().toLowerCase();
            const drop = document.getElementById(dropId);
            if (!q) {
                drop.style.display = 'none';
                return;
            }
            const list = DB[type] || [];
            const matches = list.filter(x => x.name.toLowerCase().includes(q)).slice(0, 20);
            if (!matches.length) {
                drop.style.display = 'none';
                return;
            }
            drop.innerHTML = matches.map(x =>
                '<div class="ac-item" onmousedown="selectAc(' + x.id + ',`' + x.name.replace(/`/g, '') + '`,`' +
                hiddenId + '`,`' + inputId + '`,`' + dropId + '`)">' + x.name + '</div>'
        ).join('');
        drop.style.display = 'block';
    }

    function selectAc(id, name, hiddenId, inputId, dropId) {
        document.getElementById(inputId).value = name;
        document.getElementById(hiddenId).value = id;
        document.getElementById(dropId).style.display = 'none';
    }

    function hideAcDelayed(dropId) {
        setTimeout(() => {
            const el = document.getElementById(dropId);
            if (el) el.style.display = 'none';
        }, 200);
    }

    // ============================================================
    //  TABS — إدارة التبويبات
    //
    //  showTab: يُخفي جميع التبويبات ويُظهر المختار
    //  يضع class active على الزر النشط للتلوين
    // ============================================================
    function showTab(name, btn) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        if (btn) btn.classList.add('active');
    }

    // ============================================================
    //  OVERVIEW — النظرة العامة
    //
    //  يحسب المؤشرات الإجمالية من DB.enriched بالكامل (reduce),
    //  ثم يرسم 6 مخططات بيانية
    // ============================================================
    function renderOverview() {
        const e = DB.enriched;
        // مجاميع reduce لحساب الأرقام الكلية
        const totalBoxes = e.reduce((s, r) => s + r.boxes, 0); // إجمالي الصناديق
        const totalValue = e.reduce((s, r) => s + r.total, 0); // إجمالي قيمة البضاعة
        const totalComm = e.reduce((s, r) => s + r.commissionAmt, 0); // إجمالي عمولات الحسبة
        const totalMun = e.reduce((s, r) => s + r.munAmt, 0); // إجمالي رسوم البلدية
        const totalTrans = e.reduce((s, r) => s + r.transAmt, 0); // إجمالي رسوم النقل
        // صافي المشتركين = إجمالي القيمة − عمولة − بلدية − نقل
        const netFarmers = totalValue - totalComm - totalMun - totalTrans;
        const uniqueDates = new Set(e.map(r => r.date)).size; // عدد الأيام النشطة

        document.getElementById('kpi-row').innerHTML = `
                                <div class="kpi-card"><div class="val">${fmt(DB.commons.length)}</div><div class="lbl">👥 إجمالي المشتركين</div></div>
                                <div class="kpi-card green"><div class="val">${fmt(DB.traders.length)}</div><div class="lbl">🏪 إجمالي التجار</div></div>
                                <div class="kpi-card orange"><div class="val">${fmt(DB.products.length)}</div><div class="lbl">📦 أنواع المنتجات</div></div>
                                <div class="kpi-card"><div class="val">${fmt(DB.dailybills.length)}</div><div class="lbl">📋 إجمالي الفواتير</div></div>
                                <div class="kpi-card green"><div class="val">${fmt(totalBoxes)}</div><div class="lbl">📦 إجمالي الصناديق</div></div>
                                <div class="kpi-card orange"><div class="val">${fmtNIS(totalValue)}</div><div class="lbl">💰 إجمالي القيمة</div></div>
                                <div class="kpi-card red"><div class="val">${fmtNIS(totalComm)}</div><div class="lbl">📊 إجمالي العمولات</div></div>
                                <div class="kpi-card purple"><div class="val">${fmtNIS(totalMun)}</div><div class="lbl">🏛️ رسوم البلدية</div></div>
                                <div class="kpi-card"><div class="val">${fmtNIS(totalTrans)}</div><div class="lbl">🚚 رسوم النقل</div></div>
                                <div class="kpi-card green"><div class="val">${fmtNIS(netFarmers)}</div><div class="lbl">✅ صافي المشتركين</div></div>
                                <div class="kpi-card orange"><div class="val">${fmt(uniqueDates)}</div><div class="lbl">📅 أيام النشاط</div></div>
                                <div class="kpi-card red"><div class="val">${(totalValue>0?(totalComm/totalValue*100):0).toFixed(1)}%</div><div class="lbl">نسبة العمولة</div></div>
                            `;

        // Monthly revenue
        const monthlyMap = {};
        for (const r of e) {
            if (!r.date) continue;
            const m = r.date.slice(0, 7);
            if (!monthlyMap[m]) monthlyMap[m] = 0;
            monthlyMap[m] += r.total;
        }
        const months18 = Object.keys(monthlyMap).sort().slice(-18);
        makeLineChart('chart-monthly-revenue', months18, [{
            label: 'الإيرادات ₪',
            data: months18.map(m => monthlyMap[m]),
            borderColor: '#1a3a5c',
            backgroundColor: 'rgba(26,58,92,0.1)',
            fill: true,
            tension: 0.4
        }]);

        // Top products by boxes
        const prodBoxes = {};
        for (const r of e) {
            prodBoxes[r.prodID] = (prodBoxes[r.prodID] || 0) + r.boxes;
        }
        const topProds = Object.entries(prodBoxes).sort((a, b) => b[1] - a[1]).slice(0, 10);
        makeBarChart('chart-top-products', topProds.map(([id]) => productName(id)), topProds.map(([, v]) => v),
            'الصناديق', '#2d6a4f');

        // Top commons
        const farmerBoxes = {};
        for (const r of e) {
            farmerBoxes[r.commonID] = (farmerBoxes[r.commonID] || 0) + r.boxes;
        }
        const topCommons = Object.entries(farmerBoxes).sort((a, b) => b[1] - a[1]).slice(0, 10);
        makeBarChart('chart-top-commons', topCommons.map(([id]) => commonName(id)), topCommons.map(([, v]) => v),
            'الصناديق', '#d97706');

        // Top traders
        const traderVal = {};
        for (const r of e) {
            traderVal[r.traderID] = (traderVal[r.traderID] || 0) + r.total;
        }
        const topTraders = Object.entries(traderVal).sort((a, b) => b[1] - a[1]).slice(0, 10);
        makeBarChart('chart-top-traders', topTraders.map(([id]) => traderName(id)), topTraders.map(([, v]) => v),
            'القيمة ₪', '#dc2626');

        // Pie
        const top8 = Object.entries(prodBoxes).sort((a, b) => b[1] - a[1]).slice(0, 8);
        makePieChart('chart-revenue-pie', top8.map(([id]) => productName(id)), top8.map(([, v]) => v), COLORS);
        makePieChart('chart-fees-donut',
            ['عمولات', 'بلدية', 'نقل', 'صافي المشتركين'],
            [totalComm, totalMun, totalTrans, Math.max(0, netFarmers)],
            ['#dc2626', '#d97706', '#7c3aed', '#2d6a4f']);
    }

    // ============================================================
    //  COMMONS LIST — قائمة المشتركين
    //
    //  يجمع DB.enriched حسب commonID ويحسب لكل مشترك:
    //    boxes | total | comm | mun | trans | setالفواتير
    //  ثم يرسم الجدول مرتباً تنازلياً بإجمالي القيمة
    // ============================================================
    function renderCommonsList() {
        const from = document.getElementById('commons-date-from').value;
        const to = document.getElementById('commons-date-to').value;
        const data = filterByDate(DB.enriched, from, to);
        const map = {};
        for (const r of data) {
            if (!map[r.commonID]) map[r.commonID] = {
                boxes: 0,
                total: 0,
                comm: 0,
                mun: 0,
                trans: 0,
                bills: new Set()
            };
            map[r.commonID].boxes += r.boxes;
            map[r.commonID].total += r.total;
            map[r.commonID].comm += r.commissionAmt;
            map[r.commonID].mun += r.munAmt;
            map[r.commonID].trans += r.transAmt;
            map[r.commonID].bills.add(r.billID);
        }
        const rows = Object.entries(map).sort((a, b) => b[1].total - a[1].total);
        const wrap = document.getElementById('commons-table-wrap');
        if (!rows.length) {
            wrap.innerHTML = '<div class="no-data">لا توجد بيانات</div>';
            return;
        }
        wrap.innerHTML =
            '<table><thead><tr><th>#</th><th>المشترك</th><th>الصناديق</th><th>إجمالي القيمة</th><th>العمولات</th><th>البلدية</th><th>النقل</th><th>الصافي</th><th>الفواتير</th></tr></thead><tbody>' +
            rows.map(([id, v], i) => '<tr>' +
                '<td>' + (i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : i + 1) + '</td>' +
                '<td><strong>' + commonName(id) + '</strong></td>' +
                '<td><span class="badge badge-blue">' + fmt(v.boxes) + '</span></td>' +
                '<td><strong>' + fmtNIS(v.total) + '</strong></td>' +
                '<td><span class="badge badge-red">' + fmtNIS(v.comm) + '</span></td>' +
                '<td><span class="badge badge-orange">' + fmtNIS(v.mun) + '</span></td>' +
                '<td>' + fmtNIS(v.trans) + '</td>' +
                '<td><span class="badge badge-green">' + fmtNIS(v.total - v.comm - v.mun - v.trans) + '</span></td>' +
                '<td>' + v.bills.size + '</td>' +
                '</tr>').join('') +
            '</tbody></table>';
        const top15 = rows.slice(0, 15);
        makeBarChart('chart-commons-boxes', top15.map(([id]) => commonName(id)), top15.map(([, v]) => v.boxes),
            'الصناديق', '#1a3a5c');
        makeBarChart('chart-commons-value', top15.map(([id]) => commonName(id)), top15.map(([, v]) => v.total),
            'القيمة ₪', '#2d6a4f');
    }

    // ============================================================
    //  COMMON DETAIL — تفاصيل مشترك محدد
    //
    //  يفلتر DB.enriched بالـ commonID + نطاق التاريخ
    //  يجمع حسب المنتج + حسب الشهر
    //  يرسم: KPIs + مخطط شهري + دائري + جدول منتجات
    // ============================================================
    function renderCommonDetail() {
        const fidRaw = document.getElementById('cd-common-id').value;
        const fname = document.getElementById('cd-common-input').value.trim();
        const from = document.getElementById('cd-date-from').value;
        const to = document.getElementById('cd-date-to').value;
        const wrap = document.getElementById('common-detail-content');
        if (!fidRaw && !fname) {
            wrap.innerHTML = '<div class="sec"><div class="no-data">يرجى اختيار مشترك</div></div>';
            return;
        }
        let id = +fidRaw;
        if (!id && fname) {
            const f = DB.commons.find(x => x.name === fname);
            if (f) id = f.id;
        }
        if (!id) {
            wrap.innerHTML = '<div class="sec"><div class="no-data">لم يتم العثور على المشترك</div></div>';
            return;
        }
        let data = DB.enriched.filter(r => +r.commonID === id);
        if (from) data = data.filter(r => r.date >= from);
        if (to) data = data.filter(r => r.date <= to);
        if (!data.length) {
            document.getElementById('cd-print-btn').style.display = 'none';
            wrap.innerHTML = '<div class="sec"><div class="no-data">لا توجد فواتير في الفترة المحددة</div></div>';
            return;
        }

        const totalBoxes = data.reduce((s, r) => s + r.boxes, 0);
        const totalVal = data.reduce((s, r) => s + r.total, 0);
        const totalComm = data.reduce((s, r) => s + r.commissionAmt, 0);
        const totalMun = data.reduce((s, r) => s + r.munAmt, 0);
        const totalTrans = data.reduce((s, r) => s + r.transAmt, 0);
        const net = totalVal - totalComm - totalMun - totalTrans;

        const prodMap = {};
        for (const r of data) {
            if (!prodMap[r.prodID]) prodMap[r.prodID] = {
                boxes: 0,
                total: 0,
                comm: 0,
                mun: 0,
                trans: 0,
                prices: []
            };
            prodMap[r.prodID].boxes += r.boxes;
            prodMap[r.prodID].total += r.total;
            prodMap[r.prodID].comm += r.commissionAmt;
            prodMap[r.prodID].mun += r.munAmt;
            prodMap[r.prodID].trans += r.transAmt;
            prodMap[r.prodID].prices.push(r.itemPrice);
        }
        const prodRows = Object.entries(prodMap).sort((a, b) => b[1].total - a[1].total);

        const monthMap = {};
        for (const r of data) {
            const m = r.date.slice(0, 7);
            if (!monthMap[m]) monthMap[m] = {
                total: 0,
                boxes: 0
            };
            monthMap[m].total += r.total;
            monthMap[m].boxes += r.boxes;
        }
        const months = Object.keys(monthMap).sort();

        wrap.innerHTML =
            '<div class="kpi-row">' +
            '<div class="kpi-card"><div class="val">' + fmt(totalBoxes) +
            '</div><div class="lbl">📦 الصناديق</div></div>' +
            '<div class="kpi-card green"><div class="val">' + fmtNIS(totalVal) +
            '</div><div class="lbl">💰 إجمالي القيمة</div></div>' +
            '<div class="kpi-card red"><div class="val">' + fmtNIS(totalComm) +
            '</div><div class="lbl">📊 العمولات</div></div>' +
            '<div class="kpi-card orange"><div class="val">' + fmtNIS(totalMun) +
            '</div><div class="lbl">🏛️ البلدية</div></div>' +
            '<div class="kpi-card purple"><div class="val">' + fmtNIS(totalTrans) +
            '</div><div class="lbl">🚚 النقل</div></div>' +
            '<div class="kpi-card green"><div class="val">' + fmtNIS(net) +
            '</div><div class="lbl">✅ الصافي للمشترك</div></div>' +
            '</div>' +
            '<div class="chart-grid">' +
            '<div class="sec"><h2>📈 الأداء الشهري</h2><div class="chart-wrap"><canvas id="chart-fd-monthly"></canvas></div></div>' +
            '<div class="sec"><h2>📦 توزيع المنتجات</h2><div class="chart-wrap"><canvas id="chart-fd-pie"></canvas></div></div>' +
            '</div>' +
            '<div class="sec"><h2>📋 تفاصيل كل منتج</h2><div class="tbl-wrap">' +
            '<table><thead><tr><th>#</th><th>المنتج</th><th>الصناديق</th><th>الإجمالي</th><th>متوسط السعر</th><th>أعلى سعر</th><th>أدنى سعر</th><th>العمولة</th><th>البلدية</th><th>النقل</th><th>الصافي</th></tr></thead><tbody>' +
            prodRows.map(([pid, v], i) => {
                const a = avg(v.prices),
                    mx = Math.max(...v.prices),
                    mn = Math.min(...v.prices);
                return '<tr><td>' + (i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : i + 1) + '</td>' +
                    '<td><strong>' + productName(pid) + '</strong></td>' +
                    '<td><span class="badge badge-blue">' + fmt(v.boxes) + '</span></td>' +
                    '<td><strong>' + fmtNIS(v.total) + '</strong></td>' +
                    '<td>' + fmtNIS(a) + '</td>' +
                    '<td><span class="badge badge-green">' + fmtNIS(mx) + '</span></td>' +
                    '<td><span class="badge badge-red">' + fmtNIS(mn) + '</span></td>' +
                    '<td>' + fmtNIS(v.comm) + '</td>' +
                    '<td>' + fmtNIS(v.mun) + '</td>' +
                    '<td>' + fmtNIS(v.trans) + '</td>' +
                    '<td><span class="badge badge-green">' + fmtNIS(v.total - v.comm - v.mun - v.trans) +
                    '</span></td>' +
                    '</tr>';
            }).join('') +
            '</tbody></table></div></div>';

        setTimeout(() => {
            destroyChart('chart-fd-monthly');
            const ctx = document.getElementById('chart-fd-monthly');
            if (ctx) charts['chart-fd-monthly'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                            label: 'القيمة ₪',
                            data: months.map(m => monthMap[m].total),
                            borderColor: '#1a3a5c',
                            backgroundColor: 'rgba(26,58,92,0.1)',
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'الصناديق',
                            data: months.map(m => monthMap[m].boxes),
                            borderColor: '#2d6a4f',
                            borderDash: [5, 5],
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            position: 'right',
                            title: {
                                display: true,
                                text: 'القيمة ₪'
                            }
                        },
                        y1: {
                            position: 'left',
                            title: {
                                display: true,
                                text: 'الصناديق'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
            const top8 = prodRows.slice(0, 8);
            makePieChart('chart-fd-pie', top8.map(([id]) => productName(id)), top8.map(([, v]) => v.boxes),
                COLORS);
            document.getElementById('cd-print-btn').style.display = 'inline-block';
        }, 100);
    }

    // ============================================================
    //  PRINT COMMON DETAIL — فتح نافذة طباعة التقرير
    //
    //  يلتقط صور الرسوم من Canvas قبل فتح النافذة
    //  يبني HTML كاملاً في الذاكرة ويفتحه في تبويبة جديدة
    //  ثم يدعو window.print() بعد 600ms لإتاحة تحميل الصور
    // ============================================================
    function printCommonDetail() {
        const name = document.getElementById('cd-common-input').value.trim() || 'مشترك';
        const from = document.getElementById('cd-date-from').value;
        const to = document.getElementById('cd-date-to').value;
        const dateRange = (from || to) ?
            ('\u0627\u0644\u0641\u062a\u0631\u0629: ' + (from || '—') + ' \u0625\u0644\u0649 ' + (to || '—')) :
            '\u062c\u0645\u064a\u0639 \u0627\u0644\u0641\u062a\u0631\u0627\u062a';
        const printDate = new Date().toLocaleDateString('ar-EG', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Capture chart images before opening print window
        const monthlyCanvas = document.getElementById('chart-fd-monthly');
        const pieCanvas = document.getElementById('chart-fd-pie');
        const monthlyImg = monthlyCanvas ? monthlyCanvas.toDataURL('image/png') : null;
        const pieImg = pieCanvas ? pieCanvas.toDataURL('image/png') : null;

        // Capture KPI cards HTML
        const content = document.getElementById('common-detail-content');
        const kpiHTML = content.querySelector('.kpi-row') ? content.querySelector('.kpi-row').outerHTML : '';

        // Capture product table HTML (clone to strip badges to plain text for print)
        const tblWrap = content.querySelector('.tbl-wrap');
        const tblHTML = tblWrap ? tblWrap.outerHTML : '';

        const html = `<!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
        <meta charset="UTF-8">
        <title>\u062a\u0642\u0631\u064a\u0631 \u0645\u0634\u062a\u0631\u0643 — ${name}</title>
        <style>
          * { box-sizing: border-box; margin: 0; padding: 0; }
          body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; direction: rtl; background: #fff; color: #1a202c; padding: 24px; font-size: 13px; }
          .print-header { border-bottom: 3px solid #1a3a5c; padding-bottom: 14px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; }
          .print-header h1 { font-size: 1.5rem; color: #1a3a5c; }
          .print-header .sub { color: #64748b; font-size: .9rem; margin-top: 4px; }
          .print-header .meta { text-align: left; color: #64748b; font-size: .82rem; }
          .section-title { font-size: 1rem; font-weight: 700; color: #1a3a5c; border-right: 4px solid #2d6a4f; padding-right: 10px; margin: 20px 0 12px; }
          .kpi-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
          .kpi-card { border-radius: 10px; padding: 14px; text-align: center; border: 1.5px solid #e2e8f0; border-top: 4px solid #1a3a5c; }
          .kpi-card.green  { border-top-color: #2d6a4f; }
          .kpi-card.red    { border-top-color: #dc2626; }
          .kpi-card.orange { border-top-color: #d97706; }
          .kpi-card.purple { border-top-color: #7c3aed; }
          .kpi-card .val { font-size: 1.4rem; font-weight: 700; color: #1a202c; }
          .kpi-card .lbl { font-size: .75rem; color: #64748b; margin-top: 3px; }
          .charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
          .chart-box { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 14px; }
          .chart-box h3 { font-size: .9rem; color: #1a3a5c; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
          .chart-box img { width: 100%; height: auto; }
          table { width: 100%; border-collapse: collapse; font-size: .82rem; margin-top: 4px; }
          th { background: #1a3a5c; color: #fff; padding: 8px 10px; text-align: right; white-space: nowrap; }
          td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; }
          tr:nth-child(even) td { background: #f8fafc; }
          .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: .75rem; font-weight: 600; }
          .badge-blue   { background: #dbeafe; color: #1d4ed8; }
          .badge-green  { background: #dcfce7; color: #15803d; }
          .badge-orange { background: #fef3c7; color: #b45309; }
          .badge-red    { background: #fee2e2; color: #dc2626; }
          .print-footer { border-top: 1px solid #e2e8f0; margin-top: 28px; padding-top: 10px; text-align: center; color: #94a3b8; font-size: .78rem; }
          @media print {
            body { padding: 10px; }
            .kpi-row { grid-template-columns: repeat(3, 1fr); }
            .charts-row { grid-template-columns: 1fr 1fr; }
          }
        </style>
        </head>
        <body>
          <div class="print-header">
            <div>
              <h1>\ud83d\udcca \u062a\u0642\u0631\u064a\u0631 \u0645\u0634\u062a\u0631\u0643: ${name}</h1>
              <div class="sub">${dateRange}</div>
            </div>
            <div class="meta">\u062a\u0627\u0631\u064a\u062e \u0627\u0644\u0637\u0628\u0627\u0639\u0629: ${printDate}<br>\u0646\u0638\u0627\u0645 \u0625\u062d\u0635\u0627\u0621\u0627\u062a \u0627\u0644\u062d\u0633\u0628\u0629</div>
          </div>

          <div class="section-title">\ud83d\udcca \u0645\u0644\u062e\u0635 \u0627\u0644\u0623\u062f\u0627\u0621 \u0627\u0644\u0645\u0627\u0644\u064a</div>
          ${kpiHTML}

          <div class="section-title">\ud83d\udcc8 \u0627\u0644\u0631\u0633\u0648\u0645 \u0627\u0644\u0628\u064a\u0627\u0646\u064a\u0629</div>
          <div class="charts-row">
            ${monthlyImg ? `<div class="chart-box"><h3>\ud83d\udcc8 \u0627\u0644\u0623\u062f\u0627\u0621 \u0627\u0644\u0634\u0647\u0631\u064a</h3><img src="${monthlyImg}"></div>` : ''}
            ${pieImg     ? `<div class="chart-box"><h3>\ud83c\udf5f \u062a\u0648\u0632\u064a\u0639 \u0627\u0644\u0645\u0646\u062a\u062c\u0627\u062a</h3><img src="${pieImg}"></div>` : ''}
          </div>

          <div class="section-title">\ud83d\udccb \u062a\u0641\u0635\u064a\u0644 \u0643\u0644 \u0645\u0646\u062a\u062c</div>
          ${tblHTML}

          <div class="print-footer">\u062a\u0645 \u0625\u0646\u0634\u0627\u0621 \u0647\u0630\u0627 \u0627\u0644\u062a\u0642\u0631\u064a\u0631 \u0628\u0648\u0627\u0633\u0637\u0629 \u0646\u0638\u0627\u0645 \u0625\u062d\u0635\u0627\u0621\u0627\u062a \u0627\u0644\u062d\u0633\u0628\u0629 &mdash; ${printDate}</div>
        </body>
        </html>`;

            const win = window.open('', '_blank', 'width=900,height=700');
            win.document.write(html);
            win.document.close();
            win.focus();
            setTimeout(() => {
                win.print();
            }, 600);
        }

        // ============================================================
        //  TRADERS LIST — قائمة التجار
        //
        //  يجمع DB.enriched حسب traderID ويحسب لكل تاجر:
        //    boxes | total | setالفواتير | setأنواعالمنتجات
        // ============================================================
        function renderTradersList() {
            const from = document.getElementById('traders-date-from').value;
            const to = document.getElementById('traders-date-to').value;
            const data = filterByDate(DB.enriched, from, to);
            const map = {};
            for (const r of data) {
                if (!map[r.traderID]) map[r.traderID] = {
                    boxes: 0,
                    total: 0,
                    bills: new Set(),
                    products: new Set()
                };
                map[r.traderID].boxes += r.boxes;
                map[r.traderID].total += r.total;
                map[r.traderID].bills.add(r.billID);
                map[r.traderID].products.add(r.prodID);
            }
            const rows = Object.entries(map).sort((a, b) => b[1].total - a[1].total);
            const wrap = document.getElementById('traders-table-wrap');
            if (!rows.length) {
                wrap.innerHTML = '<div class="no-data">لا توجد بيانات</div>';
                return;
            }
            wrap.innerHTML =
                '<table><thead><tr><th>#</th><th>التاجر</th><th>الصناديق</th><th>إجمالي المشتريات</th><th>متوسط الصندوق</th><th>الفواتير</th><th>أنواع المنتجات</th></tr></thead><tbody>' +
                rows.map(([id, v], i) => '<tr>' +
                    '<td>' + (i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : i + 1) + '</td>' +
                    '<td><strong>' + traderName(id) + '</strong></td>' +
                    '<td><span class="badge badge-blue">' + fmt(v.boxes) + '</span></td>' +
                    '<td><strong>' + fmtNIS(v.total) + '</strong></td>' +
                    '<td>' + fmtNIS(v.boxes > 0 ? v.total / v.boxes : 0) + '</td>' +
                    '<td>' + v.bills.size + '</td>' +
                    '<td><span class="badge badge-green">' + v.products.size + '</span></td>' +
                    '</tr>').join('') +
                '</tbody></table>';
            const top15 = rows.slice(0, 15);
            makeBarChart('chart-traders-boxes', top15.map(([id]) => traderName(id)), top15.map(([, v]) => v.boxes),
                'الصناديق', '#7c3aed');
            makeBarChart('chart-traders-value', top15.map(([id]) => traderName(id)), top15.map(([, v]) => v.total),
                'القيمة ₪', '#0891b2');
        }

        // ============================================================
        //  TRADER DETAIL — تفاصيل تاجر محدد
        //
        //  يفلتر DB.enriched بالـ traderID + نطاق التاريخ
        //  يجمع حسب المنتج + حسب المشترك + حسب الشهر
        // ============================================================
        function renderTraderDetail() {
            const tidRaw = document.getElementById('td-trader-id').value;
            const tname = document.getElementById('td-trader-input').value.trim();
            const from = document.getElementById('td-date-from').value;
            const to = document.getElementById('td-date-to').value;
            const wrap = document.getElementById('trader-detail-content');
            if (!tidRaw && !tname) {
                wrap.innerHTML = '<div class="sec"><div class="no-data">يرجى اختيار تاجر</div></div>';
                return;
            }
            let id = +tidRaw;
            if (!id && tname) {
                const t = DB.traders.find(x => x.name === tname);
                if (t) id = t.id;
            }
            if (!id) {
                wrap.innerHTML = '<div class="sec"><div class="no-data">لم يتم العثور على التاجر</div></div>';
                return;
            }
            let data = DB.enriched.filter(r => +r.traderID === id);
            if (from) data = data.filter(r => r.date >= from);
            if (to) data = data.filter(r => r.date <= to);
            if (!data.length) {
                wrap.innerHTML = '<div class="sec"><div class="no-data">لا توجد فواتير في الفترة المحددة</div></div>';
                return;
            }

            const totalBoxes = data.reduce((s, r) => s + r.boxes, 0);
            const totalVal = data.reduce((s, r) => s + r.total, 0);
            const bills = new Set(data.map(r => r.billID)).size;

            const prodMap = {},
                farmerMap = {},
                monthMap = {};
            for (const r of data) {
                if (!prodMap[r.prodID]) prodMap[r.prodID] = {
                    boxes: 0,
                    total: 0,
                    prices: []
                };
                prodMap[r.prodID].boxes += r.boxes;
                prodMap[r.prodID].total += r.total;
                prodMap[r.prodID].prices.push(r.itemPrice);
                if (!farmerMap[r.commonID]) farmerMap[r.commonID] = {
                    boxes: 0,
                    total: 0
                };
                farmerMap[r.commonID].boxes += r.boxes;
                farmerMap[r.commonID].total += r.total;
                const m = r.date.slice(0, 7);
                if (!monthMap[m]) monthMap[m] = {
                    total: 0,
                    boxes: 0
                };
                monthMap[m].total += r.total;
                monthMap[m].boxes += r.boxes;
            }
            const prodRows = Object.entries(prodMap).sort((a, b) => b[1].total - a[1].total);
            const topFarmers = Object.entries(farmerMap).sort((a, b) => b[1].total - a[1].total).slice(0, 10);
            const months = Object.keys(monthMap).sort();

            wrap.innerHTML =
                '<div class="kpi-row">' +
                '<div class="kpi-card"><div class="val">' + fmt(totalBoxes) +
                '</div><div class="lbl">📦 الصناديق</div></div>' +
                '<div class="kpi-card green"><div class="val">' + fmtNIS(totalVal) +
                '</div><div class="lbl">💰 إجمالي المشتريات</div></div>' +
                '<div class="kpi-card orange"><div class="val">' + fmtNIS(totalBoxes > 0 ? totalVal / totalBoxes : 0) +
                '</div><div class="lbl">📊 متوسط الصندوق</div></div>' +
                '<div class="kpi-card purple"><div class="val">' + fmt(bills) +
                '</div><div class="lbl">📋 الفواتير</div></div>' +
                '<div class="kpi-card"><div class="val">' + fmt(prodRows.length) +
                '</div><div class="lbl">📦 أنواع المنتجات</div></div>' +
                '</div>' +
                '<div class="chart-grid">' +
                '<div class="sec"><h2>📈 الأداء الشهري</h2><div class="chart-wrap"><canvas id="chart-td-monthly"></canvas></div></div>' +
                '<div class="sec"><h2>📦 توزيع المنتجات</h2><div class="chart-wrap"><canvas id="chart-td-pie"></canvas></div></div>' +
                '</div>' +
                '<div class="chart-grid">' +
                '<div class="sec"><h2>👥 أكثر المشتركين تعاملاً</h2><div class="chart-wrap"><canvas id="chart-td-commons"></canvas></div></div>' +
                '<div class="sec"><h2>📋 تفاصيل المنتجات</h2><div class="tbl-wrap"><table><thead><tr><th>#</th><th>المنتج</th><th>الصناديق</th><th>الإجمالي</th><th>متوسط السعر</th></tr></thead><tbody>' +
                prodRows.map(([pid, v], i) => '<tr><td>' + (i + 1) + '</td><td>' + productName(pid) +
                    '</td><td><span class="badge badge-blue">' + fmt(v.boxes) + '</span></td><td>' + fmtNIS(v.total) +
                    '</td><td>' + fmtNIS(avg(v.prices)) + '</td></tr>').join('') +
                '</tbody></table></div></div>' +
                '</div>';

            setTimeout(() => {
                destroyChart('chart-td-monthly');
                const ctx = document.getElementById('chart-td-monthly');
                if (ctx) charts['chart-td-monthly'] = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: months,
                        datasets: [{
                            label: 'القيمة ₪',
                            data: months.map(m => monthMap[m].total),
                            backgroundColor: 'rgba(124,58,237,0.75)',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
                const top8 = prodRows.slice(0, 8);
                makePieChart('chart-td-pie', top8.map(([id]) => productName(id)), top8.map(([, v]) => v.boxes),
                    COLORS);
                makeBarChart('chart-td-commons', topFarmers.map(([id]) => commonName(id)), topFarmers.map(([, v]) =>
                    v.total), 'القيمة ₪', '#2d6a4f');
            }, 100);
        }

        // ============================================================
        //  PRODUCTS LIST — قائمة المنتجات
        //
        //  يجمع DB.enriched حسب prodID ويحسب:
        //    boxes | total | prices[] | setالمشتركين | setالتجار
        //  يحسب متوسط/أعلى/أدنى سعر لكل منتج
        // ============================================================
        function renderProductsList() {
            const from = document.getElementById('products-date-from').value;
            const to = document.getElementById('products-date-to').value;
            const data = filterByDate(DB.enriched, from, to);
            const map = {};
            for (const r of data) {
                if (!map[r.prodID]) map[r.prodID] = {
                    boxes: 0,
                    total: 0,
                    prices: [],
                    commons: new Set(),
                    traders: new Set()
                };
                map[r.prodID].boxes += r.boxes;
                map[r.prodID].total += r.total;
                map[r.prodID].prices.push(r.itemPrice);
                map[r.prodID].commons.add(r.commonID);
                map[r.prodID].traders.add(r.traderID);
            }
            const rows = Object.entries(map).sort((a, b) => b[1].boxes - a[1].boxes);
            const wrap = document.getElementById('products-table-wrap');
            if (!rows.length) {
                wrap.innerHTML = '<div class="no-data">لا توجد بيانات</div>';
                return;
            }
            wrap.innerHTML =
                '<table><thead><tr><th>#</th><th>المنتج</th><th>الصناديق</th><th>إجمالي القيمة</th><th>متوسط السعر</th><th>أعلى سعر</th><th>أدنى سعر</th><th>المشتركون</th><th>التجار</th></tr></thead><tbody>' +
                rows.map(([id, v], i) => {
                    const a = avg(v.prices),
                        mx = Math.max(...v.prices),
                        mn = Math.min(...v.prices);
                    return '<tr>' +
                        '<td>' + (i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : i + 1) + '</td>' +
                        '<td><strong>' + productName(id) + '</strong></td>' +
                        '<td><span class="badge badge-blue">' + fmt(v.boxes) + '</span></td>' +
                        '<td><strong>' + fmtNIS(v.total) + '</strong></td>' +
                        '<td>' + fmtNIS(a) + '</td>' +
                        '<td><span class="badge badge-green">' + fmtNIS(mx) + '</span></td>' +
                        '<td><span class="badge badge-red">' + fmtNIS(mn) + '</span></td>' +
                        '<td>' + v.commons.size + '</td>' +
                        '<td>' + v.traders.size + '</td>' +
                        '</tr>';
                }).join('') +
                '</tbody></table>';
            makeBarChart('chart-products-boxes', rows.slice(0, 15).map(([id]) => productName(id)), rows.slice(0, 15).map(([,
                v
            ]) => v.boxes), 'الصناديق', '#059669');
        }

        // ============================================================
        //  PRODUCT DETAIL — تفاصيل منتج محدد
        //
        //  يفلتر DB.enriched بالـ prodID + نطاق التاريخ
        //  يعرض منحنى السعر الشهري + أفضل المشتركين حجماً وسعراً
        // ============================================================
        function renderProductDetail() {
            const pidRaw = document.getElementById('pd-product-id').value;
            const pname = document.getElementById('pd-product-input').value.trim();
            const from = document.getElementById('pd-date-from').value;
            const to = document.getElementById('pd-date-to').value;
            const wrap = document.getElementById('product-detail-content');
            if (!pidRaw && !pname) {
                wrap.innerHTML = '<div class="sec"><div class="no-data">يرجى اختيار منتج</div></div>';
                return;
            }
            let id = +pidRaw;
            if (!id && pname) {
                const p = DB.products.find(x => x.name === pname);
                if (p) id = p.id;
            }
            if (!id) {
                wrap.innerHTML = '<div class="sec"><div class="no-data">لم يتم العثور على المنتج</div></div>';
                return;
            }
            let data = DB.enriched.filter(r => +r.prodID === id);
            if (from) data = data.filter(r => r.date >= from);
            if (to) data = data.filter(r => r.date <= to);
            if (!data.length) {
                wrap.innerHTML = '<div class="sec"><div class="no-data">لا توجد بيانات</div></div>';
                return;
            }

            const totalBoxes = data.reduce((s, r) => s + r.boxes, 0);
            const totalVal = data.reduce((s, r) => s + r.total, 0);
            const allPrices = data.map(r => r.itemPrice);
            const avgPrice = avg(allPrices);

            const farmerMap = {},
                monthMap = {};
            for (const r of data) {
                if (!farmerMap[r.commonID]) farmerMap[r.commonID] = {
                    boxes: 0,
                    total: 0,
                    prices: []
                };
                farmerMap[r.commonID].boxes += r.boxes;
                farmerMap[r.commonID].total += r.total;
                farmerMap[r.commonID].prices.push(r.itemPrice);
                const m = r.date.slice(0, 7);
                if (!monthMap[m]) monthMap[m] = {
                    total: 0,
                    boxes: 0,
                    prices: []
                };
                monthMap[m].total += r.total;
                monthMap[m].boxes += r.boxes;
                monthMap[m].prices.push(r.itemPrice);
            }
            const byBoxes = Object.entries(farmerMap).sort((a, b) => b[1].boxes - a[1].boxes).slice(0, 15);
            const byAvgPrice = Object.entries(farmerMap).sort((a, b) => avg(b[1].prices) - avg(a[1].prices)).slice(0, 15);
            const months = Object.keys(monthMap).sort();

            wrap.innerHTML =
                '<div class="kpi-row">' +
                '<div class="kpi-card"><div class="val">' + fmt(totalBoxes) +
                '</div><div class="lbl">📦 الصناديق</div></div>' +
                '<div class="kpi-card green"><div class="val">' + fmtNIS(totalVal) +
                '</div><div class="lbl">💰 إجمالي القيمة</div></div>' +
                '<div class="kpi-card orange"><div class="val">' + fmtNIS(avgPrice) +
                '</div><div class="lbl">📊 متوسط السعر</div></div>' +
                '<div class="kpi-card red"><div class="val">' + fmtNIS(Math.max(...allPrices)) +
                '</div><div class="lbl">⬆️ أعلى سعر</div></div>' +
                '<div class="kpi-card purple"><div class="val">' + fmtNIS(Math.min(...allPrices)) +
                '</div><div class="lbl">⬇️ أدنى سعر</div></div>' +
                '<div class="kpi-card"><div class="val">' + fmt(Object.keys(farmerMap).length) +
                '</div><div class="lbl">👥 المشتركون</div></div>' +
                '</div>' +
                '<div class="sec"><h2>📈 متوسط السعر الشهري</h2><div class="chart-wrap"><canvas id="chart-pd-price" style="max-height:300px;"></canvas></div></div>' +
                '<div class="chart-grid">' +
                '<div class="sec"><h2>🥇 أفضل المشتركين — حجم الصناديق</h2><div class="chart-wrap"><canvas id="chart-pd-boxes"></canvas></div></div>' +
                '<div class="sec"><h2>💰 أفضل المشتركين — متوسط السعر</h2><div class="chart-wrap"><canvas id="chart-pd-avg-price"></canvas></div></div>' +
                '</div>' +
                '<div class="sec"><h2>👥 تفصيل المشتركين لهذا المنتج</h2><div class="tbl-wrap"><table><thead><tr><th>#</th><th>المشترك</th><th>الصناديق</th><th>الإجمالي</th><th>متوسط السعر</th><th>أعلى سعر</th><th>أدنى سعر</th></tr></thead><tbody>' +
                byBoxes.map(([fid, v], i) => '<tr>' +
                    '<td>' + (i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : i + 1) + '</td>' +
                    '<td>' + commonName(fid) + '</td>' +
                    '<td><span class="badge badge-blue">' + fmt(v.boxes) + '</span></td>' +
                    '<td>' + fmtNIS(v.total) + '</td>' +
                    '<td><strong>' + fmtNIS(avg(v.prices)) + '</strong></td>' +
                    '<td><span class="badge badge-green">' + fmtNIS(Math.max(...v.prices)) + '</span></td>' +
                    '<td><span class="badge badge-red">' + fmtNIS(Math.min(...v.prices)) + '</span></td>' +
                    '</tr>').join('') +
                '</tbody></table></div></div>';

            setTimeout(() => {
                makeLineChart('chart-pd-price', months, [{
                    label: 'متوسط السعر ₪',
                    data: months.map(m => avg(monthMap[m].prices)),
                    borderColor: '#d97706',
                    backgroundColor: 'rgba(217,119,6,0.1)',
                    fill: true,
                    tension: 0.4
                }]);
                makeBarChart('chart-pd-boxes', byBoxes.map(([fid]) => commonName(fid)), byBoxes.map(([, v]) => v
                    .boxes), 'الصناديق', '#1a3a5c');
                makeBarChart('chart-pd-avg-price', byAvgPrice.map(([fid]) => commonName(fid)), byAvgPrice.map(([,
                    v
                ]) => avg(v.prices)), 'متوسط السعر ₪', '#2d6a4f');
            }, 100);
        }

        // ============================================================
        //  PRICE TRENDS — اتجاهات أسعار منتج محدد
        //
        //  يجمع الأسعار في سطل حسب التجميع المختار (يومي/أسبوعي/شهري/سنوي)
        //  يرسم منحنى ثلاثي: متوسط سعر / أعلى سعر / أدنى سعر
        // ============================================================
        function renderPriceTrend() {
            const pidRaw = document.getElementById('pt-product-id').value;
            const pname = document.getElementById('pt-product-input').value.trim();
            const from = document.getElementById('pt-date-from').value;
            const to = document.getElementById('pt-date-to').value;
            const group = document.getElementById('pt-groupby').value;
            if (!pidRaw && !pname) {
                alert('يرجى اختيار منتج');
                return;
            }
            let id = +pidRaw;
            if (!id && pname) {
                const p = DB.products.find(x => x.name === pname);
                if (p) id = p.id;
            }
            if (!id) {
                alert('لم يتم العثور على المنتج');
                return;
            }
            let data = DB.enriched.filter(r => +r.prodID === id);
            if (from) data = data.filter(r => r.date >= from);
            if (to) data = data.filter(r => r.date <= to);
            if (!data.length) {
                document.getElementById('price-trend-stats').innerHTML = '<div class="no-data">لا توجد بيانات</div>';
                return;
            }

            function getKey(d) {
                if (group === 'day') return d;
                if (group === 'week') {
                    const dt = new Date(d),
                        day = dt.getDay();
                    const mon = new Date(dt);
                    mon.setDate(dt.getDate() - ((day + 1) % 7));
                    return mon.toISOString().slice(0, 10);
                }
                if (group === 'year') return d.slice(0, 4);
                return d.slice(0, 7);
            }
            const buckets = {};
            for (const r of data) {
                const k = getKey(r.date);
                if (!buckets[k]) buckets[k] = {
                    prices: [],
                    boxes: 0,
                    total: 0
                };
                buckets[k].prices.push(r.itemPrice);
                buckets[k].boxes += r.boxes;
                buckets[k].total += r.total;
            }
            const keys = Object.keys(buckets).sort();
            destroyChart('chart-price-trend');
            const ctx = document.getElementById('chart-price-trend');
            if (ctx) charts['chart-price-trend'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: keys,
                    datasets: [{
                            label: 'متوسط السعر',
                            data: keys.map(k => avg(buckets[k].prices)),
                            borderColor: '#d97706',
                            backgroundColor: 'rgba(217,119,6,0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'أعلى سعر',
                            data: keys.map(k => Math.max(...buckets[k].prices)),
                            borderColor: '#dc2626',
                            borderDash: [5, 5],
                            tension: 0.4,
                            fill: false
                        },
                        {
                            label: 'أدنى سعر',
                            data: keys.map(k => Math.min(...buckets[k].prices)),
                            borderColor: '#2d6a4f',
                            borderDash: [5, 5],
                            tension: 0.4,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'السعر ₪'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });

            const allPrices = data.map(r => r.itemPrice);
            const overallAvg = avg(allPrices);
            document.getElementById('price-trend-stats').innerHTML =
                '<h2>📊 ملخص إحصائي — ' + productName(id) + '</h2>' +
                '<div class="kpi-row" style="margin-top:16px;">' +
                '<div class="kpi-card orange"><div class="val">' + fmtNIS(overallAvg) +
                '</div><div class="lbl">متوسط السعر الإجمالي</div></div>' +
                '<div class="kpi-card red"><div class="val">' + fmtNIS(Math.max(...allPrices)) +
                '</div><div class="lbl">أعلى سعر</div></div>' +
                '<div class="kpi-card green"><div class="val">' + fmtNIS(Math.min(...allPrices)) +
                '</div><div class="lbl">أدنى سعر</div></div>' +
                '<div class="kpi-card"><div class="val">' + fmtNIS(Math.max(...allPrices) - Math.min(...allPrices)) +
                '</div><div class="lbl">مدى التذبذب</div></div>' +
                '<div class="kpi-card purple"><div class="val">' + fmt(data.reduce((s, r) => s + r.boxes, 0)) +
                '</div><div class="lbl">إجمالي الصناديق</div></div>' +
                '<div class="kpi-card green"><div class="val">' + fmt(keys.length) +
                '</div><div class="lbl">فترات التحليل</div></div>' +
                '</div>';
        }

        // ============================================================
        //  FINANCIAL — التحليل المالي الشامل
        //
        //  يحسب إجمالي: قيمة / عمولة / بلدية / نقل / صافي
        //  يجمع شهرياً لرسم bar chart متعدد المحاور
        //  يعرض أفضل 30 يوم أداءً في جدول
        // ============================================================
        function renderFinancial() {
            const from = document.getElementById('fin-date-from').value;
            const to = document.getElementById('fin-date-to').value;
            const data = filterByDate(DB.enriched, from, to);

            const totalVal = data.reduce((s, r) => s + r.total, 0);
            const totalComm = data.reduce((s, r) => s + r.commissionAmt, 0);
            const totalMun = data.reduce((s, r) => s + r.munAmt, 0);
            const totalTrans = data.reduce((s, r) => s + r.transAmt, 0);
            const totalBoxes = data.reduce((s, r) => s + r.boxes, 0);
            const netFarmers = totalVal - totalComm - totalMun - totalTrans;

            document.getElementById('fin-kpi-row').innerHTML =
                '<div class="kpi-card green"><div class="val">' + fmtNIS(totalVal) +
                '</div><div class="lbl">💰 إجمالي قيمة البضاعة</div></div>' +
                '<div class="kpi-card red"><div class="val">' + fmtNIS(totalComm) +
                '</div><div class="lbl">📊 إجمالي عمولاتنا</div></div>' +
                '<div class="kpi-card orange"><div class="val">' + fmtNIS(totalMun) +
                '</div><div class="lbl">🏛️ رسوم البلدية</div></div>' +
                '<div class="kpi-card purple"><div class="val">' + fmtNIS(totalTrans) +
                '</div><div class="lbl">🚚 رسوم النقل</div></div>' +
                '<div class="kpi-card"><div class="val">' + fmtNIS(netFarmers) +
                '</div><div class="lbl">✅ صافي المشتركين</div></div>' +
                '<div class="kpi-card green"><div class="val">' + fmt(totalBoxes) +
                '</div><div class="lbl">📦 إجمالي الصناديق</div></div>' +
                '<div class="kpi-card orange"><div class="val">' + fmtNIS(totalBoxes > 0 ? totalVal / totalBoxes : 0) +
                '</div><div class="lbl">📊 متوسط سعر الصندوق</div></div>' +
                '<div class="kpi-card red"><div class="val">' + (totalVal > 0 ? (totalComm / totalVal * 100) : 0).toFixed(
                    1) + '%</div><div class="lbl">نسبة العمولة</div></div>';

            const monthMap = {};
            for (const r of data) {
                if (!r.date) continue;
                const m = r.date.slice(0, 7);
                if (!monthMap[m]) monthMap[m] = {
                    val: 0,
                    comm: 0,
                    mun: 0,
                    trans: 0
                };
                monthMap[m].val += r.total;
                monthMap[m].comm += r.commissionAmt;
                monthMap[m].mun += r.munAmt;
                monthMap[m].trans += r.transAmt;
            }
            const months = Object.keys(monthMap).sort().slice(-18);
            destroyChart('chart-fin-monthly');
            const ctx1 = document.getElementById('chart-fin-monthly');
            if (ctx1) charts['chart-fin-monthly'] = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                            label: 'إجمالي القيمة',
                            data: months.map(m => monthMap[m]?.val || 0),
                            backgroundColor: 'rgba(26,58,92,0.8)',
                            borderRadius: 4
                        },
                        {
                            label: 'العمولات',
                            data: months.map(m => monthMap[m]?.comm || 0),
                            backgroundColor: 'rgba(220,38,38,0.8)',
                            borderRadius: 4
                        },
                        {
                            label: 'البلدية',
                            data: months.map(m => monthMap[m]?.mun || 0),
                            backgroundColor: 'rgba(217,119,6,0.8)',
                            borderRadius: 4
                        },
                        {
                            label: 'النقل',
                            data: months.map(m => monthMap[m]?.trans || 0),
                            backgroundColor: 'rgba(124,58,237,0.8)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            makePieChart('chart-fin-breakdown',
                ['عمولاتنا', 'رسوم البلدية', 'رسوم النقل', 'صافي المشتركين'],
                [totalComm, totalMun, totalTrans, Math.max(0, netFarmers)],
                ['#dc2626', '#d97706', '#7c3aed', '#2d6a4f']);

            // Best days
            const dayMap = {};
            for (const r of data) {
                if (!r.date) continue;
                if (!dayMap[r.date]) dayMap[r.date] = {
                    val: 0,
                    boxes: 0,
                    comm: 0,
                    bills: new Set()
                };
                dayMap[r.date].val += r.total;
                dayMap[r.date].boxes += r.boxes;
                dayMap[r.date].comm += r.commissionAmt;
                dayMap[r.date].bills.add(r.billID);
            }
            const topDays = Object.entries(dayMap).sort((a, b) => b[1].val - a[1].val).slice(0, 30);
            document.getElementById('fin-daily-wrap').innerHTML =
                '<table><thead><tr><th>#</th><th>التاريخ</th><th>إجمالي القيمة</th><th>الصناديق</th><th>العمولات</th><th>الفواتير</th></tr></thead><tbody>' +
                topDays.map(([d, v], i) => '<tr>' +
                    '<td>' + (i + 1) + '</td><td>' + d + '</td>' +
                    '<td><strong>' + fmtNIS(v.val) + '</strong></td>' +
                    '<td><span class="badge badge-blue">' + fmt(v.boxes) + '</span></td>' +
                    '<td><span class="badge badge-red">' + fmtNIS(v.comm) + '</span></td>' +
                    '<td>' + v.bills.size + '</td>' +
                    '</tr>').join('') +
                '</tbody></table>';
        }

        // ============================================================
        //  ADVANCED STATS — إحصائيات متقدمة وأرقام قياسية
        //
        //  1. يجمع DB.enriched يومياً (dayMap: date → {boxes,value,bills})
        //  2. يحسب يوم الذروة وأهدأ يوم
        //  3. يحسب توزيع النشاط على أيام الأسبوع
        //  4. يحسب الموسمية الشهرية (1-12)
        //  5. يحسب توزيع قيم الفواتير في نطاقات
        //  6. يحسب تذبذب أسعار المنتجات (max-min price)
        //  7. يعرض جداول أفضل/أسوأ 20 يوم
        // ============================================================
        function renderAdvancedStats() {
            const from = document.getElementById('adv-date-from').value;
            const to = document.getElementById('adv-date-to').value;
            const data = filterByDate(DB.enriched, from, to);

            if (!data.length) {
                document.getElementById('adv-records-row').innerHTML = '<div class="no-data">لا توجد بيانات</div>';
                return;
            }

            // === GROUP BY DATE ===
            const dayMap = {};
            for (const r of data) {
                if (!r.date) continue;
                if (!dayMap[r.date]) dayMap[r.date] = {
                    boxes: 0,
                    value: 0,
                    bills: new Set(),
                    comm: 0
                };
                dayMap[r.date].boxes += r.boxes;
                dayMap[r.date].value += r.total;
                dayMap[r.date].bills.add(r.billID);
                dayMap[r.date].comm += r.commissionAmt;
            }
            const dayEntries = Object.entries(dayMap);
            const activeDays = dayEntries.length;
            const totalBoxes = data.reduce((s, r) => s + r.boxes, 0);
            const totalValue = data.reduce((s, r) => s + r.total, 0);

            const peakBoxDay = dayEntries.reduce((a, b) => b[1].boxes > a[1].boxes ? b : a);
            const peakValDay = dayEntries.reduce((a, b) => b[1].value > a[1].value ? b : a);
            const peakBillDay = dayEntries.reduce((a, b) => b[1].bills.size > a[1].bills.size ? b : a);
            const quietDay = dayEntries.reduce((a, b) => b[1].value < a[1].value ? b : a);
            const avgDailyBoxes = totalBoxes / activeDays;
            const avgDailyValue = totalValue / activeDays;

            document.getElementById('adv-records-row').innerHTML =
                '<div class="kpi-card orange"><div class="val">' + peakBoxDay[0] +
                '</div><div class="lbl">🏆 يوم الذروة — الصناديق<br><small>' + fmt(peakBoxDay[1].boxes) +
                ' صندوق</small></div></div>' +
                '<div class="kpi-card green"><div class="val">' + peakValDay[0] +
                '</div><div class="lbl">💰 يوم الذروة — القيمة<br><small>' + fmtNIS(peakValDay[1].value) +
                '</small></div></div>' +
                '<div class="kpi-card purple"><div class="val">' + peakBillDay[0] +
                '</div><div class="lbl">📋 يوم الذروة — الفواتير<br><small>' + peakBillDay[1].bills.size +
                ' فاتورة</small></div></div>' +
                '<div class="kpi-card red"><div class="val">' + quietDay[0] +
                '</div><div class="lbl">🐤 أهدأ يوم<br><small>' + fmtNIS(quietDay[1].value) + '</small></div></div>' +
                '<div class="kpi-card"><div class="val">' + fmt(activeDays) +
                '</div><div class="lbl">📅 أيام النشاط</div></div>' +
                '<div class="kpi-card green"><div class="val">' + fmt(Math.round(avgDailyBoxes)) +
                '</div><div class="lbl">📦 متوسط صناديق/يوم</div></div>' +
                '<div class="kpi-card orange"><div class="val">' + fmtNIS(avgDailyValue) +
                '</div><div class="lbl">💵 متوسط قيمة/يوم</div></div>';

            // === DAY OF WEEK ===
            const dowNames = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
            const dowBoxes = [0, 0, 0, 0, 0, 0, 0];
            const dowValue = [0, 0, 0, 0, 0, 0, 0];
            const dowDays = [0, 0, 0, 0, 0, 0, 0];
            for (const [d, v] of dayEntries) {
                const dow = new Date(d).getDay();
                dowBoxes[dow] += v.boxes;
                dowValue[dow] += v.value;
                dowDays[dow]++;
            }
            makeBarChart('chart-adv-weekday', dowNames, dowBoxes, 'الصناديق', '#1a3a5c');

            // === MONTHLY SEASONALITY ===
            const monthNames = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر',
                'نوفمبر', 'ديسمبر'
            ];
            const monthBoxes = new Array(12).fill(0);
            for (const [d, v] of dayEntries) {
                const mo = new Date(d).getMonth();
                monthBoxes[mo] += v.boxes;
            }
            makeBarChart('chart-adv-seasonal', monthNames, monthBoxes, 'الصناديق', '#2d6a4f');

            // === PER-BILL STATS ===
            const billMap = {};
            for (const r of data) {
                if (!billMap[r.billID]) billMap[r.billID] = {
                    total: 0,
                    boxes: 0,
                    date: r.date
                };
                billMap[r.billID].total += r.total;
                billMap[r.billID].boxes += r.boxes;
            }
            const billVals = Object.values(billMap);
            const billTotals = billVals.map(b => b.total);
            const maxBill = billTotals.reduce((a, b) => b > a ? b : a, -Infinity);
            const minBill = billTotals.reduce((a, b) => b < a ? b : a, Infinity);
            const avgBill = billTotals.reduce((s, v) => s + v, 0) / billTotals.length;
            const maxBillEntry = billVals.find(b => b.total === maxBill);
            const minBillEntry = billVals.find(b => b.total === minBill);

            document.getElementById('adv-bill-kpi').innerHTML =
                '<div class="kpi-card"><div class="val">' + fmt(billVals.length) +
                '</div><div class="lbl">📋 إجمالي الفواتير</div></div>' +
                '<div class="kpi-card green"><div class="val">' + fmtNIS(maxBill) +
                '</div><div class="lbl">🏆 أعلى فاتورة قيمةً<br><small>' + (maxBillEntry?.date || '') +
                '</small></div></div>' +
                '<div class="kpi-card red"><div class="val">' + fmtNIS(minBill) +
                '</div><div class="lbl">⬇️ أدنى فاتورة قيمةً<br><small>' + (minBillEntry?.date || '') +
                '</small></div></div>' +
                '<div class="kpi-card orange"><div class="val">' + fmtNIS(avgBill) +
                '</div><div class="lbl">📊 متوسط قيمة الفاتورة</div></div>' +
                '<div class="kpi-card purple"><div class="val">' + fmt(Math.round(totalBoxes / billVals.length)) +
                '</div><div class="lbl">📦 متوسط صناديق/فاتورة</div></div>' +
                '<div class="kpi-card"><div class="val">' + fmt(Math.round(billVals.length / activeDays * 10) / 10) +
                '</div><div class="lbl">📋 متوسط فواتير/يوم</div></div>';

            // Bill value distribution
            const ranges = [0, 500, 1000, 2000, 5000, 10000, 20000, Infinity];
            const rangeLabels = ['< 500', '500–1k', '1k–2k', '2k–5k', '5k–10k', '10k–20k', '> 20k'];
            const rangeCounts = new Array(7).fill(0);
            for (const t of billTotals) {
                for (let i = 0; i < ranges.length - 1; i++) {
                    if (t >= ranges[i] && t < ranges[i + 1]) {
                        rangeCounts[i]++;
                        break;
                    }
                }
            }
            makeBarChart('chart-adv-bill-dist', rangeLabels, rangeCounts, 'عدد الفواتير', '#0891b2');

            // === PRODUCT PRICE VOLATILITY ===
            const prodPrices = {};
            for (const r of data) {
                if (!prodPrices[r.prodID]) prodPrices[r.prodID] = [];
                prodPrices[r.prodID].push(r.itemPrice);
            }
            const prodVol = Object.entries(prodPrices)
                .filter(([, arr]) => arr.length >= 3)
                .map(([id, arr]) => {
                    let mx = arr[0],
                        mn = arr[0];
                    for (const v of arr) {
                        if (v > mx) mx = v;
                        if (v < mn) mn = v;
                    }
                    return {
                        id,
                        range: mx - mn
                    };
                })
                .sort((a, b) => b.range - a.range)
                .slice(0, 12);
            makeBarChart('chart-adv-price-range', prodVol.map(p => productName(p.id)), prodVol.map(p => p.range),
                'نطاق السعر ₪', '#ea580c');

            // === TOP DAYS TABLE ===
            const sortedDays = dayEntries.slice().sort((a, b) => b[1].value - a[1].value);
            const buildDayTable = (rows) =>
                '<table><thead><tr><th>#</th><th>التاريخ</th><th>الصناديق</th><th>إجمالي القيمة</th><th>العمولات</th><th>الفواتير</th><th>متوسط قيمة الفاتورة</th></tr></thead><tbody>' +
                rows.map(([d, v], i) => '<tr>' +
                    '<td>' + (i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : i + 1) + '</td>' +
                    '<td><strong>' + d + '</strong></td>' +
                    '<td><span class="badge badge-blue">' + fmt(v.boxes) + '</span></td>' +
                    '<td><strong>' + fmtNIS(v.value) + '</strong></td>' +
                    '<td><span class="badge badge-red">' + fmtNIS(v.comm) + '</span></td>' +
                    '<td>' + v.bills.size + '</td>' +
                    '<td>' + fmtNIS(v.bills.size > 0 ? v.value / v.bills.size : 0) + '</td>' +
                    '</tr>').join('') +
                '</tbody></table>';

            document.getElementById('adv-top-days-wrap').innerHTML = buildDayTable(sortedDays.slice(0, 20));
            document.getElementById('adv-slow-days-wrap').innerHTML = buildDayTable(sortedDays.slice(-20).reverse());
        }
    </script>
</body>

</html>
