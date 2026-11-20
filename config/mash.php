<?php

return [
    // Maximum allowed video upload size in kilobytes (500 MB default; also bounded by php.ini)
    'max_video_upload_kb' => (int) env('MASH_MAX_VIDEO_UPLOAD_KB', 512000),
];
