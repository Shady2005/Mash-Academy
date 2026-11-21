<?php

return [
    // Maximum allowed video upload size in kilobytes (2 GB default; also bounded by php.ini)
    'max_video_upload_kb' => (int) env('MASH_MAX_VIDEO_UPLOAD_KB', 2097152),
];
