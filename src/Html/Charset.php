<?php

namespace Langsys\SDK\Html;

/**
 * A page's bytes as UTF-8, decoded as a browser decodes them (WHATWG
 * Encoding), so every path reads the text a visitor sees.
 *
 * The encoding is a byte-order mark's, else the one a meta in the first 1024
 * bytes declares, else UTF-8 when the bytes are UTF-8 and windows-1252 when
 * they are not. A declared label is read through the browser's label table:
 * `latin1` and `ascii` name windows-1252, so `\x93hi\x94` is "hi" in curly
 * quotes, `gb2312` and `chinese` name GBK, `euc-kr` names windows-949, and a
 * label the table lacks, such as `utf-7`, declares nothing. A meta declaring
 * UTF-16 means UTF-8. Each encoding is decoded as a browser decodes it,
 * superset and all, a byte invalid in it as U+FFFD, and the rest of the page
 * is still read in it.
 *
 * UTF-8 and UTF-16 are decoded here, every other encoding by Decoder, which
 * runs the standard's decoders over its own indexes, so no encoding needs the
 * intl extension and every host decodes alike.
 */
final class Charset
{
    /** Each encoding a browser knows, with the labels that name it. */
    const ENCODINGS = [
        'UTF-8' => ['unicode-1-1-utf-8', 'unicode11utf8', 'unicode20utf8', 'utf-8', 'utf8', 'x-unicode20utf8'],
        'IBM866' => ['866', 'cp866', 'csibm866', 'ibm866'],
        'ISO-8859-2' => ['csisolatin2', 'iso-8859-2', 'iso-ir-101', 'iso8859-2', 'iso88592', 'iso_8859-2', 'iso_8859-2:1987', 'l2', 'latin2'],
        'ISO-8859-3' => ['csisolatin3', 'iso-8859-3', 'iso-ir-109', 'iso8859-3', 'iso88593', 'iso_8859-3', 'iso_8859-3:1988', 'l3', 'latin3'],
        'ISO-8859-4' => ['csisolatin4', 'iso-8859-4', 'iso-ir-110', 'iso8859-4', 'iso88594', 'iso_8859-4', 'iso_8859-4:1988', 'l4', 'latin4'],
        'ISO-8859-5' => ['csisolatincyrillic', 'cyrillic', 'iso-8859-5', 'iso-ir-144', 'iso8859-5', 'iso88595', 'iso_8859-5', 'iso_8859-5:1988'],
        'ISO-8859-6' => ['arabic', 'asmo-708', 'csiso88596e', 'csiso88596i', 'csisolatinarabic', 'ecma-114', 'iso-8859-6', 'iso-8859-6-e', 'iso-8859-6-i', 'iso-ir-127', 'iso8859-6', 'iso88596', 'iso_8859-6', 'iso_8859-6:1987'],
        'ISO-8859-7' => ['csisolatingreek', 'ecma-118', 'elot_928', 'greek', 'greek8', 'iso-8859-7', 'iso-ir-126', 'iso8859-7', 'iso88597', 'iso_8859-7', 'iso_8859-7:1987', 'sun_eu_greek'],
        'ISO-8859-8' => ['csiso88598e', 'csisolatinhebrew', 'hebrew', 'iso-8859-8', 'iso-8859-8-e', 'iso-ir-138', 'iso8859-8', 'iso88598', 'iso_8859-8', 'iso_8859-8:1988', 'visual'],
        'ISO-8859-8-I' => ['csiso88598i', 'iso-8859-8-i', 'logical'],
        'ISO-8859-10' => ['csisolatin6', 'iso-8859-10', 'iso-ir-157', 'iso8859-10', 'iso885910', 'l6', 'latin6'],
        'ISO-8859-13' => ['iso-8859-13', 'iso8859-13', 'iso885913'],
        'ISO-8859-14' => ['iso-8859-14', 'iso8859-14', 'iso885914'],
        'ISO-8859-15' => ['csisolatin9', 'iso-8859-15', 'iso8859-15', 'iso885915', 'iso_8859-15', 'l9'],
        'ISO-8859-16' => ['iso-8859-16'],
        'KOI8-R' => ['cskoi8r', 'koi', 'koi8', 'koi8-r', 'koi8_r'],
        'KOI8-U' => ['koi8-ru', 'koi8-u'],
        'macintosh' => ['csmacintosh', 'mac', 'macintosh', 'x-mac-roman'],
        'windows-874' => ['dos-874', 'iso-8859-11', 'iso8859-11', 'iso885911', 'tis-620', 'windows-874'],
        'windows-1250' => ['cp1250', 'windows-1250', 'x-cp1250'],
        'windows-1251' => ['cp1251', 'windows-1251', 'x-cp1251'],
        'windows-1252' => ['ansi_x3.4-1968', 'ascii', 'cp1252', 'cp819', 'csisolatin1', 'ibm819', 'iso-8859-1', 'iso-ir-100', 'iso8859-1', 'iso88591', 'iso_8859-1', 'iso_8859-1:1987', 'l1', 'latin1', 'us-ascii', 'windows-1252', 'x-cp1252'],
        'windows-1253' => ['cp1253', 'windows-1253', 'x-cp1253'],
        'windows-1254' => ['cp1254', 'csisolatin5', 'iso-8859-9', 'iso-ir-148', 'iso8859-9', 'iso88599', 'iso_8859-9', 'iso_8859-9:1989', 'l5', 'latin5', 'windows-1254', 'x-cp1254'],
        'windows-1255' => ['cp1255', 'windows-1255', 'x-cp1255'],
        'windows-1256' => ['cp1256', 'windows-1256', 'x-cp1256'],
        'windows-1257' => ['cp1257', 'windows-1257', 'x-cp1257'],
        'windows-1258' => ['cp1258', 'windows-1258', 'x-cp1258'],
        'x-mac-cyrillic' => ['x-mac-cyrillic', 'x-mac-ukrainian'],
        'GBK' => ['chinese', 'csgb2312', 'csiso58gb231280', 'gb2312', 'gb_2312', 'gb_2312-80', 'gbk', 'iso-ir-58', 'x-gbk'],
        'gb18030' => ['gb18030'],
        'Big5' => ['big5', 'big5-hkscs', 'cn-big5', 'csbig5', 'x-x-big5'],
        'EUC-JP' => ['cseucpkdfmtjapanese', 'euc-jp', 'x-euc-jp'],
        'ISO-2022-JP' => ['csiso2022jp', 'iso-2022-jp'],
        'Shift_JIS' => ['csshiftjis', 'ms932', 'ms_kanji', 'shift-jis', 'shift_jis', 'sjis', 'windows-31j', 'x-sjis'],
        'EUC-KR' => ['cseuckr', 'csksc56011987', 'euc-kr', 'iso-ir-149', 'korean', 'ks_c_5601-1987', 'ks_c_5601-1989', 'ksc5601', 'ksc_5601', 'windows-949'],
        'replacement' => ['csiso2022kr', 'hz-gb-2312', 'iso-2022-cn', 'iso-2022-cn-ext', 'iso-2022-kr', 'replacement'],
        'UTF-16BE' => ['unicodefffe', 'utf-16be'],
        'UTF-16LE' => ['csunicode', 'iso-10646-ucs-2', 'ucs-2', 'unicode', 'unicodefeff', 'utf-16', 'utf-16le'],
        'x-user-defined' => ['x-user-defined'],
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
            return self::utf16(substr($html, 2), true);
        }
        if (strncmp($html, "\xFF\xFE", 2) === 0) {
            return self::utf16(substr($html, 2), false);
        }

        $encoding = self::declared($html);
        if ($encoding === null) {
            $encoding = preg_match('//u', $html) ? 'UTF-8' : 'windows-1252';
        }

        return self::decode($html, $encoding);
    }

    /**
     * $text with each byte sequence invalid in UTF-8 as one U+FFFD, as a
     * browser's UTF-8 decoder writes it: a sequence ends at the first byte
     * that cannot continue it, and that byte is read again.
     *
     * @param string $text
     * @return string
     */
    public static function scrub($text)
    {
        $text = (string) $text;
        if (preg_match('//u', $text)) {
            return $text;
        }

        // Read in windows, each ending where no sequence is split. A sequence
        // is at most four bytes from its lead, so only a lead in the last
        // three bytes can reach past the edge, and the window then ends
        // before it. A window that is UTF-8 is kept as it is.
        $out = '';
        $length = strlen($text);
        for ($at = 0; $at < $length; $at = $end) {
            $end = min($at + 65536, $length);
            for ($lead = $end - 1; $end < $length && $lead > $at && $lead >= $end - 3; $lead--) {
                if (ord($text[$lead]) >= 0xC0) {
                    $end = $lead;
                    break;
                }
            }
            $window = substr($text, $at, $end - $at);
            $out .= preg_match('//u', $window) ? $window : self::scrubBytes($window);
        }

        return $out;
    }

    /**
     * @param string $text Not UTF-8
     * @return string
     */
    private static function scrubBytes($text)
    {
        $high = '';
        for ($byte = 0x80; $byte <= 0xFF; $byte++) {
            $high .= chr($byte);
        }

        $out = '';
        $length = strlen($text);
        for ($at = 0; $at < $length;) {
            $ascii = strcspn($text, $high, $at);
            if ($ascii > 0) {
                $out .= substr($text, $at, $ascii);
                $at += $ascii;
                continue;
            }

            // How many bytes the lead at $at needs, and the range its first
            // continuation byte must fall in.
            $lead = ord($text[$at]);
            if ($lead >= 0xC2 && $lead <= 0xDF) {
                list($needs, $low, $top) = [1, 0x80, 0xBF];
            } elseif ($lead >= 0xE0 && $lead <= 0xEF) {
                list($needs, $low, $top) = [2, $lead === 0xE0 ? 0xA0 : 0x80, $lead === 0xED ? 0x9F : 0xBF];
            } elseif ($lead >= 0xF0 && $lead <= 0xF4) {
                list($needs, $low, $top) = [3, $lead === 0xF0 ? 0x90 : 0x80, $lead === 0xF4 ? 0x8F : 0xBF];
            } else {
                $out .= "\u{FFFD}";
                $at++;
                continue;
            }

            $read = 1;
            while ($read <= $needs && $at + $read < $length) {
                $next = ord($text[$at + $read]);
                if ($next < $low || $next > $top) {
                    break;
                }
                list($low, $top) = [0x80, 0xBF];
                $read++;
            }

            $out .= $read === $needs + 1 ? substr($text, $at, $read) : "\u{FFFD}";
            $at += $read;
        }

        return $out;
    }

    /**
     * The encoding a label names, as a browser reads it, or null when the
     * label names none.
     *
     * @param string $label
     * @return string|null
     */
    public static function encoding($label)
    {
        $label = strtolower(trim((string) $label, "\t\n\f\r "));
        foreach (self::ENCODINGS as $encoding => $labels) {
            if (in_array($label, $labels, true)) {
                return $encoding;
            }
        }

        return null;
    }

    /**
     * The encoding a meta in the first 1024 bytes declares, as a browser's
     * prescan reads them (HTML, "prescan a byte stream to determine its
     * encoding"), or null. A meta declares by its charset attribute, or by
     * the charset= in its content when it also says http-equiv="content-type";
     * one whose label names no encoding is passed over for the next. A
     * comment, which `<!-->` already closes, another tag's attributes and a
     * `<!`, `</` or `<?` construct are skipped. UTF-16 declared there means
     * UTF-8, since bytes a meta was read from as ASCII are not UTF-16, and
     * x-user-defined windows-1252.
     *
     * @param string $html
     * @return string|null
     */
    private static function declared($html)
    {
        $bytes = substr($html, 0, 1024);
        $length = strlen($bytes);

        for ($at = 0; $at < $length; $at++) {
            if (substr($bytes, $at, 4) === '<!--') {
                $end = strpos($bytes, '-->', $at + 2);
                if ($end === false) {
                    return null;
                }
                $at = $end + 2;
                continue;
            }

            if (preg_match('/\G<meta[\t\n\f\r \/]/i', $bytes, $unused, 0, $at)) {
                $at += 5;
                $seen = [];
                $gotPragma = false;
                $needPragma = null;
                $charset = null;
                while (($attribute = self::attribute($bytes, $at)) !== null) {
                    if ($attribute === false) {
                        return null;
                    }
                    list($name, $value) = $attribute;
                    if (isset($seen[$name])) {
                        continue;
                    }
                    $seen[$name] = true;

                    if ($name === 'http-equiv') {
                        $gotPragma = $gotPragma || $value === 'content-type';
                    } elseif ($name === 'content' && $charset === null) {
                        $label = self::charsetIn($value);
                        $encoding = $label === null ? null : self::encoding($label);
                        if ($encoding !== null) {
                            $charset = $encoding;
                            $needPragma = true;
                        }
                    } elseif ($name === 'charset') {
                        $encoding = self::encoding($value);
                        $charset = $encoding === null ? false : $encoding;
                        $needPragma = false;
                    }
                }

                if ($needPragma === null || ($needPragma && !$gotPragma) || !is_string($charset)) {
                    continue;
                }
                if ($charset === 'UTF-16BE' || $charset === 'UTF-16LE') {
                    return 'UTF-8';
                }

                return $charset === 'x-user-defined' ? 'windows-1252' : $charset;
            }

            if (preg_match('/\G<\/?[A-Za-z]/', $bytes, $unused, 0, $at)) {
                $at += strcspn($bytes, "\t\n\f\r >", $at);
                while (($attribute = self::attribute($bytes, $at)) !== null) {
                    if ($attribute === false) {
                        return null;
                    }
                }
                continue;
            }

            if (preg_match('/\G<[!\/?]/', $bytes, $unused, 0, $at)) {
                $end = strpos($bytes, '>', $at + 1);
                if ($end === false) {
                    return null;
                }
                $at = $end;
            }
        }

        return null;
    }

    /**
     * The attribute at $at, as the prescan's "get an attribute" reads it:
     * [name, value], both in ASCII lowercase, with $at left after it; null at
     * the tag's end; false when the bytes run out first.
     *
     * @param string $bytes
     * @param int $at
     * @return array|false|null
     */
    private static function attribute($bytes, &$at)
    {
        $length = strlen($bytes);
        $at += strspn($bytes, "\t\n\f\r /", $at);
        if ($at >= $length) {
            return false;
        }
        if ($bytes[$at] === '>') {
            return null;
        }

        $name = '';
        for (;; $at++) {
            if ($at >= $length) {
                return false;
            }
            $byte = $bytes[$at];
            if ($byte === '=' && $name !== '') {
                $at++;
                break;
            }
            if (strpos("\t\n\f\r ", $byte) !== false) {
                $at += strspn($bytes, "\t\n\f\r ", $at);
                if ($at >= $length) {
                    return false;
                }
                if ($bytes[$at] !== '=') {
                    return [$name, ''];
                }
                $at++;
                break;
            }
            if ($byte === '/' || $byte === '>') {
                return [$name, ''];
            }
            $name .= self::lower($byte);
        }

        $at += strspn($bytes, "\t\n\f\r ", $at);
        if ($at >= $length) {
            return false;
        }
        $quote = $bytes[$at];
        if ($quote === '"' || $quote === "'") {
            $end = strpos($bytes, $quote, $at + 1);
            if ($end === false) {
                return false;
            }
            $value = substr($bytes, $at + 1, $end - $at - 1);
            $at = $end + 1;

            return [$name, self::lower($value)];
        }
        if ($quote === '>') {
            return [$name, ''];
        }

        $run = strcspn($bytes, "\t\n\f\r >", $at);
        if ($at + $run >= $length) {
            return false;
        }
        $value = substr($bytes, $at, $run);
        $at += $run;

        return [$name, self::lower($value)];
    }

    /**
     * The label a content attribute's charset= gives, as the HTML standard's
     * "extracting a character encoding from a meta element" reads it, or null.
     *
     * @param string $content
     * @return string|null
     */
    private static function charsetIn($content)
    {
        $at = 0;
        while (($found = stripos($content, 'charset', $at)) !== false) {
            $at = $found + 7;
            $at += strspn($content, "\t\n\f\r ", $at);
            if (substr($content, $at, 1) !== '=') {
                continue;
            }
            $at++;
            $at += strspn($content, "\t\n\f\r ", $at);

            $quote = substr($content, $at, 1);
            if ($quote === '"' || $quote === "'") {
                $end = strpos($content, $quote, $at + 1);

                return $end === false ? null : substr($content, $at + 1, $end - $at - 1);
            }
            $label = substr($content, $at, strcspn($content, "\t\n\f\r ;", $at));

            return $label === '' ? null : $label;
        }

        return null;
    }

    /**
     * @param string $text
     * @return string $text with A to Z lowercased, whatever the locale
     */
    private static function lower($text)
    {
        return strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * $bytes, in $encoding, as UTF-8.
     *
     * @param string $bytes
     * @param string $encoding One ENCODINGS names, other than UTF-16 and x-user-defined
     * @return string
     */
    private static function decode($bytes, $encoding)
    {
        return $encoding === 'UTF-8' ? self::scrub($bytes) : Decoder::decode($bytes, $encoding);
    }

    /**
     * $bytes in UTF-16 as UTF-8: a surrogate without its pair, and an odd
     * byte at the end, as U+FFFD.
     *
     * @param string $bytes
     * @param bool $bigEndian
     * @return string
     */
    private static function utf16($bytes, $bigEndian)
    {
        $out = '';
        $lead = null;
        $length = strlen($bytes);
        for ($at = 0; $at + 1 < $length; $at += 2) {
            $unit = $bigEndian
                ? (ord($bytes[$at]) << 8) | ord($bytes[$at + 1])
                : (ord($bytes[$at + 1]) << 8) | ord($bytes[$at]);

            if ($lead !== null) {
                if ($unit >= 0xDC00 && $unit <= 0xDFFF) {
                    $out .= Decoder::utf8(0x10000 + (($lead - 0xD800) << 10) + ($unit - 0xDC00));
                    $lead = null;
                    continue;
                }
                $out .= "\u{FFFD}";
                $lead = null;
            }

            if ($unit >= 0xD800 && $unit <= 0xDBFF) {
                $lead = $unit;
            } elseif ($unit >= 0xDC00 && $unit <= 0xDFFF) {
                $out .= "\u{FFFD}";
            } else {
                $out .= Decoder::utf8($unit);
            }
        }

        return $lead !== null || $length % 2 === 1 ? $out . "\u{FFFD}" : $out;
    }
}
