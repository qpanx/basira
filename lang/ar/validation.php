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
    'min' => [
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب ألا يقل طول :attribute عن :min أحرف.',
    ],
    'max' => [
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
        'battery_level' => 'مستوى البطارية', 'firmware_version' => 'إصدار البرنامج',
        'lat' => 'خط العرض', 'lng' => 'خط الطول', 'timestamp' => 'الوقت', 'type' => 'النوع', 'severity' => 'الخطورة',
    ],
];
