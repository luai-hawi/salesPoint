<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
# salesPoint

## Accounts, reports and finance

- Only the administrator creates program employee accounts (additional paid access).
  Owners can adjust permissions on those existing accounts. Attendance/payroll
  employee profiles are separate and never grant POS/program access.
- Each program account has one active session. A new password login replaces the
  previous device, including remembered access. Do not share program credentials.
  Session enforcement runs on connected requests. Offline features remain available;
  a disconnected device receives the logout when it reconnects.
- Reports restore sales, customers, suppliers, purchases, employee work/payments,
  expenses and balances alongside the accountant reports. Select dates and filters,
  then generate a readable report or export CSV. Close returns to the reports page;
  reports opened in a popup close that popup.
- The financial dashboard includes inventory value, capital entries, daily trends,
  period comparison, customer/supplier balances, product/supplier rankings,
  payment/expense/salary breakdowns, returns, damaged stock and team sales.
  Inventory and account balances are **current** values, not historical snapshots.
  Supplier settlement and stock funding are not counted again as profit expenses.
- Product images: drag inside the selection to move the crop, drag its bottom-right
  corner to resize, or drag outside to pan the image. Touch dragging is supported.
  Quick stock works in table/card views, defaults to the product cost, and retains
  an intake identifier on failed requests to avoid duplicate stock on retries.
- POS kiosk mode hides the sidebar and keeps the POS full width. Use the floating
  **Full screen** / **Exit kiosk** buttons (team-locked employees cannot exit).
- Day close without financial access: grant an employee the **Close the day**
  permission. They enter only the counted cash (blind count); expected cash,
  variance and the financial dashboard remain visible only to the owner.
- Barcode labels support a custom size (width 15–200 mm, height 10–200 mm); the
  last size is remembered on the device.
- The product edit page has one **Add stock** form. It creates or merges a batch
  exactly like the former Add batch form; existing batches can still be edited.
- Charts: the financial dashboard shows KPI cards with change vs the previous
  period, sales/profit/returns trends, a "where did the money go" breakdown,
  cash in vs out, costs, top products and team sales. The admin dashboard shows
  sales and gross profit for every shop (returns and damaged goods excluded,
  same formula as the shop's own dashboard); each shop page shows its own
  month/lifetime profit, a 30-day chart and top products.
- Product reports (Reports → Product reports): sales summary, best sellers, most
  profitable, slow movers, unsold, movement history, sales by category, low
  stock & reorder, stock value, purchases, damaged and returned products. Each
  can be filtered by period, product name/barcode and category, printed or
  exported to CSV. The product edit page links to the product's sales and
  movement reports.
- Scheduled maintenance (optional): add one cron entry on the server,
  `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`.
  It prunes old offline-sync keys, auto-closes forgotten attendance check-ins and
  purges old kitchen tickets. Everything else works without it.

### توضيح الاستخدام

مسؤول النظام وحده ينشئ حسابات موظفي البرنامج الإضافية المدفوعة. صاحب المتجر
يضبط صلاحيات الحسابات الموجودة، ويستطيع إنشاء ملفات موظفي الحضور والرواتب
بشكل منفصل؛ هذه الملفات لا تسمح باستخدام نقطة البيع أو البرنامج.
كل حساب برنامج يعمل على جهاز واحد في الوقت نفسه؛ الدخول الجديد يلغي دخول
الجهاز السابق. لا تشارك بيانات الدخول.
يبقى العمل دون اتصال متاحاً؛ الجهاز المنقطع لا يتلقى إنهاء الجلسة إلا عند عودة
الاتصال، ويطبق الخادم شرط الجلسة الواحدة على كل طلب متصل.

في التقارير اختر النوع والتاريخ والعميل أو المورد أو الموظف عند الحاجة، ثم
اعرض التقرير أو صدّره. زر الإغلاق يعيدك إلى التقارير. تحديد المنتجات متاح
في عرض البطاقات والجدول لتنفيذ التفعيل أو الإيقاف الجماعي.
أرصدة العملاء والموردين وقيمة المخزون في لوحة المالية تمثل الوضع الحالي،
وحركة الصندوق هي المرجع للنقد الفعلي، وليست مقارنة رأس المال بالمخزون.
لتعديل الصورة اسحب داخل إطار القص لتحريكه أو زاويته السفلية اليمنى لتغيير حجمه؛
السحب خارج الإطار يحرك الصورة. زر إضافة المخزون يعمل في الجدول والبطاقات
ويملأ تكلفة المنتج الحالية، ويمكنك تعديلها قبل الحفظ.
وضع الكشك في نقطة البيع يخفي القائمة الجانبية، ويظهر زرا "ملء الشاشة" و"الخروج من
وضع الكشك" في زاوية الشاشة.
لإقفال اليوم دون كشف الأرقام المالية امنح الموظف صلاحية "إقفال اليوم"؛ يُدخل النقد
المعدود فقط، ويرى صاحب المتجر وحده النقد المتوقع والفرق.
في طباعة الباركود اختر "مقاس مخصص" وأدخل العرض والارتفاع بالمليمتر.
صفحة تعديل المنتج فيها نموذج واحد "إضافة مخزون" ينشئ دفعة أو يدمجها كما كان نموذج
إضافة الدفعة، وقائمة الدفعات الحالية باقية للتعديل والحذف.
لوحة المالية تعرض بطاقات مؤشرات مع نسبة التغير عن الفترة السابقة ورسوماً بيانية
للمبيعات والأرباح والمرتجعات، وأين ذهبت الأموال، والداخل والخارج، والتكاليف، وأفضل
المنتجات، ومبيعات الموظفين. لوحة الإدارة تعرض مبيعات وأرباح كل متجر بنفس معادلة
لوحة المالية الخاصة به (دون المرتجعات والتالف)، وصفحة كل متجر تعرض أرباحه ورسماً
لآخر 30 يوماً وأفضل منتجاته.
تقارير المنتجات (التقارير ← تقارير المنتجات): ملخص المبيعات، الأكثر مبيعاً، الأكثر
ربحاً، بطيئة الحركة، غير المباعة، سجل الحركة، المبيعات حسب التصنيف، المخزون المنخفض
وإعادة الطلب، قيمة المخزون، المشتريات، التالف، والمرتجع؛ مع فلترة بالفترة والمنتج
والتصنيف، وطباعة أو تصدير CSV.
المهام المجدولة (اختيارية): أضف سطر Cron واحداً على الخادم يشغّل
`php artisan schedule:run` كل دقيقة؛ ينظف مفاتيح المزامنة القديمة، ويغلق تسجيلات
الحضور المنسية، ويحذف تذاكر المطبخ القديمة. النظام يعمل بدونه.
