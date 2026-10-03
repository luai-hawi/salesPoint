<?php

return [
    'actions' => [
        'created' => 'أنشأ',
        'updated' => 'عدّل',
        'deleted' => 'حذف',
        'held' => 'علّق',
        'resumed' => 'استأنف',
        'checked_in' => 'سجّل حضورًا',
        'checked_out' => 'سجّل انصرافًا',
        'login' => 'سجّل الدخول',
        'suspended' => 'أوقف',
        'reactivated' => 'أعاد التفعيل',
        'password_reset' => 'أعاد تعيين كلمة مرور',
        'logged_out' => 'سجّل خروج',
        'revoked' => 'ألغى',
    ],

    'subjects' => [
        'bill' => 'فاتورة',
        'customer_payment' => 'حركة عميل',
        'supplier_payment' => 'دفعة مورد',
        'purchase_bill' => 'فاتورة شراء',
        'expense' => 'مصروف',
        'capital_entry' => 'قيد رأس مال',
        'customer' => 'عميل',
        'supplier' => 'مورد',
        'product' => 'منتج',
        'employee_payment' => 'دفعة موظف',
        'employee_device' => 'جهاز موظف',
        'employee_credential' => 'اعتماد موظف',
        'staff_member' => 'موظف',
        'team_account' => 'حساب فريق',
        'cash_movement' => 'حركة صندوق',
        'day_closing' => 'إقفال يوم',
        'held_bill' => 'فاتورة معلقة',
        'kitchen_order' => 'طلب مطبخ',
    ],

    'events' => [
        'generic' => ':action :subject :label',
        'bill' => [
            'created' => 'أنشأ الفاتورة :label بقيمة :amount',
            'updated' => 'عدّل الفاتورة :label (الإجمالي الآن :amount)',
            'deleted' => 'حذف الفاتورة :label (:amount)',
        ],
        'customer_payment' => [
            'created' => 'سجّل حركة عميل بقيمة :amount للعميل :label',
            'updated' => 'عدّل حركة العميل :label (أصبحت :amount)',
            'deleted' => 'حذف حركة عميل بقيمة :amount للعميل :label',
        ],
        'supplier_payment' => [
            'created' => 'سجّل دفعة للمورد :label بقيمة :amount',
            'updated' => 'عدّل دفعة المورد :label (أصبحت :amount)',
            'deleted' => 'حذف دفعة للمورد :label بقيمة :amount',
        ],
        'purchase_bill' => [
            'created' => 'أنشأ فاتورة الشراء :label بقيمة :amount',
            'updated' => 'عدّل فاتورة الشراء :label (الإجمالي الآن :amount)',
            'deleted' => 'حذف فاتورة الشراء :label (:amount)',
        ],
        'expense' => [
            'created' => 'أضاف المصروف ":label" بقيمة :amount',
            'updated' => 'عدّل المصروف ":label" (أصبح :amount)',
            'deleted' => 'حذف المصروف ":label" بقيمة :amount',
        ],
        'capital_entry' => [
            'created' => 'أضاف قيد رأس مال بقيمة :amount',
            'updated' => 'عدّل قيد رأس مال (أصبح :amount)',
            'deleted' => 'حذف قيد رأس مال بقيمة :amount',
        ],
        'customer' => [
            'created' => 'أضاف العميل :label',
            'updated' => 'عدّل العميل :label',
            'deleted' => 'حذف العميل :label',
        ],
        'supplier' => [
            'created' => 'أضاف المورد :label',
            'updated' => 'عدّل المورد :label',
            'deleted' => 'حذف المورد :label',
        ],
        'product' => [
            'created' => 'أضاف المنتج :label',
            'updated' => 'عدّل المنتج :label',
            'deleted' => 'حذف المنتج :label',
        ],
        'employee_payment' => [
            'created' => 'دفع للموظف :label مبلغ :amount',
            'updated' => 'عدّل دفعة للموظف :label (أصبحت :amount)',
            'deleted' => 'حذف دفعة بقيمة :amount للموظف :label',
        ],
        'employee_device' => [
            'revoked' => 'ألغى جهاز موظف للحساب :label',
        ],
        'employee_credential' => [
            'deleted' => 'حذف اعتماد موظف للحساب :label',
        ],
        'staff_member' => [
            'created' => 'أضاف الموظف :label',
            'updated' => 'عدّل بيانات الموظف :label',
            'deleted' => 'أزال الموظف :label',
        ],
        'team_account' => [
            'created' => 'أنشأ حساب الفريق :label',
            'updated' => 'عدّل حساب الفريق :label',
            'deleted' => 'حذف حساب الفريق :label',
            'suspended' => 'أوقف حساب الفريق :label',
            'reactivated' => 'أعاد تفعيل حساب الفريق :label',
            'password_reset' => 'أعاد تعيين كلمة مرور حساب الفريق :label',
            'logged_out' => 'سجّل خروج حساب الفريق :label',
        ],
        'cash_movement' => [
            'created' => 'سجّل حركة صندوق ":label" بقيمة :amount',
            'updated' => 'عدّل حركة الصندوق ":label" (أصبحت :amount)',
            'deleted' => 'حذف حركة الصندوق ":label" بقيمة :amount',
        ],
        'day_closing' => [
            'created' => 'حفظ إقفال اليوم :label بفارق :amount',
            'updated' => 'عدّل إقفال اليوم :label بفارق :amount',
            'deleted' => 'حذف إقفال اليوم :label',
        ],
        'held_bill' => [
            'held' => 'علّق فاتورة (:label)',
            'resumed' => 'استأنف الفاتورة المعلقة :label',
            'deleted' => 'ألغى الفاتورة المعلقة :label',
        ],
        'kitchen_order' => [
            'created' => 'أرسل الطلب :label إلى المطبخ',
            'updated' => 'حدّث طلب المطبخ :label',
        ],
    ],

    'ui' => [
        'title' => 'سجل النشاط',
        'empty' => 'لا يوجد نشاط مسجل بعد.',
        'actor' => 'من',
        'when' => 'متى',
        'what' => 'ماذا',
        'amount' => 'المبلغ',
    ],
];
