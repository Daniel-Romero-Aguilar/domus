<?php

return [
    'exams' => [
        'approved_percentage' => (int) env('EDUCATION_EXAM_APPROVED_PERCENTAGE', 50),
        'excellent_percentage' => (int) env('EDUCATION_EXAM_EXCELLENT_PERCENTAGE', 80),
    ],
];
