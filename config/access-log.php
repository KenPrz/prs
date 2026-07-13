<?php

return [
    // How long access-log rows are retained before model:prune deletes them.
    'retention_days' => (int) env('ACCESS_LOG_RETENTION_DAYS', 180),
];
