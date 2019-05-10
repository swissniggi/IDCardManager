<?php

class kireport_general_Functions {
    
    // -------------------------------------------------------
    // Datum-Funktionen
    // -------------------------------------------------------
    /**
     * Addiert oder subtrahiert Tage, Monate oder Jahre zu/von einem Datum
     * @param date $date
     * @param string $interval Bsp: '+1 days', '-2 months', '+3 years'
     * @return date
     */
    public static function dateAdd($date, $interval) {
        return strtotime(date("Y-m-d", $date)." ".$interval);
    }

    /**
     * Formatiert ein(e) angegebene(s) Ortszeit/Datum in Deutsch
     * @param string $format    Beispiel: 'Y-m-d H:i:s'
     * @param int|string $timestamp default = time()
     * @return string Gibt einen formatierten String anhand eines vorzugebenden Musters zurück.
     * Dabei wird entweder der angegebene Timestamp oder die gegenwärtige Zeit berücksichtigt,
     * wenn kein Timestamp angegegeben wird. Mit anderen Worten ausgedrückt: der Parameter Timestamp
     * ist optional und falls dieser nicht angegeben wird, wird der Wert der Funktion time() angenommen.
     */
    public static function dateFormatDe($format='', $timestamp=null) {
        if ($format=='') {
            $format = 'd.m.Y';
        }
        if (is_null($timestamp)) {
            $timestamp = time();
        } else if (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        } else {
            $timestamp = (int) $timestamp;
        }
        $return = date($format, $timestamp);
        

        // Monatsnamen übersetzen
        if (strpos($format, 'F')!==false) {
            $return = str_replace(
                    array('January', 'February', 'March', 'May', 'June', 'July', 'October', 'December'),
                    array('Januar', 'Februar', 'März', 'Mai', 'Juni', 'Juli', 'Oktober', 'Dezember'),
                    $return);
        // Kurzform
        } elseif (strpos($format, 'M')!==false) {
            $return = str_replace(
                    array('Mar', 'May', 'Oct', 'Dec'),
                    array('Mär', 'Mai', 'Okt', 'Dez'),
                    $return);
        }

        // Wochentage übersetzen
        if (strpos($format, 'l')!==false) {
            $return = str_replace(
                    array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                    array('Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'),
                    $return);
        // Kurzform
        } elseif (strpos($format, 'D')!==false) {
            $return = str_replace(
                    array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'),
                    array('Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'),
                    $return);
        }

        return $return;
    }

    /**
     * Formatiert ein(e) angegebene(s) Ortszeit/Datum in Englisch
     * @param string $format    Beispiel: 'Y-m-d H:i:s'
     * @param int|string $timestamp default = time()
     * @return string Gibt einen formatierten String anhand eines vorzugebenden Musters zurück.
     * Dabei wird entweder der angegebene Timestamp oder die gegenwärtige Zeit berücksichtigt,
     * wenn kein Timestamp angegegeben wird. Mit anderen Worten ausgedrückt: der Parameter Timestamp
     * ist optional und falls dieser nicht angegeben wird, wird der Wert der Funktion time() angenommen.
     */
    public static function dateFormatEn($format='', $timestamp=null) {
        if ($format=='') {
            $format = 'd.m.Y';
        }
        if (is_null($timestamp)) {
            $timestamp = time();
        } else if (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        } else {
            $timestamp = (int) $timestamp;
        }
        $return = date($format, $timestamp);

        return $return;
    }

    /**
     * Formatiert ein(e) angegebene(s) Ortszeit/Datum in Französisch
     * @param string $format    Beispiel: 'Y-m-d H:i:s'
     * @param int|string $timestamp default = time()
     * @return string Gibt einen formatierten String anhand eines vorzugebenden Musters zurück.
     * Dabei wird entweder der angegebene Timestamp oder die gegenwärtige Zeit berücksichtigt,
     * wenn kein Timestamp angegegeben wird. Mit anderen Worten ausgedrückt: der Parameter Timestamp
     * ist optional und falls dieser nicht angegeben wird, wird der Wert der Funktion time() angenommen.
     */
    public static function dateFormatFr($format='', $timestamp=null) {
        if ($format=='') {
            $format = 'd.m.Y';
        }
        if (is_null($timestamp)) {
            $timestamp = time();
        } else if (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        } else {
            $timestamp = (int) $timestamp;
        }
        $return = date($format, $timestamp);
        

        // Monatsnamen übersetzen
        if (strpos($format, 'F')!==false) {
            $return = str_replace(
                    array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
                    array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'),
                    $return);
        // Kurzform
        } elseif (strpos($format, 'M')!==false) {
            $return = str_replace(
                    array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'),
                    array('JAN', 'FÉV', 'MAR', 'AVR', 'MAI', 'JUN', 'JUL', 'AOÛ', 'SEP', 'OCT', 'NOV', 'DÉC'),
                    $return);
        }

        // Wochentage übersetzen
        if (strpos($format, 'l')!==false) {
            $return = str_replace(
                    array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                    array('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'),
                    $return);
        // Kurzform
        } elseif (strpos($format, 'D')!==false) {
            $return = str_replace(
                    array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'),
                    array('Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di'),
                    $return);
        }

        return $return;
    }
        
    
    // -------------------------------------------------------
    // Mathematische Funktionen
    // -------------------------------------------------------
    // Rundet eine Zahl
    // $round: Auf 5er Runden:0.05 auf 10er Runden:0.1 auf Franken:1
    public static function round($number, $round) {
        if ($round) {
            $number = round($number/$round) * $round;
        }
        return $number;
    }
    

    
    // -------------------------------------------------------
    // Andere Funktionen
    // -------------------------------------------------------
    /**
     * Erstelle aus einem Recordset eine Kreuztabelle
     * 
     * Beispiel Konfiguration:
     * "pivot":{                    // Kann auch ein Array mit mehreren Pivots sein
     *  "groupByField":"addressId"  // Feld nach dem Gruppiert wird. Bei Grupppierung über mehrere Felder: ["addressId", "languageId"]
     *  "titleField":"Kategorie",   // Die Überschriften der zusätzlichen Spalten befinden sich in diesem Feld
     *  "valueField":"KategorieId", // Werte die verglichen werden (meistens das gleiche Feld wie bei "titleField"
     *  "sortField":"Kategorie",    // Sortierung der Spaltenreihenfolge
     *  "aggregate":"COUNT",        // Welcher Wert wird in der Zelle angezeigt. Es ist auch Array mit mehreren möglich (VALUE, X, COUNT, MIN, MAX, SUM oder AVG)
     *  "columnPosition":"last",    // Position an der die Spalte im columns-Array eingefügt wird. 1=erste Spalte, "last"=am Schluss, Null oder 0=Manuell
     *  "valueSeparator":", ",      // Trennzeichen, das verwendet wird, wenn mehrere Values ins selbe Feld kommen (default: '; ')
     *  "notCountValue":"0"         // Werte, die für die Funktionen X oder COUNT als false gewertet wird. Default: ['0', 0, false, null]
     * }
     * 
     * Beispiel Ursprungs-Recordset:
     * addressId    KategorieId Kategorie
     * 1            1           Mitglieder
     * 1            1           Mitglieder
     * 1            2           Kunden
     * 1            3           Besucher
     * 2            1           Mitglieder
     * 2            3           Besucher
     * 3            2           Kunden
     * 
     * Beispiel Ergebnis-Recordset:
     * addressId    kiCol_0_title   kiCol_0_COUNT   kiCol_1_title   kiCol_1_COUNT   kiCol2_title    kiCol2_COUNT
     * 1            Mitglieder      2               Kunden          1               Besucher        1
     * 2            Mitglieder      1               Kunden          0               Besucher        1
     * 3            Mitglieder      0               Kunden          1               Besucher        0
     * 
     * Beispieldarstellung im Report:
     * addressId    Mitglieder  Kunden  Besucher
     * 1            2           1       1
     * 2            1           0       1
     * 3            0           1       0
     * 
     * @param array $rows
     * @param object $settings
     * @return array
     */
    public static function convertDataForPivot($rows, $settings) {
        $ret = null;
        $newCols = array();
        $newColsSort = array();
        $index = null;
        $groupStr = '';
        $lastGroupStr = '';
        $newRow = null;
        
        if (!is_array($settings->pivot)) {
            $settings->pivot = array($settings->pivot);
        }
        
        foreach ($settings->pivot as &$pivot) {
            $ret = array();

            // notCountValue ermitteln (Werte, die bei X oder COUNT als false gewertet werden)
            $notCountValue = array('0', 0, false, null);
            if (property_exists($pivot, 'notCountValue')) {
                if (is_array($pivot->notCountValue)) {
                    $notCountValue = $pivot->notCountValue;
                } else {
                    $notCountValue = array($pivot->notCountValue);
                }
            }
            
            // Gruppierungen ermitteln
            $groups = $pivot->groupByField;
            if (!is_array($groups)) {
                $groups = array($groups);
            }
            
            // zusätzliche Spalten ermitteln
            $index = 0;
            foreach ($rows as &$row) {
                if ($row[$pivot->titleField] !== '' && $row[$pivot->titleField] !== NULL) {
                    if (!in_array($row[$pivot->titleField], $newCols)) {
                        $newCols[] = $row[$pivot->titleField];
                        $newColsSort[] = $row[$pivot->sortField] . '_' . $index;
                    }
                }
                $index ++;
            }
            unset($row, $index);
            
            // zusätzliche Spalten sortieren
            if ($newCols) {
                $newColsUnsorted = $newCols;
                $newCols = array();
                                
                $newColsSortUnsorted = $newColsSort;
                
                sort($newColsSort);
                foreach($newColsSort as $col) {
                    $index = array_search($col, $newColsSortUnsorted);
                    $newCols[] = $newColsUnsorted[$index];
                }
                
                unset($newColsUnsorted, $newColsSortUnsorted, $col, $index);
            }
            
            
            // Aggregat-Funktionen ermitteln
            if (!is_array($pivot->aggregate)) {
                $pivot->aggregate = array($pivot->aggregate);
            }
            $aggVALUE   = in_array('VALUE', $pivot->aggregate);
            $aggX       = in_array('X', $pivot->aggregate);
            $aggCOUNT   = in_array('COUNT', $pivot->aggregate);
            $aggMIN     = in_array('MIN', $pivot->aggregate);
            $aggMAX     = in_array('MAX', $pivot->aggregate);
            $aggSUM     = in_array('SUM', $pivot->aggregate);
            $aggAVG     = in_array('AVG', $pivot->aggregate);
            
            // Neues Recordset und Array mit Keys schreiben
            foreach ($rows as &$row) {
                
                $groupStr = '';
                foreach ($groups as &$grp) {
                    $groupStr .= '||ki||' . $row[$grp];
                }
                unset($grp);
                $groupStr .= '||ki||';
                
                // Neue Gruppe
                if (!$lastGroupStr || $lastGroupStr !== $groupStr) {
                    unset($newRow);
                    
                    // Zeile übernehmen
                    $newRow = $row;
                    $ret[] = &$newRow;
                    
                    // zusätzliche Spalten einfügen
                    foreach ($newCols as $index => $col) {
                        // Titel
                        $newRow['kiCol_' . $index . '_title'] = $col;
                        // Wert (einer für jede gewünschte Aggregat-Funktion)
                        if ($aggVALUE) {
                            $newRow['kiCol_' . $index . '_VALUE'] = '';
                        }
                        if ($aggX) {
                            $newRow['kiCol_' . $index . '_X'] = '';
                        }
                        if ($aggCOUNT || $aggAVG) {
                            $newRow['kiCol_' . $index . '_COUNT'] = 0;
                        }
                        if ($aggMIN) {
                            $newRow['kiCol_' . $index . '_MIN'] = NULL;
                        }
                        if ($aggMAX) {
                            $newRow['kiCol_' . $index . '_MAX'] = NULL;
                        }
                        if ($aggSUM || $aggAVG) {
                            $newRow['kiCol_' . $index . '_SUM'] = 0;
                        }
                        if ($aggAVG) {
                            $newRow['kiCol_' . $index . '_AVG'] = 0;
                        }
                    }
                    unset($col, $index);
                    $lastGroupStr = $groupStr;
                }
                
                
                // Werte einfügen
                foreach ($newCols as $index => $col) {
                    if ($row[$pivot->titleField] !== $col) {
                        continue;
                    }

                    $value = $row[$pivot->valueField];
                    $number = is_numeric($value) ? floatval($value) : 0;

                    // als VALUE aggregieren
                    if ($value && $aggVALUE) {
                        $valueSeparator = property_exists($pivot, 'valueSeparator') && is_string($pivot->valueSeparator) ? $pivot->valueSeparator : '; ';
                        if ($newRow['kiCol_' . $index . '_VALUE']) {
                            $newRow['kiCol_' . $index . '_VALUE'] .= $valueSeparator;
                        }
                        $newRow['kiCol_' . $index . '_VALUE'] .= $value;
                    }

                    // X in Spalte setzen
                    if ($aggX && !in_array($value, $notCountValue, true)) {
                        $newRow['kiCol_' . $index . '_X'] = 'X';
                    }

                    // Zählen
                    if (($aggCOUNT || $aggAVG) && !in_array($value, $notCountValue, true)) {
                        $newRow['kiCol_' . $index . '_COUNT']++;
                    }

                    // Rechnen nur, wenn Value eine Zahl ist.
                    if (is_numeric($value)) {
                        // Minimum Wert
                        if ($aggMIN) {
                            if ($newRow['kiCol_' . $index . '_MIN'] === NULL || $number < $newRow['kiCol_' . $index . '_MIN']) {
                                $newRow['kiCol_' . $index . '_MIN'] = $number;
                            }
                        }

                        // Maximum Wert
                        if ($aggMAX) {
                            if ($newRow['kiCol_' . $index . '_MAX'] === NULL || $number > $newRow['kiCol_' . $index . '_MAX']) {
                                $newRow['kiCol_' . $index . '_MAX'] = $number;
                            }
                        }

                        // Summe
                        if ($aggSUM || $aggAVG) {
                            $newRow['kiCol_' . $index . '_SUM'] += $number;
                        }
                        // AVG kann erst am Schluss berechnet werden
                    }
                    
                }
                unset($col, $index, $value, $number);
                
                
            }
            unset($row, $newRow);
            
            // Evtl. die neuen Zeilen nochmal durchgehen um den AVG (Durchschnitt) zu berechnen
            if ($aggAVG) {
                foreach ($ret as &$newRow) {
                    foreach ($newCols as $index => $col) {
                        if ($newRow['kiCol_' . $index . '_COUNT'] === 0) {
                            $newRow['kiCol_' . $index . '_AVG'] = NULL;
                        } else {
                            $newRow['kiCol_' . $index . '_AVG'] = $newRow['kiCol_' . $index . '_SUM'] / $newRow['kiCol_' . $index . '_COUNT'];
                        }
                    }
                }
                unset($newRow);
            }
            
            $rows = $ret;
            
            
            // Neue Spalten automatisch im Bericht einfügen
            if ($settings->columns && $pivot->columnPosition) {
                $pos = 0;
                $newColsJson = array();
                
                foreach ($newCols as $index => $col) {
                    if ($aggVALUE) {
                        $newColJson = new stdClass();
                        $newColJson->label = $rows[0]['kiCol_' . $index . '_title'];
                        $newColJson->value = '[kiCol_' . $index . '_VALUE]';
                        $newColsJson[] = $newColJson;
                    }
                    if ($aggX) {
                        $newColJson = new stdClass();
                        $newColJson->label = $rows[0]['kiCol_' . $index . '_title'];
                        $newColJson->value = '[kiCol_' . $index . '_X]';
                        $newColsJson[] = $newColJson;
                    }
                    if ($aggCOUNT) {
                        $newColJson = new stdClass();
                        $newColJson->label = $rows[0]['kiCol_' . $index . '_title'];
                        $newColJson->value = '[kiCol_' . $index . '_COUNT]';
                        $newColsJson[] = $newColJson;
                    }
                    if ($aggMIN) {
                        $newColJson = new stdClass();
                        $newColJson->label = $rows[0]['kiCol_' . $index . '_title'];
                        $newColJson->value = '[kiCol_' . $index . '_MIN]';
                        $newColsJson[] = $newColJson;
                    }
                    if ($aggMAX) {
                        $newColJson = new stdClass();
                        $newColJson->label = $rows[0]['kiCol_' . $index . '_title'];
                        $newColJson->value = '[kiCol_' . $index . '_MAX]';
                        $newColsJson[] = $newColJson;
                    }
                    if ($aggSUM) {
                        $newColJson = new stdClass();
                        $newColJson->label = $rows[0]['kiCol_' . $index . '_title'];
                        $newColJson->value = '[kiCol_' . $index . '_SUM]';
                        $newColsJson[] = $newColJson;
                    }
                    if ($aggAVG) {
                        $newColJson = new stdClass();
                        $newColJson->label = $rows[0]['kiCol_' . $index . '_title'];
                        $newColJson->value = '[kiCol_' . $index . '_AVG]';
                        $newColsJson[] = $newColJson;
                    }
                }

                if ($pivot->columnPosition === 'last') {
                    $pos = count($settings->columns) + 1;
                } else {
                    $pos = intval($pivot->columnPosition);
                }
                if ($pos) {
                    array_splice($settings->columns, $pos-1, 0, $newColsJson);
                }
                
            }
            
        }
        unset($pivot);
        
        return $ret;
    }
    

    /**
     * Klont eine stdClass rekursiv, also auch alle Unterobjekte
     * @param stdClass $stdClass
     * @return \stdClass
     * @throws Exception
     */
    public static function recursiveCloneStdClass($stdClass) {
        if (!($stdClass instanceof stdClass)) {
            throw new Exception('invalid argument for clone stdClass');
        }
        $clone = clone $stdClass;
        foreach (get_object_vars($clone) as $prop => $val) {
            if ($clone->{$prop} instanceof stdClass) {
                $clone->{$prop} = self::recursiveCloneStdClass($clone->{$prop});
            }
            if (is_array($clone->{$prop})) {
                foreach ($clone->{$prop} as &$subVal) {
                    if ($subVal instanceof stdClass) {
                        $subVal = self::recursiveCloneStdClass($subVal);
                    }
                }
                unset ($subVal);
            }
        }        
        return $clone;
    }

    /**
     * Kombiniert mehrere Setting-Objekte
     * Eigenschaften von hinteren Objekten im Array überschreiben die Eigenschaften von vorherigen Objekten
     * @param object|array $settings Objekt oder Array mit mehreren Setting-Objekten
     * @return object|array|string|number|boolean|null 
     */
    public static function joinSettings($settings) {
        $return = new stdClass();

        if (!is_array($settings)) {
            $settings = array($settings);
        }
        
        foreach ($settings as $setting) {
            if ($setting) {
                foreach ($setting as $key => $val) {
                    if (is_object($val) && isset($return->$key) && is_object($return->$key)) {
                        $return->$key = self::joinSettings(array($return->$key, $val));
                    } elseif (is_array($val) && is_array($return->$key)) {
                        $return->$key  = array_merge($return->$key, $val);
                    } elseif (is_array($val)) {
                        $return->$key  = array_merge($val);
                    } elseif (isset($val)) {
                        $return->$key  = $val;
                    }
                }
            }
        }
        
        return $return;
    }
    
    
    public static function replaceFieldPlaceholder($string, &$currentDataRow, &$calcedFields, &$args, $title, $subtitle) {
        // Zuerst evtl. vorhandene Kalkulationen berechnen. Diese müssen zwischen {{...}} stehen.
        $string = preg_replace_callback(
            "/\{\{([^\}]*)\}\}/i",
            function($matches) use (&$currentDataRow, &$calcedFields, &$args, $title, $subtitle) {
                return kireport_general_Functions::_getCalc_CallFromPregReplace($matches, $currentDataRow, $calcedFields, $args, $title, $subtitle);
            },
            $string
        );
        
        // Parameter ersetzen, die nicht innerhalb einer Kalkulation stehen
        $string = preg_replace_callback(
            "/#([A-Za-z0-9\-_äöüÄÖÜ]*)#/i",
            function($matches) use (&$args, $title, $subtitle) {
                return kireport_general_Functions::_getParameter_CallFromPregReplace($matches, $args, $title, $subtitle, false);
            }, 
            $string
        );
        
        // Felder ersetzen, die nicht innerhalb einer Kalkulation stehen
        $string = preg_replace_callback(
            "/\[([A-Za-z0-9\-_äöüÄÖÜ]*)\]/i", 
            function($matches) use (&$currentDataRow, &$calcedFields) {
                return kireport_general_Functions::_getField_CallFromPregReplace($matches, $currentDataRow, $calcedFields, false);
            },
            $string
        );
        
        // Formatierungen ersetzen
        $string = preg_replace_callback(
            "/(boolean|date|number|isTrue|isFalse)\(([^\(\)\|]*)\|?([^\)]*)?\|?([^\)]*)?\)/i",
            function($matches) {
                return kireport_general_Functions::_getFormat_CallFromPregReplace($matches);
            },
            $string
        );
        
        $string = str_replace('KI_not_exist_KI', '', $string);
        
        return $string;
    }
    

    
    // -------------------------------------------------------
    // Public Methods, aufgerufen von preg_replace_callback in replaceFieldPlaceholder
    // -------------------------------------------------------
    // Workaround: In einigen PHP-Versionen funktioniert das self::... in den callback-Functions nicht, deshalb werden die Funktionen anstatt
    //             mit self::... mit kireport_general_Functions::... aufgerufen. Deswegen müssen sie public sein.
    public static function _getCalc_CallFromPregReplace($matches, &$currentDataRow, &$calcedFields, &$args, $title, $subtitle) {
        $string = $matches[1];
        
        // Parameter ersetzen und dabei sicherstellen, dass der Rückgabewert immer eine Zahl ist
        $string = preg_replace_callback(
            "/#([A-Za-z0-9\-_äöüÄÖÜ]*)#/i",
            function($matches) use (&$args, $title, $subtitle) {
                return kireport_general_Functions::_getParameter_CallFromPregReplace($matches, $args, $title, $subtitle, true);
            },
            $string
        );
        
        // Felder ersetzen und dabei sicherstellen, dass der Rückgabewert immer eine Zahl ist
        $string = preg_replace_callback(
            "/\[([A-Za-z0-9\-_äöüÄÖÜ]*)\]/i", 
            function($matches) use (&$currentDataRow, &$calcedFields) {
                return kireport_general_Functions::_getField_CallFromPregReplace($matches, $currentDataRow, $calcedFields, true);
            },
            $string
        );
        
        // Rechnung in String ausrechnen
        try {
            $string = eval("return {$string};");
        } catch (Throwable $t) { // PHP 7
            throw new Exception('Fehler bei der Berechnung der Formel '. $string . '. Meldung: '.$t->getMessage(), $t->getCode(), $t);
        } catch (Exception $e) { // PHP 5
            throw new Exception('Fehler bei der Berechnung der Formel '. $string . '. Meldung: '.$e->getMessage(), $e->getCode(), $e);
        }
        
        return $string;
    }
    
    public static function _getField_CallFromPregReplace($matches, &$currentDataRow, &$calcedFields, $asNumeric) {
        $field = $matches[1];
        $value = '';
        $exist = false;
        
        // Zuerst schauen, ob das Feld im Recordset ist
        if ($currentDataRow && array_key_exists($field, $currentDataRow)) {
            $value = $currentDataRow[$field];
            $exist = true;
            
        // sonst schauen, ob es sich um ein gerechnetes Feld handelt
        } else if ($calcedFields) {
            foreach ($calcedFields as &$cFld) { 
                if ($field === $cFld->name) {
                    $value = $cFld->value;
                    $exist = true;
                    break;
                }
            }
        }
        
        // Falls das Feld nicht existiert: nichts ersetzen
        if (!$exist) {
            $value = 'KI_not_exist_KI[' . $field . ']';
        }
        
        // Evtl. zwingend ein Numerischer Wert zurückgeben
        if ($asNumeric) {
            if (is_numeric($value)) {
                $value = (float) $value;
            } else {
                $value = 0;
            }
        }
        
        return $value;
    }

    public static function _getFormat_CallFromPregReplace($matches) {
        $format = $matches[1];
        $value = $matches[2];
        $arg = $matches[3];
        $return = '';
        
        $exist = strpos($value, 'KI_not_exist_KI') === false;
        if (!$exist) {
            $value = str_replace('KI_not_exist_KI', '', $value);
        }
        
        if ($exist) {
            switch ($format) {
                case 'boolean': $return = self::_formatBoolean($value, $arg); break;
                case 'date': $return = self::_formatDate($value, $arg); break;
                case 'number': $return = self::_formatNumber($value, $arg); break;
                case 'isTrue': $return = self::_formatIsTrue($value); break;
                case 'isFalse': $return = self::_formatIsFalse($value); break;
            }
        } else {
            // Falls der Parameter nicht existiert und es sich um ein isTrue oder isFalse Format handelt: false als Wert annehmen
            if ($format=='isTrue' || $format=='isFalse') {
                $return = false;

            // Falls der Parameter nicht existiert: nichts anpassen
            } else {
                $return = $value;
                if (mb_strlen($arg)>0) {
                    $return .= '|' . $arg;
                }
                if (mb_strlen($format)>0) {
                    $return = $format . '(' . $return . ')';
                }
            }
        }
        
        return $return;
    }

    public static function _getParameter_CallFromPregReplace($matches, &$args, $title, $subtitle, $asNumeric) {
        $param = $matches[1];
        $value = '';
        
        if ($param=='title' && $title !== null) {
            $value = $title;
        } elseif ($param=='subtitle' && $subtitle !== null) {
            $value = $subtitle;
        } elseif ($param=='curDate') {
            $value = date('Y-m-d H:i:s');
        } elseif ($args && isset($args[$param])) {
            $value = $args[$param];
        } else {
            // Falls der Parameter nicht existiert: nichts ersetzen
            $value = '';
            //$value = 'KI_not_exist_KI#' . $param . '#';
        }
        
        // Evtl. zwingend ein Numerischer Wert zurückgeben
        if ($asNumeric) {
            if (is_numeric($value)) {
                $value = (float) $value;
            } else {
                $value = 0;
            }
        }
        
        return $value;
    }
    
    // -------------------------------------------------------
    // Private Methods
    // -------------------------------------------------------
    /**
     * Formatiert einen boolschen Wert zur Ausgabe
     * @param float $value
     * @param string $format    Beispiel: "Ja|Nein"     (optional)
     *                                    1. Text für true
     *                                    2. Text für false
     * @return string 
     */
    private static function _formatBoolean($value, $format='') {
        $true = 'Ja';
        $false= 'Nein';
        
        if ($format) {
            $args = explode('|', $format);
            if (count($args) > 0) $true = $args[0];
            if (count($args) > 1) $false = $args[1];
        }
        
        if ($value!=='' && !is_null($value)) {
            return $value ? $true : $false;
        } else {
            return $value;
        }
    }   
    
    
    /**
     * Formatiert ein SQL-Datum zur Ausgabe
     * @param string $sqlDate
     * @param string $format    Beispiel: 'Y-m-d H:i:s|fr'    Mögliche Sprachen: 'de', 'en' oder 'fr' (keine = 'de')
     * @return string
     */
    private static function _formatDate($sqlDate, $format='') {
        $language = '';
        
        if ($format) {
            $args = explode('|', $format);
            if (count($args) > 0) $format = $args[0];
            if (count($args) > 1) $language = $args[1];
        }
        
        // SQL-Date '2010-05-30' oder '2010-05-30 08:00:00'
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}( [0-9]{2}:[0-9]{2}:[0-9]{2})?$/', $sqlDate)) {
            switch ($language) {
                case 'en': return self::dateFormatEn($format, $sqlDate);
                case 'fr': return self::dateFormatFr($format, $sqlDate);
                default: return self::dateFormatDe($format, $sqlDate);
            }
        }
        return null;
    }

    private static function _formatIsFalse($value) {
        return $value ? false : true;
    }
    
    private static function _formatIsTrue($value) {
        return $value ? true : false;
    }
    
    /**
     * Formatiert eine Nummer zur Ausgabe
     * @param float $number
     * @param string $format    Beispiel: "0|.|'"     (optional)
     *                                    1. Anzahl Stellen, die angezeigt werden sollen ''=auto (default='') (optional)
     *                                    2. Dezimaltrennzeichen (default='.') (optional)
     *                                    3. Tausendertrennzeichen (default="'") (optional)
     *                                    4. Runden (default="0") (optional) Auf 5er Runden:0.05 auf 10er Runden:0.1 auf Franken:1
     *                                    5. 0-Werte ausblenden (default="0") (optional) 1=ein: ersetzt '0' durch '' 0=aus
     * @return string 
     */
    private static function _formatNumber($number, $format='') {
        $decimals = '';
        $dec_point= '.';
        $thousands_sep = "'";
        $round = 0;
        $hideZero = 0;
        
        // Workaround: Hochkommas werden automatisch mit einem \ maskiert. Darum nehmen wir hier die \ wieder raus.
        $format = str_replace('\\', '', $format);

        if (is_string($format) && $format !== '') {
            $args = explode('|', $format);
            if (count($args) > 0) $decimals = $args[0];
            if (count($args) > 1) $dec_point = $args[1];
            if (count($args) > 2) $thousands_sep = $args[2];
            if (count($args) > 3) $round = $args[3];
            if (count($args) > 4) $hideZero = $args[4];
        }

        // evtl. 0-Werte ausblenden
        if ($hideZero && $number == 0) {
            return '';
        }
        
        // Runden z.B. auf 5er (round=0.05) oder 10er (round=0.1)
        if ($round) {
            $number = round($number/$round) * $round;
        }
        
        // Bei $decimals=='' automatisch die Anzahl Kommastellen ermitteln
        if (!$decimals && $decimals!==0 && $decimals!=='0') {
            $tmp = explode('.', $number);
            $decimals = (count($tmp)>1) ? mb_strlen($tmp[1]) : 0;
            unset($tmp);
        } else {
            $decimals = intval($decimals);
        }
        
        return number_format((float) $number, $decimals, $dec_point, $thousands_sep);
    }
    
    
}