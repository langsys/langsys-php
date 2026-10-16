// Writes the WHATWG Encoding Standard's indexes, as a browser's decoder reads
// them, for Html\Decoder, and the vectors tests/Html/DecoderTest.php checks it
// against. Every value comes from TextDecoder, which in Deno is encoding_rs,
// Firefox's implementation of the standard, so the tables are the browser's.
//
//   deno run --allow-write src/Html/Encoding/generate.mjs src/Html/Encoding tests/fixtures/encoding-vectors.json
//
// Index files hold three bytes per pointer, the code point big-endian, zero
// where the index has none. index-gb18030-ranges.bin holds four-byte pairs of
// (pointer, code point) starting each linear run.

const [outDir, vectorsPath] = Deno.args;
if (!outDir || !vectorsPath) {
  throw new Error("usage: generate.mjs <index directory> <vectors file>");
}

export const SINGLE_BYTE = [
  "IBM866", "ISO-8859-2", "ISO-8859-3", "ISO-8859-4", "ISO-8859-5", "ISO-8859-6", "ISO-8859-7",
  "ISO-8859-8", "ISO-8859-10", "ISO-8859-13", "ISO-8859-14", "ISO-8859-15", "ISO-8859-16", "KOI8-R",
  "KOI8-U", "macintosh", "windows-874", "windows-1250", "windows-1251", "windows-1252", "windows-1253",
  "windows-1254", "windows-1255", "windows-1256", "windows-1257", "windows-1258", "x-mac-cyrillic",
];

function decode(encoding, bytes) {
  return new TextDecoder(encoding).decode(Uint8Array.from(bytes));
}

// The one code point a byte sequence decodes to, or 0 when it is an error or
// decodes to more than one.
function codePoint(encoding, bytes) {
  const text = decode(encoding, bytes);
  const points = [...text];
  if (points.length !== 1 || points[0] === "�") {
    return 0;
  }
  return points[0].codePointAt(0);
}

// The one code point a byte sequence decodes to, U+FFFD included, or 0 when
// it decodes to more than one.
function onlyPoint(encoding, bytes) {
  const points = [...decode(encoding, bytes)];
  return points.length === 1 ? points[0].codePointAt(0) : 0;
}

function check(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function writeIndex(name, points) {
  const bytes = new Uint8Array(points.length * 3);
  points.forEach((point, i) => {
    bytes[i * 3] = (point >> 16) & 0xff;
    bytes[i * 3 + 1] = (point >> 8) & 0xff;
    bytes[i * 3 + 2] = point & 0xff;
  });
  Deno.writeFileSync(`${outDir}/${name}.bin`, bytes);
}

// Single-byte encodings: ASCII as itself, each other byte its index entry.
const singleByte = [];
for (const encoding of SINGLE_BYTE) {
  for (let byte = 0; byte < 0x80; byte++) {
    check(codePoint(encoding, [byte]) === byte, `${encoding}: ASCII ${byte}`);
  }
  for (let byte = 0x80; byte <= 0xff; byte++) {
    singleByte.push(codePoint(encoding, [byte]));
  }
}
writeIndex("index-single-byte", singleByte);

// Big5: pointer = (lead - 0x81) * 157 + (trail - (trail < 0x7F ? 0x40 : 0x62)).
const big5 = [];
for (let pointer = 0; pointer < 126 * 157; pointer++) {
  const lead = Math.floor(pointer / 157) + 0x81;
  const offset = pointer % 157;
  const trail = offset < 63 ? offset + 0x40 : offset + 0x62;
  big5.push([1133, 1135, 1164, 1166].includes(pointer) ? 0 : codePoint("big5", [lead, trail]));
}
check(decode("big5", [0x88, 0x62]) === "Ê̄", "big5 pointer 1133");
writeIndex("index-big5", big5);

// EUC-KR: pointer = (lead - 0x81) * 190 + (trail - 0x41).
const eucKr = [];
for (let pointer = 0; pointer < 126 * 190; pointer++) {
  eucKr.push(codePoint("euc-kr", [Math.floor(pointer / 190) + 0x81, (pointer % 190) + 0x41]));
}
writeIndex("index-euc-kr", eucKr);

// gb18030, two bytes: pointer = (lead - 0x81) * 190 + (trail - (trail < 0x7F ? 0x40 : 0x41)).
const gb18030 = [];
for (let pointer = 0; pointer < 126 * 190; pointer++) {
  const offset = pointer % 190;
  gb18030.push(codePoint("gb18030", [Math.floor(pointer / 190) + 0x81, offset < 63 ? offset + 0x40 : offset + 0x41]));
}
writeIndex("index-gb18030", gb18030);

// gb18030, four bytes below 0x10000: the ranges, each a pointer and the code
// point it starts at. Pointer 7457 is U+E7C7, outside any run.
function fourBytes(pointer) {
  const rest = pointer % 12600;
  return [Math.floor(pointer / 12600) + 0x81, Math.floor(rest / 1260) + 0x30, Math.floor((rest % 1260) / 10) + 0x81, (rest % 10) + 0x30];
}
const ranges = [];
for (let pointer = 0; pointer <= 39419; pointer++) {
  // The last run ends in U+FFFD itself, a character there.
  const point = onlyPoint("gb18030", fourBytes(pointer));
  check(point !== 0, `gb18030 four-byte pointer ${pointer}`);
  if (pointer === 7457) {
    check(point === 0xe7c7, "gb18030 pointer 7457");
    continue;
  }
  const last = ranges[ranges.length - 1];
  const linear = last && pointer !== 7458 && last[1] + (pointer - last[0]) === point;
  if (!linear) {
    ranges.push([pointer, point]);
  }
}
check(codePoint("gb18030", fourBytes(39420)) === 0, "gb18030 pointer 39420 has no code point");
check(codePoint("gb18030", [0x90, 0x30, 0x81, 0x30]) === 0x10000, "gb18030 pointer 189000");
const rangeBytes = new Uint8Array(ranges.length * 8);
const view = new DataView(rangeBytes.buffer);
ranges.forEach(([pointer, point], i) => {
  view.setUint32(i * 8, pointer);
  view.setUint32(i * 8 + 4, point);
});
Deno.writeFileSync(`${outDir}/index-gb18030-ranges.bin`, rangeBytes);

// jis0208, through Shift_JIS, whose pointers reach the IBM extensions:
// pointer = (lead - (lead < 0xA0 ? 0x81 : 0xC1)) * 188 + (trail - (trail < 0x7F ? 0x40 : 0x41)).
// 8836 to 10715 are the private-use range Shift_JIS decodes by formula.
const jis0208 = [];
for (let pointer = 0; pointer < 60 * 188; pointer++) {
  const index = Math.floor(pointer / 188);
  const lead = index < 31 ? index + 0x81 : index - 31 + 0xe0;
  const offset = pointer % 188;
  jis0208.push(pointer >= 8836 && pointer <= 10715 ? 0 : codePoint("shift_jis", [lead, offset < 63 ? offset + 0x40 : offset + 0x41]));
}
for (let pointer = 0; pointer < 94 * 94; pointer++) {
  const point = codePoint("euc-jp", [Math.floor(pointer / 94) + 0xa1, (pointer % 94) + 0xa1]);
  check(point === jis0208[pointer], `jis0208 pointer ${pointer}: EUC-JP and Shift_JIS agree`);
}
writeIndex("index-jis0208", jis0208);

// jis0212, through EUC-JP's 0x8F lead.
const jis0212 = [];
for (let pointer = 0; pointer < 94 * 94; pointer++) {
  jis0212.push(codePoint("euc-jp", [0x8f, Math.floor(pointer / 94) + 0xa1, (pointer % 94) + 0xa1]));
}
writeIndex("index-jis0212", jis0212);

// Vectors: byte strings in each encoding, valid and not, with what the browser
// decodes them to.
// mulberry32: a seeded generator whose every bit varies, so each byte value
// and each ending occurs.
let seed = 0x5eed;
function random(n) {
  seed = (seed + 0x6d2b79f5) | 0;
  let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return Math.floor((((t ^ (t >>> 14)) >>> 0) / 4294967296) * n);
}
function pick(list) {
  return list[random(list.length)];
}
const pieces = {
  ascii: () => [pick([0x41, 0x61, 0x20, 0x3c, 0x3e, 0x26, 0x30, 0x5c, 0x7e, 0x0a, 0x7f, 0x1a, 0x1b, 0x0e, 0x0f])],
  any: () => [random(256)],
  pair: () => [0x81 + random(126), random(256)],
  triple: () => [0x8e + random(2), random(256), random(256)],
  gbFour: () => [0x81 + random(126), 0x30 + random(10), 0x81 + random(126), 0x30 + random(10)],
  escape: () => pick([[0x1b, 0x28, 0x42], [0x1b, 0x28, 0x4a], [0x1b, 0x28, 0x49], [0x1b, 0x24, 0x40], [0x1b, 0x24, 0x42], [0x1b, 0x24], [0x1b, 0x28, 0x43], [0x1b]]),
  jisPair: () => [0x21 + random(94), 0x21 + random(94)],
};
const shapes = {
  single: ["ascii", "any", "any"],
  "Shift_JIS": ["ascii", "any", "pair", "pair"],
  "EUC-KR": ["ascii", "any", "pair", "pair"],
  "Big5": ["ascii", "any", "pair", "pair"],
  "GBK": ["ascii", "any", "pair", "pair", "gbFour"],
  "gb18030": ["ascii", "any", "pair", "gbFour", "gbFour"],
  "EUC-JP": ["ascii", "any", "pair", "triple", "triple"],
  "ISO-2022-JP": ["ascii", "escape", "jisPair", "jisPair", "any"],
};
const vectors = [];
for (const encoding of [...SINGLE_BYTE, "Shift_JIS", "EUC-KR", "Big5", "GBK", "gb18030", "EUC-JP", "ISO-2022-JP"]) {
  const shape = shapes[encoding] || shapes.single;
  for (let n = 0; n < 60; n++) {
    let bytes = [];
    const parts = 1 + random(24);
    for (let p = 0; p < parts; p++) {
      bytes = bytes.concat(pieces[pick(shape)]());
    }
    vectors.push({ encoding, bytes: btoa(String.fromCharCode(...bytes)), text: decode(encoding, bytes) });
  }
}
// And the standard's edges: every gb18030 range boundary, jis0212 pairs,
// each ISO-2022-JP state, and each lead the input ends inside.
function add(encoding, bytes) {
  vectors.push({ encoding, bytes: btoa(String.fromCharCode(...bytes)), text: decode(encoding, bytes) });
}
for (const [pointer] of ranges) {
  add("gb18030", [...fourBytes(pointer), 0x41, ...(pointer > 0 ? fourBytes(pointer - 1) : [])]);
}
add("gb18030", fourBytes(7457));
add("gb18030", [0x90, 0x30, 0x81, 0x30, 0xe3, 0x32, 0x9a, 0x35, 0xe3, 0x32, 0x9a, 0x36]);
for (let pointer = 0; pointer < 94 * 94; pointer += 37) {
  add("EUC-JP", [0x8f, Math.floor(pointer / 94) + 0xa1, (pointer % 94) + 0xa1, 0x41]);
}
for (const bytes of [
  [0x1b, 0x28, 0x4a, 0x5c, 0x7e, 0x41, 0x0e, 0x80], [0x1b, 0x28, 0x49, 0x21, 0x5f, 0x60, 0x41], [0x1b, 0x24, 0x42, 0x30, 0x21, 0x30],
  [0x1b, 0x24, 0x42, 0x1b, 0x28, 0x42, 0x41], [0x1b, 0x24, 0x42, 0x30, 0x1b, 0x28, 0x42], [0x1b, 0x28, 0x42, 0x1b, 0x28, 0x42, 0x41],
  [0x1b, 0x24, 0x40, 0x24, 0x22], [0x1b, 0x28, 0x58, 0x41], [0x1b, 0x24, 0x58], [0x41, 0x1b, 0x28],
]) {
  add("ISO-2022-JP", bytes);
}
for (const [encoding, leads] of [["Big5", [0x81, 0xa4, 0xfe]], ["EUC-KR", [0x81, 0xb0, 0xfe]], ["Shift_JIS", [0x81, 0x9f, 0xe0, 0xfc]], ["EUC-JP", [0x8e, 0x8f, 0xa1, 0xfe]], ["GBK", [0x81, 0xfe]], ["gb18030", [0x81, 0xfe]]]) {
  for (const lead of leads) {
    add(encoding, [0x41, lead]);
    add(encoding, [lead, 0x30]);
    add(encoding, [lead, 0x30, 0x81]);
  }
}
for (let byte = 0x80; byte <= 0xff; byte++) {
  add("Shift_JIS", [byte, 0x41]);
  add("EUC-JP", [byte, 0x41]);
  add("EUC-KR", [byte, 0x41]);
  add("Big5", [byte, 0x41]);
}

Deno.writeTextFileSync(vectorsPath, JSON.stringify({
  "": "Byte strings and what a browser decodes them to: TextDecoder in Deno " + Deno.version.deno + " (encoding_rs, Firefox's decoder). Written by src/Html/Encoding/generate.mjs.",
  vectors,
}, null, 1) + "\n");

console.log(`single-byte ${SINGLE_BYTE.length}, big5 ${big5.length}, euc-kr ${eucKr.length}, gb18030 ${gb18030.length}, ranges ${ranges.length}, jis0208 ${jis0208.length}, jis0212 ${jis0212.length}, vectors ${vectors.length}`);
