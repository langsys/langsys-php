<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Html\Decoder;
use PHPUnit\Framework\TestCase;

/**
 * Each encoding decodes as a browser decodes it: tests/fixtures/
 * encoding-vectors.json holds byte strings in every encoding but UTF-8 and
 * UTF-16, valid and not, with what Firefox's decoder (encoding_rs, in Deno)
 * makes of them, written by src/Html/Encoding/generate.mjs.
 */
class DecoderTest extends TestCase
{
    public function vectors(): array
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__) . '/fixtures/encoding-vectors.json'), true);
        $cases = [];
        foreach ($fixture['vectors'] as $n => $vector) {
            $cases[$vector['encoding'] . ' #' . $n] = [$vector['encoding'], base64_decode($vector['bytes']), $vector['text']];
        }

        return $cases;
    }

    /**
     * @dataProvider vectors
     */
    public function testEachEncodingDecodesAsABrowserDecodesIt(string $encoding, string $bytes, string $text): void
    {
        $this->assertSame($text, Decoder::decode($bytes, $encoding), bin2hex($bytes));
    }

    public function testEveryEncodingTheFixtureCoversIsDecodedHere(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__) . '/fixtures/encoding-vectors.json'), true);

        $this->assertSame(
            array_merge(Decoder::SINGLE_BYTE, ['Shift_JIS', 'EUC-KR', 'Big5', 'GBK', 'gb18030', 'EUC-JP', 'ISO-2022-JP']),
            array_values(array_unique(array_column($fixture['vectors'], 'encoding')))
        );
    }

    /**
     * The edges a random vector rarely reaches.
     */
    public function testTheStandardsEdgesDecodeAsItSays(): void
    {
        $this->assertSame("\u{E7C7}", Decoder::decode("\x81\x35\xF4\x37", 'gb18030'), 'pointer 7457');
        $this->assertSame("\u{10000}", Decoder::decode("\x90\x30\x81\x30", 'gb18030'), 'the first supplementary pointer');
        $this->assertSame("\u{FFFD}", Decoder::decode("\x84\x31\xA5\x30", 'gb18030'), 'a pointer past the ranges');
        $this->assertSame("\u{20AC}", Decoder::decode("\x80", 'GBK'));
        $this->assertSame("\u{E000}", Decoder::decode("\xF0\x40", 'Shift_JIS'), 'the private-use range');
        $this->assertSame("\u{FF61}\u{FF9F}", Decoder::decode("\x8E\xA1\x8E\xDF", 'EUC-JP'), 'half-width katakana');
        $this->assertSame("\u{FFFD}", Decoder::decode("\x1B\$Ba", 'ISO-2022-JP'), 'a lead with no trail');
        $this->assertSame("\u{FFFD}", Decoder::decode("\x1B", 'ISO-2022-JP'), 'an escape the input ends inside');
        $this->assertSame("\u{FFFD}(", Decoder::decode("\x1B(", 'ISO-2022-JP'), 'an escape cut short, its lead read again');
        $this->assertSame("\u{FFFD}", Decoder::decode('anything', 'replacement'));
        $this->assertSame('', Decoder::decode('', 'replacement'));
        $this->assertSame("\u{05D0}", Decoder::decode("\xE0", 'ISO-8859-8-I'), 'ISO-8859-8-I reads as ISO-8859-8');
    }
}
