<?php

require_once('pdf/kireport_pdf_Fpdf.php');
require_once('pdf/kireport_pdf_CalcPageBreaks.php');
require_once('general/kireport_general_Functions.php');
require_once('general/kireport_general_Interface.php');

class kireport_PDF {
    protected $_settings;
    protected $_args;
    protected $_rows;
    protected $_rowIndex = -1;
    protected $_currentDataRow;
    protected $_ioClass;
    protected $_fontDir = array();

    protected $_defaultStylesJsonString = '{
        "default":{
         "lineHeight":4.5,
         "lineWidth":0.2,
         "textColor":"#000",
         "drawColor":"#000",
         "rotation":0,
         "font":{
          "family":"Arial",
          "style":"",
          "size":10
         }
        },

        "line":{
         "drawColor":"#000",
         "lineWidth":0.2
        },

        "label":{
         "textColor":"#444",
         "font":{
          "style":"",
          "size":8
         }
        },
        "value":{
         "font":{
          "style":"",
          "size":10
         }
        },
        
        "tableHeader":{
         "border":"TB",
         "lineWidth":0.2,
         "fillColor":"#ddd",
         "marginBottom":0.2,
         "font":{
          "style":"B"
         }
        },
        "tableCell":{
         "fillColor":"#fff"
        },
        "tableCellPair":{
         "fillColor":"#eee"
        }
    }';
    
    // -------------------------------------------------------
    // Public Methods
    // -------------------------------------------------------
    public function createAcrobat($settings, $args=null, $rows=null, $filename='doc.pdf', $destination='I', $author='', $creator='kireport_PDF', $ioClass=Null) {
       
        if (!$settings) {
            throw new Exception('Argument $settings ist leer!');
        }
        
        $this->_settings = $settings;
        $this->_args = $args ? $args : array();
        $this->_rows = $rows ? $rows : array();
        $this->_ioClass = $ioClass;
        
        // Falls es sich um eine Kreuztabelle (pivot) handelt, müssen die Daten hier aufbereitet werden
        if ($this->_settings->pivot) {
            $this->_rows = kireport_general_Functions::convertDataForPivot($this->_rows, $this->_settings);
        }

        // Multipliziert Datensätze
        if (array_key_exists('multiplyDataRows', $this->_args)) {
            $this->_rows = $this->_multiplyDataRows($this->_rows);
        }

        // Fügt leere Datensätze am Anfang hinzu
        if (array_key_exists('emptyDataRows', $this->_args)) {
            $this->_rows = $this->_addEmptyRows($this->_rows);
        }
        
        // Falls es sich um ein mehrspaltiges Layout handelt, die Daten so aufbereiten, 
        // dass damit ein mehrspaltiges Layout gemacht werden kann
        if ($this->_settings->dataColumns) {
            $this->_rows = $this->_convertDataForMultiColumnLayout($this->_rows);
        }

        // PDF erstellen
        $pdf = new kireport_pdf_Fpdf($settings, $author, $creator, $this->_fontDir);
        
        // defaultStyles einfügen
        if (!$settings->styles) {
            $settings->styles = new stdClass();
        }
        $defaultStyles = json_decode($this->_defaultStylesJsonString);
        $settings->styles = kireport_general_Functions::joinSettings(array($defaultStyles, $settings->styles));
        
        // Falls das columns-array Spalten enthält (Tabellen-Bericht): die group- und detail-Einstellungen dafür generieren
        $this->_generateTableSettings();
        
        // Content Items aus reportHeader, details und reportFooter generieren
        $items = $this->_generateReportItemsFromRecordset();
         
        // Evtl. Subreporte einfügen
        $this->_insertSubreports($items);
        
        // Umbrüche rechnen und PDF generieren
        $this->_createLayout($pdf, $items);


        //die();
        
        // Dokument an den Browser zurückgeben
        return $pdf->Output($filename, $destination);
    }
    
    public function createAsSubreport($settings, $args, $rows, $hideIfNoData=true, $ioClass=Null) {
        $this->_settings = $settings;
        $this->_args = $args;
        $this->_rows = $rows;
        $this->_ioClass = $ioClass;

        if ($hideIfNoData && !$this->_rows) {
            return array();
        }
        
        // Falls es sich um eine Kreuztabelle (pivot) handelt, müssen die Daten hier aufbereitet werden
        if ($this->_settings->pivot) {
            $this->_rows = kireport_general_Functions::convertDataForPivot($this->_rows, $this->_settings);
        }

        // Multipliziert Datensätze
        if (array_key_exists('multiplyDataRows', $this->_args)) {
            $this->_rows = $this->_multiplyDataRows($this->_rows);
        }

        // Fügt leere Datensätze am Anfang hinzu
        if (array_key_exists('emptyDataRows', $this->_args)) {
            $this->_rows = $this->_addEmptyRows($this->_rows);
        }
        
        // Falls es sich um ein mehrspaltiges Layout handelt, die Daten so aufbereiten, 
        // dass damit ein mehrspaltiges Layout gemacht werden kann
        if ($this->_settings->dataColumns) {
            $this->_rows = $this->_convertDataForMultiColumnLayout($this->_rows);
        }
        
        // Falls das columns-array Spalten enthält (Tabellen-Bericht): die group- und detail-Einstellungen dafür generieren
        $this->_generateTableSettings();
        
        // Content Items aus reportHeader, details und reportFooter generieren
        $items = $this->_generateReportItemsFromRecordset();
        
        // Evtl. Subreporte einfügen
        $this->_insertSubreports($items);
        
        return $items;
    }
    
    public function replaceFieldPlaceholder($string) {
        return kireport_general_Functions::replaceFieldPlaceholder(
                $string, 
                $this->_currentDataRow, 
                $this->_settings->calcedFields, 
                $this->_args, 
                $this->_settings->title, 
                $this->_settings->subtitle
                );
    }

    /**
     * Fügt ein Verzeichnis hinzu, in dem Schriften abgelegt sind.
     * @param string $dir
     */
    public function addFontDir($dir) {
        if (mb_substr($dir, -1) === '/') {
            $dir = mb_substr($dir, 0, -1);
        }
        if (is_dir($dir)) {
            $this->_fontDir[] = $dir;
        }
    }
    
    
    // -------------------------------------------------------
    // Protected Methods
    // -------------------------------------------------------
    /**
     * Konvertiert ein Datenarray so um, dass ein Spaltenlayout gemacht werden
     * kann. 
     * Source Beispiel:                 ["name":"Müller"],["name":"Meier"],["name":"Moser"]
     * Ergebnis Beispiel bei 2 Spalten: ["name1":"Müller","name2":"Meier"],["name1":"Moser","name2":null]
     * Falls im report Gruppiert wird, wird sichergestellt, dass eine neue 
     * Gruppe immer mit einem neuen Datensatz beginnt. Deshalb werden die 
     * restlichen Spalten mit null-Werten aufgefüllt.
     * @param array $rows
     * @return array 
     */
    protected function _convertDataForMultiColumnLayout($rows) {
        $ret = array();
        $newRow = array();
        
        // Spaltenzahl ermitteln
        $colCount = (int) $this->_settings->dataColumns;
        
        // Gruppierungen ermitteln
        $groups = array();
        if ($this->_settings->groups) {
            foreach ($this->_settings->groups as $grp) {
                // Feldnamen ohne _Zahl ermitteln
                $tmp = explode('_', $grp->field);
                if (count($tmp)>1) {
                    array_pop($tmp);
                }
                $tmp = implode('_', $tmp);
                
                $grpNew = new stdClass();
                $grpNew->field = $tmp;
                $groups[] = $grpNew;
            }
        }
        $rowIndex = 0;
        $colIndex = 1;
        if ($colCount == 0) {
            return $rows;
        } else {
            
            while ($rowIndex < count($rows)) {
                
                // Spalten
                $newRow = array();
                $hasGroupChanged = false;
                for ($colIndex=1; $colIndex<=$colCount; $colIndex++) {
                    if ($rowIndex < count($rows)) {
                        $row = $rows[$rowIndex];
                        if (!$hasGroupChanged) {
                            // Hat die Gruppe geändert?
                            if (count($groups)>0) {
                                foreach ($groups as $grp) {
                                    if (!isset($grp->curVal) || $grp->curVal!==trim($row[$grp->field].'')){
                                        $grp->curVal = trim($row[$grp->field].'');
                                        if ($rowIndex > 0) {
                                            $hasGroupChanged = $colIndex>1;
                                        }
                                    }
                                }
                            }
                        }
                        if (!$hasGroupChanged) {
                            // Werte
                            foreach ($row as $key=>$val) {
                                $newRow[$key.'_'.$colIndex] = $val;
                            }
                            $rowIndex++;
                            
                        } else {
                            // restliche Spalten mit null-Werten auffüllen
                            foreach ($row as $key=>$val) {
                                $newRow[$key.'_'.$colIndex] = null;
                            }
                        }
                        
                    } else {
                        // restliche Spalten mit null-Werten auffüllen
                        foreach ($rows[0] as $key=>$val) {
                            $newRow[$key.'_'.$colIndex] = null;
                        }
                    }
                }
                $ret[] = $newRow;
                
            }
            
        }
        return $ret;
    }


    /**
     * Multipliziert Datensätze, z.B. wenn auf einem Etiketten-Bericht von
     * jeder Adresse mehrere sein sollen.
     * @param array $rows
     * @return array
     */
    protected function _multiplyDataRows($rows) {
        $newRows = array();
        
        // Zeilenzahl ermitteln
        $multiplyDataRows = (int) $this->_args['multiplyDataRows'];

        // Datensätze multiplizieren
        foreach ($rows as $row) {
            for ($i=0; $i<$multiplyDataRows; $i++) {
                $newRows[] = $row;
            }
        }
        
        return $newRows;
    }


    /**
     * Fügt leere Datensätze an den Anfang der Rows hinzu
     * @param array $rows
     * @return array
     */
    protected function _addEmptyRows($rows) {
        $newRows = array();
        $emptyRow = array();
        $emptyDataRows = (int) $this->_args['emptyDataRows'];

        if (count($rows)>0) {
            // Falls rows vorhanden sind, keys übernehmen
            if (array_key_exists(0, $rows)) {
                foreach (array_keys($rows[0]) as $key) {
                    $emptyRow[$key] =  '';
                }
            }

            for ($i=0; $i<$emptyDataRows; $i++) {
                $newRows[] = $emptyRow;
            }
        }

        // die neuen Zeilen werden den bestehenden vorgesetzt.
        return array_merge($newRows, $rows);
    }


    protected function _createLayout(kireport_pdf_Fpdf $pdf, $items) {
        
        // Umbrüche rechnen
        $calcPB = new kireport_pdf_CalcPageBreaks($pdf, $this->_settings);

        // content
        $calcPB->simplifyElements($items);                  // Ersetzt alle fields durch container mit multicells.
        $calcPB->splitHtmlElements($items);                 // Ersetzt grosse HTML-Felder durch mehrere kleinere HTML-Felder, so dass Zeilenumbrüche möglich werden.
        $calcPB->calcItemsSize($items);                     // Berechnet die Grössen der Einzelnen Elemente (calcAbsX, calcAbsY, calcWidth und calcHeight).
        $calcPB->calcMaxHeightItems($items);                // Items mit der Eigenschaft height='max' bekommen durch diese Funktion nun die richtige Höhe .
        $calcPB->replaceHtmlByCells($items);                // Ersetzt HTML durch cells
        $calcPB->replaceMultiCellByCells($items);           // Ersetzt MultiCells durch Cells. Dadurch wird ein Zeilenumbruch innerhalb von Multicells möglich.
        $items = $calcPB->calcPagePosAndPageBreaks($items); // Fügt die Seitenumbrüche ein und berechnet die Positionen zum linken oberen Seitenrand.

        // Weitere Elemente mit items im Setting
        $itemElements = array(
            'pageHeaderFirstPair',
            'pageHeaderFirst',
            'pageHeaderPair',
            'pageHeader',
            'pageFooterFirstPair',
            'pageFooterFirst',
            'pageFooterPair',
            'pageFooter'
        );

        foreach ($itemElements as $itemElement) {
            if (isset($this->_settings->{$itemElement})) {
                $this->_replaceHeaderFooterPlaceholders($this->_settings->{$itemElement});
                $calcPB->simplifyElements($this->_settings->{$itemElement});
                $calcPB->calcItemsSize($this->_settings->{$itemElement});
                $calcPB->calcMaxHeightItems($this->_settings->{$itemElement});
                $calcPB->replaceHtmlByCells($this->_settings->{$itemElement});
                $calcPB->replaceMultiCellByCells($this->_settings->{$itemElement});
                $this->_settings->{$itemElement} = $calcPB->calcPagePosAndPageBreaks($this->_settings->{$itemElement}, 'pageHeaderFooter');
            }
        }
        unset ($itemElements, $itemElement);

        unset($calcPB);
        $pdf->kiAddPage('', '', '0 0 0 0 0'); // die Seitenränder-Funktion von FPDF-brauchen wir nicht
        $pdf->kiInsertItemsFromArray($items);
    }
    
    protected function _generateReportItemsFromRecordset() {
        $return = array();
     
        // Report Header
        if ($this->_settings->reportHeader) {
            $row = $this->_rows && count($this->_rows)>0 ? $this->_rows[0] : array();
            $this->_currentDataRow = $row;
            $this->_rowIndex = 0;
            
            $newItem = $this->_settings->reportHeader;
            $newItem->items = $this->_generateReportItemsFromRecordsetRec($newItem->items);
            $return[] = $newItem;
        }
        
        // Pointer setzen
        // Der Zeiger $pointer zeigt auf die aktuelle Einfügeposition(Array) innerhalb des Objektmodells $return, 
        // das aus Arrays und stdClass-Objekten besteht.
        // Pro Gruppe existiert auch ein Pointer, der auf die aktuelle Einfügeposition innerhalb der Gruppe zeigt.
        $pointer = &$return;
        

        // Daten einfügen
        if (isset($this->_rows)) {
            $previousRow = null;
            $pair = true;
            $this->_rowIndex = -1;
            $rowIndexPerGroup = -1;
            foreach ($this->_rows as $row) {
                $this->_currentDataRow = $row;
                $this->_rowIndex++;
                $rowIndexPerGroup++;

                // Gerechnete-Felder (erstellt zusätzliche Felder aus der Eigenschaft 'calcedFields'
                if ($this->_settings->calcedFields) {
                    foreach ($this->_settings->calcedFields as &$cFld) {
                        
                        $baseValue = null;
                        
                        if (isset($cFld->fn)) {
                            switch ($cFld->fn) {
                                case 'concat':
                                    $separator = $cFld->data && $cFld->data->separator ? $cFld->data->separator : '; ';
                                    $values = $cFld->data->values;
                                    $baseValue = '';
                                    foreach ($values as $v) {
                                        $value = new stdClass();
                                        $value->value = '';
                                        $value->label = '';
                                        if (is_object($v)) {
                                            $value->value = (string) $v->value;
                                            $value->label = $v->label ? (string) $v->label : '';
                                        } else {
                                            $value->value = (string) $v;
                                        }
                                        $value->value = trim($this->replaceFieldPlaceholder(strval($value->value)));
                                        if($baseValue && $value->value) $baseValue .= $separator;
                                        if ($value->value) $baseValue .= $value->label . $value->value;
                                    }
                                
                                default:
                                    if ($this->_ioClass) {
                                        $baseValue = $this->_ioClass->executeCalcedFieldFunction($this, $cFld);
                                    }
                                    break;
                            }
                        } else {
                            $baseValue = $this->replaceFieldPlaceholder($cFld->baseValue);
                        }
                        
                        if (isset($cFld->aggregate)) {
                            
                            // Gruppierungen durchgehen und evtl. die Summen zurücksetzen
                            if (isset($cFld->resetOnGroup)) {
                                $groupValueHasChanged = false;
                                if ($this->_settings->groups) {
                                    foreach ($this->_settings->groups as $group) {
                                        if ($groupValueHasChanged || !isset($group->curVal) || $group->curVal!==trim($row[$group->field].'')) {
                                            $groupValueHasChanged = true;
                                            if ($group->field === $cFld->resetOnGroup) {
                                                $cFld->value = 0;
                                                if ($cFld->aggregate === 'AVG') {
                                                    $cFld->sum = 0;
                                                    $cFld->count = 0;
                                                }
                                                if ($cFld->aggregate === 'COUNT DISTINCT') {
                                                    $cFld->countedValues = array();
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            
                            if (!isset($cFld->value)) {
                                $cFld->value = 0;
                            }

                            switch ($cFld->aggregate) {
                                case 'SUM': $cFld->value += is_numeric($baseValue) ? $baseValue : 0; break;
                                case 'COUNT': $cFld->value += ($baseValue ? 1 : 0); break;
                                case 'COUNT DISTINCT': 
                                    if (!isset($cFld->countedValues)) {
                                        $cFld->countedValues = array();
                                    }
                                    if ($baseValue && !in_array($baseValue, $cFld->countedValues)) {
                                        $cFld->value++;
                                        $cFld->countedValues[] = $baseValue;
                                    }                             
                                    break;
                                case 'MIN': $cFld->value = ($baseValue<$cFld->value ? $baseValue : $cFld->value); break;
                                case 'MAX': $cFld->value = ($baseValue>$cFld->value ? $baseValue : $cFld->value); break;
                                case 'AVG': 
                                    if (!isset($cFld->sum)) {
                                        $cFld->sum = 0;
                                    }
                                    $cFld->sum += $baseValue;

                                    if (!isset($cFld->count)) {
                                        $cFld->count = 0;
                                    }
                                    $cFld->count ++;
                                    
                                    $cFld->value = $cFld->sum / $cFld->count;
                                    break;
                            }
                            
                        } else {
                            $cFld->value = $baseValue;
                        }
                        
                        $row[$cFld->name] = $cFld->value;
                    }
                }
                
      
                
                // Gruppierungen
                if ($this->_settings->groups) {
                    
                    // Welche Gruppen haben geändert?
                    $groupValueHasChanged = false;   // Falls eine Gruppe angezeigt wird, müssen auch alle untergeordneten Gruppen angezeigt werden
                    foreach ($this->_settings->groups as $group) {

                        // Group Headers einfügen
                        if ($groupValueHasChanged || !isset($group->curVal) || $group->curVal!==trim($row[$group->field].'')) {
                            $group->valueHasChanged = true;
                            $groupValueHasChanged = true;
                        } else {
                            $group->valueHasChanged = false;
                        }
                    }

                        
                    // Eventuell noch den Footer für die vorherige Gruppe einfügen
                    for ($i=count($this->_settings->groups)-1; $i>=0; $i--) {
                        $group = $this->_settings->groups[$i];
                        
                        // Falls die Gruppe keepTogether=x hatte und nun bereits x-Datensätze/Subgruppen im Container sind, 
                        // den Container abschliessen und den Pointer auf den vorherigen Contianer setzen
                        if ($group->remainingRecordsInThisContainer) {
                            if ($group->remainingRecordsInThisContainer === true) {
                                $group->remainingRecordsInThisContainer = 1;
                            }
                            
                            // Bei der letzten Gruppe die Datensätze zählen
                            if ($i==count($this->_settings->groups)) {
                                $group->remainingRecordsInThisContainer--;
                                if ($group->remainingRecordsInThisContainer<=0) {
                                    $group->pointer = &$group->origPointer;
                                    $pointer = &$group->origPointer;
                                    unset($group->remainingRecordsInThisContainer, $group->origPointer);
                                }

                            // bei den anderen Gruppen die Subgruppen zählen
                            } else {
                                $subgroup = $this->_settings->groups[$i+1];
                                if ($subgroup->curVal!==trim($row[$subgroup->field].'')) {
                                    $group->remainingRecordsInThisContainer--;
                                    if ($group->remainingRecordsInThisContainer<=0) {
                                        $group->pointer = &$group->origPointer;
                                        $pointer = &$group->origPointer;
                                        unset($group->remainingRecordsInThisContainer, $group->origPointer);
                                    }
                                }
                                unset($subgroup);
                            }

                            // Falls die Gruppe zuende ist: den Container auch abschliessen
                            if ($group->remainingRecordsInThisContainer && $group->valueHasChanged) {
                                $group->pointer = &$group->origPointer;
                                $pointer = &$group->origPointer;
                                unset($group->remainingRecordsInThisContainer, $group->origPointer);
                            }

                        }


                        if ($group->isCurrentGroup && $group->valueHasChanged) {
                            unset($group->isCurrentGroup);

                            if (isset($group->footer)) {
                                $this->_currentDataRow = $previousRow;
                                $this->_rowIndex--;
                                $newItem = $this->_generateReportItemsFromRecordsetRec($group->footer);
                                $pointer[] = $newItem;
                                $this->_currentDataRow = $row;
                                $this->_rowIndex++;

                                // Pointer wieder auf die übergeordnete Gruppe setzen
                                if ($i > 0) {
                                    $pointer = &$this->_settings->groups[$i-1]->pointer;
                                } else {
                                    $pointer = &$return;
                                }
                                // Pointer löschen (Gruppe ist abgeschlossen)
                                unset($group->pointer);
                            }
                        }
                    }

                    // Gruppierungen
                    $previousGroup = null;
                    foreach ($this->_settings->groups as $group) {

                        // Group Headers einfügen
                        if ($group->valueHasChanged) {
                            $group->curVal = trim($row[$group->field].'');
                            $group->isCurrentGroup = isset($group->footer);

                            // ganze Gruppe in einen Container packen
                            $newItem = new stdClass();
                            if (isset($group->fn)) $newItem->fn = $group->fn;
                            if (isset($group->x)) $newItem->x = $group->x;
                            if (isset($group->y)) $newItem->y = $group->y;
                            if (isset($group->width)) $newItem->width = $group->width;
                            if (isset($group->keepTogether)) $newItem->keepTogether = $group->keepTogether===true;
                            if (isset($group->style)) $newItem->style = $group->style;

                            // Falls der Header auf jeder Seite wiederholt werden soll: nur bis zu diesem Marker wiederholen
                            if (isset($group->header) && $group->header->repeat) {
                                $newItem->repeatEnd = true;
                            }

                            $newItem->items = array();
                            if ($previousGroup) {
                                $previousGroup->pointer[] = $newItem;
                            } else {
                                $return[] = $newItem;
                            }

                            // Pointer setzen
                            $group->pointer = &$newItem->items;
                            $pointer = &$newItem->items;

                            // Header einfügen
                            if (isset($group->header)) {
                                // Falls keepTogether=1 ist, dann den Header mit den nächsten x Datensätzen/Subgruppen in einen 
                                // zusätzlichen Container packen
                                if (isset($group->keepTogether) && is_int($group->keepTogether)) {
                                    $newItem = new stdClass();
                                    $newItem->fn = 'container';
                                    $newItem->keepTogether = true;
                                    $newItem->items = array();
                                    $pointer[] = $newItem;

                                    // Pointer setzen
                                    $group->origPointer = &$group->pointer;
                                    $group->pointer = &$newItem->items;  // Array merken, in das die weiteren Items dann eingefügt werden
                                    $group->remainingRecordsInThisContainer = $group->keepTogether;

                                    $pointer = &$newItem->items;
                                }

                                // Header einfügen
                                $newItem = $this->_generateReportItemsFromRecordsetRec($group->header);
                                if (!isset($newItem->fn)) $newItem->fn = 'container';

                                // Falls der Header auf jeder Seite wiederholt werden soll, den Header als Vorlage merken
                                if ($group->header->repeat) {
                                    $newItem->repeat = $this->_generateReportItemsFromRecordsetRec($group->header);
                                    if (!isset($newItem->repeat->fn)) $newItem->repeat->fn = 'container';
                                }

                                $pointer[] = $newItem;
                                
                                $rowIndexPerGroup = 0;
                            }
                        }

                        $previousGroup = $group;
                    }
                }

                // Detail einfügen
                if ($this->_settings->detail  && $this->_settings->detail->items) {
                    // Kopie des Detail-Objekts pro Datensatz machen
                    $newItem = new stdClass();
                    if (isset($this->_settings->detail->fn)) $newItem->fn = $this->_settings->detail->fn;
                    if (isset($this->_settings->detail->x)) $newItem->x = $this->_settings->detail->x;
                    // Der Erste Detail-Datensatz pro Gruppe kann auch in der Y-Achse positioniert werden
                    if ($rowIndexPerGroup == 0) {
                        if (isset($this->_settings->detail->y)) $newItem->y = $this->_settings->detail->y;
                    }
                    if (isset($this->_settings->detail->width)) $newItem->width = $this->_settings->detail->width;
                    if (isset($this->_settings->detail->keepTogether)) $newItem->keepTogether = $this->_settings->detail->keepTogether;
                    if (isset($this->_settings->detail->reportName)) $newItem->reportName = $this->_settings->detail->reportName;
                    if (isset($this->_settings->detail->args)) $newItem->args = $this->_settings->detail->args;
                    if (isset($this->_settings->detail->hideIfNoData)) $newItem->hideIfNoData = $this->_settings->detail->hideIfNoData;
                    
                    if (isset($this->_settings->detail->pageBreak)) {
                        // Seitenumbruch beim ersten Datensatz nicht machen
                        if ($this->_settings->detail->pageBreak=='before' && $this->_rowIndex != 0) {
                            $newItem->pageBreak = 'before';
                            
                        // ausser, wenn er forciert wird
                        } elseif ($this->_settings->detail->pageBreak=='beforeForced') {
                            $newItem->pageBreak = 'before';
                        }
                    }
                    
                    if (isset($this->_settings->detail->defaultLabelPosition)) $newItem->defaultLabelPosition = $this->_settings->detail->defaultLabelPosition;
                    if (isset($this->_settings->detail->defaultLabelWidth)) $newItem->defaultLabelWidth = $this->_settings->detail->defaultLabelWidth;
                    if (isset($this->_settings->detail->defaultHideIfEmpty)) $newItem->defaultHideIfEmpty = $this->_settings->detail->defaultHideIfEmpty;
                    
                    if ($pair && isset($this->_settings->detail->stylePair)) {
                        $newItem->style = $this->_settings->detail->stylePair;
                    } else {
                        if (isset($this->_settings->detail->style)) $newItem->style = $this->_settings->detail->style;
                    }
                    $newItem->items = $this->_generateReportItemsFromRecordsetRec($this->_settings->detail->items, $pair);
                    $pointer[] = $newItem;
                }

                $previousRow = $row;
                $pair = !$pair;
            }
        
            // Evtl. noch die letzten Group-Footer einfügen
            $this->_currentDataRow = $previousRow;
            $this->_rowIndex--;
            if ($this->_currentDataRow && $this->_settings->groups) {
                $previousGroup = null;
                for ($i=count($this->_settings->groups)-1; $i>=0; $i--) {
                    $group = $this->_settings->groups[$i];

                    // Eventuell noch den Footer für die vorherige Gruppe einfügen
                    if ($group->isCurrentGroup) {
                        unset($group->isCurrentGroup);

                        if (isset($group->footer)) {
                            $newItem = $this->_generateReportItemsFromRecordsetRec($group->footer);
                            if (!isset($newItem->fn)) $newItem->fn = 'container';
                            $pointer[] = $newItem;

                            // Pointer wieder auf die übergeordnete Gruppe setzen
                            if ($i > 0) {
                                $pointer = &$this->_settings->groups[$i-1]->pointer;
                            } else {
                                $pointer = &$return;
                            }
                            // Pointer löschen (Gruppe ist abgeschlossen)
                            unset($group->pointer);
                        }

                    }
                    $previousGroup = $group;
                }
            }

            unset($pointer);
        }

        // Report Footer
        if ($this->_settings->reportFooter) {
            $newItem = $this->_settings->reportFooter;
            if (!isset($newItem->fn)) $newItem->fn = 'container';
            $newItem->items = $this->_generateReportItemsFromRecordsetRec($newItem->items);
            $return[] = $newItem;
        }

        return $return;
    }
    
    protected function _generateReportItemsFromRecordsetRec($items, $pair=false) {
        $return = array();
        $returnAsArray = true;
        
        if (!is_array($items)) {
            $items = array($items);
            $returnAsArray = false;
        }
        
        foreach ($items as $item) {
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
            
            if (isset($item->keepTogether)) $newItem->keepTogether = !!$item->keepTogether;
            
            if (isset($item->defaultLabelPosition)) $newItem->defaultLabelPosition = $item->defaultLabelPosition;
            if (isset($item->defaultLabelWidth)) $newItem->defaultLabelWidth = $item->defaultLabelWidth;
            if (isset($item->defaultHideIfEmpty)) $newItem->defaultHideIfEmpty = $item->defaultHideIfEmpty;
            
            if (isset($item->labelPosition)) $newItem->labelPosition = $item->labelPosition;
            if (isset($item->labelWidth)) $newItem->labelWidth = $item->labelWidth;
            if (isset($item->hideIfEmpty)) $newItem->hideIfEmpty = $item->hideIfEmpty;
            
            if (isset($item->text)) $newItem->text = $item->text;
            if (isset($item->html)) $newItem->html = $this->replaceFieldPlaceholder($item->html);
            if (isset($item->label)) $newItem->label = $item->label;
            if (isset($item->value)) $newItem->value = $this->replaceFieldPlaceholder($item->value);
            if (isset($item->form)) {
                $newItem->form = $item->form;
                if (is_object($newItem->form) && is_string($newItem->form->value)) {
                    $newItem->form->value = $this->replaceFieldPlaceholder($newItem->form->value);
                }
            }

            if (isset($item->chartType)) $newItem->chartType = $item->chartType;
            if (isset($item->chartLabels)) $newItem->chartLabels = $this->replaceFieldPlaceholder($item->chartLabels);
            if (isset($item->chartValues)) $newItem->chartValues = $this->replaceFieldPlaceholder($item->chartValues);
            if (isset($item->chartProperties)) $newItem->chartProperties = $item->chartProperties;
           
            if (isset($item->file)) $newItem->file = $this->replaceFieldPlaceholder($item->file);

            if (isset($item->pageNo)) $newItem->pageNo = $this->replaceFieldPlaceholder($item->pageNo);
            if (isset($item->hyperlink)) $newItem->hyperlink = $this->replaceFieldPlaceholder($item->hyperlink);

            if (isset($item->xId)) $newItem->xId = $this->replaceFieldPlaceholder($item->xId);
            if (isset($item->componentId)) $newItem->componentId = $this->replaceFieldPlaceholder($item->componentId);
            if (isset($item->arguments)) $newItem->arguments = $this->replaceFieldPlaceholder($item->arguments);
            
            if (isset($item->reportName)) $newItem->reportName = $this->replaceFieldPlaceholder($item->reportName);
            if (isset($item->hideIfNoData)) $newItem->hideIfNoData = $this->replaceFieldPlaceholder($item->hideIfNoData);
            
            if (isset($item->pageBreak)) {
                // Seitenumbruch vorher beim ersten Datensatz nicht machen
                if ($this->replaceFieldPlaceholder($item->pageBreak)=='before' && $this->_rowIndex != 0) {
                    $newItem->pageBreak = 'before';
                    
                // ausser, wenn er forciert wird
                } elseif ($this->replaceFieldPlaceholder($item->pageBreak)=='beforeForced') {
                    $newItem->pageBreak = 'before';
                }
            }
            
            if (isset($item->args)) {
                $newItem->args = array();
                $newItem->args['format'] = 'acrobat';
                foreach ($item->args as $argName => $argVal) {
                    $newItem->args[$argName] = $this->replaceFieldPlaceholder($argVal);
                }
            }
            
            if ($pair && isset($item->stylePair)) {
                $newItem->style = $item->stylePair;
            } else {
                if (isset($item->style)) $newItem->style = $item->style;
            }

            // conditionStyles (werden nur angewandt, wenn die Bedingung=true ist
            if (isset($item->conditionStyles)) {
                $styles = array();
                if (isset($newItem->style)) {
                    if (is_array($newItem->style)) {
                        $styles = $newItem->style;
                    } else {
                        $styles[] = $newItem->style;
                    }
                }
                
                if (is_object($item->conditionStyles)) {
                    $item->conditionStyles = array($item->conditionStyles);
                }
                
                foreach ($item->conditionStyles as $style) {
                    $cond = false;
                    if (isset($style->condition)) {
                        $cond = !!$this->replaceFieldPlaceholder($style->condition);
                    }
                    if ($cond && isset($style->style)) {
                        $styles[] = $style->style;
                    }
                }
                
                $newItem->style = $styles;
                unset($styles, $style);
            }
            
            if (isset($item->labelStyle)) $newItem->labelStyle = $item->labelStyle;
            
            // conditionLabelStyles (werden nur angewandt, wenn die Bedingung=true ist
            if (isset($item->conditionLabelStyles)) {
                $styles = array();
                if (isset($newItem->labelStyle)) {
                    if (is_array($newItem->labelStyle)) {
                        $styles = $newItem->labelStyle;
                    } else {
                        $styles[] = $newItem->labelStyle;
                    }
                }
                
                if (is_object($item->conditionLabelStyles)) {
                    $item->conditionLabelStyles = array($item->conditionLabelStyles);
                }
                
                foreach ($item->conditionLabelStyles as $style) {
                    $cond = false;
                    if (isset($style->condition)) {
                        $cond = !!$this->replaceFieldPlaceholder($style->condition);
                    }
                    if ($cond && isset($style->style)) {
                        $styles[] = $style->style;
                    }
                }
                
                $newItem->labelStyle = $styles;
                unset($styles, $style);
            }
            
            $hidden = false;
            if (isset($item->hidden)) {
                $hidden = !!$this->replaceFieldPlaceholder($item->hidden);
            }
            if (!$hidden) {
                if (isset($item->items)) {
                    $newItem->items = $this->_generateReportItemsFromRecordsetRec($item->items, $pair);
                }

                $return[] = $newItem;
            }
            
        }

        return $returnAsArray ? $return : $return[0];
    }
    

    /**
     * Erstellt aus dem Columns-Array einen Tabellarischen Bericht
     */
    protected function _generateTableSettings() {
        if (isset($this->_settings->columns) && !isset($this->_settings->detail)) {
            // group
            if (!isset($this->_settings->groups)) {
                $this->_settings->groups = array();
            }
            $group = new stdClass();
            $group->fn = 'container';
            $group->field = '#Feld_das_es_nicht_gibt#'; // Wenn das Feld nicht existiert, wird nur eine Gruppe am Anfang erstellt
            $group->header = new stdClass();
            $group->header->keepTogether = 1;
            $group->header->repeat = true;
            $group->header->defaultHideIfEmpty = false;
            $group->header->items = array();
            
            // detail
            $this->_settings->detail = new stdClass();
            $this->_settings->detail->fn = 'container';
            $this->_settings->detail->items = array();
        
            $x = 0;
            foreach ($this->_settings->columns as $column) {

                // hide hidden columns
                if ($column->hidden === true) {
                    continue;
                }
                
                // headerStyle
                $styles = array('tableHeader');
                if (isset($column->headerStyle)) {
                    if (is_array($column->headerStyle)) {
                        $styles = array_merge($styles, $column->headerStyle);
                    } else {
                        $styles[] = $column->headerStyle;
                    }
                }
                
                // group header
                $item = new stdClass();
                $item->fn = 'multiCell';
                $item->x = $x;
                $item->y = 0;
                $item->height = 'max';
                $item->width = $column->width;
                $item->text = $column->label;
                $item->style = $styles;
                $group->header->items[] = $item;
                
                unset($styles);
                
                
                // cellStyle
                $styles = array('tableCell');
                if (isset($column->cellStyle)) {
                    if (is_array($column->cellStyle)) {
                        $styles = array_merge($styles, $column->cellStyle);
                    } else {
                        $styles[] = $column->cellStyle;
                    }
                }
                
                // cellStylePair
                $stylesPair = array('tableCell','tableCellPair');
                if (isset($column->cellStyle)) {
                    if (is_array($column->cellStyle)) {
                        $stylesPair = array_merge($stylesPair, $column->cellStyle);
                    } else {
                        $stylesPair[] = $column->cellStyle;
                    }
                }
                if (isset($column->cellPairStyle)) {
                    if (is_array($column->cellPairStyle)) {
                        $stylesPair = array_merge($stylesPair, $column->cellPairStyle);
                    } else {
                        $stylesPair[] = $column->cellPairStyle;
                    }
                }
                
                // conditionStyles
                $conditionStyles = $column->conditionStyles ? $column->conditionStyles : null;
                
                // detail
                $item = new stdClass();
                $item->fn = 'field';
                $item->x = $x;
                $item->y = 0;
                $item->width = $column->width;
                $item->height = 'max';
                $item->labelPosition = 'none';
                $item->hideIfEmpty = false;
                $item->value = $column->value;
                $item->style = $styles;
                $item->stylePair = $stylesPair;
                $item->conditionStyles = $conditionStyles;
                $this->_settings->detail->items[] = $item;
                
                unset($styles, $stylesPair);
                
                $x += $column->width;
            }
            
             // als erste Gruppe einfügen
            $this->_settings->groups = array_merge(array($group), $this->_settings->groups);
        }
    }
    
    protected function _insertSubreports($items) {
        if (!is_array($items)) {
            $items = array($items);
        }
        
        if ($items) {
            foreach ($items as $item) {
                if (!isset($item->fn)) {
                    $item->fn = 'container';
                }
                
                switch ($item->fn) {
                    // Container einfügen
                    case 'container':
                        // Untergeordnete Items durchgehen
                        if ($item->items) {
                            $this->_insertSubreports($item->items);
                        }
                        break;
                    
                    // subreport
                    case 'subreport':
                        if ($this->_ioClass && isset($item->reportName)) {
                            
                            if (!isset($item->args)) {
                                $item->args = array();
                            }
                                
                            $hideIfNoData = true;
                            if (isset($item->hideIfNoData)) {
                                $hideIfNoData = !!$item->hideIfNoData;
                            }
                            
                            $reportData = $this->_ioClass->getPdfSubreportData($this, $item->reportName, $item->args);
                            
                            if ($reportData) {
                                $subReport = new kireport_PDF();
                                $item->items = $subReport->createAsSubreport($reportData->settings, $reportData->args, $reportData->rows, $hideIfNoData, $this->_ioClass);
                                unset($subReport);
                            }
                        }
                        
                        // In einen Container verwandeln und nicht mehr gebrauchte Eigenschaften löschen
                        $item->fn = 'container';
                        unset($item->reportName, $item->args, $item->hideIfNoData);
                        break;
                }
            }
        }
    }
    
    protected function _replaceHeaderFooterPlaceholders($items) {
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
                        if ($item->items) {
                            $this->_replaceHeaderFooterPlaceholders($item->items);
                        }
                        break;
                    
                    // Textbausteine ersetzen
                    case 'field':
                        if (isset($item->value)) {
                            $item->value = $this->replaceFieldPlaceholder($item->value);
                        }
                        break;
                        
                    case 'pdf':
                        if (isset($item->pageNo)) {
                            $item->pageNo = $this->replaceFieldPlaceholder($item->pageNo);
                        }
                        break;
                        
                }
            }
        }
    }
    
}
