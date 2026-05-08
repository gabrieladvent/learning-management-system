<?php

if (! function_exists('generateCodeSubject')) {
    function generateCodeSubject(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));

        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 4));
        }

        return strtoupper(implode('', array_map(fn ($w) => substr($w, 0, 1), $words)));
    }
}
