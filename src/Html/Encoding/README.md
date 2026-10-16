# Encoding indexes

The indexes of the [WHATWG Encoding Standard](https://encoding.spec.whatwg.org/), which
`Html\Decoder` decodes every encoding but UTF-8 and UTF-16 with, as a browser does.

| File | Index | Entries |
|---|---|---|
| `index-single-byte.bin` | each single-byte encoding's bytes 0x80 to 0xFF, in `Decoder::SINGLE_BYTE` order | 27 × 128 |
| `index-big5.bin` | Big5 | 19,782 |
| `index-euc-kr.bin` | EUC-KR | 23,940 |
| `index-gb18030.bin` | gb18030, two-byte | 23,940 |
| `index-gb18030-ranges.bin` | gb18030 ranges, four-byte | 207 |
| `index-jis0208.bin` | jis0208 | 11,280 |
| `index-jis0212.bin` | jis0212 | 8,836 |

Each index file holds three bytes per pointer, the code point big-endian, and zero where the
index has none. `index-gb18030-ranges.bin` holds four-byte pairs: the pointer each linear run
starts at, and its code point.

`generate.mjs` writes them, and `tests/fixtures/encoding-vectors.json`, from `TextDecoder` in
Deno, which is encoding_rs, Firefox's implementation of the standard, so the tables are the
ones a browser decodes with:

    deno run --allow-write src/Html/Encoding/generate.mjs src/Html/Encoding tests/fixtures/encoding-vectors.json

It checks, as it writes, that every single-byte encoding reads ASCII as itself, that EUC-JP and
Shift_JIS read jis0208 alike, and the standard's fixed points (Big5's two-character pointers,
gb18030's pointer 7457 and its first supplementary pointer).

The indexes are the Encoding Standard's, © WHATWG (Apple, Google, Mozilla, Microsoft),
licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
