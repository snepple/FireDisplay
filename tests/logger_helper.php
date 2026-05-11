<?php
/**
 * Helper script to call sys_log from command line for testing.
 * Usage: php logger_helper.php <component> <message> <status> <details_json>
 */

require_once __DIR__ . '/api/logger.php';

$component = $argv[1] ?? 'TestComponent';
$message = $argv[2] ?? 'Test message';
$status = $argv[3] ?? 'info';
$details = isset($argv[4]) ? json_decode($argv[4], true) : [];

sys_log($component, $message, $status, $details);
