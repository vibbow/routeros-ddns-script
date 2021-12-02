<?php

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('filterInputPostGet')) {
    function filterInputPostGet($name, $default = null) {
        return filter_input(INPUT_POST, $name, FILTER_SANITIZE_STRING) ?? filter_input(INPUT_GET, $name, FILTER_SANITIZE_STRING) ?? $default;
    }
}

function get_ip() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $ip = trim(explode(',', $ip)[0]);

    if ( ! filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new Exception('No valid IP');
    }

    return $ip;
}
