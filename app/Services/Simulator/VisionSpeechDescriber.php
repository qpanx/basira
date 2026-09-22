<?php

namespace App\Services\Simulator;

/**
 * Builds short, localized Text-to-Speech descriptions of detection results.
 *
 * Descriptions only describe image-relative horizontal position. They never
 * claim an object is in the walking path and never estimate distance.
 */
class VisionSpeechDescriber
{
    /** Common COCO/YOLO labels mapped to Arabic for speech output. */
    private const ARABIC_LABELS = [
        'person' => 'شخص', 'bicycle' => 'دراجة', 'car' => 'سيارة', 'motorcycle' => 'دراجة نارية',
        'airplane' => 'طائرة', 'bus' => 'حافلة', 'train' => 'قطار', 'truck' => 'شاحنة',
        'boat' => 'قارب', 'traffic light' => 'إشارة مرور', 'fire hydrant' => 'صنبور حريق',
        'stop sign' => 'لافتة توقف', 'parking meter' => 'عداد موقف', 'bench' => 'مقعد',
        'bird' => 'طائر', 'cat' => 'قطة', 'dog' => 'كلب', 'horse' => 'حصان', 'sheep' => 'خروف',
        'elephant' => 'فيل', 'bear' => 'دب', 'zebra' => 'حمار وحشي', 'giraffe' => 'زرافة',
        'backpack' => 'حقيبة ظهر', 'umbrella' => 'مظلة', 'handbag' => 'حقيبة يد',
        'tie' => 'ربطة عنق', 'suitcase' => 'حقيبة سفر', 'frisbee' => 'قرص طائر',
        'snowboard' => 'لوح تزلج', 'sports ball' => 'كرة رياضية', 'kite' => 'طائرة ورقية',
        'skateboard' => 'لوح تزلج', 'surfboard' => 'لوح ريش', 'tennis racket' => 'مضرب تنس',
        'bottle' => 'زجاجة', 'wine glass' => 'كأس', 'cup' => 'كوب', 'fork' => 'شوكة',
        'knife' => 'سكين', 'spoon' => 'ملعقة', 'bowl' => 'وعاء', 'banana' => 'موزة',
        'apple' => 'تفاحة', 'sandwich' => 'ساندويتش', 'orange' => 'برتقالة', 'broccoli' => 'بروكلي',
        'carrot' => 'جزرة', 'hot dog' => 'هوت دوج', 'pizza' => 'بيتزا', 'donut' => 'دونات',
        'cake' => 'كيك', 'chair' => 'كرسي', 'couch' => 'أريكة', 'potted plant' => 'نبتة',
        'bed' => 'سرير', 'dining table' => 'طاولة طعام', 'toilet' => 'مرحاض',
        'tv' => 'تلفاز', 'laptop' => 'حاسوب محمول', 'mouse' => 'فأرة', 'remote' => 'جهاز تحكم',
        'keyboard' => 'لوحة مفاتيح', 'cell phone' => 'هاتف محمول', 'microwave' => 'ميكروويف',
        'oven' => 'فرن', 'toaster' => 'محمصة', 'sink' => 'حوض', 'refrigerator' => 'ثلاجة',
        'book' => 'كتاب', 'clock' => 'ساعة', 'vase' => 'مزهرية', 'scissors' => 'مقص',
        'teddy bear' => 'دمية', 'hair drier' => 'مجفف شعر', 'toothbrush' => 'فرشاة أسنان',
    ];

    /**
     * @param  list<array{label: string, confidence: float, position: string}>  $objects
     */
    public function describe(array $objects, string $language): string
    {
        if ($objects === []) {
            return __('filament.simulator.speech.none', [], $language);
        }

        $sentences = [];
        foreach (array_slice($objects, 0, 5) as $object) {
            $label = $this->label((string) $object['label'], $language);
            $key = match ($object['position']) {
                'front' => 'detected_center',
                'left' => 'detected_left',
                'right' => 'detected_right',
                default => 'detected_generic',
            };
            $detected = $language === 'ar' ? $label : $this->withArticle($label);
            $sentences[] = __('filament.simulator.speech.'.$key, ['detected' => $detected], $language);
        }

        return implode(' ', $sentences);
    }

    private function label(string $label, string $language): string
    {
        $normalized = mb_strtolower(trim($label));
        if ($language === 'ar') {
            return self::ARABIC_LABELS[$normalized] ?? $label;
        }

        return $label;
    }

    private function withArticle(string $label): string
    {
        $first = mb_strtolower(mb_substr($label, 0, 1));

        return (in_array($first, ['a', 'e', 'i', 'o', 'u'], true) ? 'An ' : 'A ').$label;
    }
}
