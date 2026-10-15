<?php

namespace Langsys\SDK\Html;

/**
 * A page's bytes as UTF-8, decoded as a browser decodes them (WHATWG
 * Encoding), so every path reads the text a visitor sees.
 *
 * The encoding is a byte-order mark's, else the charset the first 1024 bytes
 * declare in a meta, else UTF-8 when the bytes are UTF-8 and windows-1252
 * when they are not. A Latin-1 or ASCII label is read as windows-1252, as
 * browsers read it, so `\x93hi\x94` is "hi" in curly quotes and not two
 * control characters; a meta declaring UTF-16 means UTF-8. A byte invalid in
 * the encoding becomes U+FFFD, and the rest of the page is still read in it.
 */
final class Charset
{
    /** Labels read as windows-1252. */
    const WINDOWS_1252 = [
        'ansi_x3.4-1968', 'ascii', 'cp1252', 'cp819', 'csisolatin1', 'ibm819', 'iso-8859-1', 'iso-ir-100',
        'iso8859-1', 'iso88591', 'iso_8859-1', 'iso_8859-1:1987', 'l1', 'latin1', 'us-ascii', 'windows-1252', 'x-cp1252',
    ];

    /** Labels read as UTF-8, a meta's UTF-16 among them. */
    const UTF8 = [
        'unicode-1-1-utf-8', 'unicode11utf8', 'unicode20utf8', 'utf-8', 'utf8', 'x-unicode20utf8',
        'csunicode', 'iso-10646-ucs-2', 'ucs-2', 'unicode', 'unicodefeff', 'unicodefffe', 'utf-16', 'utf-16be', 'utf-16le',
    ];

    /**
     * @param string $html
     * @return string
     */
    public static function toUtf8($html)
    {
        $html = (string) $html;

        if (strncmp($html, "\xEF\xBB\xBF", 3) === 0) {
            return self::scrub(substr($html, 3));
        }
        if (strncmp($html, "\xFE\xFF", 2) === 0) {
            return self::from(substr($html, 2), 'UTF-16BE');
        }
        if (strncmp($html, "\xFF\xFE", 2) === 0) {
            return self::from(substr($html, 2), 'UTF-16LE');
        }

        $label = self::declared($html);

        if ($label !== null && in_array($label, self::UTF8, true)) {
            return self::scrub($html);
        }

        if ($label !== null && in_array($label, self::WINDOWS_1252, true)) {
            return self::from($html, 'windows-1252');
        }

        if ($label !== null) {
            $converted = self::transcode($html, $label);
            if ($converted !== null) {
                return $converted;
            }
        }

        return preg_match('//u', $html) ? $html : self::from($html, 'windows-1252');
    }

    /**
     * $text with each byte invalid in UTF-8 as U+FFFD.
     *
     * @param string $text
     * @return string
     */
    public static function scrub($text)
    {
        $text = (string) $text;

        return preg_match('//u', $text) ? $text : self::from($text, 'UTF-8');
    }

    /**
     * The charset a meta in the first 1024 bytes declares, as a lowercase
     * label, or null.
     *
     * @param string $html
     * @return string|null
     */
    private static function declared($html)
    {
        $head = (string) preg_replace('/<!--.*?(?:-->|$)/s', '', substr($html, 0, 1024));

        return preg_match('/<meta\b[^>]*?\bcharset\s*=\s*["\']?\s*([A-Za-z0-9._:\-]+)/i', $head, $m) ? strtolower($m[1]) : null;
    }

    /**
     * @param string $bytes
     * @param string $encoding One ICU knows
     * @return string
     */
    private static function from($bytes, $encoding)
    {
        $converted = self::transcode($bytes, $encoding);

        return $converted === null ? $bytes : $converted;
    }

    /**
     * $bytes from $encoding to UTF-8, or null when ICU does not know it.
     * ICU warns on a label it resolves to one of several converters, which a
     * framework's error handler would raise, so the call is silenced.
     *
     * @param string $bytes
     * @param string $encoding
     * @return string|null
     */
    private static function transcode($bytes, $encoding)
    {
        $converted = @\UConverter::transcode($bytes, 'UTF-8', $encoding);

        return is_string($converted) ? $converted : null;
    }
}
