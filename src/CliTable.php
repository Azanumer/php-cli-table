<?php
/**
 * CliTable — dependency-free ASCII/Unicode table renderer for CLI tools.
 *
 * Renders headers + rows into a bordered table string, with multibyte-aware
 * column widths, per-column alignment, cell wrapping and three border styles.
 *
 * Example:
 *   $table = new CliTable();
 *   $table->setHeaders( array( 'Plugin', 'Version', 'Active' ) );
 *   $table->addRow( array( 'akismet', '5.3', 'yes' ) );
 *   echo $table->render();
 *
 * @package Azanumer\CliTable
 */

namespace Azanumer;

class CliTable
{
    /** @var string[] */
    private $headers = array();

    /** @var array[] */
    private $rows = array();

    /** @var string[] Column alignments: left|right|center (default left). */
    private $alignments = array();

    /** @var string Border style: ascii|light|none. */
    private $borderStyle = 'ascii';

    /** @var int Maximum width of a single cell before wrapping (0 = unlimited). */
    private $maxCellWidth = 60;

    /**
     * Set the header row.
     *
     * @param string[] $headers Header labels.
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        $this->headers = array_map('strval', $headers);
        return $this;
    }

    /**
     * Set per-column alignment.
     *
     * @param string[] $alignments One of left|right|center per column.
     * @return $this
     */
    public function setAlignments(array $alignments)
    {
        $this->alignments = $alignments;
        return $this;
    }

    /**
     * Set the border style.
     *
     * @param string $style ascii|light|none.
     * @return $this
     */
    public function setBorderStyle($style)
    {
        if (in_array($style, array('ascii', 'light', 'none'), true)) {
            $this->borderStyle = $style;
        }
        return $this;
    }

    /**
     * Set the maximum cell width; longer cells wrap onto extra lines.
     *
     * @param int $width 0 = no wrapping.
     * @return $this
     */
    public function setMaxCellWidth($width)
    {
        $this->maxCellWidth = max(0, (int) $width);
        return $this;
    }

    /**
     * Add one row.
     *
     * @param array $row Cell values.
     * @return $this
     */
    public function addRow(array $row)
    {
        $this->rows[] = array_map('strval', array_values($row));
        return $this;
    }

    /**
     * Add several rows at once.
     *
     * @param array[] $rows Rows.
     * @return $this
     */
    public function addRows(array $rows)
    {
        foreach ($rows as $row) {
            $this->addRow((array) $row);
        }
        return $this;
    }

    /**
     * Render the table.
     *
     * @return string Table text (ends with a newline).
     */
    public function render()
    {
        $columns = $this->columnCount();
        if (0 === $columns) {
            return '';
        }

        // Wrap every cell into lines, then measure column widths.
        $wrappedRows = array();
        foreach ($this->rows as $row) {
            $wrappedRows[] = $this->wrapRow($row, $columns);
        }
        $wrappedHeaders = $this->headers ? $this->wrapRow($this->headers, $columns) : array();

        $widths = array_fill(0, $columns, 0);
        foreach (array_merge(array($wrappedHeaders), $wrappedRows) as $row) {
            foreach ($row as $i => $lines) {
                foreach ($lines as $line) {
                    $widths[$i] = max($widths[$i], $this->width($line));
                }
            }
        }

        $chars = $this->borderChars();
        $out   = '';

        if ('none' !== $this->borderStyle) {
            $out .= $this->borderLine($widths, $chars, 'top');
        }
        if ($wrappedHeaders) {
            $out .= $this->renderRowLines($wrappedHeaders, $widths, $chars);
            if ('none' !== $this->borderStyle) {
                $out .= $this->borderLine($widths, $chars, 'middle');
            }
        }
        foreach ($wrappedRows as $row) {
            $out .= $this->renderRowLines($row, $widths, $chars);
        }
        if ('none' !== $this->borderStyle) {
            $out .= $this->borderLine($widths, $chars, 'bottom');
        }

        return $out;
    }

    /**
     * Number of columns = widest of headers/rows.
     *
     * @return int
     */
    private function columnCount()
    {
        $count = count($this->headers);
        foreach ($this->rows as $row) {
            $count = max($count, count($row));
        }
        return $count;
    }

    /**
     * Wrap each cell of a row into lines (explicit newlines also split).
     *
     * @param array $row     Cell values.
     * @param int   $columns Column count (pads short rows).
     * @return array[] Lines per column.
     */
    private function wrapRow(array $row, $columns)
    {
        $out = array();
        for ($i = 0; $i < $columns; $i++) {
            $cell  = isset($row[$i]) ? (string) $row[$i] : '';
            $lines = array();
            foreach (explode("\n", $cell) as $chunk) {
                $lines = array_merge($lines, $this->wrapCell($chunk));
            }
            $out[$i] = $lines;
        }
        return $out;
    }

    /**
     * Wrap one single-line cell at maxCellWidth (word-aware, keeps long words intact by hard-splitting).
     *
     * @param string $cell Cell text.
     * @return string[]
     */
    private function wrapCell($cell)
    {
        if (0 === $this->maxCellWidth || $this->width($cell) <= $this->maxCellWidth) {
            return array($cell);
        }
        $lines   = array();
        $current = '';
        foreach (preg_split('/(\s+)/u', $cell, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
            if ('' === $part) {
                continue;
            }
            if ($this->width($current . $part) <= $this->maxCellWidth) {
                $current .= $part;
                continue;
            }
            if ('' !== trim($current)) {
                $lines[] = rtrim($current);
                $current = ltrim($part);
            } else {
                // Current line is empty (or only whitespace): start it with this part,
                // even if the part alone exceeds the limit — the loop below splits it.
                $current = $part;
            }
            // A single word longer than the limit: hard-split it.
            while ($this->width($current) > $this->maxCellWidth) {
                $lines[] = $this->substr($current, 0, $this->maxCellWidth);
                $current = $this->substr($current, $this->maxCellWidth);
            }
        }
        if ('' !== $current || empty($lines)) {
            $lines[] = $current;
        }
        return $lines;
    }

    /**
     * Render one wrapped row (possibly several physical lines).
     *
     * @param array[] $row    Lines per column.
     * @param int[]   $widths Column widths.
     * @param array   $chars  Border characters.
     * @return string
     */
    private function renderRowLines(array $row, array $widths, array $chars)
    {
        $height = 1;
        foreach ($row as $lines) {
            $height = max($height, count($lines));
        }
        $out = '';
        for ($line = 0; $line < $height; $line++) {
            $cells = array();
            foreach ($row as $i => $lines) {
                $text    = isset($lines[$line]) ? $lines[$line] : '';
                $cells[] = ' ' . $this->align($text, $widths[$i], $this->alignmentFor($i)) . ' ';
            }
            $out .= $chars['v'] . implode($chars['v'], $cells) . $chars['v'] . "\n";
        }
        return $out;
    }

    /**
     * Render a horizontal border line.
     *
     * @param int[]  $widths Column widths.
     * @param array  $chars  Border characters.
     * @param string $which  top|middle|bottom.
     * @return string
     */
    private function borderLine(array $widths, array $chars, $which)
    {
        $parts = array();
        foreach ($widths as $w) {
            $parts[] = str_repeat($chars['h'], $w + 2);
        }
        $left  = $chars[$which . '_l'];
        $mid   = $chars[$which . '_m'];
        $right = $chars[$which . '_r'];
        return $left . implode($mid, $parts) . $right . "\n";
    }

    /**
     * Border characters for the active style.
     *
     * @return array
     */
    private function borderChars()
    {
        if ('light' === $this->borderStyle) {
            return array(
                'h' => '─', 'v' => '│',
                'top_l' => '┌', 'top_m' => '┬', 'top_r' => '┐',
                'middle_l' => '├', 'middle_m' => '┼', 'middle_r' => '┤',
                'bottom_l' => '└', 'bottom_m' => '┴', 'bottom_r' => '┘',
            );
        }
        if ('none' === $this->borderStyle) {
            return array(
                'h' => '', 'v' => ' ',
                'top_l' => '', 'top_m' => '', 'top_r' => '',
                'middle_l' => '', 'middle_m' => '', 'middle_r' => '',
                'bottom_l' => '', 'bottom_m' => '', 'bottom_r' => '',
            );
        }
        return array(
            'h' => '-', 'v' => '|',
            'top_l' => '+', 'top_m' => '+', 'top_r' => '+',
            'middle_l' => '+', 'middle_m' => '+', 'middle_r' => '+',
            'bottom_l' => '+', 'bottom_m' => '+', 'bottom_r' => '+',
        );
    }

    /**
     * Pad a cell to its column width per alignment.
     *
     * @param string $text  Cell text.
     * @param int    $width Target width.
     * @param string $align left|right|center.
     * @return string
     */
    private function align($text, $width, $align)
    {
        $pad = $width - $this->width($text);
        if ($pad <= 0) {
            return $text;
        }
        if ('right' === $align) {
            return str_repeat(' ', $pad) . $text;
        }
        if ('center' === $align) {
            $left  = (int) floor($pad / 2);
            $right = $pad - $left;
            return str_repeat(' ', $left) . $text . str_repeat(' ', $right);
        }
        return $text . str_repeat(' ', $pad);
    }

    /**
     * Alignment for a column (defaults to left).
     *
     * @param int $column Column index.
     * @return string
     */
    private function alignmentFor($column)
    {
        $align = isset($this->alignments[$column]) ? $this->alignments[$column] : 'left';
        return in_array($align, array('left', 'right', 'center'), true) ? $align : 'left';
    }

    /**
     * Display width of a string (multibyte-aware when mbstring is present).
     *
     * @param string $text Text.
     * @return int
     */
    private function width($text)
    {
        if (function_exists('mb_strwidth')) {
            return mb_strwidth($text, 'UTF-8');
        }
        return strlen($text);
    }

    /**
     * Multibyte-aware substring.
     *
     * @param string $text   Text.
     * @param int    $start  Start offset.
     * @param int    $length Length (null = rest).
     * @return string
     */
    private function substr($text, $start, $length = null)
    {
        if (null === $length) {
            $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        }
        if (function_exists('mb_substr')) {
            return mb_substr($text, $start, $length, 'UTF-8');
        }
        return substr($text, $start, $length);
    }
}
