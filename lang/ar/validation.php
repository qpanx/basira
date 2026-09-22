<?php

return [
    'accepted' => 'يجب قبول :attribute.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صالحاً.',
    'required' => 'حقل :attribute مطلوب.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'integer' => 'يجب أن يكون :attribute رقماً صحيحاً.',
    'file' => 'يجب أن يكون :attribute ملفاً.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'mimes' => 'يجب أن يكون :attribute ملفاً من النوع: :values.',
    'dimensions' => 'أبعاد :attribute غير صالحة.',
    'uploaded' => 'تعذر رفع :attribute.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخاً سابقاً أو مساوياً لـ :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخاً لاحقاً أو مساوياً لـ :date.',
    'regex' => 'صيغة :attribute غير صالحة.',
    'min' => [
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب ألا يقل طول :attribute عن :min أحرف.',
    ],
    'max' => [
        'file' => 'يجب ألا يزيد حجم :attribute عن :max كيلوبايت.',
        'numeric' => 'يجب ألا تزيد قيمة :attribute عن :max.',
        'string' => 'يجب ألا يزيد طول :attribute عن :max أحرف.',
    ],
    'between' => [
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
        'integer' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
    ],
    'date' => 'يجب أن يكون :attribute تاريخاً صالحاً.',
    'uuid' => 'يجب أن يكون :attribute UUID صالحاً.',
    'in' => 'القيمة المحددة لـ :attribute غير صالحة.',
    'prohibited' => 'الحقل :attribute غير مسموح به.',
    'attributes' => [
        'image' => 'الصورة', 'accuracy' => 'دقة الموقع', 'query' => 'البحث',
        'language' => 'اللغة', 'destination_place_id' => 'معرّف الوجهة',
        'battery_level' => 'مستوى البطارية', 'firmware_version' => 'إصدار البرنامج',
        'lat' => 'خط العرض', 'lng' => 'خط الطول', 'timestamp' => 'الوقت', 'type' => 'النوع', 'severity' => 'الخطورة',
    ],
];
