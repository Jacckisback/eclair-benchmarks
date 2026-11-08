<?php
// Loaded before every PHP request ONLY during the profiling pass (never during timing measurements).
// Logs, per request: PHP files loaded, peak memory, and SQL queries (WordPress) to a JSON-lines file.
register_shutdown_function(static function (): void {
    $log = '/var/lib/eclair-bench/profile.jsonl';
    global $wpdb;
    $line = [
        'port' => (int) ($_SERVER['SERVER_PORT'] ?? 0),
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'php_files' => count(get_included_files()) - 1, // minus this file
        'peak_memory_kb' => (int) round(memory_get_peak_usage() / 1024),
        'sql_queries' => isset($wpdb) && is_object($wpdb) && isset($wpdb->num_queries) ? (int) $wpdb->num_queries : 0,
        'wall_ms' => round((microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 2),
    ];
    @file_put_contents($log, json_encode($line) . "\n", FILE_APPEND | LOCK_EX);
});
