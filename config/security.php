<?php

return [
    'max_request_body_kb' => (int) env('MAX_REQUEST_BODY_KB', 1024),
    'max_page' => (int) env('MAX_PAGE', 10000),
    'activity_retention_days' => (int) env('ACTIVITY_RETENTION_DAYS', 365),
];
