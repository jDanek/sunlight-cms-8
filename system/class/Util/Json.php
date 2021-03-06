<?php

namespace Sunlight\Util;

/**
 * JSON helper
 */
abstract class Json
{
    const CONTENT_TYPE_JSON = 'application/json; charset=UTF-8';
    const CONTENT_TYPE_JSONP = 'application/javascript; charset=UTF-8';

    /**
     * Encode data as JSON
     *
     * @param mixed $data
     * @param bool  $pretty         produce formatted JSON 1/0 (true works in PHP 5.4.0+ only)
     * @param bool  $escapedUnicode escape unicode 1/0 (false works in PHP 5.4.0+ only)
     * @param bool  $escapedSlashes escape slashes 1/0 (false works in PHP 5.4.0+ only)
     * @throws \RuntimeException in case of an error
     * @return string
     */
    static function encode($data, bool $pretty = true, bool $escapedUnicode = true, bool $escapedSlashes = false): string
    {
        $options = 0;

        if ($pretty && defined('JSON_PRETTY_PRINT')) {
            $options |= JSON_PRETTY_PRINT;
        }
        if (!$escapedSlashes && defined('JSON_UNESCAPED_SLASHES')) {
            $options |= JSON_UNESCAPED_SLASHES;
        }
        if (!$escapedUnicode && defined('JSON_UNESCAPED_UNICODE')) {
            $options |= JSON_UNESCAPED_UNICODE;
        }

        $json = json_encode($data, $options);

        if ($json === false) {
            throw new \RuntimeException(static::getErrorMessage());
        }

        return $json;
    }

    /**
     * Encode data as JSONP
     *
     * @param string $callback
     * @param mixed  $data
     * @param bool   $pretty         produce formatted JSON 1/0 (true works in PHP 5.4.0+ only)
     * @param bool   $escapedUnicode escape unicode 1/0 (false works in PHP 5.4.0+ only)
     * @param bool   $escapedSlashes escape slashes 1/0 (false works in PHP 5.4.0+ only)
     * @throws \RuntimeException in case of an error
     * @return string
     */
    static function encodeJsonp(string $callback, $data, bool $pretty = true, bool $escapedUnicode = true, bool $escapedSlashes = true): string
    {
        return sprintf('%s(%s);', $callback, static::encode($data, $pretty, $escapedUnicode, $escapedSlashes));
    }

    /**
     * Determine JSON / JSONP format using a GET parameter and return the content type and encoded data
     *
     * @param mixed  $data
     * @param bool   $pretty             produce formatted JSON 1/0 (true works in PHP 5.4.0+ only)
     * @param bool   $escapedUnicode     escape unicode 1/0 (false works in PHP 5.4.0+ only)
     * @param bool   $escapedSlashes     escape slashes 1/0 (false works in PHP 5.4.0+ only)
     * @param string $jsonpCallbackParam JSONP callback parameter name
     * @throws \RuntimeException in case of an error
     * @return string[] content type, encoded data
     */
    static function smartEncode($data, bool $pretty = true, bool $escapedUnicode = true, bool $escapedSlashes = false, string $jsonpCallbackParam = 'callback'): array
    {
        if (
            $jsonpCallbackParam !== null
            && isset($_GET[$jsonpCallbackParam])
            && preg_match('{[a-z_$]\w+$}ADi', $callback = Request::get($jsonpCallbackParam))
        ) {
            $contentType = static::CONTENT_TYPE_JSONP;
            $encodedData = static::encodeJsonp($callback, $data, $pretty, $escapedUnicode, $escapedSlashes);
        } else {
            $contentType = static::CONTENT_TYPE_JSON;
            $encodedData = static::encode($data, $pretty, $escapedUnicode, $escapedSlashes);
        }

        return [$contentType, $encodedData];
    }

    /**
     * Decode a JSON string
     *
     * @param string $json           the JSON string to decode
     * @param bool   $assoc          decode objects as associative arrays 1/0
     * @param bool   $bigIntAsString represent big integers as strings (instead of floats) 1/0
     * @throws \RuntimeException in case of an error
     * @return mixed
     */
    static function decode(string $json, bool $assoc = true, bool $bigIntAsString = false)
    {
        if (!$bigIntAsString || !defined('JSON_BIGINT_AS_STRING')) {
            $data = json_decode($json, $assoc);
        } else {
            $data = json_decode($json, $assoc, 512, JSON_BIGINT_AS_STRING);
        }

        if (($errorCode = json_last_error()) !== (JSON_ERROR_NONE)) {
            throw new \RuntimeException(static::getErrorMessage($errorCode));
        }

        return $data;
    }

    /**
     * Get error message for the given code
     *
     * @param int|null $errorCode if no code is given, json_last_error() is called automatically
     * @return string
     */
    static function getErrorMessage(?int $errorCode = null): string
    {
        if ($errorCode === null) {
            $errorCode = json_last_error();
        }

        $errorCodes = [
            JSON_ERROR_NONE => 'No error has occurred',
            JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX => 'Syntax error',
        ];

        if (defined('JSON_ERROR_UTF8')) {
            $errorCodes[JSON_ERROR_UTF8] = 'Malformed UTF-8 characters, possibly incorrectly encoded';
        }
        if (defined('JSON_ERROR_RECURSION')) {
            $errorCodes[JSON_ERROR_RECURSION] = 'One or more recursive references in the value to be encoded';
        }
        if (defined('JSON_ERROR_INF_OR_NAN')) {
            $errorCodes[JSON_ERROR_INF_OR_NAN] = 'One or more NAN or INF values in the value to be encoded ';
        }
        if (defined('JSON_ERROR_UNSUPPORTED_TYPE')) {
            $errorCodes[JSON_ERROR_UNSUPPORTED_TYPE] = 'A value of a type that cannot be encoded was given';
        }
        if (defined('JSON_ERROR_INVALID_PROPERTY_NAME')) {
            $errorCodes[JSON_ERROR_INVALID_PROPERTY_NAME] = 'A property name that cannot be encoded was given';
        }
        if (defined('JSON_ERROR_UTF16')) {
            $errorCodes[JSON_ERROR_UTF16] = 'Malformed UTF-16 characters, possibly incorrectly encoded';
        }

        if (isset($errorCodes[$errorCode])) {
            return $errorCodes[$errorCode];
        } elseif (PHP_VERSION_ID >= 50500) {
            return json_last_error_msg();
        } else {
            return sprintf('Unknown error (%s)', $errorCode);
        }
    }
}
