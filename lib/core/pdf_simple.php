<?php
/**
 * lib/core/pdf_simple.php — Generador PDF minimo SIN dependencias externas.
 *
 * Suficiente para informes clinicos: texto, encabezados, pares etiqueta/valor,
 * lineas y saltos de pagina automaticos. Usa las fuentes "core" Helvetica /
 * Helvetica-Bold (no se incrustan) con codificacion WinAnsi (CP1252), que cubre
 * el espanol (tildes y enie). Salida = string binario del PDF (A4 vertical).
 *
 * No pretende ser pixel-identico a la vista web: es un artefacto firmable,
 * legible y autocontenido. Se usa para el "PDF firmado" de la Fase 3 (c).
 */

if (!class_exists('EcoPdf')) {

    class EcoPdf
    {
        private float $w = 595.28;   // A4 ancho (pt)
        private float $h = 841.89;   // A4 alto  (pt)
        private float $mL = 50.0;
        private float $mR = 50.0;
        private float $mT = 54.0;
        private float $mB = 56.0;

        private array  $pages = [];  // contenido (string de operadores) por pagina
        private string $buf = '';    // pagina actual
        private float  $y;           // cursor vertical medido DESDE ARRIBA (pt)
        private float  $size = 11.0;
        private bool   $bold = false;
        private string $rgb = '0 0 0';

        public function __construct()
        {
            $this->y = $this->mT;
            $this->buf = '';
        }

        private function usableWidth(): float
        {
            return $this->w - $this->mL - $this->mR;
        }

        /** Ancho aproximado de un texto en pt (Helvetica ~0.50-0.52 * size). */
        private function textWidth(string $s): float
        {
            $f = $this->bold ? 0.535 : 0.512;
            return strlen($s) * $this->size * $f;
        }

        public function setFont(float $size, bool $bold = false): void
        {
            $this->size = $size;
            $this->bold = $bold;
        }

        public function setColor(int $r, int $g, int $b): void
        {
            $this->rgb = sprintf('%.3f %.3f %.3f', $r / 255, $g / 255, $b / 255);
        }

        /** Convierte UTF-8 a CP1252 y escapa para literal de cadena PDF. */
        private function enc(string $s): string
        {
            $cp = @iconv('UTF-8', 'CP1252//TRANSLIT', $s);
            if ($cp === false) {
                $cp = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
            }
            if ($cp === false) {
                $cp = preg_replace('/[^\x20-\x7E]/', '?', $s);
            }
            return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $cp);
        }

        private function ensureSpace(float $need): void
        {
            if ($this->y + $need > $this->h - $this->mB) {
                $this->addPage();
            }
        }

        public function addPage(): void
        {
            if ($this->buf !== '') {
                $this->pages[] = $this->buf;
            }
            $this->buf = '';
            $this->y = $this->mT;
        }

        /** Emite una linea de texto en (x desde margen izq) y avanza el cursor. */
        private function out(string $text, float $x, float $lineH): void
        {
            $py = $this->h - $this->y - $this->size; // baseline
            $font = $this->bold ? '/F2' : '/F1';
            $this->buf .= "BT {$this->rgb} rg {$font} " . rtrim(sprintf('%.2f', $this->size), '0')
                . " Tf 1 0 0 1 " . sprintf('%.2f %.2f', $this->mL + $x, $py) . " Tm ("
                . $this->enc($text) . ") Tj ET\n";
            $this->y += $lineH;
        }

        /** Texto con ajuste de linea (word-wrap) dentro del ancho util. */
        public function text(string $s, float $x = 0, ?float $lineH = null): void
        {
            $lineH = $lineH ?? ($this->size * 1.42);
            $maxW = $this->usableWidth() - $x;
            $s = str_replace(["\r\n", "\r"], "\n", $s);
            foreach (explode("\n", $s) as $para) {
                if ($para === '') { $this->y += $lineH; continue; }
                $words = preg_split('/ +/', $para);
                $line = '';
                foreach ($words as $word) {
                    $try = $line === '' ? $word : ($line . ' ' . $word);
                    if ($this->textWidth($try) > $maxW && $line !== '') {
                        $this->ensureSpace($lineH);
                        $this->out($line, $x, $lineH);
                        $line = $word;
                    } else {
                        $line = $try;
                    }
                }
                $this->ensureSpace($lineH);
                $this->out($line, $x, $lineH);
            }
        }

        /** Encabezado de seccion con regla inferior. */
        public function heading(string $s): void
        {
            $this->y += 8;
            $this->ensureSpace($this->size * 1.6 + 6);
            $prevB = $this->bold; $prevS = $this->size; $prev = $this->rgb;
            $this->setColor(1, 74, 130); $this->setFont(12.5, true);
            $this->out($s, 0, $this->size * 1.35);
            $this->setColor(2, 177, 244);
            $this->rule(0.8);
            $this->bold = $prevB; $this->size = $prevS; $this->rgb = $prev;
            $this->y += 4;
        }

        /** Par etiqueta: valor en una linea (etiqueta en negrita). */
        public function keyValue(string $label, string $value): void
        {
            $lineH = $this->size * 1.4;
            $this->ensureSpace($lineH);
            $py = $this->h - $this->y - $this->size;
            $lbl = $this->enc($label . ': ');
            $val = $this->enc($value === '' ? '—' : $value);
            $this->buf .= "BT {$this->rgb} rg /F2 " . rtrim(sprintf('%.2f', $this->size), '0')
                . " Tf 1 0 0 1 " . sprintf('%.2f %.2f', $this->mL, $py) . " Tm (" . $lbl . ") Tj ET\n";
            $offset = $this->bold ? 0 : 0;
            $lw = $this->boldWidth($label . ': ');
            $this->buf .= "BT {$this->rgb} rg /F1 " . rtrim(sprintf('%.2f', $this->size), '0')
                . " Tf 1 0 0 1 " . sprintf('%.2f %.2f', $this->mL + $lw, $py) . " Tm (" . $val . ") Tj ET\n";
            $this->y += $lineH;
        }

        private function boldWidth(string $s): float
        {
            return strlen($s) * $this->size * 0.535;
        }

        /** Regla horizontal a lo ancho del area util. */
        public function rule(float $thickness = 0.6): void
        {
            $this->ensureSpace(6);
            $yy = $this->h - $this->y - 2;
            $x1 = $this->mL;
            $x2 = $this->w - $this->mR;
            $this->buf .= sprintf("%s RG %.2f w %.2f %.2f m %.2f %.2f l S\n",
                $this->rgb, $thickness, $x1, $yy, $x2, $yy);
            $this->y += 8;
        }

        public function ln(float $h = 8): void
        {
            $this->y += $h;
        }

        /** Cuadro/caja resaltada con texto (para el bloque de firma). */
        public function box(array $lines, int $r = 240, int $g = 248, int $b = 255): void
        {
            $lineH = 14.0;
            $pad = 10.0;
            $boxH = $pad * 2 + count($lines) * $lineH;
            $this->ensureSpace($boxH + 6);
            $top = $this->h - $this->y;
            $x1 = $this->mL; $x2 = $this->w - $this->mR;
            $this->buf .= sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n",
                $r / 255, $g / 255, $b / 255, $x1, $top - $boxH, $x2 - $x1, $boxH);
            $this->buf .= sprintf("0.79 0.86 0.94 RG 0.7 w %.2f %.2f %.2f %.2f re S\n",
                $x1, $top - $boxH, $x2 - $x1, $boxH);
            $this->y += $pad;
            foreach ($lines as $ln) {
                $this->ensureSpace($lineH);
                $this->out($ln, $pad, $lineH);
            }
            $this->y += $pad;
        }

        public function getY(): float { return $this->y; }

        /** Ensambla y devuelve el PDF como string binario. */
        public function output(): string
        {
            if ($this->buf !== '') {
                $this->pages[] = $this->buf;
                $this->buf = '';
            }
            if (empty($this->pages)) {
                $this->pages[] = '';
            }

            $n = count($this->pages);
            // Numeracion: 1=catalog, 2=pages, 3=F1, 4=F2, luego por pagina (page, content)
            $firstPageId = 5;
            $kids = [];
            for ($i = 0; $i < $n; $i++) {
                $kids[] = ($firstPageId + $i * 2) . ' 0 R';
            }

            $objs = [];
            $objs[1] = "<</Type/Catalog/Pages 2 0 R>>";
            $objs[2] = "<</Type/Pages/Kids[" . implode(' ', $kids) . "]/Count {$n}>>";
            $objs[3] = "<</Type/Font/Subtype/Type1/BaseFont/Helvetica/Encoding/WinAnsiEncoding>>";
            $objs[4] = "<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold/Encoding/WinAnsiEncoding>>";

            for ($i = 0; $i < $n; $i++) {
                $pageId    = $firstPageId + $i * 2;
                $contentId = $pageId + 1;
                $content   = $this->pages[$i];
                $objs[$pageId] = "<</Type/Page/Parent 2 0 R/MediaBox[0 0 "
                    . rtrim(sprintf('%.2f', $this->w), '0') . " " . rtrim(sprintf('%.2f', $this->h), '0')
                    . "]/Resources<</Font<</F1 3 0 R/F2 4 0 R>>>>/Contents {$contentId} 0 R>>";
                $objs[$contentId] = "<</Length " . strlen($content) . ">>\nstream\n" . $content . "endstream";
            }

            ksort($objs);
            $maxId = max(array_keys($objs));

            $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
            $offsets = [];
            foreach ($objs as $id => $body) {
                $offsets[$id] = strlen($pdf);
                $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
            }

            $xrefPos = strlen($pdf);
            $count = $maxId + 1;
            $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
            for ($id = 1; $id <= $maxId; $id++) {
                if (isset($offsets[$id])) {
                    $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
                } else {
                    $pdf .= "0000000000 65535 f \n";
                }
            }
            $pdf .= "trailer\n<</Size {$count}/Root 1 0 R>>\nstartxref\n{$xrefPos}\n%%EOF";
            return $pdf;
        }
    }
}
