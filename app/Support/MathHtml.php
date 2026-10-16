<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Menyiapkan jawaban siswa (HTML dari Trix / teks tempelan) supaya rumus
 * matematikanya benar-benar dirender KaTeX, bukan tampil sebagai LaTeX mentah.
 *
 * Masalah yang diselesaikan:
 *   1. KaTeX auto-render (katex-init.blade.php) & MathContent.tsx hanya mengenali
 *      delimiter $…$, $$…$$, \(…\), \[…\]. Siswa biasanya menempel LaTeX tanpa
 *      delimiter sama sekali (mis. `\sin \theta = \frac{9}{41}`), jadi tidak pernah dirender.
 *   2. Blok `\begin{align*}…\end{align*}` sering terpotong markup karena Trix
 *      memecah tiap baris jadi <div>/<br> sendiri, sehingga tidak bisa dideteksi
 *      per text node.
 *   3. Konten siswa sebelumnya di-inject mentah ke panel guru (risiko stored XSS).
 *
 * Alurnya: bungkus environment lintas-markup → sanitasi allowlist → bungkus
 * inline math per text node. Output aman di-inject sebagai HtmlString.
 */
class MathHtml
{
    /** Environment LaTeX yang dirender sebagai display math. */
    private const ENVIRONMENTS = [
        'align*', 'align', 'aligned', 'alignat*', 'alignat', 'array',
        'bmatrix', 'Bmatrix', 'cases', 'equation*', 'equation',
        'gather*', 'gather', 'gathered', 'matrix', 'pmatrix',
        'smallmatrix', 'split', 'vmatrix', 'Vmatrix',
    ];

    /**
     * Command LaTeX yang dianggap "pasti math" walau tidak diikuti argumen {…}.
     * Command di luar daftar ini hanya dianggap math kalau langsung diikuti
     * `{` atau `[` — supaya teks seperti `C:\Users\nama` tidak ikut terbawa.
     */
    private const MATH_COMMANDS = [
        // Huruf Yunani
        'alpha', 'beta', 'gamma', 'delta', 'epsilon', 'varepsilon', 'zeta', 'eta',
        'theta', 'vartheta', 'iota', 'kappa', 'lambda', 'mu', 'nu', 'xi', 'pi',
        'varpi', 'rho', 'varrho', 'sigma', 'varsigma', 'tau', 'upsilon', 'phi',
        'varphi', 'chi', 'psi', 'omega', 'Gamma', 'Delta', 'Theta', 'Lambda',
        'Xi', 'Pi', 'Sigma', 'Upsilon', 'Phi', 'Psi', 'Omega',
        // Trigonometri & fungsi
        'sin', 'cos', 'tan', 'cot', 'sec', 'csc', 'arcsin', 'arccos', 'arctan',
        'sinh', 'cosh', 'tanh', 'log', 'ln', 'lg', 'exp', 'deg', 'gcd',
        // Operator & relasi
        'cdot', 'cdots', 'ldots', 'dots', 'times', 'div', 'pm', 'mp', 'ast',
        'star', 'circ', 'bullet', 'neq', 'ne', 'leq', 'le', 'geq', 'ge',
        'approx', 'equiv', 'cong', 'sim', 'simeq', 'propto', 'infty', 'partial',
        'nabla', 'forall', 'exists', 'in', 'notin', 'subset', 'supset',
        'subseteq', 'supseteq', 'cup', 'cap', 'emptyset', 'varnothing', 'angle',
        'perp', 'parallel', 'prime', 'degree', 'triangle', 'square',
        // Struktur & limit
        'sum', 'prod', 'int', 'iint', 'iiint', 'oint', 'lim', 'limsup',
        'liminf', 'max', 'min', 'sup', 'inf', 'to', 'rightarrow', 'leftarrow',
        'Rightarrow', 'Leftarrow', 'leftrightarrow', 'Leftrightarrow', 'mapsto',
        'implies', 'iff', 'left', 'right', 'bmod', 'pmod', 'ldots',
        // Spasi & pembungkus
        'quad', 'qquad', 'thinspace', 'negthinspace', 'displaystyle',
    ];

    /** Tag HTML yang diizinkan lolos sanitasi. */
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'code', 'del', 'div', 'em', 'figcaption',
        'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins',
        'li', 'ol', 'p', 'pre', 's', 'span', 'strike', 'strong', 'sub', 'sup',
        'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    /** Tag yang dibuang total beserta isinya (bukan di-unwrap). */
    private const DROPPED_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button'];

    /** Atribut yang diizinkan per tag; '*' berlaku untuk semua tag. */
    private const ALLOWED_ATTRIBUTES = [
        '*' => ['class', 'title'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    /**
     * Render jawaban siswa menjadi HTML siap tampil: sudah tersanitasi dan
     * seluruh math-nya terbungkus delimiter yang dikenali KaTeX.
     */
    public static function render(?string $raw): string
    {
        $raw = (string) $raw;

        if (trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === ''
            && ! preg_match('/<(img|hr)\b/i', $raw)) {
            return '';
        }

        $html = static::looksLikeHtml($raw)
            ? $raw
            : nl2br(e($raw), false);

        $rendered = static::sanitizeAndWrapInline(static::wrapEnvironments($html));

        if (static::plainText($rendered) === static::plainText($raw)) {
            return $rendered;
        }

        // Parser membuang isi — terjadi mis. saat markup bersarang lebih dari 256
        // level (batas keras libxml). Jangan pernah menampilkan jawaban kosong:
        // ratakan dulu jadi teks berbaris, lalu render ulang.
        $flattened = static::sanitizeAndWrapInline(
            static::wrapEnvironments(nl2br(e(static::markupToText($raw)), false))
        );

        return static::plainText($flattened) === static::plainText($raw)
            ? $flattened
            : nl2br(e(static::markupToText($raw)), false);
    }

    /**
     * Teks murni untuk membandingkan sebelum/sesudah render: tag, entity, spasi,
     * dan delimiter `$` dibuang supaya yang dibandingkan benar-benar isinya.
     */
    private static function plainText(string $html): string
    {
        // Isi <script>/<style>/<noscript>/<template> memang SENGAJA dibuang
        // sanitizer. Kalau ikut dihitung, pembanding akan menyangka ada teks
        // yang hilang lalu meratakan markup — dan source-nya malah tampil
        // sebagai teks di layar guru.
        $html = preg_replace(
            '#<(script|style|noscript|template)\b[^>]*>.*?</\1\s*>#is',
            '',
            $html
        ) ?? $html;

        return preg_replace(
            '/[\s$]+/u',
            '',
            html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ) ?? '';
    }

    private static function looksLikeHtml(string $raw): bool
    {
        return (bool) preg_match('/<\/?(?:'.implode('|', self::ALLOWED_TAGS).')\b[^>]*>/i', $raw);
    }

    /**
     * Bungkus `\begin{env}…\end{env}` jadi display math ($$…$$) dalam satu span.
     * Markup di dalam blok dibuang (jadi newline) karena baris di environment
     * LaTeX sudah dipisah `\\`, bukan oleh <div>/<br> dari Trix.
     */
    private static function wrapEnvironments(string $html): string
    {
        $envPattern = implode('|', array_map(fn (string $env) => preg_quote($env, '/'), self::ENVIRONMENTS));

        return preg_replace_callback(
            '/\\\\begin\{('.$envPattern.')\}(.*?)\\\\end\{\1\}/s',
            function (array $m): string {
                $tex = static::markupToText('\begin{'.$m[1].'}'.$m[2].'\end{'.$m[1].'}');

                return '<span class="math-block">$$'.e($tex).'$$</span>';
            },
            $html
        ) ?? $html;
    }

    /** HTML → teks LaTeX: tag blok jadi newline, entity di-decode. */
    private static function markupToText(string $html): string
    {
        $text = preg_replace('/<br\b[^>]*>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/(?:div|p|li|tr|h[1-6]|blockquote|pre)>/i', "\n", $text) ?? $text;

        return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Parse HTML (sekalian merapikan markup yang jadi tidak seimbang setelah
     * wrapEnvironments), sanitasi node per node, lalu bungkus inline math
     * di setiap text node.
     */
    private static function sanitizeAndWrapInline(string $html): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>'
            .'<body><div id="math-html-root">'.$html.'</div></body></html>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('math-html-root');

        if (! $root) {
            return nl2br(e(static::markupToText($html)), false);
        }

        static::cleanChildren($root, $doc);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function cleanChildren(DOMNode $parent, DOMDocument $doc): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMText) {
                static::wrapInlineMathNode($node, $doc);

                continue;
            }

            if (! $node instanceof DOMElement) {
                // Komentar, CDATA, processing instruction — tidak perlu ditampilkan.
                $parent->removeChild($node);

                continue;
            }

            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::DROPPED_TAGS, true)) {
                $parent->removeChild($node);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                static::cleanChildren($node, $doc);
                static::unwrap($node, $parent);

                continue;
            }

            static::cleanAttributes($node, $tag);

            // Blok math hasil wrapEnvironments sudah final — jangan dipindai lagi.
            if ($tag === 'span' && str_contains($node->getAttribute('class'), 'math-block')) {
                continue;
            }

            static::cleanChildren($node, $doc);
        }
    }

    private static function unwrap(DOMElement $node, DOMNode $parent): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            $parent->insertBefore($child, $node);
        }

        $parent->removeChild($node);
    }

    private static function cleanAttributes(DOMElement $node, string $tag): void
    {
        $allowed = array_merge(
            self::ALLOWED_ATTRIBUTES['*'],
            self::ALLOWED_ATTRIBUTES[$tag] ?? []
        );

        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $node->removeAttribute($attribute->nodeName);

                continue;
            }

            if (($name === 'href' || $name === 'src') && ! static::isSafeUrl($attribute->nodeValue, $name)) {
                $node->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a' && $node->hasAttribute('href')) {
            $node->setAttribute('target', '_blank');
            $node->setAttribute('rel', 'noopener nofollow');
        }
    }

    private static function isSafeUrl(?string $url, string $attribute): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        // Relatif / anchor / query — tidak membawa skema, aman.
        if (preg_match('/^[^:]*$/', $url) || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        if ($attribute === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp|bmp);base64,#i', $url)) {
            return true;
        }

        return (bool) preg_match('#^(?:https?|mailto|tel)://#i', $url)
            || (bool) preg_match('#^(?:mailto|tel):#i', $url);
    }

    /**
     * Pecah satu text node jadi potongan teks biasa + span math ($…$).
     * Math yang sudah punya delimiter dibiarkan apa adanya (sudah dikenali KaTeX).
     */
    private static function wrapInlineMathNode(DOMText $node, DOMDocument $doc): void
    {
        $text = $node->nodeValue ?? '';

        if ($text === '' || ! preg_match('/[\\\\^_]/', $text)) {
            return;
        }

        $runs = static::findMathRuns($text);

        if ($runs === []) {
            return;
        }

        $fragment = $doc->createDocumentFragment();
        $cursor = 0;

        foreach ($runs as [$start, $end]) {
            if ($start > $cursor) {
                $fragment->appendChild($doc->createTextNode(substr($text, $cursor, $start - $cursor)));
            }

            $span = $doc->createElement('span');
            $span->setAttribute('class', 'math-inline');
            $span->appendChild($doc->createTextNode('$'.substr($text, $start, $end - $start).'$'));
            $fragment->appendChild($span);

            $cursor = $end;
        }

        if ($cursor < strlen($text)) {
            $fragment->appendChild($doc->createTextNode(substr($text, $cursor)));
        }

        $node->parentNode?->replaceChild($fragment, $node);
    }

    /**
     * Cari rentang LaTeX tanpa delimiter di dalam teks.
     * Satu "run" diawali command math dan boleh memuat lem (operator, angka,
     * huruf tunggal) selama masih diapit command; lem berhuruf di ujung
     * dipotong supaya kalimat seperti "\cos \theta lebih besar" tidak ikut.
     *
     * @return array<int, array{0:int, 1:int}>
     */
    private static function findMathRuns(string $text): array
    {
        $protected = static::delimitedRanges($text);
        $runs = [];
        $length = strlen($text);
        $i = 0;

        while ($i < $length) {
            if (static::insideRanges($i, $protected)) {
                $i++;

                continue;
            }

            $atomEnd = static::matchAtom($text, $i);

            if ($atomEnd === null) {
                $i++;

                continue;
            }

            $start = $i;
            $end = $atomEnd;
            $cursor = $atomEnd;
            $numericTail = null;

            while ($cursor < $length && ! static::insideRanges($cursor, $protected)) {
                $next = static::matchAtom($text, $cursor);

                if ($next !== null) {
                    $end = $cursor = $next;
                    $numericTail = null;

                    continue;
                }

                $glue = static::matchGlue($text, $cursor);

                if ($glue === null) {
                    break;
                }

                [$glueEnd, $hasLetter] = $glue;

                if ($hasLetter) {
                    $numericTail = null;
                } elseif (trim(substr($text, $cursor, $glueEnd - $cursor)) !== '') {
                    $numericTail = $glueEnd;
                }

                $cursor = $glueEnd;
            }

            $end = max($end, $numericTail ?? $end);
            $runs[] = [$start, $end];
            $i = $end;
        }

        return $runs;
    }

    /**
     * Rentang math yang sudah berdelimiter ($…$, $$…$$, \(…\), \[…\]) —
     * dilewati karena KaTeX sudah bisa menanganinya.
     *
     * @return array<int, array{0:int, 1:int}>
     */
    private static function delimitedRanges(string $text): array
    {
        $ranges = [];

        $patterns = [
            '/\$\$.+?\$\$/s',
            '/\$[^$\n]+?\$/',
            '/\\\\\(.+?\\\\\)/s',
            '/\\\\\[.+?\\\\\]/s',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as [$value, $offset]) {
                if (static::insideRanges($offset, $ranges)) {
                    continue;
                }

                $ranges[] = [$offset, $offset + strlen($value)];
            }
        }

        return $ranges;
    }

    /**
     * @param  array<int, array{0:int, 1:int}>  $ranges
     */
    private static function insideRanges(int $offset, array $ranges): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($offset >= $start && $offset < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cocokkan satu atom command di posisi $offset, termasuk argumennya
     * (`\frac{a}{b}`, `\sqrt[n]{x}`, `x^{2}`). Mengembalikan offset akhir atau null.
     *
     * `\begin`/`\end` sengaja ditolak: kalau sampai di sini berarti blok
     * environment-nya tidak lengkap, lebih baik dibiarkan jadi teks biasa
     * daripada dibungkus dan gagal dirender.
     */
    /** Satu atom math: command LaTeX, atau pangkat/indeks polos seperti `x^2`. */
    private static function matchAtom(string $text, int $offset): ?int
    {
        if ($text[$offset] === '\\') {
            return static::matchCommand($text, $offset);
        }

        return static::matchPower($text, $offset);
    }

    /**
     * Pangkat/indeks tanpa backslash: `x^2`, `a_1`, `2^{10}`, `H_2`.
     *
     * Sengaja ketat supaya tidak menelan teks biasa:
     *  - basisnya maksimal 2 karakter alfanumerik dan harus di awal kata,
     *    jadi `laporan_akhir_v2`, `bab_2`, dan `2024_01` tidak ikut;
     *  - setelah `^`/`_` wajib ada angka/huruf tunggal atau grup `{…}`.
     */
    private static function matchPower(string $text, int $offset): ?int
    {
        if ($offset > 0 && preg_match('/[A-Za-z0-9]/', $text[$offset - 1])) {
            return null;
        }

        if (! preg_match('/[A-Za-z0-9]{1,2}(?=[\^_])/A', $text, $m, 0, $offset)) {
            return null;
        }

        $base = $offset + strlen($m[0]);
        $end = static::consumeArguments($text, $base);

        return $end > $base ? $end : null;
    }

    private static function matchCommand(string $text, int $offset): ?int
    {
        if (! preg_match('/\\\\(?:([a-zA-Z]+\*?)|\\\\|[,;!:>]|\s)/A', $text, $m, 0, $offset)) {
            return null;
        }

        $name = $m[1] ?? null;
        $end = $offset + strlen($m[0]);

        if ($name !== null) {
            $bare = rtrim($name, '*');

            if ($bare === 'begin' || $bare === 'end') {
                return null;
            }

            $followedByArgument = isset($text[$end]) && ($text[$end] === '{' || $text[$end] === '[');

            if (! $followedByArgument && ! in_array($bare, self::MATH_COMMANDS, true)) {
                return null;
            }
        }

        return static::consumeArguments($text, $end);
    }

    /** Telan `{…}`, `[…]`, `^…`, `_…` yang menempel setelah command. */
    private static function consumeArguments(string $text, int $offset): int
    {
        $length = strlen($text);

        while ($offset < $length) {
            $char = $text[$offset];

            if ($char === '{' || $char === '[') {
                $closed = static::matchBalanced($text, $offset);

                if ($closed === null) {
                    break;
                }

                $offset = $closed;

                continue;
            }

            if ($char === '^' || $char === '_') {
                $next = $offset + 1;

                if ($next < $length && $text[$next] === '{') {
                    $closed = static::matchBalanced($text, $next);

                    if ($closed === null) {
                        break;
                    }

                    $offset = $closed;

                    continue;
                }

                if ($next < $length && preg_match('/[0-9a-zA-Z]/A', $text, $m, 0, $next)) {
                    $offset = $next + 1;

                    continue;
                }

                break;
            }

            break;
        }

        return $offset;
    }

    /** Offset setelah penutup grup `{`/`[` yang seimbang, atau null kalau tidak lengkap. */
    private static function matchBalanced(string $text, int $offset): ?int
    {
        $open = $text[$offset];
        $close = $open === '{' ? '}' : ']';
        $depth = 0;
        $length = strlen($text);

        for ($i = $offset; $i < $length; $i++) {
            $char = $text[$i];

            if ($char === '\\') {
                $i++;

                continue;
            }

            if ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;

                if ($depth === 0) {
                    return $i + 1;
                }
            }
        }

        return null;
    }

    /**
     * Token "lem" antar command: spasi, angka (termasuk desimal koma),
     * operator, grup kurung kurawal, atau huruf tunggal.
     *
     * @return array{0:int, 1:bool}|null [offset akhir, mengandung huruf]
     */
    private static function matchGlue(string $text, int $offset): ?array
    {
        if (preg_match('/[ \t]+/A', $text, $m, 0, $offset)) {
            return [$offset + strlen($m[0]), false];
        }

        if (preg_match('/[0-9]+(?:[.,][0-9]+)?/A', $text, $m, 0, $offset)) {
            return [$offset + strlen($m[0]), false];
        }

        if (preg_match('/(?:[=+\-*\/<>()|^_]|≈|±|×|÷|≤|≥|≠|°)+/Au', $text, $m, 0, $offset)) {
            return [$offset + strlen($m[0]), false];
        }

        if (isset($text[$offset]) && $text[$offset] === '{') {
            $closed = static::matchBalanced($text, $offset);

            if ($closed !== null) {
                return [$closed, false];
            }
        }

        if (preg_match('/[a-zA-Z](?![a-zA-Z])/A', $text, $m, 0, $offset)) {
            return [$offset + 1, true];
        }

        return null;
    }
}
