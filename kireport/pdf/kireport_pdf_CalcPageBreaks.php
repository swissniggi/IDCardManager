<?php

class kireport_pdf_CalcPageBreaks {
    private $_fpdf;
    private $_settings;
    private $_currentContainer;  // Objekt mit Eigenschaften zum aktuellen Container (x, y, absX, absY, height, width, keepTogether)
    private $_pageNo = 0;
    
    private $_pageWidth = 0;
    private $_pageHeight = 0;
    
    private $_marginTop = 0;
    private $_marginRight = 0;
    private $_marginBottom = 0;
    private $_marginLeft = 0;
    
    private $_previuosPagesHeight = 0;
    
    private $_groupHeadersToRepeat = array();
    
    private $_x = null;
    private $_y = null;
    
    
    // -------------------------------------------------------
    // Public Methods
    // -------------------------------------------------------
    public function __construct(kireport_pdf_Fpdf $fpdf, $settings) {
        $this->_fpdf = $fpdf;
        $this->_settings = $settings ? $settings : new stdClass();
    }
    
    
    /**
     * Berechnet die Grössen der Einzelnen Elemente (calcAbsX, calcAbsY, calcWidth und calcHeight)
     * (calcAbsX und calcAbsY beziehen sich auf die Linke Obere Ecke eines unendlich grossen Papiers)
     * @param array|object $items
     */
    public function calcItemsSize($items) {
        if (!is_array($items)) {
            $items = array($items);
        }
        
        $containerWidth = 0;
        $containerHeight = 0;
        
        // Seitengrösse ermitteln
        $size = 'a4';        
        if ($this->_settings && $this->_settings->size) {
            $size = $this->_settings->size;
        }
        $pageSize = $this->_getPageSize($size);
        
        if ($this->_settings && $this->_settings->orientation && $this->_settings->orientation=='L') {
            $containerWidth = $pageSize[1];
            $containerHeight = $pageSize[0];
        } else {
            $containerWidth = $pageSize[0];
            $containerHeight = $pageSize[1];
        }

        // Seitenhöhe wird zum Positionieren der Fusszeilen benötigt
        $this->_pageHeight = $containerHeight;
        
        // Ränder abziehen
        $margins = $this->_settings && $this->_settings->margins ? $this->_settings->margins : '20 20 20 25';
        $marginRight = 20;
        $marginLeft = 25;
        if (strlen($margins)) {
            $tmp = explode(' ', $margins);
            if (count($tmp)>1) {
                $marginRight = $tmp[1];
            }
            if (count($tmp)>3) {
                $marginLeft = $tmp[3];
            }
        }
        $containerWidth = $containerWidth - $marginLeft - $marginRight;
        
        // Übergeordneter Container erstellen
        $curCont = new stdClass();
        $curCont->absX = 0;
        $curCont->absY = 0;
        $curCont->height = null;  // null = wie Inhalt
        $curCont->width = $containerWidth;
        $curCont->keepTogether = false;   // ganzer Container auf der gleichen Seite/Spalte anzeigen (eventueller Umbruch erfolgt vorher)
        $curCont->defaultLabelPosition = $this->_settings && isset($this->_settings->defaultLabelPosition) ? $this->_settings->defaultLabelPosition : 'left';
        $curCont->defaultLabelWidth = $this->_settings && isset($this->_settings->defaultLabelWidth) ? $this->_settings->defaultLabelWidth : 30;
        $curCont->defaultHideIfEmpty = $this->_settings && isset($this->_settings->defaultHideIfEmpty) ? $this->_settings->defaultHideIfEmpty : false;
        $curCont->maxHeight = 0;
        $this->_currentContainer = $curCont;
        
        $this->_x = 0;
        $this->_y = 0;
        $this->_calcItemsSizeRec($items);
    }
    
    /**
     * Items mit der Eigenschaft height='max' bekommen durch diese Funktion nun die richtige Höhe 
     * (bis zum unteren Rand des übergeordneten Containers)
     * @param array|object $items
     */
    public function calcMaxHeightItems($items, $parentContainerCalcAbsY=null, $parentContainerHeight=null) {
        if (!is_array($items)) {
            $items = array($items);
        }
        if ($items) {
            foreach ($items as $item) {
                if (!isset($item->fn)) {
                    $item->fn = 'container';
                }
                
                switch ($item->fn) {
                    // Container
                    case 'container':
                        if ($item->height === 'max') {
                            if (is_null($parentContainerHeight) || is_null($parentContainerCalcAbsY)) {
                                unset($item->height);
                            } else {
                                $item->height = $parentContainerHeight - ($item->calcAbsY - $parentContainerCalcAbsY);
                                if ($item->height > $item->calcHeight) {
                                    $item->calcHeight = $item->height;
                                } else {
                                    unset($item->height);
                                }
                            }                                 
                        }
                        if ($item->items) {
                            $this->calcMaxHeightItems($item->items, $item->calcAbsY, $item->calcHeight);
                        }
                        
                        break;
                        
                    // multiCell
                    case 'multiCell':
                        if ($item->height === 'max') {
                            if (is_null($parentContainerHeight) || is_null($parentContainerCalcAbsY)) {
                                unset($item->height);
                            } else {
                                $item->height = $parentContainerHeight - ($item->calcAbsY - $parentContainerCalcAbsY);
                                if ($item->height > $item->calcHeight) {
                                    $item->calcHeight = $item->height;
                                } else {
                                    unset($item->height);
                                }
                            }
                        }
                        
                        break;
                        
                    // Linie
                    case 'line':
                        $doCalcHeight = false;
                        if ($item->y1 === 'max') {
                            if (is_null($parentContainerHeight) || is_null($parentContainerCalcAbsY)) {
                                unset($item->y1);
                            } else {
                                $item->y1 = $parentContainerHeight;
                                $item->calcAbsY1 = $parentContainerCalcAbsY + $item->y1;
                            }
                            $doCalcHeight = true;
                        }
                        
                        if ($item->y2 === 'max') {
                            if (is_null($parentContainerHeight) || is_null($parentContainerCalcAbsY)) {
                                unset($item->y2);
                            } else {
                                $item->y2 = $parentContainerHeight;
                                $item->calcAbsY2 = $parentContainerCalcAbsY + $item->y2;
                            }
                            $doCalcHeight = true;
                        }
                        
                        if ($doCalcHeight) {
                            $height = $item->y2 - $item->y1;
                            if ($height < 0) {
                                $height = $height * -1;
                                $item->calcHeight = $height;
                            }
                        }
                        unset($doCalcHeight);
                        
                        break;
                        
                }
            }
            
        }
    }
    
    /**
     * Fügt die Seitenumbrüche ein und berechnet die Positionen zum linken oberen Seitenrand
     * (inkl. Seitenränder)  (absX, absY, pageBreakBefore)
     * @param array|object $items
     * @param string $mode 'pageHeaderFooter', 'groupHeaderToRepeat' oder 'default'
     * @return array $items
     */
    public function calcPagePosAndPageBreaks($items, $mode='default') {
        $isObject = !is_array($items);
        
        if ($isObject) {
            $items = array($items);
        }
        
        $this->_pageNo = 0;
        
        switch ($mode) {
            case 'pageHeaderFooter':
                $this->_marginTop = 0;
                $this->_marginRight = 0;
                $this->_marginBottom = 0;
                $this->_marginLeft = 0;
                $this->_setY(0);
                break;
            
            case 'groupHeaderToRepeat':
                $this->_pageNo = 1;
                $this->_insertPageBreak('', '', '', true);
                break;
            
            case 'default':
                $this->_insertPageBreak('', '', '', true);
                break;
        }
        $this->_x = 0;
        $this->_y = 0;
        $this->_previuosPagesHeight = 0; 

        $return = $this->_calcPagePosAndPageBreaksRec($items, $mode);
       
        return $isObject ? $return[0] : $return;
    }

    /**
     * Ersetzt HTML durch cells.
     * Dies muss vor der Funktion calcPagePosAndPageBreaks ausgeführt werden,
     * Somit werden Zeilenumbrüche durch HTML korrekt ausgeführt.
     * @param array|object $items
     */
    public function replaceHtmlByCells(&$items) {
        if (is_array($items)) {
            $newItems = array();
            foreach ($items as $item) {
                if ($item->fn == 'html') { 
                    $cells = $this->_makeCellsByHtml($item);

                    foreach ($cells as $cell) {
                        $newItems[] = $cell;
                    }

                } else {
                    // rekursion
                    if (is_object($item) && isset($item->items)) {
                        $this->replaceHtmlByCells($item->items);
                    }
                    $newItems[] = $item;
                }
            }

            $items = $newItems;
            unset($newItems);
        }

        if (is_object($items)) {
            if (isset($items->items)) {
                // rekursion
                $this->replaceHtmlByCells($items->items);
            }
        }
    }
    
    /**
     * Ersetzt MultiCells durch Cells. Dadurch wird ein Zeilenumbruch innerhalb von Multicells möglich.
     * Dies muss vor der Funktion calcPagePosAndPageBreaks ausgeführt werden,
     * Somit werden Zeilenumbrüche durch MultiCells korrekt ausgeführt.
     * @param array|object $items
     */
    public function replaceMultiCellByCells(&$items) {
        if (is_array($items)) {
            $newItems = array();
            foreach ($items as $item) {
                if ($item->fn == 'multiCell') {
                    $cells = $this->_makeCellsByMultiCell($item);

                    foreach ($cells as $cell) {
                        $newItems[] = $cell;
                    }

                } else {
                    // rekursion
                    if (is_object($item) && isset($item->items)) {
                        $this->replaceMultiCellByCells($item->items);
                    }
                    $newItems[] = $item;
                }
            }

            $items = $newItems;
            unset($newItems);
        }

        if (is_object($items)) {
            if (isset($items->items)) {
                // rekursion
                $this->replaceMultiCellByCells($items->items);
            }
        }
    }
    
    // Ersetzt alle fields durch container mit multicells
    public function simplifyElements($items) {
        if (!is_array($items)) {
            $items = array($items);
        }
        
        $containerWidth = 0;
        
        // Seitengrösse ermitteln
        $size = 'a4';
        if ($this->_settings && $this->_settings->size) {
            $size = $this->_settings->size;
        }
        $pageSize = $this->_getPageSize($size);
        
        if ($this->_settings && $this->_settings->orientation && $this->_settings->orientation=='L') {
            $containerWidth = $pageSize[1];
        } else {
            $containerWidth = $pageSize[0];
        }

        // Ränder abziehen
        $margins = $this->_settings && $this->_settings->margins ? $this->_settings->margins : '20 20 20 25';
        $marginRight = 20;
        $marginLeft = 25;
        if (strlen($margins)) {
            $tmp = explode(' ', $margins);
            if (count($tmp)>1) {
                $marginRight = $tmp[1];
            }
            if (count($tmp)>3) {
                $marginLeft = $tmp[3];
            }
        }
        $containerWidth = $containerWidth - $marginLeft - $marginRight;
        
        // Übergeordneter Container erstellen
        $curCont = new stdClass();
        $curCont->absX = 0;
        $curCont->absY = 0;
        $curCont->height = null;  // null = wie Inhalt
        $curCont->width = $containerWidth;
        $curCont->keepTogether = false;   // ganzer Container auf der gleichen Seite/Spalte anzeigen (eventueller Umbruch erfolgt vorher)
        $curCont->defaultLabelPosition = $this->_settings && isset($this->_settings->defaultLabelPosition) ? $this->_settings->defaultLabelPosition : 'left';
        $curCont->defaultLabelWidth = $this->_settings && isset($this->_settings->defaultLabelWidth) ? $this->_settings->defaultLabelWidth : 30;
        $curCont->defaultHideIfEmpty = $this->_settings && isset($this->_settings->defaultHideIfEmpty) ? $this->_settings->defaultHideIfEmpty : false;
        $curCont->maxHeight = 0;
        $this->_currentContainer = $curCont;
        
        $this->_x = 0;
        $this->_y = 0;
        $this->_simplifyElementsRec($items);
    }
    
    // Ersetzt grosse HTML-Felder durch mehrere kleinere HTML-Felder, so dass Zeilenumbrüche möglich werden.
    public function splitHtmlElements($items) {
        if (!is_array($items)) {
            $items = array($items);
        }
        $this->_splitHtmlElementsRec($items);
    }

    // -------------------------------------------------------
    // Protected Methods
    // -------------------------------------------------------
    /**
     * Berechnet die grössen der Einzelnen Elemente (calcAbsX, calcAbsY, calcWidth und calcHeight)
     * @param array $items 
     */
    protected function _calcItemsSizeRec($items) {
        if ($items) {
            foreach ($items as $item) {
                switch ($item->fn) {
                    // Einzeiliger Text. Anschliessende Position des Cursors kann mit ln definiert werden.
                    case 'cell':
                        if (isset($item->x)) {
                            $this->_setX($item->x);
                        }
                        if (isset($item->y)) {
                            $this->_setY($item->y);
                        }
                        
                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);

                        $width = isset($item->width) ? $item->width : 0;
                        if ($width==0) {
                            $width = $this->_currentContainer->width - $this->_getX();
                        }
                        if (isset($item->style->marginTop)) {
                            $this->_y += $item->style->marginTop;
                        }
                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;
                        
                        // Masse berechnen
                        $height = $item->style->lineHeight ? $item->style->lineHeight : 4;

                        $item->calcHeight = $height;
                        $item->calcWidth = $width;
                        
                        // Durch die Drehung verändert sich die Grösse
                        list($rStyle, $width, $height, $xd, $yd) = $this->_calcSizeIfRotated($item->style->rotation, $width, $height, $item->calcAbsX, $item->calcAbsY);
                        $item->style->rotation = $rStyle;
                        unset ($rStyle);

                        // Falls rotiert wird, befindet sich die Koordinate nicht mehr oben Links
                        $item->calcAbsX += $xd;
                        $item->calcAbsY += $yd;
                        
                        $newY = $this->_getY() + $height;
                        if (isset($item->style->marginBottom)) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }
                        
                        // Neue X/Y-Positionen ermitteln
                        $this->_setY($newY);
                        $this->_setX(0);
                        
                        break;
                        
                    // Container
                    case 'container':
                        $x = isset($item->x) ? $item->x : $this->_getX();
                        $y = isset($item->y) ? $item->y : $this->_getY();
                        
                        if ($x < 0) {
                            $x = $this->_currentContainer->width + $x;
                        }
                        
                        $this->_setY($y);
                        $this->_setX($x);
                        
                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);
                        
                        
                        if (isset($item->style->marginTop)) {
                            $this->_y += $item->style->marginTop;
                        }
                        
                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;
                        
                        $width = $item->width ? $item->width : $this->_currentContainer->width - $x;
                        
                        $parentContainer = $this->_currentContainer;
                        
                        $curCont = new stdClass();
                        $curCont->absX = $item->calcAbsX;
                        $curCont->absY = $item->calcAbsY;
                        $curCont->height = $item->height ? $item->height : null;  // null = wie Inhalt
                        $curCont->width = $width;
                        $curCont->keepTogether = !!$item->keepTogether;   // ganzer Container auf der gleichen Seite/Spalte anzeigen (eventueller Umbruch erfolgt vorher)
                        $curCont->defaultLabelPosition = isset($item->defaultLabelPosition) ? 
                                $item->defaultLabelPosition : $parentContainer->defaultLabelPosition;
                        $curCont->defaultLabelWidth = isset($item->defaultLabelWidth) ? 
                                $item->defaultLabelWidth : $parentContainer->defaultLabelWidth;
                        $curCont->defaultHideIfEmpty = isset($item->defaultHideIfEmpty) ? 
                                $item->defaultHideIfEmpty : $parentContainer->defaultHideIfEmpty;
                        $curCont->maxHeight = 0;
                        $this->_currentContainer = $curCont;
                        
                        $this->_setY(0);
                        $this->_setX(0);
                        
                        if ($item->items) {
                            $this->_calcItemsSizeRec($item->items);
                        }
                        
                        $height = $item->height ? $item->height : 0;
                        if ($this->_currentContainer->maxHeight > $height) {
                            $height = $this->_currentContainer->maxHeight;
                        }
                        $item->calcWidth = $width;
                        $item->calcHeight = $height;

                        // Drehungen ganzer Container werden zurzeit nicht unterstüzt
                        // Für diese müsste die "Rotate"-Eigenschaft auf alle subitems
                        // übernommen werden.
                        $item->style->rotation = 0;

                        $newY = $y + $item->calcHeight;
                        if (isset($item->style->marginTop)) {
                            $newY += $item->style->marginTop;
                        }
                        if (isset($item->style->marginBottom)) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $parentContainer->maxHeight) {
                            $parentContainer->maxHeight = $newY;
                        }
                        
                        $this->_currentContainer = $parentContainer;
                        
                        $this->_setY($newY);
                        $this->_setX(0);
                        
                        break;
                        
                    // HTML
                    case 'html':
                        $x = isset($item->x) ? $item->x : $this->_getX();
                        $y = isset($item->y) ? $item->y : $this->_getY();
                        
                        $this->_setY($y);
                        $this->_setX($x);
                        
                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);
                        
                        
                        if ($item->style->marginTop) {
                            $this->_y += $item->style->marginTop;
                        }
                        
                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;
                        
                        $width = $item->width ? $item->width : $this->_currentContainer->width - $x;
                        $height = $this->_fpdf->kiGetHtmlCells($item->html, $item->calcWidth, true);

                        $item->calcWidth = $width;
                        $item->calcHeight = $height;
                        
                        // Durch die Drehung verändert sich die Grösse
                        list($rStyle, $width, $height, $xd, $yd) = $this->_calcSizeIfRotated($item->style->rotation, $width, $height, $item->calcAbsX, $item->calcAbsY);
                        $item->style->rotation = $rStyle;
                        unset ($rStyle);

                        // Falls rotiert wird, befindet sich die Koordinate nicht mehr oben Links
                        $item->calcAbsX += $xd;
                        $item->calcAbsY += $yd;
                        
                        // Neue Y-Position ermitteln
                        $newY = $y + $height;
                        if ($item->style->marginTop) {
                            $newY += $item->style->marginTop;
                        }
                        if ($item->style->marginBottom) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }
                        $this->_setY($newY);
                        $this->_setX(0);
                        break;
                        
                    // Bild
                    case 'image':
                        $x = isset($item->x) ? $item->x : $this->_getX();
                        $y = isset($item->y) ? $item->y : $this->_getY();
                        
                        $this->_setY($y);
                        $this->_setX($x);
                        
                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);
                        
                        
                        if ($item->style->marginTop) {
                            $this->_y += $item->style->marginTop;
                        }
                        
                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;
                        
                        $width = isset($item->width) ? $item->width : 0;
                        $height = isset($item->height) ? $item->height : 0;

                        // Breite und Höhe so berechnen, dass das Bild nicht verzogen wird
                        $size = $this->_fpdf->kiGetImageSize($item->file, $width, $height);
                        $width = $size->width;
                        $height = $size->height;

                        $item->calcWidth = $width;
                        $item->calcHeight = $height;

                        // Durch die Drehung verändert sich die Grösse
                        list($rStyle, $width, $height, $xd, $yd) = $this->_calcSizeIfRotated($item->style->rotation, $width, $height, $item->calcAbsX, $item->calcAbsY);
                        $item->style->rotation = $rStyle;
                        unset ($rStyle);

                        // Falls rotiert wird, befindet sich die Koordinate nicht mehr oben Links
                        $item->calcAbsX += $xd;
                        $item->calcAbsY += $yd;
                        
                        // Neue Y-Position ermitteln
                        $newY = $y + $height;
                        if ($item->style->marginTop) {
                            $newY += $item->style->marginTop;
                        }
                        if ($item->style->marginBottom) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }
                        $this->_setY($newY);
                        $this->_setX(0);
                        break;
                        
                    // Linie
                    case 'line':
                        $x1 = isset($item->x1) ? $item->x1 : null;
                        $y1 = isset($item->y1) ? $item->y1 : null;
                        $x2 = isset($item->x2) ? $item->x2 : null;
                        $y2 = isset($item->y2) ? $item->y2 : null;
                        if (is_null($x1)) {
                            $x1 = 0;
                        }
                        if (is_null($y1)) {
                            $y1 = $this->_getY();
                        }
                        if (is_null($x2)) {
                            $x2 = $this->_currentContainer->width;
                        }
                        if (is_null($y2)) {
                            $y2 = $this->_getY();
                        }
                        
                        // style
                        $styles = array('default','line');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);
                        
                        if ($item->style->marginTop) {
                            if ($y1!=='max') {
                                $y1 += $item->style->marginTop;
                            }
                            if ($y2!=='max') {
                                $y2 += $item->style->marginTop;
                            }
                        }
                        
                        if ($y1!=='max' && $y2!=='max') {
                            $height = $y2 - $y1;
                            if ($height < 0) {
                                $height = $height * -1;
                            }
                        }
                        
                        $width = $x2 - $x1;
                        if ($width < 0) {
                            $width = $width * -1;
                        }

                        // Durch die Drehung verändert sich die Grösse
                        list($rStyle, $wr, $hr, $xd, $yd) = $this->_calcSizeIfRotated($item->style->rotation, $width, $height, $item->calcAbsX, $item->calcAbsY);
                        $item->style->rotation = $rStyle;
                        unset ($rStyle);
                        
                        $item->calcAbsX1 = $this->_currentContainer->absX + $x1 + $xd;
                        $item->calcAbsX2 = $this->_currentContainer->absX + $x2 + $xd;
                        $item->calcWidth = $width;
                        
                        if ($y1!=='max') {
                            $item->calcAbsY1 = $this->_currentContainer->absY + $y1 + $yd;
                        }
                        if ($y2!=='max') {
                            $item->calcAbsY2 = $this->_currentContainer->absY + $y2 + $yd;
                        }
                        if ($y1!=='max' && $y2!=='max') {
                            $item->calcHeight = $height;
                        }
                        
                        // Neue Y-Position ermitteln
                        if ($y1!=='max' && $y2!=='max') {
                            $newY = ($y2>$y1 ? $y2 : $y1);
                        } elseif ($y1!=='max') {
                            $newY = $y1;
                        } elseif ($y2!=='max') {
                            $newY = $y2;
                        } else {
                            $newY = 0;
                        }
                        
                        if ($item->style->marginBottom) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }
                        break;
                    
                    // Mehrzeiliger Text. Cursor steht anschliessend am Anfang einer neuen Zeile.
                    case 'multiCell':
                        if (isset($item->x)) {
                            $this->_setX($item->x);
                        }
                        if (isset($item->y)) {
                            $this->_setY($item->y);
                        }
                        
                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);
                        
                        $width = isset($item->width) ? $item->width : 0;
                        if ($width == 0) {
                            $width = $this->_currentContainer->width - $this->_getX();
                        }
                        
                        // Grösse berechnen
                        $height = $this->_fpdf->kiGetMultiCellHeight($item->text, $width, $item->style);
                        if (isset($item->height) && $item->height!=='max') {
                            if ($item->height > $height) {
                                $height = $item->height;
                            }
                            if ($height > $item->height) {
                                $item->height = $height;
                            }
                        }
                        
                        if ($item->style->marginTop) {
                            $this->_y += $item->style->marginTop;
                        }
                        
                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;
                        
                        $item->calcWidth = $width;
                        $item->calcHeight = $height;

                        // Durch die Drehung verändert sich die Grösse
                        list($rStyle, $width, $height, $xd, $yd) = $this->_calcSizeIfRotated($item->style->rotation, $width, $height, $item->calcAbsX, $item->calcAbsY);
                        $item->style->rotation = $rStyle;
                        unset ($rStyle);

                        // Falls rotiert wird, befindet sich die Koordinate nicht mehr oben Links
                        $item->calcAbsX += $xd;
                        $item->calcAbsY += $yd;
                        
                        // Neue Y-Position ermitteln
                        $newY = $this->_getY() + $height;
                        if ($item->style->marginBottom) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }
                        
                        $this->_setY($newY);
                        $this->_setX(0);

                        break;
                        
                    // PDF
                    case 'pdf':
                        $x = isset($item->x) ? $item->x : $this->_getX();
                        $y = isset($item->y) ? $item->y : $this->_getY();
                        
                        $this->_setY($y);
                        $this->_setX($x);
                        
                        if (!$item->file) {
                            $item->file = $this->_settings->templateFilePath;
                        }
                        
                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);
                        
                        
                        if ($item->style->marginTop) {
                            $this->_y += $item->style->marginTop;
                        }
                        
                        
                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;
                        
                        $width = isset($item->width) ? $item->width : 0;
                        $height = isset($item->height) ? $item->height : 0;

                        // Breite und Höhe so berechnen, dass das PDF nicht verzogen wird
                        $size = $this->_fpdf->kiGetPdfSize($item->file, $item->pageNo, $width, $height);
                        $width = $size->width;
                        $height = $size->height;
                        
                        $item->calcWidth = $width;
                        $item->calcHeight = $height;
                        
                        // Neue Y-Position ermitteln
                        $newY = $y + $item->calcHeight;
                        if ($item->style->marginTop) {
                            $newY += $item->style->marginTop;
                        }
                        if ($item->style->marginBottom) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }
                        $this->_setY($newY);
                        $this->_setX(0);
                        break;
                        
                    // Abstand
                    case 'space':
                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);
                        
                        if ($item->style->marginTop) {
                            $this->_y += $item->style->marginTop;
                        }
                        
                        $item->calcAbsX = $this->_getX();
                        $item->calcAbsY = $this->_getY();
                        
                        $height = isset($item->style->lineHeight) ? $item->style->lineHeight : 4;
                        if (isset($item->height)) {
                            $height = $item->height;
                        }

                        $item->calcHeight = $height;
                        $item->calcWidth = $width;
                        
                        // Durch die Drehung verändert sich die Grösse
                        list($rStyle, $width, $height, $xd, $yd) = $this->_calcSizeIfRotated($item->style->rotation, $width, $height, $item->calcAbsX, $item->calcAbsY);
                        $item->style->rotation = $rStyle;
                        unset ($rStyle);

                        // Falls rotiert wird, befindet sich die Koordinate nicht mehr oben Links
                        $item->calcAbsX += $xd;
                        $item->calcAbsY += $yd;
                                                
                        // Neue Y-Position ermitteln
                        $newY = $this->_getY() + $height;
                        if ($item->style->marginBottom) {
                            $newY += $item->style->marginBottom;
                        }
                        $this->_setY($newY);
                        $this->_setX(0);
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }
                        
                        break;

                    // Code 128
                    case 'code128':
                        $x = isset($item->x) ? $item->x : $this->_getX();
                        $y = isset($item->y) ? $item->y : $this->_getY();

                        $this->_setY($y);
                        $this->_setX($x);

                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);


                        if ($item->style->marginTop) {
                            $this->_y += $item->style->marginTop;
                        }

                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;
                        
                        // Höhe und Breite = Default wenn nicht gesetzt
                        $width = isset($item->width) ? $item->width : 40;
                        $height = isset($item->height) ? $item->height : 5;

                        // Höhe und Breite = Default wenn nicht gesetzt
                        $item->calcWidth = $width;
                        $item->calcHeight = $height;

                        // Durch die Drehung verändert sich die Grösse
                        list($rStyle, $width, $height, $xd, $yd) = $this->_calcSizeIfRotated($item->style->rotation, $width, $height, $item->calcAbsX, $item->calcAbsY);
                        $item->style->rotation = $rStyle;
                        unset ($rStyle);

                        $item->calcAbsX += $xd;
                        $item->calcAbsY += $yd;

                        // Neue Y-Position ermitteln
                        $newY = $y + $height;
                        if ($item->style->marginTop) {
                            $newY += $item->style->marginTop;
                        }
                        if ($item->style->marginBottom) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }
                        $this->_setY($newY);
                        $this->_setX(0);
                        break;

                    // QR-Code
                    case 'qrcode':
                        $x = isset($item->x) ? $item->x : $this->_getX();
                        $y = isset($item->y) ? $item->y : $this->_getY();

                        $this->_setY($y);
                        $this->_setX($x);

                        // text übernehmen
                        if (isset($item->value)) {
                            $item->text = $item->value;
                            unset ($item->value);
                        }

                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);


                        if ($item->style->marginTop) {
                            $this->_y += $item->style->marginTop;
                        }

                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;

                        // Höhe und Breite = Default wenn nicht gesetzt
                        $width = 40;
                        $height = 40;

                        // QR-Code muss die selbe Höhe wie Breite haben.
                        // diese wird hier berechnet.
                        if (isset($item->width) && isset($item->height)) {
                            $width  = min($item->width, $item->height);
                            $height = min($item->width, $item->height);
                        } else if (isset($item->width) && !isset($item->height)) {
                            $width  = $item->width;
                            $height = $item->width;
                        } else if (!isset($item->width) && isset($item->height)) {
                            $width  = $item->height;
                            $height = $item->height;
                        }

                        $item->calcWidth = $width;
                        $item->calcHeight = $height;

                        // Durch die Drehung verändert sich die Grösse
                        list($rStyle, $width, $height, $xd, $yd) = $this->_calcSizeIfRotated($item->style->rotation, $width, $height, $item->calcAbsX, $item->calcAbsY);
                        $item->style->rotation = $rStyle;
                        unset ($rStyle);

                        // Falls rotiert wird, befindet sich die Koordinate nicht mehr oben Links
                        $item->calcAbsX += $xd;
                        $item->calcAbsY += $yd;

                        // Neue Y-Position ermitteln
                        $newY = $y + $height;
                        if ($item->style->marginTop) {
                            $newY += $item->style->marginTop;
                        }
                        if ($item->style->marginBottom) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }

                        $this->_setY($newY);
                        $this->_setX(0);
                        break;
                        
                    // Chart
                    case 'chart':
                        $x = isset($item->x) ? $item->x : $this->_getX();
                        $y = isset($item->y) ? $item->y : $this->_getY();

                        $this->_setY($y);
                        $this->_setX($x);

                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);


                        if ($item->style->marginTop) {
                            $this->_y += $item->style->marginTop;
                        }

                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;

                        // Höhe und Breite = Default wenn nicht gesetzt
                        $item->calcWidth = isset($item->width) ? $item->width : $this->_currentContainer->width;
                        $item->calcHeight = isset($item->height) ? $item->height : 60;

                        // Neue Y-Position ermitteln
                        $newY = $y + $item->calcHeight;
                        if ($item->style->marginTop) {
                            $newY += $item->style->marginTop;
                        }
                        if ($item->style->marginBottom) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }
                        $this->_setY($newY);
                        $this->_setX(0);
                        break;

                    // Formular
                    case 'form_textfield':
                    case 'form_combobox':
                    case 'form_checkbox':
                    case 'form_radiobox':
                        $x = isset($item->x) ? $item->x : $this->_getX();
                        $y = isset($item->y) ? $item->y : $this->_getY();

                        $this->_setY($y);
                        $this->_setX($x);

                        // style
                        $styles = array('default');
                        if (isset($item->style)) {
                            if (is_array($item->style)) {
                                $styles = array_merge($styles, $item->style);
                            } else {
                                $styles[] = $item->style;
                            }
                        }
                        $item->style = $this->_joinStyles($styles);
                        unset($styles);


                        if ($item->style->marginTop) {
                            $this->_y += $item->style->marginTop;
                        }

                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;

                        // Höhe und Breite = Default wenn nicht gesetzt
                        if ($item->fn === 'form_checkbox' || $item->fn === 'form_radiobox') {
                            $item->calcWidth = isset($item->width) ? $item->width : (isset($item->style->lineHeight) ? $item->style->lineHeight : 6);
                            $item->calcHeight = $item->calcWidth;
                        } else {
                            $item->calcWidth = isset($item->width) ? $item->width : 50;
                            $item->calcHeight = isset($item->height) ? $item->height : (isset($item->style->lineHeight) ? $item->style->lineHeight * 1.5 : 6);
                        }

                        // Neue Y-Position ermitteln
                        $newY = $y + $item->calcHeight;
                        if ($item->style->marginTop) {
                            $newY += $item->style->marginTop;
                        }
                        if ($item->style->marginBottom) {
                            $newY += $item->style->marginBottom;
                        }
                        if ($newY > $this->_currentContainer->maxHeight) {
                            $this->_currentContainer->maxHeight = $newY;
                        }
                        $this->_setY($newY);
                        $this->_setX(0);
                        break;
                }
            }
        }
    }
    
    /**
     * Teilt die Elemente einzelnen Seiten zu und berechnet die Positionen zum linken oberen 
     * Seitenrand (inkl. Seitenränder)  (absX, absY, pageBreakBefore)
     * @param array $items
     * @param string $mode 'pageHeaderFooter', 'groupHeaderToRepeat' oder 'default'
     * @return array $items
     */
    protected function _calcPagePosAndPageBreaksRec($items, $mode='default') {
        $return = array();
        if ($items) {
            foreach ($items as $item) {
                switch ($item->fn) {
                    // Container einfügen
                    case 'container':
                        $newItem = $this->_cloneItem($item);

                        // Evtl. Seitenumbruch einfügen
                        if ($mode=='default') {
                            if (isset($item->pageBreak) && $item->pageBreak=='before') {
                                $this->_previuosPagesHeight = $newItem->calcAbsY;
                                $headerContainerToInsert = $this->_insertPageBreak();
                                if ($headerContainerToInsert) {
                                    $return[] = $headerContainerToInsert;
                                } else {
                                    $newItem->pageBreakBefore = true;
                                }
                                unset($headerContainerToInsert);

                            } elseif ($newItem->keepTogether) {
                                if ($newItem->calcAbsY-$this->_previuosPagesHeight+$newItem->calcHeight > 
                                        $this->_pageHeight-$this->_marginTop-$this->_marginBottom) {

                                    // Umbruch nur machen, wenn der Container auf der nächsten Seite ganz Platz hat
                                    if ($newItem->calcHeight < $this->_getNextPageInnerHeight()) {
                                        $this->_previuosPagesHeight = $newItem->calcAbsY;
                                        $headerContainerToInsert = $this->_insertPageBreak();
                                        if ($headerContainerToInsert) {
                                            $return[] = $headerContainerToInsert;
                                        } else {
                                            $newItem->pageBreakBefore = true;
                                        }
                                        unset($headerContainerToInsert);
                                    }
                                }
                            }
                        }
                        // Absolute Positionen ermitteln
                        $newItem->absX = $newItem->calcAbsX + $this->_marginLeft;
                        $newItem->absY = $newItem->calcAbsY - $this->_previuosPagesHeight + $this->_marginTop;
                        
                        // Falls es sich um einen groupHeader handelt, der sich auf jeder Seite wiederholen soll,
                        // den Header zum wiederholen vormerken
                        if ($newItem->repeat) {
                            $newItem->repeat->x = $newItem->calcAbsX;
                            $this->_groupHeadersToRepeat[] = $newItem->repeat;
                        }

                        // falls das item gedreht ist, der Drehpunkt berechnen (selbe Distanz von den Koordinaten wie beim Abs)
                        if ($newItem->style->rotation && $newItem->style->rotation instanceof stdClass) {
                            $newItem->style->rotation->rotateX = $newItem->absX - ($newItem->calcAbsX - $newItem->style->rotation->rotateAbsX);
                            $newItem->style->rotation->rotateY = $newItem->absY - ($newItem->calcAbsY - $newItem->style->rotation->rotateAbsY);
                        }
                        
                        // Untergeordnete Items durchgehen
                        if ($item->items) {
                            $newItem->items = $this->_calcPagePosAndPageBreaksRec($item->items, $mode);
                        }
                        
                        
                        // die Gruppe ist nun vorbei, die Vormerkung kann nun wieder entfernt werden.
                        if ($newItem->repeatEnd) {
                            array_pop($this->_groupHeadersToRepeat);
                        }
                        
                        $return[] = $newItem;
                        break;
                    
                    // Linie
                    case 'line':
                        $newItem = $this->_cloneItem($item);

                        // Zum Berechnen muss das kleinere Y (=weiter oben) genommen werden
                        $y = $newItem->calcAbsY1<$newItem->calcAbsY2 ? $newItem->calcAbsY1 : $newItem->calcAbsY2;
                        
                        // Evtl. Seitenumbruch einfügen
                        if ($mode=='default') {
                            if ($y-$this->_previuosPagesHeight+$newItem->calcHeight > $this->_pageHeight-$this->_marginTop-$this->_marginBottom) {
                                // Umbruch nur machen, wenn der Container auf der nächsten Seite ganz Platz hat
                                if ($newItem->calcHeight < $this->_getNextPageInnerHeight()) {
                                    $this->_previuosPagesHeight = $y;
                                    $headerContainerToInsert = $this->_insertPageBreak();
                                    if ($headerContainerToInsert) {
                                        $return[] = $headerContainerToInsert;
                                    } else {
                                        $newItem->pageBreakBefore = true;
                                    }
                                    unset($headerContainerToInsert);
                                }
                            }
                        }
                        
                        // Absolute Positionen ermitteln
                        $newItem->absX1 = $newItem->calcAbsX1 + $this->_marginLeft;
                        $newItem->absY1 = $newItem->calcAbsY1 - $this->_previuosPagesHeight + $this->_marginTop;
                        $newItem->absX2 = $newItem->calcAbsX2 + $this->_marginLeft;
                        $newItem->absY2 = $newItem->calcAbsY2 - $this->_previuosPagesHeight + $this->_marginTop;
                        
                        $return[]  = $newItem;
                        break;
                        
                    // Abstand (wegen einem Abstand machen wir keinen Seitenumbruch, weil sonst die neue Seite mit einem Abstand begint)
                    case 'space':
                        $newItem = $this->_cloneItem($item);
                        
                        // Absolute Positionen ermitteln
                        $newItem->absX = $newItem->calcAbsX + $this->_marginLeft;
                        $newItem->absY = $newItem->calcAbsY - $this->_previuosPagesHeight + $this->_marginTop;
                        
                        $return[] = $newItem;
                        break;
                        
                    // andere Felder
                    case 'cell':
                    case 'code128':
                    case 'qrcode':
                    case 'html':
                    case 'image':
                    case 'ln':
                    case 'multiCell':
                    case 'pdf':
                    case 'chart':
                    case 'form_textfield':
                    case 'form_combobox':
                    case 'form_checkbox':
                    case 'form_radiobox':
                        
                        $newItem = $this->_cloneItem($item);

                        // Evtl. Seitenumbruch einfügen
                        if ($mode=='default') {
                            if ($newItem->calcAbsY-$this->_previuosPagesHeight+$newItem->calcHeight > $this->_pageHeight-$this->_marginTop-$this->_marginBottom) {
                                // Umbruch nur machen, wenn der Container auf der nächsten Seite ganz Platz hat
                                if ($newItem->calcHeight < $this->_getNextPageInnerHeight()) {
                                    $this->_previuosPagesHeight = $newItem->calcAbsY;
                                    $headerContainerToInsert = $this->_insertPageBreak();
                                    if ($headerContainerToInsert) {
                                        $return[] = $headerContainerToInsert;
                                    } else {
                                        $newItem->pageBreakBefore = true;
                                    }
                                    unset($headerContainerToInsert);
                                }
                            }
                        }
                        
                        // Absolute Positionen ermitteln
                        $newItem->absX = $newItem->calcAbsX + $this->_marginLeft;
                        $newItem->absY = $newItem->calcAbsY - $this->_previuosPagesHeight + $this->_marginTop;

                        // falls das item gedreht ist, der Drehpunkt berechnen (selbe Distanz von den Koordinaten wie beim Abs)
                        if ($newItem->style->rotation && $newItem->style->rotation instanceof stdClass) {
                            $newItem->style->rotation->rotateX = $newItem->absX - ($newItem->calcAbsX - $newItem->style->rotation->rotateAbsX);
                            $newItem->style->rotation->rotateY = $newItem->absY - ($newItem->calcAbsY - $newItem->style->rotation->rotateAbsY);                            
                        }

                        $return[] = $newItem;
                        break;
                        
                }
            }
            
        }
        return $return;
    }

    /**
     * Wird ein Element rotiert, ändert sich Breite und Höhe
     * Hier wird die neue Grösse berechnet.
     * @param int $rotation
     * @param float $width
     * @param float $height
     * @return array
     */
    protected function _calcSizeIfRotated($rotation, $width, $height, $calcAbsX, $calcAbsY) {
        $multicellAbsX = null;
        $multicellAbsY = null;

        // bei cells wurde die Rotation bereits durch die Multicell berechnet
        if (is_object($rotation)) {
            $rotation = (int) $rotation->rotation;
            $multicellAbsX = $rotation->rotateAbsX;
            $multicellAbsY = $rotation->rotateAbsY;

        } else if (is_numeric($rotation)) {
            $rotation = (int) $rotation;
            
        } else {
            $rotation = 0;
        }
        
        $xDiff = 0;
        $yDiff = 0;

        // prüfen, dass die rotation zwischen 0-359 liegt
        if ($rotation >= 360 || $rotation < -360) {
            $rotation = 0;
        } else if ($rotation < 0 && $rotation >= -360) {
            $rotation = 360 + $rotation;
        }

        if ($rotation !== 0) {
            $h1 = sin(deg2rad($rotation)) * $width;
            $h2 = cos(deg2rad($rotation)) * $height;
            $w1 = cos(deg2rad($rotation)) * $width;
            $w2 = sin(deg2rad($rotation)) * $height;

            $width = abs($w1)+abs($w2);
            $height = abs($h1)+abs($h2);

            // offset zum X-Punkt rechnen
            $xDiff = 0;
            $yDiff = 0;

            if ($rotation <= 90) {
                $xDiff = 0;
                $yDiff = abs($h1);

            } else if ($rotation <= 180) {
                $xDiff = abs($w1);
                $yDiff = $height;
                
            } else if ($rotation <= 270) {
                $xDiff = $width;
                $yDiff = abs($h2);

            } else {
                $xDiff = abs($w2);
                $yDiff = 0;
            }
        }

        // Der Winkel sowie die Position des Drehpunkts speichern
        // Falls der Drehpunkt von der MultiCell bereits berechnet wurde,
        // übernehmen wir diesen von dort.
        $rStyle = new stdClass();
        $rStyle->rotation = $rotation;
        $rStyle->rotateAbsX = $multicellAbsX !== null ? $multicellAbsX : $calcAbsX + $xDiff;
        $rStyle->rotateAbsY = $multicellAbsY !== null ? $multicellAbsY : $calcAbsY + $yDiff;
        
        return array($rStyle, $width, $height, $xDiff, $yDiff);
    }


    /**
     * Klont ein Element (die untergeordneten items werden nicht geklont)
     * @param type $item
     * @return \stdClass 
     */
    protected function _cloneItem($item) {
        $newItem = new stdClass();
        if (isset($item->fn)) $newItem->fn = $item->fn;
        if (isset($item->x)) $newItem->x = $item->x;
        if (isset($item->x1)) $newItem->x1 = $item->x1;
        if (isset($item->x2)) $newItem->x2 = $item->x2;
        if (isset($item->y)) $newItem->y = $item->y;
        if (isset($item->y1)) $newItem->y1 = $item->y1;
        if (isset($item->y2)) $newItem->y2 = $item->y2;
        if (isset($item->width)) $newItem->width = $item->width;
        if (isset($item->height)) $newItem->height = $item->height;
        if (isset($item->text)) $newItem->text = $item->text;
        if (isset($item->html)) $newItem->html = $item->html;
        if (isset($item->hyperlink)) $newItem->hyperlink = $item->hyperlink;
        if (isset($item->style)) $newItem->style = $item->style;
        if (isset($item->keepTogether)) $newItem->keepTogether = $item->keepTogether;
        if (isset($item->pageBreak)) $newItem->pageBreak = $item->pageBreak;
        if (isset($item->pageBreakBefore)) $newItem->pageBreakBefore = $item->pageBreakBefore;
        if (isset($item->repeat)) $newItem->repeat = $item->repeat;
        if (isset($item->repeatEnd)) $newItem->repeatEnd = $item->repeatEnd;
        if (isset($item->file)) $newItem->file = $item->file;
        if (isset($item->pageNo)) $newItem->pageNo = $item->pageNo;
        if (isset($item->chartType)) $newItem->chartType = $item->chartType;
        if (isset($item->chartLabels)) $newItem->chartLabels = $item->chartLabels;
        if (isset($item->chartValues)) $newItem->chartValues = $item->chartValues;
        if (isset($item->chartProperties)) $newItem->chartProperties = $item->chartProperties;
        
        if (isset($item->componentId)) $newItem->componentId = $item->componentId;
        if (isset($item->xId)) $newItem->xId = $item->xId;
        if (isset($item->arguments)) $newItem->arguments = $item->arguments;
        
        if (isset($item->calcAbsX)) $newItem->calcAbsX = $item->calcAbsX;
        if (isset($item->calcAbsX1)) $newItem->calcAbsX1 = $item->calcAbsX1;
        if (isset($item->calcAbsX2)) $newItem->calcAbsX2 = $item->calcAbsX2;
        if (isset($item->calcAbsY)) $newItem->calcAbsY = $item->calcAbsY;
        if (isset($item->calcAbsY1)) $newItem->calcAbsY1 = $item->calcAbsY1;
        if (isset($item->calcAbsY2)) $newItem->calcAbsY2 = $item->calcAbsY2;
        
        if (isset($item->calcHeight)) $newItem->calcHeight = $item->calcHeight;
        if (isset($item->calcWidth)) $newItem->calcWidth = $item->calcWidth;

        // Formulare
        if (isset($item->form)) $newItem->form = $item->form;
        
        return $newItem;
    }
    
    protected function _getNextPageInnerHeight($orientation='', $size='', $margins='') {
        $headerContainerToInsert = null;
        
        // Stand der aktuelle Modulvariablen merken
        $prevCurrentContainer = $this->_currentContainer;
        $prevPageNo = $this->_pageNo;
        $prevPageWidth = $this->_pageWidth;
        $prevPageHeight = $this->_pageHeight;
        $prevMarginTop = $this->_marginTop;
        $prevMarginRight = $this->_marginRight;
        $prevMarginBottom = $this->_marginBottom;
        $prevMarginLeft = $this->_marginLeft;
        $prevPreviuosPagesHeight = $this->_previuosPagesHeight;
        $prevX = $this->_x;
        $prevY = $this->_y;

        
        if (!$margins) {
           $margins = $this->_settings->margins;
        }
        
        $this->_pageNo++;
        
        // Seitengrösse
        if (!$size && $this->_settings->size) {
            $size = $this->_settings->size;
        }

        if (!$size) {
            $size = 'a4';
        }
        $pageSize = $this->_getPageSize($size);
        
        if (!$orientation && $this->_settings->orientation) {
            $orientation = $this->_settings->orientation;
        }
        
        if ($orientation != 'L') {
            $orientation = 'P';
        }
        
        if ($orientation == 'L') {
            $this->_pageWidth = $pageSize[1];
            $this->_pageHeight = $pageSize[0];
        } else {
            $this->_pageWidth = $pageSize[0];
            $this->_pageHeight = $pageSize[1];
        }
        
        // Seitenränder
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
            if ($this->_pageNo == 1) {
                if (count($tmp)>4) $top = $tmp[4];
            }
        }
        
        $this->_marginTop = $top;
        $this->_marginRight = $right;
        $this->_marginBottom = $bottom;
        $this->_marginLeft = $left;
        $this->_setY(0);
        
        
        // Evtl. GroupHeadersToRepeat einfügen
        if (count($this->_groupHeadersToRepeat)>0) {
            // Container mit Group-Headers erstellen
            $headerContainerToInsert = new stdClass();
            $headerContainerToInsert->fn = 'container';
            $headerContainerToInsert->pageBreakBefore = true;
            $headerContainerToInsert->items = array();

            foreach ($this->_groupHeadersToRepeat as $header) {
                $headerContainerToInsert->items[] = $header;
            }
                        
            // Masse der Group-Headers berechnen
            $this->simplifyElements($headerContainerToInsert);
            $this->calcItemsSize($headerContainerToInsert);
            $this->calcMaxHeightItems($headerContainerToInsert);
            $headerContainerToInsert = $this->calcPagePosAndPageBreaks($headerContainerToInsert, 'groupHeaderToRepeat');
        }
        
        $return = $this->_pageHeight - $this->_marginTop - $headerContainerToInsert->calcHeight - $this->_marginBottom;
        
        // Modulvariablen wieder auf den vorherigen Stand zurücksetzen
        $this->_currentContainer = $prevCurrentContainer;
        $this->_pageNo = $prevPageNo;
        $this->_pageWidth = $prevPageWidth;
        $this->_pageHeight = $prevPageHeight;
        $this->_marginTop = $prevMarginTop;
        $this->_marginRight = $prevMarginRight;
        $this->_marginBottom = $prevMarginBottom;
        $this->_marginLeft = $prevMarginLeft;
        $this->_previuosPagesHeight = $prevPreviuosPagesHeight;
        $this->_x = $prevX;
        $this->_y = $prevY;
        
        return $return;
    }
    
    /**
     * Gibt die Seitengrösse in mm zurück
     * @param string $size
     * @return array
     */
    protected function _getPageSize($size) {
        $size = strtolower($size);
        // Seitengrösse
        if ($size == 'a1') return array(594, 841);
        if ($size == 'a2') return array(420, 594);
        if ($size == 'a3') return array(297, 420);
        if ($size == 'a4') return array(210, 297);
        if ($size == 'a5') return array(148.5, 210);
        if ($size == 'a6') return array(105, 148.5);
        if ($size == 'letter') return array(215.9, 279.4);
        if ($size == 'legal') return array(215.9, 355.6);

        // default: A4
        return array(210, 297);
    }   
    
    /**
     * Gibt die aktuelle calcX-Position bezüglich zum linken Containerrand zurück
     * @return single 
     */
    protected function _getX() {
        return $this->_x - $this->_currentContainer->absX;
    }
    
    /**
     * Gibt die aktuelle calcY-Position bezüglich zum oberen Containerrand zurück
     * @return single 
     */
    protected function _getY() {
        return $this->_y - $this->_currentContainer->absY;
    }
    
    /**
     * Fügt eine neue Seite hinzu
     * @param string $orientation
     * @param string $size
     * @param string $margins
     * @return null|object Falls Group-Headers eingefügt werden sollen, wird deren Container zurückgegeben
     */
    protected function _insertPageBreak($orientation='', $size='', $margins='', $skipGroupHeaders=false) {
        $headerContainerToInsert = null;
        
        if (!$margins) {
           $margins = $this->_settings->margins;
        }
        
        $this->_pageNo++;
        
        if (!$size && $this->_settings->size) {
            $size = $this->_settings->size;
        }
        
        // Seitengrösse
        if (!$size) {
            $size = 'a4';
        }
        $pageSize = $this->_getPageSize($size);
        
        if (!$orientation && $this->_settings->orientation) {
            $orientation = $this->_settings->orientation;
        }
        
        if ($orientation != 'L') {
            $orientation = 'P';
        }
        
        if ($orientation == 'L') {
            $this->_pageWidth = $pageSize[1];
            $this->_pageHeight = $pageSize[0];
        } else {
            $this->_pageWidth = $pageSize[0];
            $this->_pageHeight = $pageSize[1];
        }
        
        // Seitenränder
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
            if ($this->_pageNo == 1) {
                if (count($tmp)>4) $top = $tmp[4];
            }
        }
        
        $this->_marginTop = $top;
        $this->_marginRight = $right;
        $this->_marginBottom = $bottom;
        $this->_marginLeft = $left;
        $this->_setY(0);
        
        
        // Evtl. GroupHeadersToRepeat einfügen
        if (!$skipGroupHeaders && count($this->_groupHeadersToRepeat)>0) {
            // Container mit Group-Headers erstellen
            $headerContainerToInsert = new stdClass();
            $headerContainerToInsert->fn = 'container';
            $headerContainerToInsert->pageBreakBefore = true;
            $headerContainerToInsert->items = array();

            foreach ($this->_groupHeadersToRepeat as $header) {
                $headerContainerToInsert->items[] = $header;
            }
            
            // Stand der aktuelle Modulvariablen merken
            $prevCurrentContainer = $this->_currentContainer;
            $prevX = $this->_x;
            $prevY = $this->_y;
            $prevPageNo = $this->_pageNo;
            $prevMarginTop = $this->_marginTop;
            $prevMarginRight = $this->_marginRight;
            $prevMarginBottom = $this->_marginBottom;
            $prevMarginLeft = $this->_marginLeft;
            $prevPreviuosPagesHeight = $this->_previuosPagesHeight;
            
            // Masse der Group-Headers berechnen
            $this->simplifyElements($headerContainerToInsert);
            $this->calcItemsSize($headerContainerToInsert);
            $this->calcMaxHeightItems($headerContainerToInsert);
            $headerContainerToInsert = $this->calcPagePosAndPageBreaks($headerContainerToInsert, 'groupHeaderToRepeat');
            
            // Die nachfolgenden Elemente müssen nun weiter unten positioniert werden
            $prevPreviuosPagesHeight -= $headerContainerToInsert->calcHeight;
            
            // Modulvariablen wieder auf den vorherigen Stand zurücksetzen
            $this->_currentContainer = $prevCurrentContainer;
            $this->_x = $prevX;
            $this->_y = $prevY;
            $this->_pageNo = $prevPageNo;
            $this->_marginTop = $prevMarginTop;
            $this->_marginRight = $prevMarginRight;
            $this->_marginBottom = $prevMarginBottom;
            $this->_marginLeft = $prevMarginLeft;
            $this->_previuosPagesHeight = $prevPreviuosPagesHeight;
        }        
        return $headerContainerToInsert;
    }
    
    /**
     * Kombiniert mehrere Style-Objekte
     * Eigenschaften von hinteren Objekten im Array überschreiben die Eigenschaften von vorherigen Objekten
     * @param object|array $styles Style Objekt oder Array mit mehreren Style-Objekten
     */
    public function _joinStyles($styles) {
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
     * Macht aus einer MultiCell mehrere Cell
     * @param \stdClass $multiCell
     * @return array
     */
    protected function _makeCellsByMultiCell($multiCell) {
        $cells = array();
        $i = 0;
        $wordspacing = array();
        $lines = $this->_fpdf->kiGetMultiCellRows($multiCell->text, $multiCell->calcWidth, $multiCell->style, $wordspacing);
        $lineHeight = $multiCell->style->lineHeight ? $multiCell->style->lineHeight : 4;
        $mcBorder = strtoupper($multiCell->style->border);
        $mcHeight = isset($multiCell->height) ? $multiCell->height : null;
        $firstCell = true;

        // Falls eine Höhe angegeben wurde, die höher ist als alle Linien zusammen, 
        // wird eine leere Cell mit der Differenzhöhe eingefügt.
        $additionalCellForHeight = null;
        if ($mcHeight) {
            $additionalCellForHeight = $mcHeight - ($lineHeight * count($lines));
            if ($additionalCellForHeight > 0) {
                $lines[] = '';
            }
        }
        
        foreach ($lines as $k => $line) {
            $ws = array_key_exists($k, $wordspacing) ? $wordspacing[$k] : null;
            $lastCell = $i == (count($lines)-1);
            $cell = new stdClass();
            $cell->fn = 'cell';
            $cell->text = $line;
            $cell->style = clone $multiCell->style;
            $cell->style->wordspacing = $ws;
            $cell->x = (float) $multiCell->x;
            $cell->y = (float) $multiCell->y  + ($i * $lineHeight);
            $cell->calcAbsX = (float) $multiCell->calcAbsX;
            $cell->calcAbsY = (float) $multiCell->calcAbsY + ($i * $lineHeight);
            $cell->calcWidth = (float) $multiCell->calcWidth;
            $cell->calcHeight = (float) $lineHeight;

            if (isset($multiCell->width)) {
                $cell->width = (float) $multiCell->width;
            }
            if (isset($multiCell->height) && !$lastCell) {
                $cell->height = (float) $lineHeight;
            } else if (isset($multiCell->height) && $lastCell) {
                $cell->height = $additionalCellForHeight;
                $cell->style->lineHeight = $additionalCellForHeight;
            }

            $cell->style->border = '';
            if (($mcBorder == 1 || mb_strstr($mcBorder, 'T')) && $firstCell) {
                $cell->style->border .= 'T';
            }
            if (($mcBorder == 1 || mb_strstr($mcBorder, 'R'))) {
                $cell->style->border .= 'R';
            }
            if (($mcBorder == 1 || mb_strstr($mcBorder, 'L'))) {
                $cell->style->border .= 'L';
            }
            if (($mcBorder == 1 || mb_strstr($mcBorder, 'B')) && $lastCell) {
                $cell->style->border .= 'B';
            }
            if ($cell->style->border === '') {
                $cell->style->border = 0;
            }

            $i++;
            $firstCell = false;
            $cells[] = $cell;
            unset ($cell);
        }

        return $cells;
    }
    
    /**
     * Setzt die aktuelle calcX-Position relativ zum Container-Rand
     * @param single $x     minus-Wert = Abstand vom rechten Container-Rand
     */
    protected function _setX($x) {
        if ($x < 0 ) {
            $this->_x = $this->_currentContainer->absX + $this->_currentContainer->width + $x;
        } else {
            $this->_x = $this->_currentContainer->absX + $x;
        }
    }
    
    /**
     * Setzt die aktuelle calcY-Position relativ zum Container-Rand
     * @param single $y     minus-Wert = Abstand vom unteren Container-Rand (bei Fixer Container-Höhe) oder vom unteren Seiten-Rand (bei auto-Höhe)
     */
    protected function _setY($y) {
        if ($y >= 0) {
            $this->_y = $this->_currentContainer->absY + $y;
            
        // Negativwert ist nur beim Container der Fusszeile erlaubt (absoluter Wert zur Positionierung am unteren Seitenrand)
        } else {
            $this->_y = $this->_pageHeight + $y;
        }
    }
    
    /**
     * Ersetzt alle fields durch container mit multicells
     * @param array $items 
     */
    protected function _simplifyElementsRec($items) {
        if ($items) {
            foreach ($items as $item) {
                if (!isset($item->fn)) {
                    $item->fn = 'container';
                }
                
                switch ($item->fn) {
                    // Container
                    case 'container':
                        $x = isset($item->x) ? $item->x : $this->_getX();
                        $y = isset($item->y) ? $item->y : $this->_getY();
                        
                        if ($x < 0) {
                            $x = $this->_currentContainer->width + $x;
                        }
                        
                        $this->_setY($y);
                        $this->_setX($x);
                        
                        $item->calcAbsX = $this->_x;
                        $item->calcAbsY = $this->_y;
                        
                        $width = $item->width ? $item->width : $this->_currentContainer->width - $x;
                        
                        $parentContainer = $this->_currentContainer;
                        
                        $curCont = new stdClass();
                        $curCont->absX = $parentContainer->absX + $x;
                        $curCont->absY = $parentContainer->absY + $y;
                        $curCont->height = $item->height ? $item->height : null;  // null = wie Inhalt
                        $curCont->width = $width;
                        $curCont->keepTogether = !!$item->keepTogether;   // ganzer Container auf der gleichen Seite/Spalte anzeigen (eventueller Umbruch erfolgt vorher)
                        $curCont->defaultLabelPosition = isset($item->defaultLabelPosition) ? 
                                $item->defaultLabelPosition : $parentContainer->defaultLabelPosition;
                        $curCont->defaultLabelWidth = isset($item->defaultLabelWidth) ? 
                                $item->defaultLabelWidth : $parentContainer->defaultLabelWidth;
                        $curCont->defaultHideIfEmpty = isset($item->defaultHideIfEmpty) ? 
                                $item->defaultHideIfEmpty : $parentContainer->defaultHideIfEmpty;
                        $curCont->maxHeight = 0;
                        $this->_currentContainer = $curCont;
                        
                        $this->_setY(0);
                        $this->_setX(0);
                        
                        if ($item->items) {
                            $this->_simplifyElementsRec($item->items);
                        }
                        
                        $item->calcWidth = $width;
                        $item->calcHeight = $this->_currentContainer->maxHeight;
                        
                        $newY = $y + $item->calcHeight;
                        if ($newY > $parentContainer->maxHeight) {
                            $parentContainer->maxHeight = $newY;
                        }
                        
                        $this->_currentContainer = $parentContainer;
                        $this->_setY($newY);
                        $this->_setX(0);
                        
                        break;
                    
                    // Feld durch einen container mit MultiCells ersetzen
                    case 'field':
                        $item->fn = 'container';
                        $item->keepTogether = true;
                        $item->items = array();
                        
                        $item->hideIfEmpty = isset($item->hideIfEmpty) ? $item->hideIfEmpty : $this->_currentContainer->defaultHideIfEmpty;
                        $hidden = $item->hideIfEmpty && (is_null($item->value) || $item->value==='');
                        if (!$hidden) {
                            
                            $labelWidth = isset($item->labelWidth) ? $item->labelWidth : 
                                (isset($this->_currentContainer->defaultLabelWidth) ? $this->_currentContainer->defaultLabelWidth : 30);
                            // Label-Position ermitteln
                            $item->labelPosition = isset($item->labelPosition) ? $item->labelPosition : $this->_currentContainer->defaultLabelPosition;
                            if (!$item->labelPosition) {
                                $item->labelPosition = 'left';
                            }
                            
                            // Label einfügen
                            if ($item->labelPosition != 'none') {
                                $styles = array('label');
                                if (isset($item->labelStyle)) {
                                    if (is_array($item->labelStyle)) {
                                        $styles = array_merge($styles, $item->labelStyle);
                                    } else {
                                        $styles[] = $item->labelStyle;
                                    }
                                }
                                
                                $newItm = new stdClass();
                                $newItm->fn = 'multiCell';
                                $newItm->x = 0;
                                $newItm->y = 0;
                                if ($item->labelPosition == 'left') {
                                    $newItm->width = $labelWidth;
                                    if (isset($item->height)) {
                                        $newItm->height = 'max';
                                    }
                                }
                                $newItm->text = $item->label;
                                $newItm->style = $styles;
                                $item->items[] = $newItm;
                                unset($newItm, $styles);
                            }
                            
                            // Value einfügen
                            $styles = array('value');
                            if (isset($item->style)) {
                                if (is_array($item->style)) {
                                    $styles = array_merge($styles, $item->style);
                                } else {
                                    $styles[] = $item->style;
                                }
                            }
                            
                            $newItm = new stdClass();
                            $newItm->fn = 'multiCell';
                            if ($item->labelPosition == 'left') {
                                $newItm->x = $labelWidth;
                            } else {
                                $newItm->x = 0;
                            }
                            if ($item->labelPosition == 'left') {
                                $newItm->y = 0;
                            }
                            if (isset($item->height)) {
                                $newItm->height = 'max';
                            }
                            $newItm->text = $item->value;
                            $newItm->style = $styles;
                            $item->items[] = $newItm;
                            unset($newItm, $styles);
                        }
                        
                        // nicht mehr gebrauchte Eigenschaften löschen
                        unset($item->labelPosition);
                        unset($item->labelWidth);
                        unset($item->hideIfEmpty);
                        unset($item->label);
                        unset($item->value);
                        unset($item->style);
                        unset($item->labelStyle);
                        break;
                             
                }
            }
            
        }
    }

    /**
     * Macht aus einem HTML-Item mehrere cells.
     * @param \stdClass $html
     * @return array array von Cells
     */
    protected function _makeCellsByHtml($html) {
        $cells = $this->_fpdf->kiGetHtmlCells($html->html, $html->calcWidth);

        $newCells = array();
        foreach ($cells as $htmlCell) {

            $cell = new stdClass();
            if ($htmlCell->type == 'cell') {
                $cell->fn = 'cell';
                $cell->text = $htmlCell->text;
            }
            if ($htmlCell->type == 'image') {
                $cell->fn = 'image';
                $cell->file = $htmlCell->file;
            }

            $cell->style = $htmlCell->style;
            $cell->x = (float) $html->x + $htmlCell->x;
            $cell->y = (float) $html->y + $htmlCell->y;
            $cell->calcAbsX = (float) $html->calcAbsX + $htmlCell->x;
            $cell->calcAbsY = (float) $html->calcAbsY + $htmlCell->y;
            $cell->calcWidth = (float) $htmlCell->width;
            $cell->calcHeight = (float) $htmlCell->height;
            $cell->width = (float) $htmlCell->width;
            $cell->height = (float) $htmlCell->height;
            $cell->hyperlink = $htmlCell->hyperlink;
            $newCells[] = $cell;
            unset ($cell);
        }

        return $newCells;
    }



    protected function _splitHtmlElementsRec($items) {
        if ($items) {
            foreach ($items as &$item) {
                if (!isset($item->fn)) {
                    $item->fn = 'container';
                }
                
                switch ($item->fn) {
                    // Container
                    case 'container':
                        if ($item->items) {
                            $this->_splitHtmlElementsRec($item->items);
                        }
                        break;
                        
                        
                    // HTML
                    case 'html':
                        
                        // HTML-Code in einzelne Zeilen-Elemente unterteilen
                        if ($item->html) {
                            $html = $item->html;

                            $html = str_replace("\n", ' ', $html);
                            $html = str_replace("&nbsp;", ' ', $html);

                            // Aufteilen in P-Tags
                            $htmlElements = explode('<p', $html);
                            $first = true;
                            foreach ($htmlElements as &$el) {
                                if ($first) {
                                    $first = false;
                                } else {
                                    $el = '<p' . $el;
                                }
                            }
                            unset($el);

                            // Style-Objekt sichern für die neuen Html-Elemente
                            $itemStyle = $this->_joinStyles($item->style);

                            // Style-Objekt mit Margins für den Container erstellen
                            $containerStyle = new stdClass();
                            if ($itemStyle && $itemStyle->marginTop) {
                                $containerStyle->marginTop = $itemStyle->marginTop;
                                unset($itemStyle->marginTop);
                            }
                            if ($itemStyle && $itemStyle->marginBottom) {
                                $containerStyle->marginBottom = $itemStyle->marginBottom;
                                unset($itemStyle->marginBottom);
                            }

                            // HTML-Element in Container umwandeln
                            $item->fn = 'container';
                            $item->style = $containerStyle;
                            unset($item->html);
                            unset($containerStyle);

                            // HTML-Elemente erstellen und in den Container einfügen
                            $item->items = array();
                            foreach ($htmlElements as $el) {
                                $newItm = new stdClass();
                                $newItm->fn = 'html';
                                $newItm->width = $item->width;
                                $newItm->style = $itemStyle;
                                $newItm->html = $el;
                                $item->items[] = $newItm;
                                unset($newItm);
                            }
                            unset($el, $htmlElements);
                        }
                        break;
                }
            }
            unset($item);
        }
    }
    
}