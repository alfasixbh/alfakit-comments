<?php

declare(strict_types=1);

return [
    'label' => 'التعليقات',
    'empty' => 'لا توجد تعليقات بعد.',
    'edited' => '(معدَّل)',
    'unknown_author' => 'مستخدم محذوف',

    'editor' => [
        'placeholder' => 'اكتب تعليقًا… اكتب @ للإشارة إلى شخص ما.',
    ],

    'actions' => [
        'post' => 'إضافة تعليق',
        'post_submit' => 'تعليق',
        'edit' => 'تعديل',
        'edit_heading' => 'تعديل التعليق',
        'save' => 'حفظ',
        'delete' => 'حذف',
        'delete_heading' => 'حذف التعليق',
        'delete_description' => 'سيتم حذف هذا التعليق وتفاعلاته. لا يمكن التراجع عن هذا الإجراء.',
        'more' => 'إجراءات التعليق',
        'load_more' => 'تحميل المزيد من التعليقات',
        'close' => 'إغلاق',
    ],

    'columns' => [
        'avatar' => 'الصورة الرمزية',
        'author' => 'الكاتب',
        'date' => 'تاريخ النشر',
        'body' => 'التعليق',
    ],

    'reactions' => [
        'add' => 'إضافة تفاعل',
        'react' => 'التفاعل بـ :reaction',
    ],

    'notifications' => [
        'posted' => 'تمت إضافة التعليق',
        'updated' => 'تم تحديث التعليق',
        'deleted' => 'تم حذف التعليق',
        'mentioned' => [
            'title' => 'أشار إليك :name في تعليق',
            'view' => 'عرض',
        ],
    ],

    'validation' => [
        'blank' => 'اكتب شيئًا قبل النشر.',
    ],
];
