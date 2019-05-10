<?php
require_once(dirname(__DIR__) . '/general/kireport_general_Functions.php');
require_once(dirname(__DIR__) . '/pdf/kireport_pdf_AlphaPdf.php');

class kireport_pdf_Fpdf extends kireport_pdf_AlphaPdf {
    private $_settings = null;
    
    private $_kiCalcPdf = null;         // Klon von kireport_pdf_Fpdf um Höhen zu berechnen (kiGetMultiCellHeight und kiGetImageSize)
    private $_fontDir = array();

    // Code 128 Variablen
    private $_code128_isInitialized = false;
    private $_T128 = array();                                   // tableau des codes 128
    private $_Aset='';                                          // Set A du jeu des caractères éligibles
    private $_Bset='';                                          // Set B du jeu des caractères éligibles
    private $_Cset='';                                          // Set C du jeu des caractères éligibles
    private $_SetFrom = array();                                // Convertisseur source des jeux vers le tableau
    private $_SetTo = array();                                  // Convertisseur destination des jeux vers le tableau
    private $_JStart = array('A'=>103, 'B'=>104, 'C'=>105);     // Caractères de sélection de jeu au début du C128
    private $_JSwap = array('A'=>101, 'B'=>100, 'C'=>99);       // Caractères de changement de jeu
    
    // HTML2PDF Variablen
    private $_href = '';                // HTML2PDF
    private $_styles = array();         // HTML2PDF
    private $_currentLineHeight = 0;    // HTML2PDF
    
    // JS Variablen
    private $_kiJavaScript;
    private $_ki_n_Js;

    // Formular Variablen
    private $_ki_page_Annots = array();
    private $_ki_acroFormFields = array();
    private $_ki_FormFields = array();

    // rotate Variabeln
    private $_ki_angle = 0;
    
    // -------------------------------------------------------
    // Overwrites
    // -------------------------------------------------------
    
    public function __construct($settings=null, $author='', $creator='', $fontDir=null) {
        $this->_settings = $settings ? $settings : new stdClass();
        
        $orientation = $this->_settings->orientation ? $this->_settings->orientation : 'P';
        $size = $this->_settings->size ? $this->_settings->size : 'A4';

        if (is_array($fontDir)) {
            $this->_fontDir = $fontDir;
        }
        
        // call parent
        parent::__construct($orientation, 'mm', $size);
        
        // Zusätzliche Schriften einbinden
        if ($this->_settings->fonts) {
            foreach ($this->_settings->fonts as $font) {
                $this->AddFont($font->family, $font->style, $font->file, $font->unicode);
            }
        }
        
        $this->kiSetMarginsFromString($this->_settings->margins);
        
        if (isset($this->_settings->title)) {
            $this->SetTitle($this->_settings->title, true);
        }
        if (isset($this->_settings->subtitle)) {
            $this->SetSubject($this->_settings->subtitle, true);
        }
        if ($author) {
            $this->SetAuthor($author, true);
        }
        if ($creator) {
            $this->SetCreator($creator, true);
        }
        $this->AliasNbPages();
    }


    protected function _endpage() {
        if ($this->_ki_angle !== 0) {
            $this->_out('Q');
            $this->_ki_angle = 0;
        }

        parent::_endpage();    
    }

    protected function _getfontpath($filename=null, $unicode=false) {
        if ($filename) {
            foreach ($this->_fontDir as $fontDir) {
                if ($unicode) {
                    $fontDir .= '/unifont';
                }
                
                if (is_file($fontDir . '/' . $filename)) {
                    return $fontDir;
                }
            }
        }
        return parent::_getfontpath($filename, $unicode);
    }


    protected function _putheader() {
        parent::_putheader();

        // Textfeld-Objekte gleich nach dem Header, da die IDs im
        // _putpages() zur Verfügung stehen müssen.
        $this->_kiPutFields();
    }

    /**
     * Anpassungen: Eigene Annots hinzufügen
     *              Objekt-ID der Seiten speichern und im "Pages"-Objekt ausgeben
     */
    protected function _putpages() {
        $nb = $this->page;
        $pageN = array();

        if (!empty($this->AliasNbPages)) {
            // Replace number of pages in fonts using subsets
            $alias = $this->UTF8ToUTF16BE($this->AliasNbPages, false);
            $r = $this->UTF8ToUTF16BE("$nb", false);
            for($n=1;$n<=$nb;$n++) {
                $this->pages[$n] = str_replace($alias,$r,$this->pages[$n]);
            }
            // Now repeat for no pages in non-subset fonts
            for($n=1;$n<=$nb;$n++) {
                $this->pages[$n] = str_replace($this->AliasNbPages,$nb,$this->pages[$n]);
            }
        }
        if ($this->DefOrientation=='P') {
            $wPt = $this->DefPageSize[0]*$this->k;
            $hPt = $this->DefPageSize[1]*$this->k;
        } else {
            $wPt = $this->DefPageSize[1]*$this->k;
            $hPt = $this->DefPageSize[0]*$this->k;
        }
        $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
        for($n=1;$n<=$nb;$n++) {
            // Page
            $this->_newobj();
            $pageN[] = (int) $this->n;
            $this->_out('<</Type /Page');
            $this->_out('/Parent 1 0 R');
            if (isset($this->PageSizes[$n]))
                $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->PageSizes[$n][0],$this->PageSizes[$n][1]));
            $this->_out('/Resources 2 0 R');
            
            $annots = '';
            if (isset($this->PageLinks[$n])) {
                // Links
                foreach($this->PageLinks[$n] as $pl) {
                    $rect = sprintf('%.2F %.2F %.2F %.2F',$pl[0],$pl[1],$pl[0]+$pl[2],$pl[1]-$pl[3]);
                    $annots .= '<</Type /Annot /Subtype /Link /Rect ['.$rect.'] /Border [0 0 0] ';
                    if (is_string($pl[4]))
                        $annots .= '/A <</S /URI /URI '.$this->_textstring($pl[4]).'>>>>';
                    else
                    {
                        $l = $this->links[$pl[4]];
                        $h = isset($this->PageSizes[$l[0]]) ? $this->PageSizes[$l[0]][1] : $hPt;
                        $annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>',1+2*$l[0],$h-$l[1]*$this->k);
                    }
                }
            }

            // Eigene Annots hinzufügen
            if (array_key_exists($n, $this->_ki_page_Annots) && is_array($this->_ki_page_Annots[$n])) {
                if ($annots) {
                    $annots .= ' ';
                }
                foreach ($this->_ki_page_Annots[$n] as $kiAnnot) {
                    if (is_int($kiAnnot)) {
                        $annots .= $kiAnnot . ' 0 R ';
                    } else if (is_string($kiAnnot)) {
                        $annots .= $kiAnnot . ' ';
                    }
                }
            }

            if ($annots) {
                $this->_out('/Annots [' . $annots . ']');
            }

            if ($this->PDFVersion>'1.3')
                $this->_out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
            $this->_out('/Contents '.($this->n+1).' 0 R>>');
            $this->_out('endobj');
            // Page content
            $p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
            $this->_newobj();
            $this->_out('<<'.$filter.'/Length '.strlen($p).'>>');
            $this->_putstream($p);
            $this->_out('endobj');
        }
        
        // Pages root
        $this->offsets[1] = strlen($this->buffer);
        $this->_out('1 0 obj');
        $this->_out('<</Type /Pages');
        $kids = '/Kids [';
        foreach ($pageN as $pn) {
            $kids .= $pn . ' 0 R ';
        }
        $this->_out($kids.']');
        $this->_out('/Count '.$nb);
        $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]',$wPt,$hPt));
        $this->_out('>>');
        $this->_out('endobj');
    }


    protected function _putresources() {
        parent::_putresources();
        if ($this->_kiJavaScript) {
            $this->_ki_n_Js = $this->_kiPutJavaScript($this->_kiJavaScript);
        }
    }

    protected function _putcatalog() {
        parent::_putcatalog();

        // Acroform
        if ($this->_ki_acroFormFields) {
            $acroForm = '/AcroForm <</NeedAppearances true /Fields [';

            foreach ($this->_ki_acroFormFields as $acroFormFieldId) {
                if (is_string($acroFormFieldId)) {
                    $acroForm .= $acroFormFieldId . ' ';
                } else if (is_int($acroFormFieldId)) {
                    $acroForm .= $acroFormFieldId . ' 0 R ';
                }
            }
            $acroForm .= ']>>';
            $this->_out($acroForm);
        }

        // Javascript
        if ($this->_kiJavaScript) {
            $this->_out('/Names <</JavaScript ' . ($this->_ki_n_Js) . ' 0 R>>');
        }
    }

    // -------------------------------------------------------
    // Public Methods
    // -------------------------------------------------------

    /**
     * Fügt eine neue Seite hinzu
     * @param string $orientation
     * @param string $size
     * @param string $margins
     */
    public function kiAddPage($orientation='', $size='', $margins='') {
        $this->AddPage($orientation, $size);
        $this->kiSetMarginsFromString($margins);
    }
    
    /**
     * Wendet ein Style-Objekt an
     * @param object|array $style Style Objekt oder Array mit mehreren Style-Objekten
     * @return object 
     */
    public function kiApplyStyle($style) {
        // Styles anwenden
        if (isset($style->alpha)) $this->setAlpha($style->alpha, $style->blendMode);
        if (isset($style->fillColor)) $this->setColor('fill', $style->fillColor);
        if (isset($style->textColor)) $this->setColor('text', $style->textColor);
        if (isset($style->drawColor)) $this->setColor('draw', $style->drawColor);
        if (isset($style->lineWidth)) $this->SetLineWidth($style->lineWidth);
        if (isset($style->font)) $this->setFontFromObject($style->font);

        $this->kiRotate(
                is_object($style->rotation) ? $style->rotation->rotation : 0,
                is_object($style->rotation) ? $style->rotation->rotateX : -1,
                is_object($style->rotation) ? $style->rotation->rotateY : -1
            );

        
        return $style;
    }

    /**
     * Zeichnet eine Chart Grfik
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @param string $type
     * @param array/string $labels
     * @param array/string $values
     * @param object $properties
     */
    public function kiChart($x, $y, $width, $height, $type, $labels, $values, $properties) {
      
        // Strings und Arrays falls nötig umwandeln
        if (!is_array($labels)) $labels = explode(';', $labels);
        $colors = (is_string($properties->colors)) ? explode(';', $properties->colors) : $properties->colors;
        if (is_string($values)) {
            $valuesArray = array(explode(';', $values));
        } else {
            $valuesArray = array();
            foreach ($values as $value) {
                $valuesArray[] = explode(';', $value);
            }
        }

        // Max Value festlegen
        $maxValue = $properties->maxValue;
        if ($maxValue) {
            foreach($valuesArray as $key1=>$values) {
                foreach ($values as $key2=>$value) {
                    $valuesArray[$key1][$key2] = ($value > $maxValue) ? $maxValue : $value;
                }
            }
        } else {
            foreach($valuesArray as $values) {
                foreach ($values as $value) {
                    $maxValue = ($value > $maxValue) ? $value : $maxValue;
                }
            }
            $maxValue = ceil($maxValue/5)*5;
        }

        // Eigenschaften
        $this->SetLineWidth(0.1);
        $precision = ($properties->precision) ? $properties->precision : 5;
        $AnzDiagrams = count($valuesArray);
        $AnzValues = 0;
        foreach ($valuesArray as $values) {
            $AnzValues = ($AnzValues > count($values)) ? $AnzValues : count($values);
        }
     
        switch ($type) {
            // Radar Chart
            case 'radar':
                // Zusätzliche Breite für den Text berechnen
                $this->SetFont('Arial', B, $height/12);
                $keyLabel1 = ceil($AnzValues/4)+1;
                $keyLabel2 = $AnzValues-ceil($AnzValues/4)+1;
                $widthLabel1 = $this->GetStringWidth($labels[$keyLabel1]);
                $widthLabel2 = $this->GetStringWidth($labels[$keyLabel2]);
     
                // 4 statt nur 2 Labels sind relevant im Sonderfall
                if (($AnzValues-2)%4 == 0) {
                    $widthLabel3 = $this->GetStringWidth($labels[$keyLabel1-1]);
                    $widthLabel4 = $this->GetStringWidth($labels[$keyLabel2-1]);
                    $widthLabel1 = ($widthLabel1 > $widthLabel3) ? $widthLabel1 : $widthLabel3;
                    $widthLabel2 = ($widthLabel2 > $widthLabel4) ? $widthLabel2 : $widthLabel4;
                }
                $widthLabels = $widthLabel1+$widthLabel2;
              
                // Zentrum berechnen
                $r = ($height < $width-$widthLabels) ? $height/2 : ($width-$widthLabels)/2;
                $chartX = $x + $r + $widthLabel2;
                $chartY = $y + $r;
                $zoom = ($r / 1.05)/$maxValue; 
                  
                // Referenz Koordinaten berechnen, min 3 Werte werden für die Darstellung benötigt
                if ($AnzValues < 3) break;
                $refPoints = array();
                for($i = 0; $i < $AnzValues; $i++) {
                    $point = new stdClass();
                    $point->x = sin(deg2rad((360/$AnzValues)*$i));
                    $point->y = -cos(deg2rad((360/$AnzValues)*$i));
                    $refPoints[] = $point;
                }

                // Achsen zeichnen
                foreach ($refPoints as $key=>$point) {
                    $this->Line($chartX, $chartY, $chartX+$point->x*$maxValue*$zoom, $chartY+$point->y*$maxValue*$zoom);
                   
                    // Beschriften
                    $this->SetFont('Arial', 'B', $height/12); // 1.7*$zoom
                    $align = ($point->x > 0.001) ? 'L' : 'R';
                    $this->SetXY($chartX+$point->x*$maxValue*$zoom, $chartY+$point->y*$maxValue*$zoom);
                    $this->Cell(0.1, 0, $labels[$key], 0, 0, $align);
                }

                // Netz zeichnen
                for($i = 1; $i <= $precision; $i++) {
                    $diff = $maxValue / $precision * $i * $zoom;
                    $polyPoints = array();
                    foreach($refPoints as $point) {
                        $polyPoints[] = $chartX+$point->x*$diff;
                        $polyPoints[] = $chartY+$point->y*$diff;
                    }
                    $this->kiPolygon($polyPoints);

                    // Beschriften
                    $this->SetFont('Arial', '', $height/13);
                    $this->SetXY($chartX, $chartY+$refPoints[0]->y*$diff);
                    $this->Cell(0, 0, $maxValue/$precision*$i, 0, 0, 'L');
                }
  
                // Daten zeichnen
                $this->SetLineWidth(0.4);
                foreach ($valuesArray as $key1=>$values) {
                    $polyPoints = array();
                    foreach($refPoints as $key2=>$point) {
                        $polyPoints[] = $chartX+$point->x*$values[$key2]*$zoom;
                        $polyPoints[] = $chartY+$point->y*$values[$key2]*$zoom;
                    }
                    $this->setColor('draw', $colors[$key1]);
                    $this->kiPolygon($polyPoints);
                }
                break;

            // Balkendiagram
            case 'bar':
                // Label Breite und Höhe berechnen
                $this->SetFont('Arial', B, $height/12);
                foreach ($valuesArray as $values) {
                    foreach ($values as $value) {
                        $widthValue = $this->GetStringWidth($value);
                        $widthLabel = ($widthValue > $widthLabel) ? $widthValue : $widthValue;
                    }
                }
                $heightLabel = ($height/12)*25.4/72;

                // Chart Koordinaten berechnen
                $chartHeight = $height - $heightLabel - ($height/40);
                $chartWidth = $width - $widthLabel - ($height/40);
                $chartX = $x + $widthLabel + ($height/40);
                $chartY = $y + $chartHeight;
                
                // Achsen und Raster zeichnen
                $this->Line($chartX, $y, $chartX, $chartY);
                for ($i = 0; $i <= $precision; $i++) {
                    $diffY = $chartY-($chartHeight/$precision*$i);
                    $this->Line($chartX, $diffY, $chartX+$chartWidth, $diffY);
                    
                    // Werte beschriften
                    $this->SetFont('Arial', '', $height/12);
                    $this->SetXY($chartX, $diffY);
                    $this->Cell(0.1, 0, $maxValue/$precision*$i, 0, 0, 'R');
                }

                // Balken zeichnen und beschriften
                $widthBar = $chartWidth/$AnzValues/$AnzDiagrams;
                foreach ($valuesArray as $key=>$values) {
                    for ($i = 0; $i < $AnzValues; $i++) {
                        $this->setColor('fill', $colors[$key]);
                        
                        // Koordinaten berechnen und Diagramm zeichnen
                        $rectX = $chartX+$widthBar*$AnzDiagrams*$i+($key)*$widthBar+$widthBar/10;
                        $rectWidth = $widthBar-$widthBar/5;
                        $rectHeight = -$values[$i]*$chartHeight/$maxValue;
                        $this->Rect($rectX, $chartY, $rectWidth, $rectHeight, "F");

                        // Beschriften
                        $this->SetFont('Arial', 'B', $height/12);
                        $this->SetXY($chartX+$widthBar*$AnzDiagrams*$i, $chartY+$heightLabel);
                        $this->Cell($widthBar*$AnzDiagrams, 0, $labels[$i], 0, 0, 'C');                    
                    }
                }
                
                break;                
                      
            // Liniendiagramm
            case 'line':

            default:
                // Label Breite und Höhe berechnen
                $this->SetFont('Arial', B, $height/12);
                foreach ($valuesArray as $values) {
                    foreach ($values as $value) {
                        $widthValue = $this->GetStringWidth($value);
                        $widthLabel = ($widthValue > $widthLabel) ? $widthValue : $widthValue;
                    }
                }
                $heightLabel = ($height/12)*25.4/72;

                // Chart Koordinaten berechnen
                $chartHeight = $height - $heightLabel - ($height/40);
                $chartWidth = $width - $widthLabel - ($height/40);
                $chartX = $x + $widthLabel + ($height/40);
                $chartY = $y + $chartHeight;
                
                // Achsen und Raster zeichnen
                $this->Line($chartX, $y, $chartX, $chartY);
                for ($i = 0; $i <= $precision; $i++) {
                    $diffY = $chartY-($chartHeight/$precision*$i);
                    $this->Line($chartX, $diffY, $chartX+$chartWidth, $diffY);
                    
                    // Y-Achse beschriften
                    $this->SetFont('Arial', '', $height/12);
                    $this->SetXY($chartX, $diffY);
                    $this->Cell(0.1, 0, $maxValue/$precision*$i, 0, 0, 'R');
                }

                // Diagramm zeichnen und beschriften
                $widthPoint = $chartWidth/$AnzValues;
                foreach ($valuesArray as $key=>$values) {
                    for ($i = 1; $i < $AnzValues; $i++) {
                        $this->setColor('fill', $colors[$key]);
                        $this->setColor('draw', $colors[$key]);
                        $this->SetLineWidth(0.4);
                        
                        // Koordinaten berechnen
                        $lineX = $chartX+$widthPoint*$i-$widthPoint/2;
                        $lineX2 = $chartX+$widthPoint*($i+1)-$widthPoint/2;
                        $lineY = $chartY-$values[$i-1]*$chartHeight/$maxValue;
                        $lineY2 = $chartY-$values[$i]*$chartHeight/$maxValue;
                        $circleX1 = $chartX+$widthPoint*$i-$widthPoint/2;
                        $circleX2 = $chartX+$widthPoint*($i+1)-$widthPoint/2;
                        $circleY1 = $chartY-$values[$i-1]*$chartHeight/$maxValue;
                        $circleY2 = $chartY-$values[$i]*$chartHeight/$maxValue;

                        // Diagramm zeichnen
                        $this->Line($lineX, $lineY, $lineX2, $lineY2);
                        $this->kiCircle($circleX1, $circleY1, $height/80, 'F');
                        $this->kiCircle($circleX2, $circleY2, $height/80, 'F');

                        // X-Achse Beschriften
                        $this->SetFont('Arial', 'B', $height/12);
                        $this->SetXY($chartX+$widthPoint*$i, $chartY+$heightLabel);
                        $this->Cell($widthPoint, 0, $labels[$i], 0, 0, 'C');                    
                    }
                }
                $this->SetXY($chartX, $chartY+$heightLabel);
                $this->Cell($widthPoint, 0, $labels[0], 0, 0, 'C');  
        }        
    }
    
    /**
     * Kreis
     * Siehe http://www.fpdf.org/en/script/script6.php
     * @param Number $x X-Position
     * @param Number $y Y-Position
     * @param Number $r Radius
     * @param String [$style='D'] 'D'=nur Rand, 'F'=nur Füllung, 'DF'=Rand und Füllung
     */
    public function kiCircle($x, $y, $r, $style='D') {
        $this->kiEllipse($x, $y, $r, $r, $style);
    }

    /**
     * Funktion zum Erstellen von Code128 barcode
     * Author: Roland Gautier
     * Lizenz: FPDF
     * @param int $x, $y
     * @param string $code
     * @param int $w, $h
     */
    public function kiCode128($x, $y, $code, $w, $h) {
        // initialisieren
        if (!$this->_code128_isInitialized) $this->_code128Init();

        // Création des guides de choix ABC
        $Aguid = "";
        $Bguid = "";
        $Cguid = "";
        for ($i=0; $i < strlen($code); $i++) {
            $needle = substr($code,$i,1);
            $Aguid .= ((strpos($this->_Aset,$needle)===false) ? "N" : "O");
            $Bguid .= ((strpos($this->_Bset,$needle)===false) ? "N" : "O");
            $Cguid .= ((strpos($this->_Cset,$needle)===false) ? "N" : "O");
        }

        $SminiC = "OOOO";
        $IminiC = 4;

        $crypt = "";
        while ($code > "") {
            // BOUCLE PRINCIPALE DE CODAGE
            $i = strpos($Cguid,$SminiC); // forçage du jeu C, si possible
            if ($i!==false) {
                $Aguid [$i] = "N";
                $Bguid [$i] = "N";
            }

            // jeu C
            if (substr($Cguid,0,$IminiC) == $SminiC) {
                // début Cstart, sinon Cswap
                $crypt .= chr(($crypt > "") ? $this->_JSwap["C"] : $this->_JStart["C"]);
                // étendu du set C
                $made = strpos($Cguid,"N");
                if ($made === false) {
                    $made = strlen($Cguid);
                }
                if (fmod($made,2)==1) {
                    // seulement un nombre pair
                    $made--;
                }
                for ($i=0; $i < $made; $i += 2) {
                    // conversion 2 par 2
                    $crypt .= chr(strval(substr($code,$i,2)));
                }
                $jeu = "C";
            } else {
                // étendu du set A
                $madeA = strpos($Aguid,"N");
                if ($madeA === false) {
                    $madeA = strlen($Aguid);
                }
                // étendu du set B
                $madeB = strpos($Bguid,"N");
                if ($madeB === false) {
                    $madeB = strlen($Bguid);
                }
                // étendu traitée
                $made = (($madeA < $madeB) ? $madeB : $madeA );
                // Jeu en cours
                $jeu = (($madeA < $madeB) ? "B" : "A" );

                // début start, sinon swap
                $crypt .= chr(($crypt > "") ? $this->_JSwap[$jeu] : $this->_JStart[$jeu]);

                // conversion selon jeu
                $crypt .= strtr(substr($code, 0,$made), $this->_SetFrom[$jeu], $this->_SetTo[$jeu]);

            }
            // raccourcir légende et guides de la zone traitée
            $code = substr($code,$made);
            $Aguid = substr($Aguid,$made);
            $Bguid = substr($Bguid,$made);
            $Cguid = substr($Cguid,$made);
            // FIN BOUCLE PRINCIPALE
        }

        // calcul de la somme de contrôle
        $check = ord($crypt[0]);
        for ($i=0; $i<strlen($crypt); $i++) {
            $check += (ord($crypt[$i]) * $i);
        }
        $check %= 103;

        // Chaine Cryptée complète
        $crypt .= chr($check) . chr(106) . chr(107);

        // calcul de la largeur du module
        $i = (strlen($crypt) * 11) - 8;
        $modul = $w/$i;

        // BOUCLE D'IMPRESSION
        for ($i=0; $i<strlen($crypt); $i++) {
            $c = $this->_T128[ord($crypt[$i])];
            for ($j=0; $j<count($c); $j++) {
                $this->Rect($x,$y,$c[$j]*$modul,$h,"F");
                $x += ($c[$j++]+$c[$j])*$modul;
            }
        }
    }

    /**
     * Ellipse
     * Siehe http://www.fpdf.org/en/script/script6.php
     * @param Number $x     X-Positon
     * @param Number $y     Y-Position
     * @param Number $rx    Radius X-Achse
     * @param Number $ry    radius Y-Achse
     * @param String [$style='D'] 'D'=nur Rand, 'F'=nur Füllung, 'DF'=Rand und Füllung
     */
    public function kiEllipse($x, $y, $rx, $ry, $style='D') {
        if ($style=='F') {
            $op='f';
        } elseif ($style=='FD' || $style=='DF') {
            $op='B';
        } else {
            $op='S';
        }

        $lx=4/3*(M_SQRT2-1)*$rx;
        $ly=4/3*(M_SQRT2-1)*$ry;

        $k=$this->k;
        $h=$this->h;

        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x+$rx)*$k,($h-$y)*$k,
            ($x+$rx)*$k,($h-($y-$ly))*$k,
            ($x+$lx)*$k,($h-($y-$ry))*$k,
            $x*$k,($h-($y-$ry))*$k));

        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$lx)*$k,($h-($y-$ry))*$k,
            ($x-$rx)*$k,($h-($y-$ly))*$k,
            ($x-$rx)*$k,($h-$y)*$k));

        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$rx)*$k,($h-($y+$ly))*$k,
            ($x-$lx)*$k,($h-($y+$ry))*$k,
            $x*$k,($h-($y+$ry))*$k));

        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x+$lx)*$k,($h-($y+$ry))*$k,
            ($x+$rx)*$k,($h-($y+$ly))*$k,
            ($x+$rx)*$k,($h-$y)*$k,
            $op));
    }
    
    /**
     * Zeichnet einen QR-Code mithilfe der phpqrcode-library
     * @param int $x
     * @param int $y
     * @param string $text
     * @param int $size
     * @param int $level QR-Fehler-Level 1-4
     */
    public function kiQrCode($x, $y, $text, $size, $level=2) {
        require_once(dirname(dirname(__DIR__)) . '/phpqrcode/phpqrcode.php');
	
        switch ($level) {
            case 1: $level = QR_ECLEVEL_L; break;
            case 2: $level = QR_ECLEVEL_M; break;
            case 3: $level = QR_ECLEVEL_Q; break;
            case 4: $level = QR_ECLEVEL_H; break;
        }

        $text = (string) $text;
        if (!$text) return;

        // Matrix laden
        // Gibt ein Array von Strings zurück. String enthält QR code (1=Schwarz, 0=Weiss).
        $textarr = QRcode::text($text, false, $level);
        $points = count($textarr);
        $pointSize = $size / $points;

        for ($yC=0; $yC < count($textarr); $yC++) {
            $row = str_split($textarr[$yC]);
            for ($xC=0; $xC < count($row); $xC++) {
                if ($row[$xC] == '1') {
                    // Schwarzen Punkt machen.
                    $this->Rect($x+($xC*$pointSize), $y+($yC*$pointSize),$pointSize,$pointSize,"F");
                }
            }
        }
    }


    /**
     * Gibt die Höhe und Breite eines Bildes zurück Format: {height:100, width:100}
     * @param string $file
     * @param $width (maximale) Breite
     * @param $height (maximale) Höhe
     * @return stdClass   Objkekt mit width und height in mm
     */
    public function kiGetImageSize($file, $width, $height) {
        if ($file && is_file(utf8_decode($file))) {
            $imagesize = getimagesize(utf8_decode($file));
            $originalPixelWidth = is_array($imagesize) ? $imagesize[0] : 0;
            $originalPixelHeight = is_array($imagesize) ? $imagesize[1] : 0;
            unset ($imagesize);

            // Automatic width and height calculation if needed
            if ($width==0 && $height==0) {
                // Put image at 96 dpi
                $width = -96;
                $height = -96;
            }

            // Wenn eine Breite und Höhe angegeben wurde, wird nur ein Wert genommen,
            // damit das Seitenverhältnis stimmt. Der andere Wert wird weiter unten berechnet.
            if ($width>0 && $height>0 && $originalPixelWidth>0 && $originalPixelHeight>0) {
                if (($width / $originalPixelWidth) > ($height / $originalPixelHeight)) {
                    $width = 0;
                } else {
                    $height = 0;
                }
            }

            if ($width<0) {
                $width = -$originalPixelWidth * 72 / $width / $this->k;
            }
            if ($height<0) {
                $height = -$originalPixelHeight * 72 / $height / $this->k;
            }
            if ($width==0) {
                $width = $height * $originalPixelWidth / $originalPixelHeight;
            }
            if ($height==0) {
                $height = $width * $originalPixelHeight / $originalPixelWidth;
            }

        } else {
            $width = 0;
            $height = 0;
        }
        
        $ret = new stdClass();
        $ret->width = $width;
        $ret->height = $height;
        return $ret;
    }
    
    /**
     * ** ALT ** ALT ** ALT ** ALT ** ALT ** ALT **
     * Berechnet die Höhe eines Multicells
     * @param string $text      Text als utf-8 String
     * @param int $width        Breite des MultiCell
     * @return int              Anzahl Zeilen
     * ** ALT ** ALT ** ALT ** ALT ** ALT ** ALT **
     */
    public function kiGetMultiCellHeight_old($text, $width, $style=null) {
        if (!$this->_kiCalcPdf) {
            $this->_kiCalcPdf = new kireport_pdf_Fpdf();
            
            // Zusätzliche Schriften einbinden
            if ($this->_settings->fonts) {
                foreach ($this->_settings->fonts as $font) {
                    $this->_kiCalcPdf->AddFont($font->family, $font->style, $font->file, $font->unicode);
                }
            }
        }
        
        $this->_kiCalcPdf->SetXY(0,0);
        $this->_kiCalcPdf->kiApplyStyle($style);
        $this->_kiCalcPdf->MultiCell($width, $style->lineHeight ? $style->lineHeight : 4, 
                utf8_decode($text), $style->border ? $style->border : 0,
                $style->align ? $style->align : '', $style->fillColor ? 1 : 0);
        $height = $this->_kiCalcPdf->GetY();
        
        // Speicher wieder freigeben
//        unset($this->_kiCalcPdf);

        return $height;
    }

    /**
     * Berechnet die Höhe einer Multicell
     * @param string $txt
     * @param int $w
     * @param stdClass $style
     * @return int
     */
    public function kiGetMultiCellHeight($txt, $w, $style=null) {        
        if ($style) {
            $this->kiApplyStyle($style);
        }
        $uc = $this->isUnicodeFont();
        if (!$uc) {
            $txt = utf8_decode($txt);
        }
        
        $lineHeight = 4;
        $border = 0;
        $align = 'J';
        $fillColor = 0;
        if (is_object($style)) {
            $lineHeight = $style->lineHeight ? $style->lineHeight : 4;
            $border = $style->border ? $style->border : 0;
            $align = $style->align ? $style->align : 'J';
            $fillColor = $style->fillColor ? 1 : 0;
        }

        $cw = &$this->CurrentFont['cw'];
        $wmax = ($w-2*$this->cMargin);
        $s = str_replace("\r",'',$txt);

        
        // Position des letzten Zeichens, dass kein Zeilenumbruch ist ermitteln
        $nb = 0;
        if ($uc) {
            $nb = mb_strlen($s);
            while ($nb>0 && mb_substr($s,$nb-1,1)=="\n") {
                $nb--;
            }
        } else {
            $nb = strlen($s);
            if ($nb>0 && $s[$nb-1]=="\n") {     // Wieso ist hier ein if statt ein while?
                $nb--;
            }
        }

        $sep = -1; // Position des letzten Leerzeichen
        $i = 0; // Aktueller Pointer
        $j = 0; // Position des letzten Zeilenumbruchs
        $l = 0; // Länge der aktuellen Zeile
        $ns = 0; // Anzahl Leerzeichen in der aktuellen Zeile
        $nl = 1; // Anzahl Zeilen
        $numberOfLines = 1;
        while ($i < $nb) {
            // Get next character
            if ($uc) {
                $c = mb_substr($s, $i, 1);
            } else {
                $c = $s[$i];
            }
            
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                $numberOfLines++;
                continue;
            }
            
            if ($c == ' ') {
                $sep = $i;
                $ns++;
            }

            if ($uc) { 
                $l += $this->GetStringWidth($c); 
            } else {
                $l += $cw[$c] * $this->FontSize / 1000;
            }

            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                    $numberOfLines++;
                } else {
                    $numberOfLines++;
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        
        return $numberOfLines * $lineHeight;
    }

    /**
     * Gibt die Zeilen einer Multicell zurück,
     * aufgeteilt pro Linie;
     * @param String $txt
     * @param int $w
     * @param stdClass $style
     * @return type
     */
    public function kiGetMultiCellRows($txt, $w, $style=null, &$wordspacing=array()) {
        $multiCellRows = array();
        
        if ($style) {
            $this->kiApplyStyle($style);
        }

        $uc = $this->isUnicodeFont();
        $lineHeight = 4;
        $border = 0;
        $align = 'J';
        $fillColor = 0;
        
        if (is_object($style)) {
            $lineHeight = $style->lineHeight ? $style->lineHeight : 4;
            $border = $style->border ? $style->border : 0;
            $align = $style->align ? mb_strtoupper($style->align) : 'L';
            $fillColor = $style->fillColor ? 1 : 0;
        }

        // Output text with automatic or explicit line breaks
        $cw = &$this->CurrentFont['cw'];
        if ($w==0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = $w - (2 * $this->cMargin);
        
        $s = str_replace("\r", '', $txt);
        
        // Position des letzten Zeichens, dass kein Zeilenumbruch ist ermitteln
        $nb = mb_strlen($s);
        while ($nb>0 && mb_substr($s,$nb-1,1) == "\n") {
            $nb--;
        }

        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        while ($i<$nb) {
            // Get next character
            $c = mb_substr($s, $i, 1);
            
            if ($c == "\n") {
                $multiCellRows[] = mb_substr($s, $j, $i-$j);
                $wordspacing[] = 0;
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }

            if ($uc) {
                $l += $this->GetStringWidth($c); 
            } else {
                $l += $cw[utf8_decode($c)] * $this->FontSize / 1000;
            }

            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                    $multiCellRows[] = mb_substr($s, $j, $i-$j);
                    $wordspacing[] = 0;
                } else {
                    if ($align=='J') {
                        $wordspacing[] = ($ns>1) ? ($wmax-$ls)/($ns-1) : 0;
                    } else {
                        $wordspacing[] = 0;
                    }
                    $multiCellRows[] = mb_substr($s, $j, $sep-$j);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
            } else {
                $i++;
            }
        }

        $multiCellRows[] = mb_substr($s, $j, $i-$j);
        $wordspacing[] = 0;
        return $multiCellRows;
    }


    /**
     * gibt die cells eines HTML-Felds zurück
     * @param string $html
     * @param int $w
     * @param bool $heightOnly true, falls nur die Höhe gesucht wird.
     */
    public function kiGetHtmlCells($html, $w, $heightOnly=false) {
        if (!$w) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        // HTML parsen
        if (!class_exists('DOMDocument')) {
            throw new Exception('HTML-Parser benötigt die DOMDocument-Klasse vom PHP');
        }
        
        // Wir müssen dem DOMDocument einen content-type mitgeben, damit utf-8 richtig formatiert wird.
        $dom = new DOMDocument();
        $dom->loadHTML('
            <html>
                <head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
                <body>' . $html . '</body>
            </html>');
        
        $elements = $dom->getElementsByTagName('body');
        $body = null;
        if ($elements->length == 1) {
            $body = $elements->item(0);
        } else {
            throw new Exception('Fehler beim lesen des HTML.');
        }
        unset ($elements);
        
        // wir erstellen ein hirarchisches Array aller HTML-Elemente
        $elements = $this->_DOMNodeListToArray($body->childNodes);
        unset ($dom, $body, $html);
        
        // Report-Styles ergänzen
        $defaultStyle = $this->_joinStyles('default');
        $this->_completeHtmlArrayWithStyle($elements, $defaultStyle);

        // da wir nun die styles ergänzt haben, erstellen wir ein flaches Array
        $elements = $this->_makeFlatArray($elements);
        $cells = array();
        $curX = 0;
        $lineNumber = 1;
        
        // Nun berechnen wir die X-Achse von jedem Element.
        foreach ($elements as &$element) {
            $this->kiApplyStyle($element->style);            
            if ($element->name == 'br' || $element->name == 'tr') {
                $curX = 0;
                $lineNumber++;

            } else if ($element->name == 'img') {
                $imgWidth = 0;
                $imgHeight = 0;
                if ($element->attributes['width']) {
                    $imgWidth = (int) $element->attributes['width'];
                }
                if ($element->attributes['height']) {
                    $imgHeight = (int) $element->attributes['height'];
                }
                $wh = $this->kiGetImageSize($element->attributes['src'], $imgWidth, $imgHeight);

                // Bild auf die nächste zeile, wenn es zu breit ist.
                if ($wh->width < $w && ($curX+$wh->width) > ($w-$curX)) {
                    $lineNumber++;
                    $curX = 0; 
                }

                $cell = new stdClass();
                $cell->type = 'image';
                $cell->x = $curX;
                $cell->lineNumber = $lineNumber;
                $cell->width = $wh->width;
                $cell->height = $wh->height;
                $cell->file = $element->attributes['src'];
                if ($element->hyperlink) {
                    $cell->hyperlink = $element->hyperlink;
                }
                $cells[] = $cell;

                $curX += $wh->width;


            } else if ($element->textContent) {
                // Wörter aufteilen
                $words = explode(' ', $element->textContent);
                while ($words) {
                    $lineTxt = '';
                    $strWidth = 0;
                    $spacer = '';
                    // Wörter hinzufügen solange platz da ist
                    while ($words) {
                        $wordWith = $this->GetStringWidth($spacer.$words[0]);
                        if (($strWidth+$wordWith) <= ($w-$curX)) {
                            $lineTxt .= $spacer . array_shift($words);
                            $strWidth += $wordWith;
                            $spacer = ' ';
                        } else {
                            break;
                        }
                    }

                    // Falls kein Wort Platz hatte, nehmen wir trotzdem eins,
                    // da wir sonst in eine Endlosschlaufe kommen
                    if ($lineTxt === '' && $words) {
                        $lineTxt .= $spacer . array_shift($words);
                        $strWidth += $wordWith;
                    }

                    $cell = new stdClass();
                    $cell->type = 'cell';
                    $cell->x = $curX;
                    $cell->text = $lineTxt;
                    $cell->lineNumber = $lineNumber;
                    $cell->width = $strWidth;
                    $cell->height = $element->style->lineHeight ? $element->style->lineHeight : $this->FontSize;
                    $cell->style = $element->style;
                    $cell->hyperlink = $element->hyperlink ? $element->hyperlink : null;

                    if ($element->tableId && $element->rowNr  && $element->colNr) {
                        $cell->tableId = $element->tableId;
                        $cell->rowNr = $element->rowNr;
                        $cell->colNr = $element->colNr;
                    }
                    
                    $cells[] = $cell;
                    
                    // X hochrechnen
                    $curX += $strWidth;

                    // Falls noch wörter vorhanden sind, machen wir einen Zeilenumbruch
                    if ($words) {
                        $curX = 0;
                        $lineNumber++;
                    }
                }
            }
        }

        // Tabellen: X jeder Spalte aufs Maximum strecken
        $this->_strechTableColumn($cells);

        // Maximale Höhe jeder Zeile berechnen
        $lineHeight = array();
        foreach ($cells as $cell) {
            if (!array_key_exists($cell->lineNumber, $lineHeight)) {
                $lineHeight[$cell->lineNumber] = $cell->height;
            } else {
                if ($cell->height > $lineHeight[$cell->lineNumber]) {
                    $lineHeight[$cell->lineNumber] = $cell->height;
                }
            }
        }
        unset ($cell);

        $maxLineNumber = 0;
        $maxHeight = 0;
        
        // y berechnen
        foreach ($cells as &$cell) {
            $maxLineNumber = max($maxLineNumber, $cell->lineNumber);
            $y = 0;
            for ($line=1; $line<$cell->lineNumber; $line++) {
                $y += array_key_exists($line, $lineHeight) ? $lineHeight[$line] : $cell->style->lineHeight;
            }
            $cell->y = $y;
            $maxHeight = max($maxHeight, ($cell->y + $cell->height));
        }
        unset ($cell);

        // Falls die totale Höhe zurückgegeben werden soll
        if ($heightOnly) {
            return $maxHeight;
        }

        return $cells;
    }


    /**
     * Gibt die Höhe und Breite eines PDF zurück Format: {height:100, width:100}
     * @param string $file
     * @param $width (maximale) Breite
     * @param $height (maximale) Höhe
     * @return stdClass   Objekt mit width und height in mm
     */
    public function kiGetPdfSize($file, $pageNo, $width, $height) {
        if ($file) {
            if (!$this->_kiCalcPdf) {
                $this->_kiCalcPdf = new kireport_pdf_Fpdf();
            }
            $this->_kiCalcPdf->SetXY(0, 0);
            
            $this->_kiCalcPdf->setSourceFile(utf8_decode($file));
            $tplIdx = $this->_kiCalcPdf->importPage($pageNo);
            $tmp = $this->_kiCalcPdf->getTemplateSize($tplIdx, $width, $height);
            $width = $tmp['w'];
            $height = $tmp['h'];

            // Speicher wieder freigeben
//            unset($this->_kiCalcPdf);
        } else {
            $width = 0;
            $height = 0;
        }
        
        $ret = new stdClass();
        $ret->width = $width;
        $ret->height = $height;
        return $ret;
    }
    
    
    public function kiHtml($width, $defaultLineHeight, $html) {
        $this->_styles[] = $this->_joinStyles('default');
        
        $this->_currentLineHeight = $defaultLineHeight ? $defaultLineHeight : 4;
        
        // Temporär die Seitenränder von FPDF einschalten
        $left = $this->GetX();
        $right = $this->w - ($left + $width);
        $this->SetMargins($left, 0, $right);
        // HTML parser
        $html = str_replace("\n", ' ', $html);
        $html = str_replace("&nbsp;", ' ', $html);
        $a = preg_split('/(<[^>]*>)/U', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($a as $e) {
            if (strpos($e, '<')===0) {
                // Tag
                // --------
                // erstes '<' und leztes Zeichen '>' abschneiden
                $e = substr($e, 1, -1);

                // schliessendes Tag
                if ($e{0} == '/') {
                    $this->_closeTag(strtoupper(substr($e, 1)), $defaultLineHeight);
                    
                // öffnendes Tag
                } else {
                    //Extract attributes
                    $a2 = explode(' ', $e);
                    $tag = strtoupper(array_shift($a2));

                    $attr = array();
                    foreach ($a2 as $v) {
                        if (preg_match('/^([^=]*)=["\']?([^"\']*)["\']?$/', $v, $a3)) {
                            $attr[strtoupper($a3[1])] = $a3[2];
                        }
                    }
                    $this->_openTag($tag, $attr, $defaultLineHeight);
                    
                    // Bsp: <img />
                    if (substr($e, -1, 1) == '/') {
                        $this->_closeTag(strtoupper($tag), $defaultLineHeight);
                    }
                }
            } else {
                // Text
                // --------
                $text = $e;
                $text = str_replace('&quot;', '"', $text);
                $text = str_replace('&lt;', '<', $text);
                $text = str_replace('&gt;', '>', $text);
                if ($this->_href) {
                    $this->Write(5, $text, $this->_href);
                } else {
                    $this->Write(5, $text);
                }
            }
            
        }
        
        // Die Seitenränder von FPDF wieder ausschalten
        $this->SetMargins(0, 0, 0);
    }
    
    
    public function kiGetHtmlHeight($width, $defaultLineHeight, $html) {
        $height = 0;

        if ($html) {
            if (!$this->_kiCalcPdf) {
                $this->_kiCalcPdf = new kireport_pdf_Fpdf($this->_settings);
            }
            $this->_kiCalcPdf->SetXY(0, 0);
            $this->_kiCalcPdf->kiHtml($width, $defaultLineHeight, $html);
            $height = $this->_kiCalcPdf->GetY();
        }
        
        return $height;
    }
    
    
    
    /**
     * Fügt anhand eines Json-Objekts Items ein
     * @param array $items 
     */
    public function kiInsertItemsFromArray($items) {
        if ($items) {

            $pageNoOffset = isset($this->_settings) && isset($this->_settings->pageNoOffset) && is_int($this->_settings->pageNoOffset)
                    ? (int) $this->_settings->pageNoOffset : 0;

            foreach ($items as $item) {
                switch ($item->fn) {
                    // Einzeiliger Text.
                    case 'cell':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }

                        $this->SetXY($item->absX, $item->absY);

                        $this->kiApplyStyle($item->style);
                        
                        $text = str_replace('#pageNo#', $this->PageNo() + $pageNoOffset, $item->text);

                        // Für Blocksatz wird der Wortabsatz geändert
                        if (is_numeric($item->style->wordspacing)) {
                            $this->ws = $item->style->wordspacing;
                        }

                        $this->Cell(
                                $item->calcWidth,
                                $item->style->lineHeight ? $item->style->lineHeight : 4,
                                $this->isUnicodeFont() ? $text : utf8_decode($text),
                                $item->style->border ? $item->style->border : 0,
                                0,
                                $item->style->align ? mb_strtoupper($item->style->align) : '',
                                $item->style->fillColor ? 1 : 0,
                                isset($item->hyperlink) ? $item->hyperlink : '');

                        $this->ws = 0;
                        
                        break;

                    case 'chart':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }

                        $this->kiApplyStyle($item->style);
                        $this->kiChart(
                                $item->absX, 
                                $item->absY,
                                $item->calcWidth,
                                $item->calcHeight,
                                $item->chartType,
                                $item->chartLabels, 
                                $item->chartValues, 
                                $item->chartProperties);
                        break;
                        
                    case 'code128':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }

                        // Parameter umwandeln
                        $text = $this->_generateCode128Text($item->componentId, $item->xId, $item->arguments);

                        $this->kiApplyStyle($item->style);
                        $this->kiCode128(
                                $item->absX,
                                $item->absY,
                                utf8_decode($text), // code 128 ist immer ohne UTF8-Zeichen
                                $item->calcWidth,
                                $item->calcHeight);
                        
                        break;

                    case 'qrcode':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }

                        $this->kiApplyStyle($item->style);
                        $this->kiQrCode($item->absX, $item->absY, $item->text, min($item->calcWidth, $item->calcHeight));
                        break;
                    
                    // Container einfügen
                    case 'container':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();

                        }

                        $pn = $this->PageNo();

                        $this->kiApplyStyle($item->style);
                        
                        // Die Hintergrundfarbe eines Containers wird mithilfe eines Rechtecks gezeichnet.
                        // Sollte der Container über mehrere Seiten gehen, wird das Rechteck nicht gezeichnet.
                        if ($item->style && $item->style->fillColor && $pn == $this->PageNo()) {
                            $this->Rect($item->absX, $item->absY, $item->calcWidth, $item->calcHeight, "F");
                        }
                        
                        // untergeordnete Elemente einfügen
                        if ($item->items) {
                            $this->kiInsertItemsFromArray($item->items);
                        }

                        $this->kiApplyStyle($item->style);
                        
                        // Der Border eines Containers wird mithilfe von Linien gezeichnet.
                        // Sollte der Container über mehrere Seiten gehen, werden die Linien nicht gezeichnet.
                        // TODO: Linien bei Container über mehrere Seiten zeichnen.
                        if ($item->style && $item->style->border && $pn == $this->PageNo()) {
                            $border = $item->style->border;
                            if ($border === 1) {
                                $border = 'TBLR';
                            }
                            $border = strtoupper($border);
                            if (strstr($border, 'T')) { // Top Line
                                $this->Line($item->absX, $item->absY, $item->absX + $item->calcWidth, $item->absY);
                            }
                            if (strstr($border, 'B')) { // Bottom Line
                                $this->Line($item->absX, $item->absY + $item->calcHeight, $item->absX + $item->calcWidth, $item->absY + $item->calcHeight);
                            }
                            if (strstr($border, 'L')) { // Left Line
                                $this->Line($item->absX, $item->absY, $item->absX, $item->absY + $item->calcHeight);
                            }
                            if (strstr($border, 'R')) { // Right Line
                                $this->Line($item->absX + $item->calcWidth, $item->absY, $item->absX + $item->calcWidth, $item->absY + $item->calcHeight);
                            }
                        }
                        
                        break;
                    
                    // HTML
                    case 'html': die ('INSERT HTML: '. htmlspecialchars($item->html));
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }

                        $this->SetXY($item->absX, $item->absY);
                        
                        $this->kiApplyStyle($item->style);
                        
                        $this->kiHtml(
                                    $item->calcWidth,
                                    $item->style->lineHeight ? $item->style->lineHeight : 4,
                                    $this->isUnicodeFont() ? $item->html : utf8_decode($item->html));
                        
                        break;
                    
                    // Bild
                    case 'image':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }
                        if ($item->file && is_file(utf8_decode($item->file))) {
                            $this->kiApplyStyle($item->style);
                            
                            $this->Image(
                                    utf8_decode($item->file),
                                    $item->absX,
                                    $item->absY,
                                    $item->calcWidth,
                                    $item->calcHeight,
                                    '', 
                                    isset($item->hyperlink) ? $item->hyperlink : '');
                        }
                        break;
                        

                    
                    // Linie
                    case 'line':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }

                        $this->kiApplyStyle($item->style);

                        $this->Line($item->absX1, $item->absY1, $item->absX2, $item->absY2);
                        
                        break;
                        
                    // Mehrzeiliger Text. Cursor steht anschliessend am Anfang einer neuen Zeile.
                    case 'multiCell':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }
                        $this->SetXY($item->absX, $item->absY);
                        
                        $this->kiApplyStyle($item->style);
                        
                        $text = str_replace('#pageNo#', $this->PageNo() + $pageNoOffset, $item->text);
                        
                        if (isset($item->height)) {
                            $this->kiMultiCellWithFixedHeight(
                                    $item->height, 
                                    null, // style muss nicht übergeben und neu gesetzt werden, da oben gesetzt
                                    $item->calcWidth, 
                                    $item->style->lineHeight ? $item->style->lineHeight : 4,
                                    $this->isUnicodeFont() ? $text : utf8_decode($text),
                                    $item->style->border ? $item->style->border : 0,
                                    $item->style->align ? mb_strtoupper($item->style->align) : '',
                                    $item->style->fillColor ? 1 : 0);
                        } else {
                            $this->MultiCell(
                                    $item->calcWidth, 
                                    $item->style->lineHeight ? $item->style->lineHeight : 4,
                                    $this->isUnicodeFont() ? $text : utf8_decode($text),
                                    $item->style->border ? $item->style->border : 0,
                                    $item->style->align ? mb_strtoupper($item->style->align) : '',
                                    $item->style->fillColor ? 1 : 0);
                        }
                        break;
                        
                    // PDF
                    case 'pdf':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }
                        
                        $this->setSourceFile(utf8_decode($item->file));
                        $tplIdx = $this->importPage($item->pageNo);
                        $this->useTemplate($tplIdx, $item->absX, $item->absY, $item->calcWidth, $item->calcHeight);

                        break;

                    // Formularfelder
                    case 'form_textfield':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }

                        $this->kiApplyStyle($item->style);
                        $this->kiFormField(
                                    'textfield',
                                    $item->absX+0.2,
                                    $item->absY+0.2,
                                    $item->calcWidth-0.4,
                                    $item->calcHeight-0.4,
                                    $item->form,
                                    $item->style,
                                    $item->name
                            );
                        break;

                    case 'form_combobox':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }

                        $this->kiApplyStyle($item->style);
                        $this->kiFormField(
                                    'combobox',
                                    $item->absX+0.2,
                                    $item->absY+0.2,
                                    $item->calcWidth-0.4,
                                    $item->calcHeight-0.4,
                                    $item->form,
                                    $item->style,
                                    $item->name
                            );
                        break;
                    case 'form_checkbox':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }

                        $this->kiApplyStyle($item->style);
                        $this->kiFormField(
                                    'checkbox',
                                    $item->absX+0.2,
                                    $item->absY+0.2,
                                    $item->calcWidth-0.4,
                                    $item->calcHeight-0.4,
                                    $item->form,
                                    $item->style,
                                    $item->name
                            );
                        break;

                    case 'form_radiobox':
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }

                        $this->kiApplyStyle($item->style);
                        $this->kiFormField(
                                    'radiobox',
                                    $item->absX+0.2,
                                    $item->absY+0.2,
                                    $item->calcWidth-0.4,
                                    $item->calcHeight-0.4,
                                    $item->form,
                                    $item->style,
                                    $item->name
                            );
                        break;
                        
                    // andere
                    default:
                        // Evtl. Seitenumbruch einfügen
                        if ($item->pageBreakBefore) {
                            $this->kiAddPage();
                        }
                        
                        break;
                }
            }
            
        }
    }

    public function kiMultiCellWithFixedHeight($height, $style, $w, $h, $txt, $border=0, $align='J', $fill=false) {
        if ($height > $h) {
            $tmp = $this->kiGetMultiCellHeight($txt, $w, $style);
            if ($height-$tmp > 0) { 
                $lines = ($height-$tmp) / $h;

                $txt .= str_repeat("\n ", $lines);
            }
        }
        $this->MultiCell($w, $h, $txt, $border, $align, $fill);
    }
    
    /**
     * Polygon
     * Siehe http://fpdf.de/downloads/add-ons/polygons.html
     * @param Array $points Array mit Punkten im Format [x1, y1, x2, y2, x3, y3, ...]
     * @param String [$style='D'] 'D'=nur Rand, 'F'=nur Füllung, 'DF'=Rand und Füllung
     */
    public function kiPolygon($points, $style='D') {
        if ($style=='F') {
            $op = 'f';
        } elseif ($style=='FD' || $style=='DF') {
            $op = 'b';
        } else {
            $op = 's';
        }

        $h = $this->h;
        $k = $this->k;

        $points_string = '';
        for ($i=0; $i<count($points); $i+=2) {
            $points_string .= sprintf('%.2F %.2F', $points[$i]*$k, ($h-$points[$i+1])*$k);
            if ($i==0) {
                $points_string .= ' m ';
            } else {
                $points_string .= ' l ';
            }
        }
        $this->_out($points_string . $op);
    }

    /**
     * Rotiert nachfolgende Elemente um die angegebenen Grad
     * @param int $angle
     * @param float $x
     * @param float $y
     */
    public function kiRotate($angle, $x=-1, $y=-1) {
        if ($x === -1) {
            $x = $this->x;
        }

        if ($y === -1) {
            $y = $this->y;
        }

        if ($this->_ki_angle !== 0) {
            $this->_out('Q');
        }

        $this->_ki_angle = $angle;
        
        if ($angle !== 0) {
            $angle *= M_PI / 180;
            $c  = cos($angle);
            $s  = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }
    
    /**
     * Setzt die Seitenränder Beispiel "20 20 20 25 50" (top right bottom left [topFirstPage])
     * @param string $margins 
     */
    public function kiSetMarginsFromString($margins) {
        if (!$margins) {
            $margins = $this->_settings->margins;
        }
        
        $top = 20;
        $right = 20;
        $bottom = 20;
        $left = 25;
        if (strlen($margins)) {
            $tmp = explode(' ', $margins);
            if (count($tmp)>0) $top = $tmp[0];
            if (count($tmp)>1) $right = $tmp[1];
            if (count($tmp)>2) $bottom = $tmp[2];
            if (count($tmp)>3) $left = $tmp[3];
            
            // Für die erste Seite kann der obere Rand als 5. Argument angegeben werden
            if ($this->PageNo() == 1) {
                if (count($tmp)>4) $top = $tmp[4];
            }
        }
        
        $this->SetMargins($left, $top, $right);
        $this->SetAutoPageBreak(false, $bottom);
    }


    /**
     *
     * @param string $type textfield|combobox|checkbox|radiobox
     * @param int $x
     * @param int $y
     * @param int $w
     * @param int $h
     * @param stdClass $cnf
     * @param stdClass $style
     */
    public function kiFormField($type, $x, $y, $w, $h, $cnf=null, $style=null) {
        if (!is_object($cnf)) {
            $cnf = new stdClass();
        }

        // Feldtyp
        $cnf->type = $type;

        // border mit rechteck zeichnen
        if ($style->border) {
            $this->Rect($x, $y, $w, $h);
        }

        // weitere infos speichern
        $cnf->pageNo = $this->pageNo();
        $cnf->currentFont_i = $this->CurrentFont['i'];
        $cnf->FontSizePt = $this->FontSizePt;
        
        $k = $this->k;
        $cnf->x1 = $x*$k;
        $cnf->y1 = ($this->h-$y-$h)*$k;
        $cnf->x2 = ($x+$w)*$k;
        $cnf->y2 = ($this->h-$y)*$k;

        $this->_ki_FormFields[] = $cnf;
        
    }

    /**
     * Fügt dem PDF JavaScript-Code hinzu.
     * @param string $javascript
     */
    public function includeJavaScript($javascript) {
        if (!is_string($this->_kiJavaScript)) {
            $this->_kiJavaScript = '';
        } else {
            $this->_kiJavaScript .= ' ';
        }
        $this->_kiJavaScript .= $javascript;
    }


    // -------------------------------------------------------
    // Protected Methods
    // -------------------------------------------------------
    
    protected function _generateCode128Text($componentId, $xId=0, $arguments='') {
        $string = '%';
        $componentId = (int)$componentId;

        $string .= base_convert($componentId, 10, 33);
        $string .= '%';

        if (intval($xId)) {
            $string .= base_convert(intval($xId), 10, 33);
            $string .= '%';
        }
        if ($arguments) {
            if (is_array($arguments)) {
                $arguments = implode('%', $arguments);
            }
            $string .= preg_replace('/[^a-zA-Z0-9\%]/', '', $arguments);
            $string .= '%';
        }
        return $string;
    }

    
    
    // -------------------------------------------------------
    // Private Methods
    // -------------------------------------------------------
    private function _code128Init() {
        // composition des caractères
        $this->_T128[] = array(2, 1, 2, 2, 2, 2);           //0 : [ ]
        $this->_T128[] = array(2, 2, 2, 1, 2, 2);           //1 : [!]
        $this->_T128[] = array(2, 2, 2, 2, 2, 1);           //2 : ["]
        $this->_T128[] = array(1, 2, 1, 2, 2, 3);           //3 : [#]
        $this->_T128[] = array(1, 2, 1, 3, 2, 2);           //4 : [$]
        $this->_T128[] = array(1, 3, 1, 2, 2, 2);           //5 : [%]
        $this->_T128[] = array(1, 2, 2, 2, 1, 3);           //6 : [&]
        $this->_T128[] = array(1, 2, 2, 3, 1, 2);           //7 : [']
        $this->_T128[] = array(1, 3, 2, 2, 1, 2);           //8 : [(]
        $this->_T128[] = array(2, 2, 1, 2, 1, 3);           //9 : [)]
        $this->_T128[] = array(2, 2, 1, 3, 1, 2);           //10 : [*]
        $this->_T128[] = array(2, 3, 1, 2, 1, 2);           //11 : [+]
        $this->_T128[] = array(1, 1, 2, 2, 3, 2);           //12 : [,]
        $this->_T128[] = array(1, 2, 2, 1, 3, 2);           //13 : [-]
        $this->_T128[] = array(1, 2, 2, 2, 3, 1);           //14 : [.]
        $this->_T128[] = array(1, 1, 3, 2, 2, 2);           //15 : [/]
        $this->_T128[] = array(1, 2, 3, 1, 2, 2);           //16 : [0]
        $this->_T128[] = array(1, 2, 3, 2, 2, 1);           //17 : [1]
        $this->_T128[] = array(2, 2, 3, 2, 1, 1);           //18 : [2]
        $this->_T128[] = array(2, 2, 1, 1, 3, 2);           //19 : [3]
        $this->_T128[] = array(2, 2, 1, 2, 3, 1);           //20 : [4]
        $this->_T128[] = array(2, 1, 3, 2, 1, 2);           //21 : [5]
        $this->_T128[] = array(2, 2, 3, 1, 1, 2);           //22 : [6]
        $this->_T128[] = array(3, 1, 2, 1, 3, 1);           //23 : [7]
        $this->_T128[] = array(3, 1, 1, 2, 2, 2);           //24 : [8]
        $this->_T128[] = array(3, 2, 1, 1, 2, 2);           //25 : [9]
        $this->_T128[] = array(3, 2, 1, 2, 2, 1);           //26 : [:]
        $this->_T128[] = array(3, 1, 2, 2, 1, 2);           //27 : [;]
        $this->_T128[] = array(3, 2, 2, 1, 1, 2);           //28 : [<]
        $this->_T128[] = array(3, 2, 2, 2, 1, 1);           //29 : [=]
        $this->_T128[] = array(2, 1, 2, 1, 2, 3);           //30 : [>]
        $this->_T128[] = array(2, 1, 2, 3, 2, 1);           //31 : [?]
        $this->_T128[] = array(2, 3, 2, 1, 2, 1);           //32 : [@]
        $this->_T128[] = array(1, 1, 1, 3, 2, 3);           //33 : [A]
        $this->_T128[] = array(1, 3, 1, 1, 2, 3);           //34 : [B]
        $this->_T128[] = array(1, 3, 1, 3, 2, 1);           //35 : [C]
        $this->_T128[] = array(1, 1, 2, 3, 1, 3);           //36 : [D]
        $this->_T128[] = array(1, 3, 2, 1, 1, 3);           //37 : [E]
        $this->_T128[] = array(1, 3, 2, 3, 1, 1);           //38 : [F]
        $this->_T128[] = array(2, 1, 1, 3, 1, 3);           //39 : [G]
        $this->_T128[] = array(2, 3, 1, 1, 1, 3);           //40 : [H]
        $this->_T128[] = array(2, 3, 1, 3, 1, 1);           //41 : [I]
        $this->_T128[] = array(1, 1, 2, 1, 3, 3);           //42 : [J]
        $this->_T128[] = array(1, 1, 2, 3, 3, 1);           //43 : [K]
        $this->_T128[] = array(1, 3, 2, 1, 3, 1);           //44 : [L]
        $this->_T128[] = array(1, 1, 3, 1, 2, 3);           //45 : [M]
        $this->_T128[] = array(1, 1, 3, 3, 2, 1);           //46 : [N]
        $this->_T128[] = array(1, 3, 3, 1, 2, 1);           //47 : [O]
        $this->_T128[] = array(3, 1, 3, 1, 2, 1);           //48 : [P]
        $this->_T128[] = array(2, 1, 1, 3, 3, 1);           //49 : [Q]
        $this->_T128[] = array(2, 3, 1, 1, 3, 1);           //50 : [R]
        $this->_T128[] = array(2, 1, 3, 1, 1, 3);           //51 : [S]
        $this->_T128[] = array(2, 1, 3, 3, 1, 1);           //52 : [T]
        $this->_T128[] = array(2, 1, 3, 1, 3, 1);           //53 : [U]
        $this->_T128[] = array(3, 1, 1, 1, 2, 3);           //54 : [V]
        $this->_T128[] = array(3, 1, 1, 3, 2, 1);           //55 : [W]
        $this->_T128[] = array(3, 3, 1, 1, 2, 1);           //56 : [X]
        $this->_T128[] = array(3, 1, 2, 1, 1, 3);           //57 : [Y]
        $this->_T128[] = array(3, 1, 2, 3, 1, 1);           //58 : [Z]
        $this->_T128[] = array(3, 3, 2, 1, 1, 1);           //59 : [[]
        $this->_T128[] = array(3, 1, 4, 1, 1, 1);           //60 : [\]
        $this->_T128[] = array(2, 2, 1, 4, 1, 1);           //61 : []]
        $this->_T128[] = array(4, 3, 1, 1, 1, 1);           //62 : [^]
        $this->_T128[] = array(1, 1, 1, 2, 2, 4);           //63 : [_]
        $this->_T128[] = array(1, 1, 1, 4, 2, 2);           //64 : [`]
        $this->_T128[] = array(1, 2, 1, 1, 2, 4);           //65 : [a]
        $this->_T128[] = array(1, 2, 1, 4, 2, 1);           //66 : [b]
        $this->_T128[] = array(1, 4, 1, 1, 2, 2);           //67 : [c]
        $this->_T128[] = array(1, 4, 1, 2, 2, 1);           //68 : [d]
        $this->_T128[] = array(1, 1, 2, 2, 1, 4);           //69 : [e]
        $this->_T128[] = array(1, 1, 2, 4, 1, 2);           //70 : [f]
        $this->_T128[] = array(1, 2, 2, 1, 1, 4);           //71 : [g]
        $this->_T128[] = array(1, 2, 2, 4, 1, 1);           //72 : [h]
        $this->_T128[] = array(1, 4, 2, 1, 1, 2);           //73 : [i]
        $this->_T128[] = array(1, 4, 2, 2, 1, 1);           //74 : [j]
        $this->_T128[] = array(2, 4, 1, 2, 1, 1);           //75 : [k]
        $this->_T128[] = array(2, 2, 1, 1, 1, 4);           //76 : [l]
        $this->_T128[] = array(4, 1, 3, 1, 1, 1);           //77 : [m]
        $this->_T128[] = array(2, 4, 1, 1, 1, 2);           //78 : [n]
        $this->_T128[] = array(1, 3, 4, 1, 1, 1);           //79 : [o]
        $this->_T128[] = array(1, 1, 1, 2, 4, 2);           //80 : [p]
        $this->_T128[] = array(1, 2, 1, 1, 4, 2);           //81 : [q]
        $this->_T128[] = array(1, 2, 1, 2, 4, 1);           //82 : [r]
        $this->_T128[] = array(1, 1, 4, 2, 1, 2);           //83 : [s]
        $this->_T128[] = array(1, 2, 4, 1, 1, 2);           //84 : [t]
        $this->_T128[] = array(1, 2, 4, 2, 1, 1);           //85 : [u]
        $this->_T128[] = array(4, 1, 1, 2, 1, 2);           //86 : [v]
        $this->_T128[] = array(4, 2, 1, 1, 1, 2);           //87 : [w]
        $this->_T128[] = array(4, 2, 1, 2, 1, 1);           //88 : [x]
        $this->_T128[] = array(2, 1, 2, 1, 4, 1);           //89 : [y]
        $this->_T128[] = array(2, 1, 4, 1, 2, 1);           //90 : [z]
        $this->_T128[] = array(4, 1, 2, 1, 2, 1);           //91 : [{]
        $this->_T128[] = array(1, 1, 1, 1, 4, 3);           //92 : [|]
        $this->_T128[] = array(1, 1, 1, 3, 4, 1);           //93 : [}]
        $this->_T128[] = array(1, 3, 1, 1, 4, 1);           //94 : [~]
        $this->_T128[] = array(1, 1, 4, 1, 1, 3);           //95 : [DEL]
        $this->_T128[] = array(1, 1, 4, 3, 1, 1);           //96 : [FNC3]
        $this->_T128[] = array(4, 1, 1, 1, 1, 3);           //97 : [FNC2]
        $this->_T128[] = array(4, 1, 1, 3, 1, 1);           //98 : [SHIFT]
        $this->_T128[] = array(1, 1, 3, 1, 4, 1);           //99 : [Cswap]
        $this->_T128[] = array(1, 1, 4, 1, 3, 1);           //100 : [Bswap]
        $this->_T128[] = array(3, 1, 1, 1, 4, 1);           //101 : [Aswap]
        $this->_T128[] = array(4, 1, 1, 1, 3, 1);           //102 : [FNC1]
        $this->_T128[] = array(2, 1, 1, 4, 1, 2);           //103 : [Astart]
        $this->_T128[] = array(2, 1, 1, 2, 1, 4);           //104 : [Bstart]
        $this->_T128[] = array(2, 1, 1, 2, 3, 2);           //105 : [Cstart]
        $this->_T128[] = array(2, 3, 3, 1, 1, 1);           //106 : [STOP]
        $this->_T128[] = array(2, 1);                       //107 : [END BAR]

        $ABCset = '';
        for ($i = 32; $i <= 95; $i++) {
            $ABCset .= chr($i);
        }
        $this->_Aset = $ABCset;
        $this->_Bset = $ABCset;
        for ($i = 0; $i <= 31; $i++) {
            $ABCset .= chr($i);
            $this->_Aset .= chr($i);
        }
        for ($i = 96; $i <= 126; $i++) {
            $ABCset .= chr($i);
            $this->_Bset .= chr($i);
        }
        $this->_Cset="0123456789";

        for ($i=0; $i<96; $i++) {
            $this->_SetFrom["A"] .= chr($i);
            $this->_SetFrom["B"] .= chr($i + 32);
            $this->_SetTo["A"] .= chr(($i < 32) ? $i+64 : $i-32);
            $this->_SetTo["B"] .= chr($i);
        }

        $this->_code128_isInitialized = true;
    }

    /**
     * Erstellt aus einer DOMNodeList (http://de2.php.net/manual/de/book.dom.php) ein hirarchisches Array mit StdClass
     * @param DOMNodeList $nodeList
     * @return array
     */
    private function _DOMNodeListToArray($nodeList) {
        $elements = array();
        foreach ($nodeList as $node) {
            $element = new stdClass();
            $element->name = $node->nodeType === XML_TEXT_NODE ? '#TEXT#' : mb_strtolower(strval($node->nodeName));
            $element->attributes = array();
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attribute) {
                    $element->attributes[mb_strtolower(strval($attribute->name))] = (string) $attribute->value;
                }
            }
            if (!$node->hasChildNodes()) {
                $element->textContent = (string) $node->textContent;
            } else {
                // rekursion
                $element->children = $this->_DOMNodeListToArray($node->childNodes);
            }
            $elements[] = $element;
        }
        return $elements;
    }


    /**
     * ergänzt das Array mit Styles
     * @param \stdClass $style
     * @param string $tag
     * @param array $attr
     */
    private function _completeHtmlArrayWithStyle(&$nodeArray, $parentStyle) {
        foreach ($nodeArray as $node) {
            if ($parentStyle instanceof stdClass) {
                $node->style = kireport_general_Functions::recursiveCloneStdClass($parentStyle);
            } else {
                $node->style = new stdClass();
            }
            
            if (!$node->style->font) {
                $node->style->font = new stdClass();
            }

            // CSS-Style-Namen als Style-Namen des Report-Generators benutzen
            if (isset($node->attributes['class'])) {
                $node->style = $this->_joinStyles(array($node->style, $node->attributes['class']));
            }


            if (!$node->style->font->style) {
                $node->style->font->style = '';
            }

            // Fett
            if ($node->name=='b') {
                if (strrpos($node->style->font->style, 'B') === false) {
                    $node->style->font->style .= 'B';
                }
            }

            // Kursiv
            if ($node->name=='i') {
                if (strrpos($node->style->font->style, 'I') === false) {
                    $node->style->font->style .= 'I';
                }
            }

            // Unterstrichen
            if ($node->name=='u') {
                if (strrpos($node->style->font->style, 'U') === false) {
                    $node->style->font->style .= 'U';
                }
            }

            // Link (=Unterstrichen)
            if ($node->name=='a') {
                if (strrpos($node->style->font->style, 'U') === false) {
                    $node->style->font->style .= 'U';
                }            
            }

            // rekursion
            if (is_array($node->children)) {
                $this->_completeHtmlArrayWithStyle($node->children, $node->style);
            }
        }
    }

    /**
     * Erstellt aus einem hirarchischen Array ein Flaches.
     * @param array $nodes
     * @param string $href (nur für rekursion)
     * @param string $tableId (nur für rekursion)
     * @param int $rowNr (nur für rekursion)
     * @param int $colNr (nur für rekursion)
     * @return array
     */
    private function _makeFlatArray($nodes, $href='', $tableId=null, $rowNr=null, $colNr=null) {
        $return = array();
        // wir brauchen nur nodes vom typ img, br oder solche mit Text.
        $keepNodes = array('img', 'br');
        // die folgende Node fügen wir nach den childnodes ein
        $keepNodesAfter = array('tr');
        $pos = 0;
        foreach ($nodes as $node) {
            $pos++;
            // Daten vom übergeordneten Element zum Anhängen?
            if ($href) {
                $node->hyperlink = $href;
            }
            if ($tableId) {
                $node->tableId = $tableId;
            }
            if ($rowNr) {
                $node->rowNr = $rowNr;
            }
            if ($colNr) {
                $node->colNr = $colNr;
            }

            // Tabellen-Werten hängen wir als padding noch Leerzeichen an.
            if ($colNr && $node->textContent) {
                $node->textContent .= '   ';
            }

            if (in_array($node->name, $keepNodes) || $node->textContent) {
                $return[] = $node;
            }

            // Link den nachfolgenden Elementen mitgeben
            $c_href = $href;
            if ($node->name == 'a') {
                $c_href = (string) $node->attributes['href'];
            }

            // Tabellen-ID erstellen und den nachfolgenden Elementen mitgeben
            $c_tableId = $tableId;
            if ($node->name == 'table') {
                $c_tableId = uniqid('table', true);
            }
            // Tabellen-Zeile
            $c_rowNr = $rowNr;
            if ($tableId && $node->name == 'tr') {
                $c_rowNr = $pos;
            }
            // Tabellen-Spalte
            $c_colNr = $colNr;
            if ($tableId && ($node->name == 'th' || $node->name == 'td')) {
                $c_colNr = $pos;
            }

            // Rekursion
            if (is_array($node->children)) {
                $return = array_merge($return, $this->_makeFlatArray($node->children, $c_href, $c_tableId, $c_rowNr, $c_colNr));
            }

            // Nach den ChildNodes?
            if (in_array($node->name, $keepNodesAfter)) {
                $return[] = $node;
            }
        }
        return $return;
    }

    /**
     * Stretcht HTML-Tabellen, dass alle columns auf der selben Höhe sind.
     * @param array $cells Die generierten HTML-Cells.
     */
    private function _strechTableColumn(&$cells) {
        $tables = array();
        foreach ($cells as &$cell) {
            if ($cell->tableId) {
                if (!array_key_exists($cell->tableId, $tables)) {
                    $tables[$cell->tableId] = array();
                }
                // Maximale Breite jeder Spalte eintragen
                if (!array_key_exists($cell->colNr, $tables[$cell->tableId])) {
                    $tables[$cell->tableId][$cell->colNr] = $cell->width;
                } else if ($cell->width > $tables[$cell->tableId][$cell->colNr]) {
                    $tables[$cell->tableId][$cell->colNr] = $cell->width;
                }
            }
        }
        unset ($cell);

        // Breite jeder Spalte ans max. anpassen und X verschieben
        foreach ($cells as &$cell) {
            if ($cell->tableId && $cell->colNr) {
                $x = 0;
                for ($i=0; $i<$cell->colNr; $i++) {
                    $x += intval($tables[$cell->tableId][$i]);
                }
                $cell->x = $x;
                $cell->width = $tables[$cell->tableId][$cell->colNr];
            }
        }
    }
    
    
    private function _openTag($tag, $attr, $defaultLineHeight) {
        // aktueller Style ermitteln
        $style = clone end($this->_styles);
        if ($style->font) {
            $style->font = clone $style->font;
        }
        
        if (!$style->font) {
            $style->font = new stdClass();
        }
        
        // CSS-Style-Namen als Style-Namen des Report-Generators benutzen
        if (isset($attr['CLASS'])) {
            $style = $this->_joinStyles(array($style, $attr['CLASS']));
        }
        
        
        if (!$style->font->style) {
            $style->font->style = '';
        }
        
        
        if ($tag=='B') {
            if (strrpos($style->font->style, 'B') === false) {
                $style->font->style .= 'B';
            }
        }
        if ($tag=='I') {
            if (strrpos($style->font->style, 'I') === false) {
                $style->font->style .= 'I';
            }
        }
        if ($tag=='U') {
            if (strrpos($style->font->style, 'U') === false) {
                $style->font->style .= 'U';
            }
        }
        if ($tag=='A') {
            $this->_href = $attr['HREF'];
            if (strrpos($style->font->style, 'U') === false) {
                $style->font->style .= 'U';
            }
        }
        if ($tag=='BR') {
            $this->Ln($this->_currentLineHeight);
            $this->_currentLineHeight = $defaultLineHeight;
        }
        if ($tag=='P') {
            $this->_currentLineHeight = $defaultLineHeight;
        }
        
        if ($tag=='TH') {
            
        }
        if ($tag=='TD') {
            
        }
        
        if ($tag=='IMG') {
            if ($attr['SRC']) {
                $src = $attr['SRC'];
                $width = array_key_exists('WIDTH', $attr) ? $attr['WIDTH'] : 0;
                $height = array_key_exists('HEIGHT', $attr) ? $attr['HEIGHT'] : 0;
                
                $width = (double) str_replace('"', '', $width);
                $height = (double) str_replace('"', '', $height);
                
                $size = $this->kiGetImageSize($src, $width, $height);
                
                if ($size->height > $this->_currentLineHeight) {
                    $this->_currentLineHeight = $size->height;
                }

                $this->Image($src, $this->GetX()+1, $this->GetY(), $width, $height);
                $this->SetX($this->GetX()+1 + $size->width);
            }
        }
        
        $this->kiApplyStyle($style);
        $this->_styles[] = $style;
    }

    private function _closeTag($tag, $defaultLineHeight) {
        if ($tag=='A') {
            $this->_href = '';
        }
        if ($tag=='P') {
            $this->Ln($this->_currentLineHeight);
            $this->_currentLineHeight = $defaultLineHeight;
        }
        if ($tag=='TH') {
            $this->Write(5, '    ');
        }
        if ($tag=='TD') {
            $this->Write(5, '    ');
        }
        if ($tag=='TR') {
            $this->Ln($this->_currentLineHeight);
            $this->_currentLineHeight = $defaultLineHeight;
        }
        
        array_pop($this->_styles);
        $this->kiApplyStyle(end($this->_styles));
    }

    // Gibt zurück, ob es sich um ein assoziatives Array handelt
    private function _is_assoc($arr) {
        return (is_array($arr) && count(array_filter(array_keys($arr),'is_string')) == count($arr));
    }
    
    /**
     * Kombiniert mehrere Style-Objekte
     * Eigenschaften von hinteren Objekten im Array überschreiben die Eigenschaften von vorherigen Objekten
     * @param object|array|string $styles Style Objekt oder Array mit mehreren Style-Objekten
     */
    private function _joinStyles($styles) {
        if (!is_array($styles)) {
            $styles = array($styles);
        }
        
        // Stylenamen durch die echten Styles ersetzen
        if ($this->_settings && isset($this->_settings->styles)) {
            foreach ($styles as &$style) {
                if (is_string($style)) {
                    $style = isset($this->_settings->styles->$style) ? $this->_settings->styles->$style : new stdClass();
                }
            }
        }
        // Styles verschmelzen
        return kireport_general_Functions::joinSettings($styles);
    }

    /**
     * Fügt dem PDF das JS hinzu.
     * @param string $js
     * @return n Nummer von obj
     */
    private function _kiPutJavaScript($js) {
        $this->_newobj();
        $n = $this->n;
        $this->_out('<<');
        $this->_out('/Names [(EmbeddedJS) ' . ($this->n + 1) . ' 0 R]');
        $this->_out('>>');
        $this->_out('endobj');
        $this->_newobj();
        $this->_out('<<');
        $this->_out('/S /JavaScript');
        $this->_out('/JS ' . $this->_textstring($js));
        $this->_out('>>');
        $this->_out('endobj');

        return $n;
    }

    /**
     * Schreibt die Textfeld-Objekte in den out-stream.
     */
    private function _kiPutFields() {
        // Falls ein Checkbox oder Radiobox enthalten ist,
        // muss die ZapfDings Schrift eingebunden werden.
        $needZapfDingbats = false;
        foreach ($this->_ki_FormFields as $field) {
            if (is_object($field) && ($field->type === 'checkbox' || $field->type === 'radiobox')) {
                $needZapfDingbats = true;
                break;
            }
        }
        $ZaDi_n = null;
        if ($needZapfDingbats) {
            $this->_newobj();
            $ZaDi_n = (int) $this->n;
            $this->_out('<</Type /Font /Subtype /Type1 /BaseFont /ZapfDingbats');
            $this->_out('>>');
            $this->_out('endobj');
        }

        $id = 0;
        foreach ($this->_ki_FormFields as $field) {
            
            // ************
            // Textfeld
            // ************
            if (is_object($field) && $field->type === 'textfield') {
                $id++;
                
                $this->_newobj();
                $n = (int) $this->n;

                // bitmask flags
                $bitmask = 0;
                if ($field->readOnly) $bitmask = $this->_kiSetBit(1, $bitmask);
                if ($field->required) $bitmask = $this->_kiSetBit(2, $bitmask);
                if ($field->noExport) $bitmask = $this->_kiSetBit(3, $bitmask);
                if ($field->multiline) $bitmask = $this->_kiSetBit(13, $bitmask);
                if ($field->password) $bitmask = $this->_kiSetBit(14, $bitmask);

                $this->_out('<< /Type /Annot /Subtype /Widget /F 4');
                $this->_out(sprintf(
                        '/Rect [%.2f %.2f %.2f %.2f]',
                        $field->x1,
                        $field->y1,
                        $field->x2,
                        $field->y2
                    ));
                $this->_out('/FT /Tx');
                $this->_out('/Ff ' . $bitmask);
                $this->_out('/T ' . $this->_textstring($this->_UTF8toUTF16($field->name ? $field->name : 'textfield' . $id)));
                $this->_out('/V ' . $this->_textstring($this->_UTF8toUTF16($field->value ? $field->value : '')));
                $this->_out('/DR 2 0 R');

                if ($field->maxLength && is_int($field->maxLength) && $field->maxLength > 0) {
                    $this->_out('/MaxLen ' . $field->maxLength);
                }

                // 0 0 0 rg ist die Textfarbe, könnte noch gesetzt werden.
                $this->_out(sprintf('/DA (0 0 0 rg /F%d %.2F Tf)', $field->currentFont_i, $field->FontSizePt));
                $this->_out('/AP <</N ' . ($n + 1) . ' 0 R >>');
                $this->_out('>>');
                $this->_out('endobj');

                // out stream
                $stream = '/Tx BMC EMC';

                $this->_newobj();
                $this->_out('<<');
                $this->_out('/Resources 2 0 R');
                $this->_out('/Length ' . strlen($stream) . '>>');
                $this->_putstream($stream);
                $this->_out('endobj');

                if (!is_array($this->_ki_page_Annots[$field->pageNo])) {
                    $this->_ki_page_Annots[$field->pageNo] = array();
                }

                // ID von Felder für weitere Verarbeitung speichern
                $this->_ki_page_Annots[$field->pageNo][] = $n;
                $this->_ki_acroFormFields[] = $n;


            // ************
            // combo
            // ************
            } else if (is_object($field) && $field->type === 'combobox') {
                $id++;

                $this->_newobj();
                $n = (int) $this->n;

                // bitmask flags
                $bitmask = 0;
                if ($field->readOnly) $bitmask = $this->_kiSetBit(1, $bitmask);
                if ($field->required) $bitmask = $this->_kiSetBit(2, $bitmask);
                if ($field->noExport) $bitmask = $this->_kiSetBit(3, $bitmask);
                if (!$field->listbox) $bitmask = $this->_kiSetBit(18, $bitmask);
                if ($field->editable) $bitmask = $this->_kiSetBit(19, $bitmask);
                
                $this->_out('<< /Type /Annot /Subtype /Widget /F 4');
                $this->_out(sprintf(
                        '/Rect [%.2f %.2f %.2f %.2f]',
                        $field->x1,
                        $field->y1,
                        $field->x2,
                        $field->y2
                    ));
                $this->_out('/FT /Ch');
                $this->_out('/Ff ' . $bitmask);
                $this->_out('/T ' . $this->_textstring($this->_UTF8toUTF16($field->name ? $field->name : 'combobox' . $id)));
                $this->_out('/V ' . $this->_textstring($this->_UTF8toUTF16($field->value ? $field->value : '')));
                
                $options = is_array($field->values) ? $field->values : array();
                $Opt = '';
                foreach ($options as $option) {
                    $Opt .= $this->_textstring($this->_UTF8toUTF16($option ? $option : '')) . ' ';
                }
                unset ($option, $options);

                $this->_out('/Opt [' . $Opt . ']');
                $this->_out('/DR 2 0 R');
                
                // 0 0 0 rg ist die Textfarbe, könnte noch gesetzt werden.
                $this->_out(sprintf('/DA (0 0 0 rg /F%d %.2F Tf)', $field->currentFont_i, $field->FontSizePt));
                $this->_out('/AP <</N ' . ($n + 1) . ' 0 R >>');
                $this->_out('>>');
                $this->_out('endobj');

                // out stream
                $stream = '/Tx BMC EMC';

                $this->_newobj();
                $this->_out('<<');
                $this->_out('/Resources 2 0 R');
                $this->_out('/Length ' . strlen($stream) . '>>');
                $this->_putstream($stream);
                $this->_out('endobj');

                if (!is_array($this->_ki_page_Annots[$field->pageNo])) {
                    $this->_ki_page_Annots[$field->pageNo] = array();
                }

                // ID von Felder für weitere Verarbeitung speichern
                $this->_ki_page_Annots[$field->pageNo][] = $n;
                $this->_ki_acroFormFields[] = $n;

            // ************
            // checkbox
            // ************
            } else if (is_object($field) && $field->type === 'checkbox') {
                $id++;

                $this->_newobj();
                $n = (int) $this->n;

                // bitmask flags
                $bitmask = 0;
                if ($field->readOnly) $bitmask = $this->_kiSetBit(1, $bitmask);
                if ($field->required) $bitmask = $this->_kiSetBit(2, $bitmask);
                if ($field->noExport) $bitmask = $this->_kiSetBit(3, $bitmask);

                $checkChar = '8';
                if ($field->checkChar && strlen($field->checkChar) === 1) {
                    $checkChar = $field->checkChar;
                }

                $this->_out('<< /Type /Annot /Subtype /Widget /F 4');
                $this->_out(sprintf(
                        '/Rect [%.2f %.2f %.2f %.2f]',
                        $field->x1,
                        $field->y1,
                        $field->x2,
                        $field->y2
                    ));
                $this->_out('/FT /Btn');
                $this->_out('/Ff ' . $bitmask);
                $this->_out('/T ' . $this->_textstring($this->_UTF8toUTF16($field->name ? $field->name : 'combobox' . $id)));
                $this->_out('/V ' . ($field->checked ? '/On' : '/Off'));

                // 0 0 0 rg ist die Textfarbe, könnte noch gesetzt werden.
                $this->_out('/DR << /Font << /ZaDi ' . $ZaDi_n . ' 0 R >> >>');
                $this->_out('/AP <</N << /On ' . ($n + 1) . ' 0 R /Off ' . ($n + 2) . ' 0 R >> >>');
                $this->_out('/DA(0 0 0 rg /ZaDi 0 Tf)');
                $this->_out('/MK << /CA(' . $checkChar . ') >>');
                $this->_out('>>');
                $this->_out('endobj');

                // On  stream
                $stream = '/Tx BMC q BT 0 0 0 rg /ZaDi 11.1 Tf 1.8 1.8 Td (' . $checkChar . ') Tj ET Q EMC';

                $this->_newobj();
                $this->_out('<<');
                $this->_out('/Resources << /Font << /ZaDi ' . $ZaDi_n . ' 0 R >> >>');
                $this->_out('/Length ' . strlen($stream) . '>>');
                $this->_putstream($stream);
                $this->_out('endobj');

                // Off  stream
                $stream = '/Tx BMC EMC';

                $this->_newobj();
                $this->_out('<<');
                $this->_out('/Resources 2 0 R');
                $this->_out('/Length ' . strlen($stream) . '>>');
                $this->_putstream($stream);
                $this->_out('endobj');

                if (!is_array($this->_ki_page_Annots[$field->pageNo])) {
                    $this->_ki_page_Annots[$field->pageNo] = array();
                }

                // ID von Felder für weitere Verarbeitung speichern
                $this->_ki_page_Annots[$field->pageNo][] = $n;
                $this->_ki_acroFormFields[] = $n;

            // ************
            // radiobox
            // ************
            } else if (is_object($field) && $field->type === 'radiobox') {
                $id++;

                $this->_newobj();
                $n = (int) $this->n;

                // bitmask flags
                $bitmask = 0;
                if ($field->readOnly) $bitmask = $this->_kiSetBit(1, $bitmask);
                if ($field->required) $bitmask = $this->_kiSetBit(2, $bitmask);
                if ($field->noExport) $bitmask = $this->_kiSetBit(3, $bitmask);

                // 15: no toggle to Off (kann nicht abgewählt werden)
                // 16: radio flag
                $bitmask = $this->_kiSetBit(15, $bitmask);
                $bitmask = $this->_kiSetBit(16, $bitmask);


                $checkChar = '4';
                if ($field->checkChar && strlen($field->checkChar) === 1) {
                    $checkChar = $field->checkChar;
                }

                $this->_out('<< /Type /Annot /Subtype /Widget /F 4');
                $this->_out(sprintf(
                        '/Rect [%.2f %.2f %.2f %.2f]',
                        $field->x1,
                        $field->y1,
                        $field->x2,
                        $field->y2
                    ));
                $this->_out('/FT /Btn');
                $this->_out('/Ff ' . $bitmask);
                $this->_out('/T ' . $this->_textstring($this->_UTF8toUTF16($field->name ? $field->name : 'combobox' . $id)));
                $this->_out('/V ' . ($field->checked ? '/On' : '/Off'));
                $this->_out('/DR << /Font << /ZaDi ' . $ZaDi_n . ' 0 R >> >>');
                $this->_out('/AP <</N << /On ' . ($n + 1) . ' 0 R /Off ' . ($n + 2) . ' 0 R >> >>');

                // 0 0 0 rg ist die Textfarbe, könnte noch gesetzt werden.
                $this->_out('/DA(0 0 0 rg /ZaDi 0 Tf)');
                $this->_out('/MK << /CA(' . $checkChar . ') >>');
                $this->_out('>>');
                $this->_out('endobj');

                // On  stream
                $stream = '/Tx BMC q BT 0 0 0 rg /ZaDi 11.1 Tf 1.8 1.8 Td (' . $checkChar . ') Tj ET Q EMC';

                $this->_newobj();
                $this->_out('<<');
                $this->_out('/Resources << /Font << /ZaDi ' . $ZaDi_n . ' 0 R >> >>');
                $this->_out('/Length ' . strlen($stream) . '>>');
                $this->_putstream($stream);
                $this->_out('endobj');

                // Off  stream
                $stream = '/Tx BMC EMC';

                $this->_newobj();
                $this->_out('<<');
                $this->_out('/Resources 2 0 R');
                $this->_out('/Length ' . strlen($stream) . '>>');
                $this->_putstream($stream);
                $this->_out('endobj');

                if (!is_array($this->_ki_page_Annots[$field->pageNo])) {
                    $this->_ki_page_Annots[$field->pageNo] = array();
                }

                // ID von Felder für weitere Verarbeitung speichern
                $this->_ki_page_Annots[$field->pageNo][] = $n;
                $this->_ki_acroFormFields[] = $n;
            }
        }
    }

    /**
     * Setzt das bit an Position $position auf 1.
     * @param int $position
     * @param int $input
     * @return int
     */
    private function _kiSetBit($position, $input = 0) {
        $base = 1;
        $base = $base << ($position-1);
        return $input | $base;
    }

    // -------------------------------------------------------
    // Events
    // -------------------------------------------------------

    // Event: Fusszeile erstellen (overwrite)
    public function Footer() {
        $pageNoOffset = isset($this->_settings) && isset($this->_settings->pageNoOffset) && is_int($this->_settings->pageNoOffset)
                ? (int) $this->_settings->pageNoOffset : 0;

        // Erste Seite / Ungerade Seite?
        $isPair  = ($this->PageNo() + $pageNoOffset) % 2 === 0;
        $isFirst = $this->PageNo() == 1;

        $hasFirstFooterPair = isset($this->_settings) &&
                isset($this->_settings->pageFooterFirstPair) &&
                $this->_settings->pageFooterFirstPair;

        $hasFirstFooter = isset($this->_settings) &&
                isset($this->_settings->pageFooterFirst) &&
                $this->_settings->pageFooterFirst;

        $hasFooterPair = isset($this->_settings) &&
                isset($this->_settings->pageFooterPair) &&
                $this->_settings->pageFooterPair;

        $hasFooter = isset($this->_settings) &&
                isset($this->_settings->pageFooter) &&
                $this->_settings->pageFooter;

        // Ermitteln, welcher Footer angzeigt werden soll
        $container = null;
        if ($isPair && $isFirst && $hasFirstFooterPair) {
            $container = $this->_settings->pageFooterFirstPair;

        } else if ($isFirst && $hasFirstFooter) {
            $container = $this->_settings->pageFooterFirst;

        } else if($isPair && $hasFooterPair) {
            $container = $this->_settings->pageFooterPair;

        } else if($hasFooter) {
            $container = $this->_settings->pageFooter;
        }

        // Footer erstellen
        $this->kiInsertItemsFromArray($container->items);        
    }

    // Event: Kopfzeile erstellen (overwrite)
    public function Header() {
        $pageNoOffset = isset($this->_settings) && isset($this->_settings->pageNoOffset) && is_int($this->_settings->pageNoOffset) 
                ? (int) $this->_settings->pageNoOffset : 0;
        
        // Erste Seite / Ungerade Seite? 
        $isPair  = ($this->PageNo() + $pageNoOffset) % 2 === 0;

        // Erste Seite?
        $isFirst = $this->PageNo() == 1;
        
        $hasFirstHeaderPair = isset($this->_settings) &&
                isset($this->_settings->pageHeaderFirstPair) &&
                $this->_settings->pageHeaderFirstPair;

        $hasFirstHeader = isset($this->_settings) &&
                isset($this->_settings->pageHeaderFirst) && 
                $this->_settings->pageHeaderFirst;

        $hasHeaderPair = isset($this->_settings) &&
                isset($this->_settings->pageHeaderPair) &&
                $this->_settings->pageHeaderPair;
        
        $hasHeader = isset($this->_settings) &&
                isset($this->_settings->pageHeader) && 
                $this->_settings->pageHeader;

        // Ermitteln, welcher Header angzeigt werden soll
        $container = null;
        if ($isPair && $isFirst && $hasFirstHeaderPair) {
            $container = $this->_settings->pageHeaderFirstPair;

        } else if ($isFirst && $hasFirstHeader) {
            $container = $this->_settings->pageHeaderFirst;

        } else if($isPair && $hasHeaderPair) {
            $container = $this->_settings->pageHeaderPair;

        } else if($hasHeader) {
            $container = $this->_settings->pageHeader;
        }
        
        // Header erstellen
        $this->kiInsertItemsFromArray($container->items);
    }
    

    
    
    /**
     * Setzt eine Farbe
     * @param string $type 'fill', 'text' oder 'draw'
     * @param string $color gewünschte Farbe im Format #ff0000 oder #f00
     */
    public function setColor($type, $color) {
        $r = 0;
        $g = 0;
        $b = 0;
        
        if (strlen($color)==4) {
            $r = base_convert(substr($color, 1, 1).substr($color, 1, 1), 16, 10);
            $g = base_convert(substr($color, 2, 1).substr($color, 2, 1), 16, 10);
            $b = base_convert(substr($color, 3, 1).substr($color, 3, 1), 16, 10);
        } else if (strlen($color)==7) {
            $r = base_convert(substr($color, 1, 2), 16, 10);
            $g = base_convert(substr($color, 3, 2), 16, 10);
            $b = base_convert(substr($color, 5, 2), 16, 10);
        }
        switch ($type) {
            case 'fill': $this->SetFillColor($r, $g, $b); break;
            case 'text': $this->SetTextColor($r, $g, $b); break;
            case 'draw': $this->SetDrawColor($r, $g, $b); break;
        }
    }
    
    /**
     * Setzen der Schriftart mit einem Style-Objekt
     * @param type $obj     Beispiel:
     *                      {
     *                          family: "arial",
     *                          style: "B",
     *                          size: 12
     *                      }
     */
    public function setFontFromObject($obj) {
        // Original-Funktion aufrufen
        $this->SetFont(isset($obj->family) ? $obj->family : '', isset($obj->style) ? $obj->style : '', isset($obj->size) ? $obj->size : 0);
    }

    /**
     * overwrite
     * @param string $family
     * @param string $style
     * @param float $size
     * @param string $fontfile
     * @param string $subset
     * @param bool $out
     */
    public function SetFont($family, $style = '', $size = null, $fontfile = '', $subset = 'default', $out = true) {
		$family = mb_strtolower($family);
        $style = mb_strtoupper($style);
        
        // Underline ist kein Style einer Schriftart, sondern wird vom PDF mit setFont eingefügt.
        $fStyle = str_replace('U','',$style);
        $fontkey = $family.$fStyle;

        // Sonderfall Arial
        // Diese wird als TTF eingebunden (SocialOffice-Standardschriftart).
        if($family == 'arial' && !isset($this->fonts[$fontkey])) {
            $this->AddFont('arial', $fStyle, '', true);
        }

        parent::SetFont($family, $style, $size, $fontfile, $subset, $out);
    }
    
    
}
