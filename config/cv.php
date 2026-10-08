<?php

return [
    // Private disk: CV files are never publicly reachable.
    'disk' => env('CV_DISK', 'local'),
    'max_file_kb' => (int) env('CV_MAX_FILE_KB', 5120),
    'max_per_user' => (int) env('CV_MAX_PER_USER', 10),
    // CVs longer than this are rejected rather than silently truncated before analysis.
    'max_text_chars' => (int) env('CV_MAX_TEXT_CHARS', 60000),
];
