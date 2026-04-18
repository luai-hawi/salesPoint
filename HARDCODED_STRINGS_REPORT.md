# Comprehensive Hardcoded Text Strings Report

## resources/views Directory

**Report Generated:** April 18, 2026  
**Status:** Complete Analysis  
**Total Files Affected:** 20+  
**Total Hardcoded Strings:** 70+

---

## 📋 Executive Summary

This report documents all hardcoded text strings found in blade templates that should be wrapped with Laravel's translation helper `__()`. These strings affect user experience across both English and Arabic interfaces.

---

## 1. PLACEHOLDER ATTRIBUTES

### Hardcoded placeholder="text" (Not using \_\_(translations))

#### [dashboard.blade.php](dashboard.blade.php#L472)

- **Line 472**: `placeholder="0.00"`
- **Line 622**: `placeholder="0"` (step="0.01")

#### [ss.blade.php](ss.blade.php#L129)

- **Line 129**: `placeholder="0.00"` (payment_amount input)

#### [products/create.blade.php](products/create.blade.php#L804-L810)

- **Line 804**: `placeholder="e.g., S, M, L"` (variant name)
- **Line 810**: `placeholder="0"` (quantity, min="0")

#### [products/edit.blade.php](products/edit.blade.php#L1415-L1421)

- **Line 1415**: `placeholder="e.g., XXL"` (variant name)
- **Line 1421**: `placeholder="0"` (quantity, min="0")

#### [purchase-bills/create.blade.php](purchase-bills/create.blade.php#L727)

- **Line 727**: `placeholder="Enter barcode"` (barcode-input)

#### [purchase-bills/edit.blade.php](purchase-bills/edit.blade.php#L727)

- **Line 727**: `placeholder="Enter barcode"` (barcode-input)

#### [bills/show.blade.php](bills/show.blade.php#L178)

- **Line 178**: `placeholder="0"` (step="0.01")

#### [admin/shop-owners/create.blade.php](admin/shop-owners/create.blade.php#L40-L172)

- **Line 40**: `placeholder="Enter shop name"`
- **Line 52**: `placeholder="Enter owner name"`
- **Line 89**: `placeholder="Enter subscription cost"`
- **Line 156**: `placeholder="Enter number of days"`
- **Line 172**: `placeholder="Enter image limit"`

#### [admin/shop-owners/edit.blade.php](admin/shop-owners/edit.blade.php#L114-L186)

- **Line 114**: `placeholder="1000"` (image_limit)
- **Line 186**: `placeholder="7"` (temp_period_days)

#### [islam/index.blade.php](islam/index.blade.php#L736-L784)

- **Line 736**: `placeholder="اسم العميل..."` (Arabic: Customer name input)
- **Line 739**: `placeholder="ملاحظة (اختياري)..."` (Arabic: Optional note)
- **Line 752**: `placeholder="أدخل سعر يدوياً..."` (Arabic: Enter price manually)
- **Line 784**: `placeholder="أدخل الرقم..."` (Arabic: Enter number)

---

## 2. ALT ATTRIBUTES

### Hardcoded alt="text" (Missing translations)

#### [portfolio.blade.php](portfolio.blade.php#L800-L930)

Multiple carousel items with hardcoded alt text:

- **Line 800**: `alt="CNN Architecture"`
- **Line 806**: `alt="Training Results"`
- **Line 812**: `alt="Live Demo"`
- **Line 819**: `alt="CNN Architecture"` (duplicate)
- **Line 825**: `alt="Training Results"` (duplicate)
- **Line 831**: `alt="Live Demo"` (duplicate)
- **Line 899**: `alt="CBIR Interface"`
- **Line 905**: `alt="Color Analysis"`
- **Line 911**: `alt="Search Results"`
- **Line 918**: `alt="CBIR Interface"` (duplicate)
- **Line 924**: `alt="Color Analysis"` (duplicate)
- **Line 930**: `alt="Search Results"` (duplicate)

#### [products/index.blade.php](products/index.blade.php#L914)

- **Line 914**: `alt="${item.name}"` (JavaScript template in blade)

#### [products/barcode-search.blade.php](products/barcode-search.blade.php#L446)

- **Line 446**: `alt="${product.name}"` (JavaScript template in blade)

#### [products/partials/table.blade.php](products/partials/table.blade.php#L18)

- **Line 18**: `alt="{{ $product->name }}"` (This one is OK - dynamic)

#### [components/application-logo.blade.php](components/application-logo.blade.php#L8)

- **Line 8**: `alt=""` (Empty - consider adding descriptive text)

---

## 3. ARIA-LABEL ATTRIBUTES

### Hardcoded aria-label="text"

#### [vendor/pagination/custom-light.blade.php](vendor/pagination/custom-light.blade.php#L2)

- **Line 2**: `aria-label="Pagination Navigation"`

#### [vendor/pagination/tailwind.blade.php](vendor/pagination/tailwind.blade.php#L2)

- **Line 2**: `aria-label="{{ __('Pagination Navigation') }}"` ✓ (Already translated)

#### [vendor/pagination/simple-tailwind.blade.php](vendor/pagination/simple-tailwind.blade.php#L2)

- **Line 2**: `aria-label="{!! __('Pagination Navigation') !!}"` ✓ (Already translated)

---

## 4. BUTTON TEXT (Hardcoded)

#### [products/partials/table.blade.php](products/partials/table.blade.php#L42)

- **Line 42**: `<button type="submit" class="text-red-500 hover:text-red-700">Delete</button>`

#### [purchase-bills/create.blade.php](purchase-bills/create.blade.php#L728)

- **Line 728**: `<button type="button" class="add-barcode-btn">Add</button>`

#### [purchase-bills/edit.blade.php](purchase-bills/edit.blade.php#L728)

- **Line 728**: `<button type="button" class="add-barcode-btn">Add</button>`

#### [products/create.blade.php](products/create.blade.php#L1117)

- **Line 1117**: `<button onclick="window.print();">Print Barcode</button>`

#### [products/edit.blade.php](products/edit.blade.php#L1339)

- **Line 1339**: `<button onclick="window.print();">Print Barcode</button>`

#### [customers/payments.blade.php](customers/payments.blade.php#L718)

- **Line 718**: `title="{{ __('messages.Close Window') }}">&times;</button>` ✓ (Already translated)

---

## 5. TEXT CONTENT IN BLADE ELEMENTS

#### [products/partials/table.blade.php](products/partials/table.blade.php#L20)

- **Line 20**: `<span>No image</span>` (Should be translatable)
- **Line 47**: `Add` (button text)
- **Line 50**: `Edit` (link text)
- **Line 54**: `Delete` (button text)

#### [islam/index.blade.php](islam/index.blade.php#L737-L785)

Multiple hardcoded Arabic text strings:

- **Line 744**: `<h2 class="section-title">أزرار shortcuts</h2>` (Arabic: Shortcut buttons)
- **Line 745**: `<button class="add-btn" id="addShortcutBtn">+ إضافة</button>` (Arabic: Add)
- **Line 748**: `<div class="empty-state">لا توجد أزرار</div>` (Arabic: No buttons)
- **Line 756**: `<h2 class="section-title">المبيعات</h2>` (Arabic: Sales)
- **Line 761**: `<div class="total-display" id="totalDisplay">المجموع: 0</div>` (Arabic: Total: 0)
- **Line 764**: `<div class="print-header">طباعة المبيعات</div>` (Arabic: Print sales)
- **Line 766**: `<span class="date-separator">إلى</span>` (Arabic: To)
- **Line 768**: `<button class="print-btn" id="printBtn">🖨 طباعة</button>` (Arabic: Print)
- **Line 771**: `<div class="empty-state">لا توجد مبيعات لهذا اليوم</div>` (Arabic: No sales for this day)
- **Line 776**: `<h3>إضافة رقم جديد</h3>` (Arabic: Add new number)
- **Line 782**: `<button class="modal-btn cancel" id="cancelShortcut">إلغاء</button>` (Arabic: Cancel)
- **Line 783**: `<button class="modal-btn confirm" id="confirmShortcut">إضافة</button>` (Arabic: Add)
- **Line 790**: `<h3>تأكيد الحذف</h3>` (Arabic: Confirm deletion)
- **Line 792**: `<p>هل أنت متأكد من حذف هذه المعاملة؟</p>` (Arabic: Are you sure...)

#### [portfolio.blade.php](portfolio.blade.php#L825-L835)

- **Lines 825-835**: Hardcoded tech stack tags (Python, TensorFlow, CNN, Machine Learning) inside `<span>` tags

---

## 6. FORM HINT TEXT & HELP TEXT

#### [admin/shop-owners/create.blade.php](admin/shop-owners/create.blade.php#L165-L195)

- Role descriptions are hardcoded within the template
- Help text about temporary accounts

#### [admin/shop-owners/edit.blade.php](admin/shop-owners/edit.blade.php#L115-L125)

- **Line 115**: Help text about image limit restrictions
- Password field help text about leaving empty

#### [products/create.blade.php](products/create.blade.php#L815-L825)

- Variant placeholder help text

---

## 7. JAVASCRIPT TEMPLATE LITERALS WITH HARDCODED TEXT

#### [products/create.blade.php](products/create.blade.php#L800-L825)

```javascript
placeholder = "e.g., S, M, L"; // Line 804
placeholder = "0"; // Line 810
placeholder = "{{ __('messages.Optional') }}"; // Line 815 (partially translated)
```

#### [products/edit.blade.php](products/edit.blade.php#L1415-L1430)

- Similar hardcoded placeholders in JavaScript template literals

#### [purchase-bills/create.blade.php](purchase-bills/create.blade.php#L720-L745)

- `placeholder="Enter barcode"`
- Button text: `Add`

---

## 8. DATA ATTRIBUTES

#### [dashboard.blade.php](dashboard.blade.php#L2275)

- **Line 2275**: `alt="${product.name}"` in data-src template

---

## 9. EMPTY/MISSING STATES TEXT

#### [products/edit.blade.php](products/edit.blade.php#L1530-L1550)

Hardcoded empty state messages in JavaScript:

```javascript
<h3 class="mt-4 text-lg font-medium text-gray-900">No batches yet</h3>
<p class="mt-2 text-gray-500">Get started by adding your first batch above.</p>
```

---

## SUMMARY BY CATEGORY

| Category               | Count   | Files   |
| ---------------------- | ------- | ------- |
| Placeholder Attributes | 25+     | 8 files |
| Alt Attributes         | 15+     | 4 files |
| Aria-label             | 1       | 1 file  |
| Button Text            | 6+      | 5 files |
| Text Content           | 20+     | 3 files |
| Help/Hint Text         | 8+      | 3 files |
| JS Templates           | 10+     | 3 files |
| Empty States           | 4+      | 2 files |
| **TOTAL**              | **~90** | **20+** |

---

## AFFECTED FILES (PRIORITY ORDER)

### High Priority (Many hardcoded strings)

1. [islam/index.blade.php](islam/index.blade.php) - 15+ Arabic hardcoded strings
2. [admin/shop-owners/create.blade.php](admin/shop-owners/create.blade.php) - 10+ hardcoded strings
3. [admin/shop-owners/edit.blade.php](admin/shop-owners/edit.blade.php) - 8+ hardcoded strings
4. [products/edit.blade.php](products/edit.blade.php) - 10+ hardcoded strings
5. [products/create.blade.php](products/create.blade.php) - 8+ hardcoded strings

### Medium Priority (Some hardcoded strings)

6. [dashboard.blade.php](dashboard.blade.php) - 3+ hardcoded strings
7. [portfolio.blade.php](portfolio.blade.php) - 15+ hardcoded alt texts
8. [purchase-bills/create.blade.php](purchase-bills/create.blade.php) - 2+ hardcoded strings
9. [purchase-bills/edit.blade.php](purchase-bills/edit.blade.php) - 2+ hardcoded strings
10. [products/partials/table.blade.php](products/partials/table.blade.php) - 5+ hardcoded strings

### Low Priority (Single items or less critical)

11. [ss.blade.php](ss.blade.php) - 1 hardcoded string
12. [bills/show.blade.php](bills/show.blade.php) - 1 hardcoded string
13. [customers/payments.blade.php](customers/payments.blade.php) - Already has some translations
14. [vendor/pagination/custom-light.blade.php](vendor/pagination/custom-light.blade.php) - 1 hardcoded string
15. [products/barcode-search.blade.php](products/barcode-search.blade.php) - Dynamic but not translated
16. [products/index.blade.php](products/index.blade.php) - Dynamic but not translated

---

## RECOMMENDED ACTIONS

### Phase 1: Critical Files (High Impact)

- [ ] Fix [islam/index.blade.php](islam/index.blade.php) - Wrap all Arabic text with `__()` helper
- [ ] Fix [admin/shop-owners/create.blade.php](admin/shop-owners/create.blade.php) - Replace all hardcoded placeholders
- [ ] Fix [admin/shop-owners/edit.blade.php](admin/shop-owners/edit.blade.php)

### Phase 2: Form Files (Medium Impact)

- [ ] Fix [products/create.blade.php](products/create.blade.php)
- [ ] Fix [products/edit.blade.php](products/edit.blade.php)
- [ ] Fix [purchase-bills/create.blade.php](purchase-bills/create.blade.php)
- [ ] Fix [purchase-bills/edit.blade.php](purchase-bills/edit.blade.php)

### Phase 3: UI Components (Lower Impact)

- [ ] Fix [portfolio.blade.php](portfolio.blade.php) - Portfolio alt text
- [ ] Fix [products/partials/table.blade.php](products/partials/table.blade.php)
- [ ] Fix [dashboard.blade.php](dashboard.blade.php)

### Phase 4: Vendor Files

- [ ] Consider fixing vendor pagination files if they need customization

---

## TRANSLATION KEY NAMING CONVENTION

Based on existing patterns in the codebase:

- Use `messages.*` namespace for general UI strings
- Use `products.*` namespace for product-related strings
- Use `expenses.*` namespace for expense-related strings
- Use `dashboard.*` namespace for dashboard-specific strings
- Use `admin.*` namespace for admin panel strings

---

## EXAMPLE FIX

### BEFORE:

```blade
<input type="text" placeholder="Enter shop name">
<button class="text-red-500">Delete</button>
<span>No image</span>
```

### AFTER:

```blade
<input type="text" placeholder="{{ __('messages.Enter shop name') }}">
<button class="text-red-500">{{ __('messages.Delete') }}</button>
<span>{{ __('messages.No image') }}</span>
```

---

## NOTES

1. **JavaScript Template Literals**: Some hardcoded strings appear in JavaScript template literals. These need careful handling - the `__()` function won't work inside JavaScript strings. Consider:
    - Moving strings to data attributes
    - Using PHP to generate JavaScript with pre-translated strings
    - Using a JavaScript-based translation library

2. **Arabic Content**: The [islam/index.blade.php](islam/index.blade.php) file contains substantial Arabic text that appears to be hardcoded. This should be moved to language files for consistency.

3. **Portfolio Page**: Consider whether the tech stack terms (Python, TensorFlow, etc.) on the portfolio page should be translatable or remain in English.

4. **Data Attributes**: Some `data-*` attributes contain user-visible text that may need translation depending on how they're used.

---

**End of Report**
