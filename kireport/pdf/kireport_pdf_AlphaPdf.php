<?php
/**
 * Transparenz Addon für FPDF
 * http://fpdf.de/downloads/add-ons/transparency.html
 * Author: Martin Hall-May
 * This script gives transparency support. 
 * You can set the alpha channel from 0 (fully transparent) to 1 (fully opaque). 
 * It applies to all elements (text, drawings, images).
 */
require_once(dirname(dirname(__DIR__)) . '/kireport/pdf/fpdf17/fpdf.php');
require_once(dirname(dirname(__DIR__)) . '/kireport/pdf/fpdi/fpdi.php');

class kireport_pdf_AlphaPdf extends FPDI {
    protected $_extgstates = array();

    
    
    // -------------------------------------------------------
    // Public Methods
    // -------------------------------------------------------
    public function addExtGState($parms) {
        $n = count($this->_extgstates)+1;
        $this->_extgstates[$n]['parms'] = $parms;
        return $n;
    }

    public function setExtGState($gs) {
        $this->_out(sprintf('/GS%d gs', $gs));
    }
    
    // alpha: real value from 0 (transparent) to 1 (opaque)
    // bm:    blend mode, one of the following:
    //          Normal, Multiply, Screen, Overlay, Darken, Lighten, ColorDodge, ColorBurn, 
    //          HardLight, SoftLight, Difference, Exclusion, Hue, Saturation, Color, Luminosity
    public function setAlpha($alpha, $blendMode=Null) {
        if (!$alpha && $alpha !== 0 && $alpha !== '0') {
            $alpha = 1;
        }
        
        if (!$blendMode) {
            $blendMode = 'Normal';
        }
        
        // set alpha for stroking (CA) and non-stroking (ca) operations
        $gs = $this->addExtGState(array('ca'=>$alpha, 'CA'=>$alpha, 'BM'=>'/'.$blendMode));
        $this->setExtGState($gs);
    }
    
    
    
    // -------------------------------------------------------
    // Protected Methods
    // -------------------------------------------------------
    // overwrite
    // Ist in FPDI public und muss hier desshalb auch public sein
    public function _enddoc() {
        if (!empty($this->_extgstates) && $this->PDFVersion<'1.4') {
            $this->PDFVersion = '1.4';
        }
        parent::_enddoc();
    }
    
    // overwrite
    protected function _putresourcedict() {
        parent::_putresourcedict();
        $this->_out('/ExtGState <<');
        foreach ($this->_extgstates as $k=>$extgstate) {
            $this->_out('/GS'.$k.' '.$extgstate['n'].' 0 R');
        }
        $this->_out('>>');
    }

    // overwrite
    protected function _putresources() {
        $this->_putextgstates();
        parent::_putresources();
    }
    
    // overwrite
    protected function _putextgstates() {
        for ($i = 1; $i <= count($this->_extgstates); $i++) {
            $this->_newobj();
            $this->_extgstates[$i]['n'] = $this->n;
            $this->_out('<</Type /ExtGState');
            $parms = $this->_extgstates[$i]['parms'];
            $this->_out(sprintf('/ca %.3F', $parms['ca']));
            $this->_out(sprintf('/CA %.3F', $parms['CA']));
            $this->_out('/BM '.$parms['BM']);
            $this->_out('>>');
            $this->_out('endobj');
        }
    }
}
