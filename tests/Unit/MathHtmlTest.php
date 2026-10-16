<?php

namespace Tests\Unit;

use App\Support\MathHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit test MURNI (tanpa DB) untuk normalisasi jawaban siswa sebelum dirender KaTeX.
 * Fokusnya: LaTeX tanpa delimiter harus dibungkus, environment lintas-markup harus
 * disatukan, teks biasa tidak boleh terseret, dan HTML berbahaya harus dibuang.
 */
class MathHtmlTest extends TestCase
{
    public function test_empty_content_returns_empty_string(): void
    {
        $this->assertSame('', MathHtml::render(null));
        $this->assertSame('', MathHtml::render(''));
        $this->assertSame('', MathHtml::render('<div></div><div><br></div>'));
    }

    public function test_plain_text_keeps_line_breaks(): void
    {
        $html = MathHtml::render("1. C\n2. B");

        $this->assertStringContainsString('1. C<br>', $html);
        $this->assertStringContainsString('2. B', $html);
    }

    public function test_answer_without_math_is_left_alone(): void
    {
        $raw = '<div>1. C</div><div>2. C</div><div>3. B</div>';

        $this->assertSame($raw, MathHtml::render($raw));
    }

    public function test_inline_latex_without_delimiter_gets_wrapped(): void
    {
        $html = MathHtml::render('<div>Diketahui: \sin \theta = \frac{9}{41} → depan = 9</div>');

        $this->assertStringContainsString('<span class="math-inline">$\sin \theta = \frac{9}{41}$</span>', $html);
        // Prosa sesudah rumus tetap di luar math.
        $this->assertStringContainsString('→ depan = 9', $html);
    }

    public function test_prose_after_command_is_not_swallowed(): void
    {
        $html = MathHtml::render('<div>✅ \cos \theta lebih besar, karena \frac{40}{41} > \frac{9}{41}. Pembilang 40.</div>');

        $this->assertStringContainsString('<span class="math-inline">$\cos \theta$</span> lebih besar, karena', $html);
        $this->assertStringContainsString('<span class="math-inline">$\frac{40}{41} &gt; \frac{9}{41}$</span>. Pembilang 40.', $html);
    }

    public function test_chained_operators_stay_in_one_run(): void
    {
        $html = MathHtml::render('<div>- Sisi samping = \sqrt{41^2 - 9^2} = \sqrt{1600} = \boldsymbol{40}</div>');

        $this->assertStringContainsString(
            '<span class="math-inline">$\sqrt{41^2 - 9^2} = \sqrt{1600} = \boldsymbol{40}$</span>',
            $html
        );
        $this->assertStringContainsString('- Sisi samping = <span', $html);
    }

    public function test_align_environment_split_across_markup_is_merged_into_display_math(): void
    {
        // Trix memecah tiap baris jadi <div> sendiri, jadi \begin & \end beda node.
        $raw = '<div>\begin{align*}</div>'
            .'<div>\sin \theta &amp;= \frac{21}{29} \\\\[6pt]</div>'
            .'<div>\cos \theta &amp;= \frac{20}{29}</div>'
            .'<div>\end{align*}</div>';

        $html = MathHtml::render($raw);

        $this->assertStringContainsString('<span class="math-block">$$\begin{align*}', $html);
        $this->assertStringContainsString('\end{align*}$$</span>', $html);
        // Ampersand align tetap sebagai entity di HTML (textContent-nya jadi "&").
        $this->assertStringContainsString('&amp;=', $html);
        // Tidak ada <div> yang nyangkut di dalam blok math.
        $this->assertStringNotContainsString('</div><div>\cos', $html);
    }

    public function test_existing_delimiters_are_left_untouched(): void
    {
        $raw = '<div>Jadi $x^2 + 1$ dan $$\frac{a}{b}$$ tetap.</div>';

        $this->assertSame($raw, MathHtml::render($raw));
    }

    public function test_backslash_in_prose_is_not_treated_as_math(): void
    {
        $html = MathHtml::render('<div>File ada di C:\Users\nama\tugas.docx ya pak</div>');

        $this->assertStringNotContainsString('math-inline', $html);
    }

    public function test_incomplete_environment_is_not_wrapped(): void
    {
        $html = MathHtml::render('<div>\begin{align*} tanpa penutup</div>');

        $this->assertStringNotContainsString('math-block', $html);
        $this->assertStringNotContainsString('math-inline', $html);
    }

    /**
     * Jawaban mapel non-matematika tidak boleh ikut dirender sebagai rumus,
     * termasuk yang mengandung karakter "mirip math" (persen, path backslash,
     * tanda >, underscore).
     */
    #[DataProvider('nonMathAnswers')]
    public function test_non_math_answer_gets_no_math_markup(string $raw): void
    {
        $html = MathHtml::render($raw);

        $this->assertStringNotContainsString('math-inline', $html);
        $this->assertStringNotContainsString('math-block', $html);
        $this->assertSame(
            $this->plainText($raw),
            $this->plainText($html),
            'Teks jawaban tidak boleh berubah.'
        );
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function nonMathAnswers(): array
    {
        return [
            'esai dengan bold & list' => ['<div>Kutipan tidak langsung lebih <strong>padat</strong>.</div>'
                .'<ul><li>Aktif: "Petani memanen padi."</li><li>Pasif: "Padi dipanen petani."</li></ul>'],
            'persen' => ['<div>Draf esai saya sudah 80% selesai, tinggal simpulan.</div>'],
            'path windows' => ['<div>File-nya di C:\Users\ahmad\Documents\tugas_bahasa.docx</div>'],
            'tanda lebih besar' => ['<div>Jumlah kata bab 1 > bab 2, jadi saya ringkas.</div>'],
            'underscore' => ['<div>Nama berkas pakai format laporan_akhir_v2 biar rapi.</div>'],
            'inggris' => ['<div>We spent about 60% of the time at the beach.</div>'],
            'rumus kimia' => ['<div>Reaksi: 2H2 + O2 -> 2H2O (perbandingan 2:1)</div>'],
            'bab bernomor' => ['<div>Jumlah kata bab_2 lebih banyak dari bab_1.</div>'],
            'arsip tahun' => ['<div>Arsip 2024_01 sudah saya kirim ke email.</div>'],
            'markdown italic' => ['<div>Ini kata _penting_ dalam kalimat saya.</div>'],
        ];
    }

    private function plainText(string $html): string
    {
        return preg_replace(
            '/\s+/',
            ' ',
            trim(strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5)))
        );
    }

    public function test_plain_power_and_subscript_are_wrapped(): void
    {
        $html = MathHtml::render('<div>Buktikan x^2 + y^2 = z^2 untuk segitiga siku-siku.</div>');

        $this->assertStringContainsString('<span class="math-inline">$x^2 + y^2 = z^2$</span>', $html);
        $this->assertStringContainsString('untuk segitiga siku-siku.', $html);
    }

    public function test_subscript_and_braced_power_are_wrapped(): void
    {
        $this->assertStringContainsString(
            '<span class="math-inline">$x_1 = 3$</span>',
            MathHtml::render('<div>Akarnya x_1 = 3 dan x_2 = 2.</div>')
        );

        $this->assertStringContainsString(
            '<span class="math-inline">$2^{10} = 1024$</span>',
            MathHtml::render('<div>Hitung 2^{10} = 1024.</div>')
        );
    }

    /**
     * libxml berhenti mem-parse pada kedalaman tag > 256 dan membuang isinya.
     * Jawaban siswa TIDAK BOLEH hilang dari layar guru gara-gara itu.
     */
    public function test_extremely_nested_markup_keeps_its_content(): void
    {
        $raw = str_repeat('<div>', 400).'Jawaban: \sin \theta'.str_repeat('</div>', 400);

        $html = MathHtml::render($raw);

        $this->assertStringContainsString('Jawaban:', $html);
        $this->assertStringContainsString('math-inline', $html);
    }

    public function test_rendering_twice_changes_nothing(): void
    {
        $once = MathHtml::render('<div>Diketahui \sin \theta = \frac{9}{41} dan x^2 + 1</div>');

        $this->assertSame($once, MathHtml::render($once));
    }

    public function test_utf8_characters_around_math_stay_intact(): void
    {
        $html = MathHtml::render('<div>Hasilnya ✅\sin \theta→selesai, β-nya \frac{1}{2}—oke</div>');

        $this->assertTrue(mb_check_encoding($html, 'UTF-8'));
        foreach (['✅', '→', 'β', '—'] as $char) {
            $this->assertStringContainsString($char, $html);
        }
    }

    /**
     * Isi <script>/<style> sengaja dibuang sanitizer. Jaring pengaman "teks
     * hilang" tidak boleh menyangka itu kehilangan data, lalu meratakan markup
     * dan menumpahkan source-nya sebagai teks ke layar guru.
     */
    public function test_script_and_style_source_never_leaks_as_text(): void
    {
        $html = MathHtml::render(
            '<div>Jawaban \sin \theta</div><style>body{color:red}</style><script>alert(1)</script>'
        );

        $this->assertStringNotContainsString('body{color:red}', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString('math-inline', $html);
        $this->assertStringContainsString('Jawaban', $html);
    }

    public function test_dangerous_markup_is_stripped(): void
    {
        $html = MathHtml::render(
            '<div onclick="steal()">hai</div>'
            .'<script>alert(1)</script>'
            .'<img src="x" onerror="alert(1)">'
            .'<a href="javascript:alert(1)">klik</a>'
        );

        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('hai', $html);
    }

    public function test_external_link_is_kept_but_hardened(): void
    {
        $html = MathHtml::render('<div><a href="https://example.com">sumber</a></div>');

        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener nofollow"', $html);
    }
}
