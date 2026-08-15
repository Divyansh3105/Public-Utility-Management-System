<?php
/*******************************************************************************
* FPDF — Pure PHP PDF generation library                                       *
* Version: 1.86 (PHP 8.0+ compatible)                                         *
*******************************************************************************/

define('FPDF_VERSION', '1.86');

class FPDF
{
    protected int $page = 0;
    protected int $n = 2;
    protected array $offsets = [];
    protected string $buffer = '';
    protected array $pages = [];
    protected int $state = 0;
    protected bool $compress;
    protected float $k;
    protected string $DefOrientation;
    protected string $CurOrientation;
    protected array $StdPageSizes = [];
    protected array $DefPageSize = [];
    protected array $CurPageSize = [];
    protected array $CurRotation = [];
    protected array $PageInfo = [];
    protected float $wPt;
    protected float $hPt;
    protected float $w;
    protected float $h;
    protected float $lMargin;
    protected float $tMargin;
    protected float $rMargin;
    protected float $bMargin;
    protected float $cMargin;
    protected float $x;
    protected float $y;
    protected float $lasth;
    protected float $LineWidth;
    protected string $fontpath = '';
    protected array $CoreFonts = [];
    protected array $fonts = [];
    protected array $FontFiles = [];
    protected array $encodings = [];
    protected array $cmaps = [];
    protected string $FontFamily = '';
    protected string $FontStyle = '';
    protected bool $underline = false;
    protected array $CurrentFont = [];
    protected float $FontSizePt = 12;
    protected float $FontSize = 12;
    protected string $DrawColor = '0 G';
    protected string $FillColor = '0 g';
    protected string $TextColor = '0 g';
    protected bool $ColorFlag = false;
    protected bool $WithAlpha = false;
    protected float $ws = 0;
    protected array $images = [];
    protected array $PageLinks = [];
    protected array $links = [];
    protected bool $AutoPageBreak;
    protected float $PageBreakTrigger;
    protected bool $InHeader = false;
    protected bool $InFooter = false;
    protected string $AliasNbPages = '';
    protected string $ZoomMode;
    protected string $LayoutMode;
    protected string $metadata = '';
    protected float $extgstates = 0;

    public function __construct(string $orientation = 'P', string $unit = 'mm', $size = 'A4')
    {
        $this->CoreFonts = ['courier', 'helvetica', 'times', 'symbol', 'zapfdingbats'];
        if ($unit === 'pt') $this->k = 1;
        elseif ($unit === 'mm') $this->k = 72 / 25.4;
        elseif ($unit === 'cm') $this->k = 72 / 2.54;
        elseif ($unit === 'in') $this->k = 72;
        else $this->Error('Incorrect unit: ' . $unit);

        $this->StdPageSizes = [
            'a3' => [841.89, 1190.55],
            'a4' => [595.28, 841.89],
            'a5' => [420.94, 595.28],
            'letter' => [612, 792],
            'legal' => [612, 1008]
        ];
        $size = $this->_getpagesize($size);
        $this->DefPageSize = $size;
        $this->CurPageSize = $size;

        $orientation = strtolower($orientation);
        if ($orientation === 'p' || $orientation === 'portrait') {
            $this->DefOrientation = 'P';
            $this->w = $size[0];
            $this->h = $size[1];
        } elseif ($orientation === 'l' || $orientation === 'landscape') {
            $this->DefOrientation = 'L';
            $this->w = $size[1];
            $this->h = $size[0];
        } else {
            $this->Error('Incorrect orientation: ' . $orientation);
        }
        $this->CurOrientation = $this->DefOrientation;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;

        $margin = 28.35 / $this->k;
        $this->SetMargins($margin, $margin);
        $this->cMargin = $margin / 10;
        $this->LineWidth = .567 / $this->k;
        $this->SetAutoPageBreak(true, 2 * $margin);
        $this->SetDisplayMode('default');
        $this->SetCompression(true);
    }

    public function SetMargins(float $left, float $top, float $right = null): void
    {
        $this->lMargin = $left;
        $this->tMargin = $top;
        if ($right === null) $right = $left;
        $this->rMargin = $right;
    }

    public function SetLeftMargin(float $margin): void
    {
        $this->lMargin = $margin;
        if ($this->page > 0 && $this->x < $margin) $this->x = $margin;
    }

    public function SetTopMargin(float $margin): void
    {
        $this->tMargin = $margin;
    }

    public function SetRightMargin(float $margin): void
    {
        $this->rMargin = $margin;
    }

    public function SetAutoPageBreak(bool $auto, float $margin = 0): void
    {
        $this->AutoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->PageBreakTrigger = $this->h - $margin;
    }

    public function SetDisplayMode($zoom, string $layout = 'default'): void
    {
        if ($zoom === 'fullpage' || $zoom === 'fullwidth' || $zoom === 'real' || $zoom === 'default' || !is_string($zoom)) {
            $this->ZoomMode = (string)$zoom;
        } else {
            $this->Error('Incorrect zoom display mode: ' . $zoom);
        }
        if ($layout === 'single' || $layout === 'continuous' || $layout === 'two' || $layout === 'default') {
            $this->LayoutMode = $layout;
        } else {
            $this->Error('Incorrect layout display mode: ' . $layout);
        }
    }

    public function SetCompression(bool $compress): void
    {
        $this->compress = function_exists('gzcompress') ? $compress : false;
    }

    public function SetTitle(string $title, bool $isUTF8 = false): void
    {
        $this->metadata['Title'] = $isUTF8 ? $title : utf8_encode($title);
    }

    public function SetAuthor(string $author, bool $isUTF8 = false): void
    {
        $this->metadata['Author'] = $isUTF8 ? $author : utf8_encode($author);
    }

    public function SetSubject(string $subject, bool $isUTF8 = false): void
    {
        $this->metadata['Subject'] = $isUTF8 ? $subject : utf8_encode($subject);
    }

    public function SetCreator(string $creator, bool $isUTF8 = false): void
    {
        $this->metadata['Creator'] = $isUTF8 ? $creator : utf8_encode($creator);
    }

    public function AliasNbPages(string $alias = '{nb}'): void
    {
        $this->AliasNbPages = $alias;
    }

    public function Error(string $msg): void
    {
        throw new \RuntimeException('FPDF error: ' . $msg);
    }

    public function Open(): void
    {
        $this->state = 1;
    }

    public function Close(): void
    {
        if ($this->state === 3) return;
        if ($this->page === 0) $this->AddPage();
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
        $this->_endpage();
        $this->_enddoc();
    }

    public function AddPage(string $orientation = '', $size = '', float $rotation = 0): void
    {
        if ($this->state === 0) $this->Open();
        $family = $this->FontFamily;
        $style = $this->FontStyle . ($this->underline ? 'U' : '');
        $fontsize = $this->FontSizePt;
        $lw = $this->LineWidth;
        $dc = $this->DrawColor;
        $fc = $this->FillColor;
        $tc = $this->TextColor;
        $cf = $this->ColorFlag;
        if ($this->page > 0) {
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            $this->_endpage();
        }
        $this->_beginpage($orientation, $size, $rotation);
        $this->_out('2 J');
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w', $lw * $this->k));
        if ($family) $this->SetFont($family, $style, $fontsize);
        $this->DrawColor = $dc;
        if ($dc !== '0 G') $this->_out($dc);
        $this->FillColor = $fc;
        if ($fc !== '0 g') $this->_out($fc);
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
        $this->InHeader = true;
        $this->Header();
        $this->InHeader = false;
        if ($this->LineWidth !== $lw) {
            $this->LineWidth = $lw;
            $this->_out(sprintf('%.2F w', $lw * $this->k));
        }
        if ($family) $this->SetFont($family, $style, $fontsize);
        if ($this->DrawColor !== $dc) {
            $this->DrawColor = $dc;
            $this->_out($dc);
        }
        if ($this->FillColor !== $fc) {
            $this->FillColor = $fc;
            $this->_out($fc);
        }
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
    }

    public function Header(): void {}
    public function Footer(): void {}

    public function PageNo(): int
    {
        return $this->page;
    }

    public function SetDrawColor(int $r, int $g = null, int $b = null): void
    {
        if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
            $this->DrawColor = sprintf('%.3F G', $r / 255);
        } else {
            $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
        }
        if ($this->page > 0) $this->_out($this->DrawColor);
    }

    public function SetFillColor(int $r, int $g = null, int $b = null): void
    {
        if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
            $this->FillColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }
        $this->ColorFlag = ($this->FillColor !== $this->TextColor);
        if ($this->page > 0) $this->_out($this->FillColor);
    }

    public function SetTextColor(int $r, int $g = null, int $b = null): void
    {
        if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
            $this->TextColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }
        $this->ColorFlag = ($this->FillColor !== $this->TextColor);
    }

    public function GetStringWidth(string $s): float
    {
        $s = (string)$s;
        $cw = &$this->CurrentFont['cw'];
        $w = 0;
        $l = strlen($s);
        for ($i = 0; $i < $l; $i++) {
            $w += $cw[$s[$i]] ?? 600;
        }
        return $w * $this->FontSize / 1000;
    }

    public function SetLineWidth(float $width): void
    {
        $this->LineWidth = $width;
        if ($this->page > 0) $this->_out(sprintf('%.2F w', $width * $this->k));
    }

    public function Line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $this->k, ($this->h - $y1) * $this->k, $x2 * $this->k, ($this->h - $y2) * $this->k));
    }

    public function Rect(float $x, float $y, float $w, float $h, string $style = ''): void
    {
        $style = strtoupper($style);
        if ($style === 'F') $op = 'f';
        elseif ($style === 'FD' || $style === 'DF') $op = 'B';
        else $op = 'S';
        $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $this->k, ($this->h - $y) * $this->k, $w * $this->k, -$h * $this->k, $op));
    }

    public function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void
    {
        $k = $this->k;
        $hp = $this->h;
        $style = strtoupper($style);
        if ($style === 'F') $op = 'f';
        elseif ($style === 'FD' || $style === 'DF') $op = 'B';
        else $op = 'S';
        $myArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x+$r)*$k, ($hp-$y)*$k ));
        $xc = $x+$w-$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-$y)*$k ));
        $this->_Arc($xc + $r*$myArc, $yc - $r, $xc + $r, $yc - $r*$myArc, $xc + $r, $yc);
        $xc = $x+$w-$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k, ($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$myArc, $xc + $r*$myArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$myArc, $yc + $r, $xc - $r, $yc + $r*$myArc, $xc - $r, $yc);
        $xc = $x+$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', ($x)*$k, ($hp-$yc)*$k ));
        $this->_Arc($xc - $r, $yc - $r*$myArc, $xc - $r*$myArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    protected function _Arc($x1, $y1, $x2, $y2, $x3, $y3): void
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

    public function SetFont(string $family, string $style = '', float $size = 0): void
    {
        $family = strtolower($family);
        if ($family === '') $family = $this->FontFamily;
        if ($family === 'arial') $family = 'helvetica';
        $style = strtoupper($style);
        if (strpos($style, 'U') !== false) {
            $this->underline = true;
            $style = str_replace('U', '', $style);
        } else {
            $this->underline = false;
        }
        if ($style === 'IB') $style = 'BI';
        if ($size == 0) $size = $this->FontSizePt;

        if ($this->FontFamily === $family && $this->FontStyle === $style && $this->FontSizePt === $size) return;

        $fontkey = $family . $style;
        if (!isset($this->fonts[$fontkey])) {
            if (in_array($family, $this->CoreFonts)) {
                $this->fonts[$fontkey] = [
                    'i' => count($this->fonts) + 1,
                    'type' => 'core',
                    'name' => $this->_getcorefontname($family, $style),
                    'up' => -100,
                    'ut' => 50,
                    'cw' => $this->_getcorewidths($family, $style)
                ];
            } else {
                $this->Error('Undefined font: ' . $family . ' ' . $style);
            }
        }
        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size / $this->k;
        $this->CurrentFont = &$this->fonts[$fontkey];
        if ($this->page > 0) $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
    }

    public function SetFontSize(float $size): void
    {
        if ($this->FontSizePt === $size) return;
        $this->FontSizePt = $size;
        $this->FontSize = $size / $this->k;
        if ($this->page > 0) $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
    }

    public function SetXY(float $x, float $y): void
    {
        $this->SetY($y);
        $this->SetX($x);
    }

    public function SetX(float $x): void
    {
        if ($x >= 0) $this->x = $x;
        else $this->x = $this->w + $x;
    }

    public function SetY(float $y, bool $resetX = true): void
    {
        if ($resetX) $this->x = $this->lMargin;
        if ($y >= 0) $this->y = $y;
        else $this->y = $this->h + $y;
    }

    public function GetX(): float
    {
        return $this->x;
    }

    public function GetY(): float
    {
        return $this->y;
    }

    public function GetPageWidth(): float
    {
        return $this->w;
    }

    public function GetPageHeight(): float
    {
        return $this->h;
    }

    public function Cell(float $w, float $h = 0, string $txt = '', $border = 0, int $ln = 0, string $align = '', bool $fill = false, $link = ''): void
    {
        $k = $this->k;
        if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
            $x = $this->x;
            $ws = $this->ws;
            if ($ws > 0) {
                $this->ws = 0;
                $this->_out('0 Tw');
            }
            $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation[$this->page] ?? 0);
            $this->x = $x;
            if ($ws > 0) {
                $this->ws = $ws;
                $this->_out(sprintf('%.3F Tw', $ws * $k));
            }
        }
        if ($w === 0.0) $w = $this->w - $this->rMargin - $this->x;
        $s = '';
        if ($fill || $border == 1) {
            if ($fill) $op = ($border == 1) ? 'B' : 'f';
            else $op = 'S';
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ', $this->x * $k, ($this->h - $this->y) * $k, $w * $k, -$h * $k, $op);
        }
        if (is_string($border)) {
            $x = $this->x;
            $y = $this->y;
            if (strpos($border, 'L') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, $x * $k, ($this->h - ($y + $h)) * $k);
            if (strpos($border, 'T') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - $y) * $k);
            if (strpos($border, 'R') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x + $w) * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
            if (strpos($border, 'B') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - ($y + $h)) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
        }
        if ($txt !== '') {
            if ($align === 'R') $dx = $w - $this->cMargin - $this->GetStringWidth($txt);
            elseif ($align === 'C') $dx = ($w - $this->GetStringWidth($txt)) / 2;
            else $dx = $this->cMargin;
            if ($this->ColorFlag) $s .= 'q ' . $this->TextColor . ' ';
            $txtstring = $this->_escape($txt);
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET', ($this->x + $dx) * $k, ($this->h - ($this->y + .5 * $h + .3 * $this->FontSize)) * $k, $txtstring);
            if ($this->underline) $s .= ' ' . $this->_dounderline($this->x + $dx, $this->y + .5 * $h + .3 * $this->FontSize, $txt);
            if ($this->ColorFlag) $s .= ' Q';
        }
        if ($s) $this->_out($s);
        $this->lasth = $h;
        if ($ln > 0) {
            $this->y += $h;
            if ($ln === 1) $this->x = $this->lMargin;
        } else {
            $this->x += $w;
        }
    }

    public function MultiCell(float $w, float $h, string $txt, $border = 0, string $align = 'J', bool $fill = false): void
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w === 0.0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") $nb--;
        $b = 0;
        if ($border) {
            if ($border == 1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            } else {
                $b2 = '';
                if (strpos($border, 'L') !== false) $b2 .= 'L';
                if (strpos($border, 'R') !== false) $b2 .= 'R';
                $b = (strpos($border, 'T') !== false) ? $b2 . 'T' : $b2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") {
                $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($border && $nl === 2) $b = $b2;
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
            $l += $cw[$c] ?? 600;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) $i++;
                    $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                } else {
                    $this->Cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($border && $nl === 2) $b = $b2;
            } else {
                $i++;
            }
        }
        if ($border && strpos($border, 'B') !== false) $b .= 'B';
        $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    public function Ln(float $h = null): void
    {
        $this->x = $this->lMargin;
        if ($h === null) $this->y += $this->lasth;
        else $this->y += $h;
    }

    public function AcceptPageBreak(): bool
    {
        return $this->AutoPageBreak;
    }

    public function Output(string $dest = '', string $name = '', bool $isUTF8 = false): string
    {
        $this->Close();
        if (strlen($name) === 0) {
            $name = 'doc.pdf';
            $dest = 'I';
        }
        $dest = strtoupper($dest);
        if ($dest === 'I') {
            $this->_checkoutput();
            if (PHP_SAPI !== 'cli') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; ' . $this->_httpencode('filename', $name, $isUTF8));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
            }
            echo $this->buffer;
        } elseif ($dest === 'D') {
            $this->_checkoutput();
            header('Content-Type: application/x-download');
            header('Content-Disposition: attachment; ' . $this->_httpencode('filename', $name, $isUTF8));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $this->buffer;
        } elseif ($dest === 'F') {
            $f = fopen($name, 'wb');
            if (!$f) $this->Error('Unable to create output file: ' . $name);
            fwrite($f, $this->buffer, strlen($this->buffer));
            fclose($f);
        } elseif ($dest === 'S') {
            return $this->buffer;
        } else {
            $this->Error('Incorrect output destination: ' . $dest);
        }
        return '';
    }

    protected function _getpagesize($size): array
    {
        if (is_string($size)) {
            $size = strtolower($size);
            if (!isset($this->StdPageSizes[$size])) $this->Error('Unknown page size: ' . $size);
            $a = $this->StdPageSizes[$size];
            return [$a[0] / $this->k, $a[1] / $this->k];
        }
        if ($size[0] > $size[1]) return [$size[1], $size[0]];
        return $size;
    }

    protected function _beginpage(string $orientation, $size, float $rotation): void
    {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->PageInfo[$this->page] = [];
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->FontFamily = '';
        if ($orientation === '') $orientation = $this->DefOrientation;
        else $orientation = strtoupper($orientation[0]);
        if ($size === '') $size = $this->DefPageSize;
        else $size = $this->_getpagesize($size);
        if ($orientation !== $this->CurOrientation || $size[0] !== $this->CurPageSize[0] || $size[1] !== $this->CurPageSize[1]) {
            if ($orientation === 'P') {
                $this->w = $size[0];
                $this->h = $size[1];
            } else {
                $this->w = $size[1];
                $this->h = $size[0];
            }
            $this->wPt = $this->w * $this->k;
            $this->hPt = $this->h * $this->k;
            $this->PageBreakTrigger = $this->h - $this->bMargin;
            $this->CurOrientation = $orientation;
            $this->CurPageSize = $size;
        }
        $this->PageInfo[$this->page]['size'] = [$this->wPt, $this->hPt];
        $this->PageInfo[$this->page]['rotation'] = $rotation;
    }

    protected function _endpage(): void
    {
        $this->state = 1;
    }

    protected function _escape(string $s): string
    {
        return str_replace(['\\', ')', '(', "\r"], ['\\\\', '\\)', '\\(', '\\r'], $s);
    }

    protected function _dounderline(float $x, float $y, string $txt): string
    {
        $up = $this->CurrentFont['up'] ?? -100;
        $ut = $this->CurrentFont['ut'] ?? 50;
        $w = $this->GetStringWidth($txt) + $this->ws * substr_count($txt, ' ');
        return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->k, ($this->h - ($y - $up / 1000 * $this->FontSize)) * $this->k, $w * $this->k, -$ut / 1000 * $this->FontSizePt);
    }

    protected function _out(string $s): void
    {
        if ($this->state === 2) $this->pages[$this->page] .= $s . "\n";
        elseif ($this->state === 1) $this->_put($s);
        elseif ($this->state === 0) $this->Error('No page has been added yet');
        elseif ($this->state === 3) $this->Error('The document is closed');
    }

    protected function _put(string $s): void
    {
        $this->buffer .= $s . "\n";
    }

    protected function _getcorefontname(string $family, string $style): string
    {
        $name = 'Helvetica';
        if ($family === 'helvetica') {
            if ($style === 'B') $name = 'Helvetica-Bold';
            elseif ($style === 'I') $name = 'Helvetica-Oblique';
            elseif ($style === 'BI') $name = 'Helvetica-BoldOblique';
            else $name = 'Helvetica';
        } elseif ($family === 'times') {
            if ($style === 'B') $name = 'Times-Bold';
            elseif ($style === 'I') $name = 'Times-Italic';
            elseif ($style === 'BI') $name = 'Times-BoldItalic';
            else $name = 'Times-Roman';
        } elseif ($family === 'courier') {
            if ($style === 'B') $name = 'Courier-Bold';
            elseif ($style === 'I') $name = 'Courier-Oblique';
            elseif ($style === 'BI') $name = 'Courier-BoldOblique';
            else $name = 'Courier';
        }
        return $name;
    }

    protected function _getcorewidths(string $family, string $style): array
    {
        $cw = [];
        for ($i = 0; $i <= 255; $i++) {
            $cw[chr($i)] = ($family === 'courier') ? 600 : 500;
        }
        // Specific adjustments for common characters
        $cw[' '] = 250;
        $cw['!'] = 278;
        $cw['"'] = 355;
        $cw['#'] = 556;
        $cw['$'] = 556;
        $cw['%'] = 889;
        $cw['&'] = 667;
        $cw['\''] = 191;
        $cw['('] = 333;
        $cw[')'] = 333;
        $cw['*'] = 389;
        $cw['+'] = 584;
        $cw[','] = 278;
        $cw['-'] = 333;
        $cw['.'] = 278;
        $cw['/'] = 278;
        for ($i = 48; $i <= 57; $i++) $cw[chr($i)] = 556;
        $cw[':'] = 278;
        $cw[';'] = 278;
        $cw['<'] = 584;
        $cw['='] = 584;
        $cw['>'] = 584;
        $cw['?'] = 556;
        $cw['@'] = 1015;
        for ($i = 65; $i <= 90; $i++) $cw[chr($i)] = ($style === 'B' || $style === 'BI') ? 722 : 667;
        for ($i = 97; $i <= 122; $i++) $cw[chr($i)] = ($style === 'B' || $style === 'BI') ? 556 : 500;
        return $cw;
    }

    protected function _enddoc(): void
    {
        $this->state = 3;
        $this->_putheader();
        $this->_putpages();
        $this->_putresources();
        $this->_putinfo();
        $this->_puttrailer();
    }

    protected function _putheader(): void
    {
        $this->_put('%PDF-1.4');
    }

    protected function _putpages(): void
    {
        $nb = $this->page;
        for ($n = 1; $n <= $nb; $n++) {
            $this->PageInfo[$n]['n'] = $this->n + $n;
        }
        for ($n = 1; $n <= $nb; $n++) {
            $this->_newobj();
            $this->_put('<</Type /Page');
            $this->_put('/Parent 1 0 R');
            $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->PageInfo[$n]['size'][0], $this->PageInfo[$n]['size'][1]));
            $this->_put('/Resources 2 0 R');
            $this->_put('/Contents ' . ($this->n + $nb) . ' 0 R>>');
            $this->_put('endobj');
        }
        for ($n = 1; $n <= $nb; $n++) {
            $p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
            $this->_newobj();
            $this->_put('<<' . ($this->compress ? '/Filter /FlateDecode ' : '') . '/Length ' . strlen($p) . '>>');
            $this->_putstream($p);
            $this->_put('endobj');
        }
        $this->offsets[1] = strlen($this->buffer);
        $this->_put('1 0 obj');
        $this->_put('<</Type /Pages');
        $kids = '/Kids [';
        for ($n = 1; $n <= $nb; $n++) $kids .= (2 + $n) . ' 0 R ';
        $this->_put($kids . ']');
        $this->_put('/Count ' . $nb);
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _putresources(): void
    {
        $this->_putfonts();
        $this->offsets[2] = strlen($this->buffer);
        $this->_put('2 0 obj');
        $this->_put('<<');
        $this->_put('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_put('/Font <<');
        foreach ($this->fonts as $font) {
            $this->_put('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
        }
        $this->_put('>>');
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _putfonts(): void
    {
        foreach ($this->fonts as $k => $font) {
            $this->fonts[$k]['n'] = $this->n + 1;
            $this->_newobj();
            $this->_put('<</Type /Font');
            $this->_put('/Subtype /Type1');
            $this->_put('/BaseFont /' . $font['name']);
            $this->_put('/Encoding /WinAnsiEncoding');
            $this->_put('>>');
            $this->_put('endobj');
        }
    }

    protected function _putinfo(): void
    {
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/Producer ' . $this->_textstring('FPDF ' . FPDF_VERSION));
        $this->_put('/CreationDate ' . $this->_textstring('D:' . @date('YmdHis')));
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _puttrailer(): void
    {
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/Type /Catalog');
        $this->_put('/Pages 1 0 R');
        $this->_put('>>');
        $this->_put('endobj');

        $o = strlen($this->buffer);
        $this->_put('xref');
        $this->_put('0 ' . ($this->n + 1));
        $this->_put('0000000000 65535 f ');
        for ($i = 1; $i <= $this->n; $i++) {
            $this->_put(sprintf('%010d 00000 n ', $this->offsets[$i]));
        }
        $this->_put('trailer');
        $this->_put('<<');
        $this->_put('/Size ' . ($this->n + 1));
        $this->_put('/Root ' . $this->n . ' 0 R');
        $this->_put('/Info ' . ($this->n - 1) . ' 0 R');
        $this->_put('>>');
        $this->_put('startxref');
        $this->_put((string)$o);
        $this->_put('%%EOF');
    }

    protected function _newobj(): void
    {
        $this->n++;
        $this->offsets[$this->n] = strlen($this->buffer);
        $this->_put($this->n . ' 0 obj');
    }

    protected function _putstream(string $data): void
    {
        $this->_put('stream');
        $this->_put($data);
        $this->_put('endstream');
    }

    protected function _textstring(string $s): string
    {
        return '(' . $this->_escape($s) . ')';
    }

    protected function _checkoutput(): void
    {
        if (PHP_SAPI !== 'cli') {
            if (headers_sent($file, $line)) {
                $this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
            }
        }
    }

    protected function _httpencode(string $param, string $value, bool $isUTF8): string
    {
        return $param . '="' . basename($value) . '"';
    }
}
