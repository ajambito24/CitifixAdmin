<?php


function cf_icon(string $name, string $class = 'h-6 w-6'): string
{
    static $cache = [];

    
    if (!preg_match('/^[a-z0-9-]+$/', $name)) return '';

    if (!isset($cache[$name])) {
        $file = dirname(__DIR__, 2) . '/vendor/twbs/bootstrap-icons/icons/' . $name . '.svg';
        $cache[$name] = is_file($file) ? (string) file_get_contents($file) : '';
    }

    if ($cache[$name] === '') return '';

    return preg_replace(
        '/<svg\b[^>]*>/',
        '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16" class="' . htmlspecialchars($class, ENT_QUOTES) . '" aria-hidden="true" focusable="false">',
        $cache[$name],
        1
    );
}