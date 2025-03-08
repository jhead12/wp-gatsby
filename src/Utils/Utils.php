<?php

namespace WPGatsby\Utils;

class Utils {

    /**
     * Checks if any of the strings in $substringArray is a substring in the $haystack.
     *
     * @since 2.1.2
     *
     * @param string $haystack The string to search in.
     * @param array $substringArray An array of substrings to look for.
     * @param int $offset Optional. The offset to start searching from. Default is 0.
     *
     * @return bool True if any of the strings are found, false otherwise.
     */
    public static function strInSubstringArray(string $haystack, array $substringArray, int $offset = 0): bool {
        foreach ($substringArray as $substring) {
            if (!empty($substring) && strpos($haystack, $substring, $offset) !== false) {
                return true;
            }
        }

        return false;
    }
}