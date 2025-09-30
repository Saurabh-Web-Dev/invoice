<?php
require_once('includes/autoload.php');

class invoicr extends FPDF_rotation
{
    // MODIFIED: Changed 'var' to 'public' for all properties
    public $font = 'helvetica';
    public $columnOpacity = 0.06;
    public $columnSpacing = 0.3;
    public $referenceformat = ['.', ','];
    public $margins = ['l' => 20, 't' => 20, 'r' => 20];

    public $l;
    public $document;
    public $type;
    public $reference;
    public $logo;
    public $color;
    public $date;
    public $due;
    public $from;
    public $to;
    public $ship;
    public $items;
    public $totals;
    public $badge;
    public $addText;
    public $footernote;
    public $dimensions;

    // ADDED: Declared properties that were being created dynamically
    public $columns;
    public $currency;
    public $firstColumnWidth;
    public $maxImageDimensions;
    public $discountField = false;
    public $productsEnded = false;
    public $flipflop = false;
    public $title;
    public $language;


    /*******************************************************************************
     * *
     * Public methods                                  *
     * *
     *******************************************************************************/

    // MODIFIED: Changed to modern __construct method
    public function __construct($size = 'A4', $currency = '€', $language = 'en')
    {
        $this->items = [];
        $this->totals = [];
        $this->addText = [];
        
        // MODIFIED: Moved property initializations to the constructor body from dynamic creation
        $this->columns = 5;
        $this->firstColumnWidth = 70;
        $this->currency = $currency;
        $this->maxImageDimensions = [230, 130];

        $this->setLanguage($language);
        $this->setDocumentSize($size);
        $this->setColor("#222222");

        // MODIFIED: Changed to modern parent constructor call
        parent::__construct('P', 'mm', [$this->document['w'], $this->document['h']]);

        $this->AliasNbPages();
        $this->SetMargins($this->margins['l'], $this->margins['t'], $this->margins['r']);
    }

    public function setType($title)
    {
        $this->title = $title;
    }

    public function setColor($rgbcolor)
    {
        $this->color = $this->hex2rgb($rgbcolor);
    }

    public function setDate($date)
    {
        $this->date = $date;
    }

    public function setDue($date)
    {
        $this->due = $date;
    }

    public function setLogo($logo = 0, $maxWidth = 0, $maxHeight = 0)
    {
        if ($maxWidth && $maxHeight) {
            $this->maxImageDimensions = [$maxWidth, $maxHeight];
        }
        $this->logo = $logo;
        $this->dimensions = $this->resizeToFit($logo);
    }

    public function setFrom($data)
    {
        $this->from = array_filter($data);
    }

    public function setTo($data)
    {
        $this->to = $data;
    }

    public function shipTo($data)
    {
        $this->ship = $data;
    }

    public function setReference($reference)
    {
        $this->reference = $reference;
    }

    public function setNumberFormat($decimals, $thousands_sep)
    {
        $this->referenceformat = [$decimals, $thousands_sep];
    }

    public function flipflop()
    {
        $this->flipflop = true;
    }

    public function addItem($item, $description, $quantity, $vat, $price, $discount = 0, $total)
    {
        $p['item']          = $item;
        $p['description']   = $this->br2nl($description);
        $p['vat']           = $vat;
        if (is_numeric($vat)) {
            $p['vat']       = $this->currency . ' ' . number_format($vat, 2, $this->referenceformat[0], $this->referenceformat[1]);
        }
        $p['quantity']      = $quantity;
        $p['price']         = $price;
        $p['total']         = $total;

        if ($discount !== false) {
            $this->firstColumnWidth = 58;
            $p['discount'] = $discount;
            if (is_numeric($discount)) {
                $p['discount']  = $this->currency . ' ' . number_format($discount, 2, $this->referenceformat[0], $this->referenceformat[1]);
            }
            $this->discountField = true;
            $this->columns = 6;
        }

        $this->items[]      = $p;
    }

    public function addTotal($name, $value, $colored = 0)
    {
        $t['name']          = $name;
        $t['value']         = $value;
        if (is_numeric($value)) {
            $t['value']         = $this->currency . ' ' . number_format($value, 2, $this->referenceformat[0], $this->referenceformat[1]);
        }
        $t['colored']       = $colored;
        $this->totals[]     = $t;
    }

    public function addTitle($title)
    {
        $this->addText[] = ['title', $title];
    }

    public function addParagraph($paragraph)
    {
        $paragraph = $this->br2nl($paragraph);
        $this->addText[] = ['paragraph', $paragraph];
    }

    public function addBadge($badge)
    {
        $this->badge = $badge;
    }

    public function setFooternote($note)
    {
        $this->footernote = $note;
    }

    public function render($name = '', $destination = '')
    {
        $this->AddPage();
        $this->Body();
        $this->AliasNbPages();
        $this->Output($name, $destination);
    }
    
    /*******************************************************************************
     * *
     * Create Invoice                                  *
     * *
     *******************************************************************************/

    public function Header()
    {
        if (isset($this->logo) && !empty($this->logo)) {
            $this->Image($this->logo, $this->margins['l'], $this->margins['t'], $this->dimensions[0], $this->dimensions[1]);
        }

        //Title
        $this->SetTextColor(0, 0, 0);
        $this->SetFont($this->font, 'B', 20);
        $this->Cell(0, 5, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($this->title)), 0, 1, 'R');
        $this->SetFont($this->font, '', 9);
        $this->Ln(5);

        $lineheight = 5;
        //Calculate position of strings
        $this->SetFont($this->font, 'B', 9);
        $positionX = $this->document['w'] - $this->margins['l'] - $this->margins['r'] - max(strtoupper($this->GetStringWidth($this->l['number'])), strtoupper($this->GetStringWidth($this->l['date'])), strtoupper($this->GetStringWidth($this->l['due']))) - 35;

        //Number
        $this->Cell($positionX, $lineheight);

        $this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
        $this->Cell(32, $lineheight, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($this->l['number']) . ':'), 0, 0, 'L');
        $this->SetTextColor(50, 50, 50);
        $this->SetFont($this->font, '', 9);
        $this->Cell(0, $lineheight, $this->reference, 0, 1, 'R');

        //Date
        $this->Cell($positionX, $lineheight);
        $this->SetFont($this->font, 'B', 9);
        $this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
        $this->Cell(32, $lineheight, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($this->l['date'])) . ':', 0, 0, 'L');
        $this->SetTextColor(50, 50, 50);
        $this->SetFont($this->font, '', 9);
        $this->Cell(0, $lineheight, $this->date, 0, 1, 'R');

        //Due date
        if ($this->due) {
            $this->Cell($positionX, $lineheight);
            $this->SetFont($this->font, 'B', 9);
            $this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
            $this->Cell(32, $lineheight, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($this->l['due'])) . ':', 0, 0, 'L');
            $this->SetTextColor(50, 50, 50);
            $this->SetFont($this->font, '', 9);
            $this->Cell(0, $lineheight, $this->due, 0, 1, 'R');
        }

        //First page
        if ($this->PageNo() == 1) {
            if (($this->margins['t'] + ($this->dimensions[1] ?? 0)) > $this->GetY()) {
                $this->SetY($this->margins['t'] + ($this->dimensions[1] ?? 0) + 10);
            } else {
                $this->SetY($this->GetY() + 10);
            }
            $this->Ln(5);
            $this->SetTextColor($this->color[0], $this->color[1], $this->color[2]);
            $this->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
            $this->SetFont($this->font, 'B', 10);
            $width = ($this->document['w'] - $this->margins['l'] - $this->margins['r']) / 3;
            if ($this->flipflop) {
                $to = $this->l['to'];
                $from = $this->l['from'];
                $this->l['to'] = $from;
                $this->l['from'] = $to;

                $to = $this->to;
                $from = $this->from;
                $this->to = $from;
                $this->from = $to;
            }
            $this->Cell($width, $lineheight, strtoupper($this->l['from']), 0, 0, 'L');
            $this->Cell($width, $lineheight, strtoupper($this->l['to']), 0, 0, 'L');
            $this->Cell(0, $lineheight, strtoupper($this->l['ship']), 0, 0, 'L');
            $this->Ln(7);
            $this->SetLineWidth(0.3);
            $this->Line($this->margins['l'], $this->GetY(), $this->margins['l'] + $width - 10, $this->GetY());
            $this->Line($this->margins['l'] + $width, $this->GetY(), $this->margins['l'] + $width * 2 - 10, $this->GetY());
            $this->Line($this->margins['l'] + $width * 2, $this->GetY(), $this->margins['l'] + $width * 3, $this->GetY());

            //Information
            $this->Ln(5);
            $this->SetTextColor(50, 50, 50);
            $this->SetFont($this->font, 'B', 10);
            $from_name = isset($this->from[0]) ? $this->from[0] : '';
            $to_name = isset($this->to[0]) ? $this->to[0] : '';
            $ship_name = isset($this->ship[0]) ? $this->ship[0] : '';
            $this->Cell($width, $lineheight, iconv("UTF-8", "ISO-8859-1//TRANSLIT", $from_name), 0, 0, 'L');
            $this->Cell($width, $lineheight, iconv("UTF-8", "ISO-8859-1//TRANSLIT", $to_name), 0, 0, 'L');
            $this->Cell(0, $lineheight, iconv("UTF-8", "ISO-8859-1//TRANSLIT", $ship_name), 0, 1, 'L');

            $this->SetFont($this->font, '', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Ln(2);

            $maxLines = max(count($this->from), count($this->to), count($this->ship));
            for ($i = 1; $i < $maxLines; $i++) {
                $from_line = isset($this->from[$i]) ? $this->from[$i] : '';
                $to_line = isset($this->to[$i]) ? $this->to[$i] : '';
                $ship_line = isset($this->ship[$i]) ? $this->ship[$i] : '';
                $this->Cell($width, $lineheight, iconv("UTF-8", "ISO-8859-1//TRANSLIT", $from_line), 0, 0, 'L');
                $this->Cell($width, $lineheight, iconv("UTF-8", "ISO-8859-1//TRANSLIT", $to_line), 0, 0, 'L');
                $this->Cell(0, $lineheight, iconv("UTF-8", "ISO-8859-1//TRANSLIT", $ship_line), 0, 1, 'L');
            }
            $this->Ln(5);
        }
        
        //Table header
        if (!$this->productsEnded) {
            $width_other = ($this->document['w'] - $this->margins['l'] - $this->margins['r'] - $this->firstColumnWidth - ($this->columns * $this->columnSpacing)) / ($this->columns - 1);
            $this->SetTextColor(50, 50, 50);
            $this->Ln(12);
            $this->SetFont($this->font, 'B', 9);
            $this->Cell(1, 10, '', 0, 0, 'L', 0);
            $this->Cell($this->firstColumnWidth, 10, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($this->l['product'])), 0, 0, 'L', 0);
            $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $this->Cell($width_other, 10, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($this->l['amount'])), 0, 0, 'C', 0);
            $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $this->Cell($width_other, 10, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($this->l['vat'])), 0, 0, 'C', 0);
            $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $this->Cell($width_other, 10, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($this->l['price'])), 0, 0, 'C', 0);
            if ($this->discountField) {
                $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
                $this->Cell($width_other, 10, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($this->l['discount'])), 0, 0, 'C', 0);
            }
            $this->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $this->Cell($width_other, 10, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($this->l['total'])), 0, 0, 'C', 0);
            $this->Ln();
            $this->SetLineWidth(0.3);
            $this->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
            $this->Line($this->margins['l'], $this->GetY(), $this->document['w'] - $this->margins['r'], $this->GetY());
            $this->Ln(2);
        } else {
            $this->Ln(12);
        }
    }

    public function Body()
    {
        $width_other = ($this->document['w'] - $this->margins['l'] - $this->margins['r'] - $this->firstColumnWidth - ($this->columns * $this->columnSpacing)) / ($this->columns - 1);
        $cellHeight = 9;
        $bgcolor = (1 - $this->columnOpacity) * 255;
        if ($this->items) {
            foreach ($this->items as $item) {
                if (!empty($item['description'])) {
                    $calculateHeight = new self(); // Use self for new instance
                    $calculateHeight->addPage();
                    $calculateHeight->setXY(0, 0);
                    $calculateHeight->SetFont($this->font, '', 7);
                    $calculateHeight->MultiCell($this->firstColumnWidth, 3, iconv("UTF-8", "ISO-8859-1//TRANSLIT", $item['description']), 0, 'L', 1);
                    $descriptionHeight = $calculateHeight->getY() + $cellHeight + 2;
                    $pageHeight = $this->document['h'] - $this->GetY() - $this->margins['t'] * 2;
                    if ($this->GetY() + $descriptionHeight > $this->document['h'] - $this->margins['t']) {
                        $this->AddPage();
                    }
                }

                $cHeight = $cellHeight;
                $this->SetFont($this->font, 'b', 8);
                $this->SetTextColor(50, 50, 50);
                $this->SetFillColor($bgcolor, $bgcolor, $bgcolor);
                $this->Cell(1, $cHeight, '', 0, 0, 'L', 1);
                $x = $this->GetX();
                $y = $this->GetY();
                
                $this->MultiCell($this->firstColumnWidth, $cHeight, iconv("UTF-8", "ISO-8859-1//TRANSLIT", $item['item']), 0, 'L', 1);
                $newY = $this->GetY();
                $cHeight = $newY - $y;
                $this->SetXY($x + $this->firstColumnWidth, $y);
                
                if (!empty($item['description'])) {
                    $resetX = $this->GetX();
                    $resetY = $this->GetY();
                    $this->SetTextColor(120, 120, 120);
                    $this->SetXY($x, $this->GetY() + $cHeight);
                    $this->SetFont($this->font, '', 7);
                    $this->MultiCell($this->firstColumnWidth, 3, iconv("UTF-8", "ISO-8859-1//TRANSLIT", $item['description']), 0, 'L', 1);
                    $newY = $this->GetY();
                    $cHeight = $newY - $resetY;
                    $this->SetXY($x - 1, $resetY);
                    $this->Cell(1, $cHeight, '', 0, 0, 'L', 1);
                    $this->SetXY($resetX, $resetY);
                }

                $this->SetTextColor(50, 50, 50);
                $this->SetFont($this->font, '', 8);
                $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                $this->Cell($width_other, $cHeight, $item['quantity'], 0, 0, 'C', 1);
                $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                $this->Cell($width_other, $cHeight, iconv('UTF-8', 'windows-1252//TRANSLIT', $item['vat']), 0, 0, 'C', 1);
                $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                $this->Cell($width_other, $cHeight, iconv('UTF-8', 'windows-1252//TRANSLIT', $this->currency . ' ' . number_format($item['price'], 2, $this->referenceformat[0], $this->referenceformat[1])), 0, 0, 'C', 1);
                if ($this->discountField) {
                    $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    $this->Cell($width_other, $cHeight, iconv('UTF-8', 'windows-1252//TRANSLIT', $item['discount'] ?? ''), 0, 0, 'C', 1);
                }
                $this->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                $this->Cell($width_other, $cHeight, iconv('UTF-8', 'windows-1252//TRANSLIT', $this->currency . ' ' . number_format($item['total'], 2, $this->referenceformat[0], $this->referenceformat[1])), 0, 0, 'C', 1);
                $this->Ln($cHeight);
                $this->Ln($this->columnSpacing);
            }
        }

        $badgeX = $this->getX();
        $badgeY = $this->getY();
        
        //Add totals
        if ($this->totals) {
            foreach ($this->totals as $total) {
                $this->SetTextColor(50, 50, 50);
                $this->SetFillColor($bgcolor, $bgcolor, $bgcolor);
                $this->Cell(1 + $this->firstColumnWidth, $cellHeight, '', 0, 0, 'L', 0);
                for ($i = 0; $i < $this->columns - 3; $i++) {
                    $this->Cell($width_other, $cellHeight, '', 0, 0, 'L', 0);
                    $this->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
                }
                $this->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
                if ($total['colored']) {
                    $this->SetTextColor(255, 255, 255);
                    $this->SetFillColor($this->color[0], $this->color[1], $this->color[2]);
                }
                $this->SetFont($this->font, 'b', 8);
                $this->Cell(1, $cellHeight, '', 0, 0, 'L', 1);
                $this->Cell($width_other - 1, $cellHeight, iconv('UTF-8', 'windows-1252//TRANSLIT', $total['name']), 0, 0, 'L', 1);
                $this->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
                $this->SetFont($this->font, 'b', 8);
                $this->SetFillColor($bgcolor, $bgcolor, $bgcolor);
                if ($total['colored']) {
                    $this->SetTextColor(255, 255, 255);
                    $this->SetFillColor($this->color[0], $this->color[1], $this->color[2]);
                }
                $this->Cell($width_other, $cellHeight, iconv('UTF-8', 'windows-1252//TRANSLIT', $total['value']), 0, 0, 'C', 1);
                $this->Ln();
                $this->Ln($this->columnSpacing);
            }
        }
        $this->productsEnded = true;
        $this->Ln();
        $this->Ln(3);

        //Badge
        if ($this->badge) {
            $badge = ' ' . strtoupper($this->badge) . ' ';
            $resetX = $this->getX();
            $resetY = $this->getY();
            $this->setXY($badgeX, $badgeY + 15);
            $this->SetLineWidth(0.4);
            $this->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
            $this->setTextColor($this->color[0], $this->color[1], $this->color[2]);
            $this->SetFont($this->font, 'b', 15);
            $this->Rotate(10, $this->getX(), $this->getY());
            $this->Rect($this->GetX(), $this->GetY(), $this->GetStringWidth($badge) + 2, 10);
            $this->Write(10, $badge);
            $this->Rotate(0);
            if ($resetY > $this->getY() + 20) {
                $this->setXY($resetX, $resetY);
            } else {
                $this->Ln(18);
            }
        }

        //Add information
        foreach ($this->addText as $text) {
            if ($text[0] == 'title') {
                $this->SetFont($this->font, 'b', 9);
                $this->SetTextColor(50, 50, 50);
                $this->Cell(0, 10, iconv("UTF-8", "ISO-8859-1//TRANSLIT", strtoupper($text[1])), 0, 0, 'L', 0);
                $this->Ln();
                $this->SetLineWidth(0.3);
                $this->SetDrawColor($this->color[0], $this->color[1], $this->color[2]);
                $this->Line($this->margins['l'], $this->GetY(), $this->document['w'] - $this->margins['r'], $this->GetY());
                $this->Ln(4);
            }
            if ($text[0] == 'paragraph') {
                $this->SetTextColor(80, 80, 80);
                $this->SetFont($this->font, '', 8);
                $this->MultiCell(0, 4, iconv("UTF-8", "ISO-8859-1//TRANSLIT", $text[1]), 0, 'L', 0);
                $this->Ln(4);
            }
        }
    }

    public function Footer()
    {
        $this->SetY(-$this->margins['t']);
        $this->SetFont($this->font, '', 8);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(0, 10, $this->footernote, 0, 0, 'L');
        $this->Cell(0, 10, $this->l['page'] . ' ' . $this->PageNo() . ' ' . $this->l['page_of'] . ' {nb}', 0, 0, 'R');
    }

    /*******************************************************************************
     * *
     * Private methods                                 *
     * *
     *******************************************************************************/
    private function setLanguage($language)
    {
        $this->language = $language;
        include('languages/' . $language . '.inc');
        $this->l = $l;
    }

    private function setDocumentSize($dsize)
    {
        switch ($dsize) {
            case 'A4':
                $document['w'] = 210;
                $document['h'] = 297;
                break;
            case 'letter':
                $document['w'] = 215.9;
                $document['h'] = 279.4;
                break;
            case 'legal':
                $document['w'] = 215.9;
                $document['h'] = 355.6;
                break;
            default:
                $document['w'] = 210;
                $document['h'] = 297;
                break;
        }
        $this->document = $document;
    }

    // MODIFIED: Added check for file existence to prevent fatal errors
    private function resizeToFit($image)
    {
        if (!$image || !file_exists($image) || !is_readable($image)) {
            return [0, 0]; // Return zero dimensions if image is invalid
        }

        list($width, $height) = getimagesize($image);
        $newWidth = $this->maxImageDimensions[0] / $width;
        $newHeight = $this->maxImageDimensions[1] / $height;
        $scale = min($newWidth, $newHeight);
        return [
            round($this->pixelsToMM($scale * $width)),
            round($this->pixelsToMM($scale * $height))
        ];
    }

    private function pixelsToMM($val)
    {
        $mm_inch = 25.4;
        $dpi = 96;
        return $val * $mm_inch / $dpi;
    }

    private function hex2rgb($hex)
    {
        $hex = str_replace("#", "", $hex);

        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return [$r, $g, $b];
    }

    private function br2nl($string)
    {
        return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
    }
}
?>