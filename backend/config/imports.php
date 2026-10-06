<?php

return [
    // Max upload size for import files (KB).
    'max_file_kb' => (int) env('IMPORT_MAX_FILE_KB', 10240),

    // Remote product images listed in the "Image URLs" column.
    'image_timeout' => (int) env('IMPORT_IMAGE_TIMEOUT', 20),
    'max_image_kb' => (int) env('IMPORT_MAX_IMAGE_KB', 5120),

    // Block image URLs that resolve to private/internal addresses (SSRF protection).
    // Only switch this off for local testing with images served from your own network.
    'allow_private_image_hosts' => (bool) env('IMPORT_ALLOW_PRIVATE_IMAGE_HOSTS', false),

    // Uploaded import files are deleted after this many days (import history is kept).
    'keep_files_days' => (int) env('IMPORT_KEEP_FILES_DAYS', 30),
];
