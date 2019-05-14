<?php
class IDCardManager_Controller {
	
    protected $arrayLdap = [];
    protected $sUsername = null;
    protected static $sChangeLogPath = 'C:/logs/changelog.log';
    protected static $sErrorLogPath = 'C:/logs/errorlog.log';
    
    // --------------------------------------------------------------
    // CONSTRUCTOR
    // --------------------------------------------------------------
    public function __construct() {
        session_start();
        
        $this->arrayLdap = json_decode(file_get_contents(realpath('config/config.json')));
        
        if (isset($_SESSION['username'])) {
            $this->sUsername = $_SESSION['username'];
        }
        // error handler erstellen
        set_error_handler(function($errno, $errstr, $errfile, $errline){
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }
    
    // --------------------------------------------------------------
    // PUBLIC MEMBERS
    // --------------------------------------------------------------
    /**
     * Requests analysieren
     */
    public function analizeRequests() {
        $objectRequests = json_decode(file_get_contents("php://input"));
        
        if (isset($objectRequests)) {
            $objectResponses = array();
            
            foreach ($objectRequests as $objectRequest) {
                $objectResponse = new stdClass();
                $objectResponse->tid = $objectRequest->tid;
                
                try {
                    switch($objectRequest->facadeFn) {
                        
                        // feststellen ob ein Benutzer angemeldet ist
                        case 'idcardmanager.checkLogin':
                            $objectResponse->responseData = ['username' => $this->sUsername ?? false];
                            break;
                        
                        // Daten in Editor laden
                        case 'idcardmanager.loadEditorData':                            
                            $arrayReturn = $this->_searchADUser($objectRequest->requestData);
                            
                            if ($arrayReturn instanceof Exception || $arrayReturn instanceof Error) {
                                self::writeErrorLog($arrayReturn);
                                $objectResponse->errorMsg = $arrayReturn->getMessage();
                            } else {
                                $objectResponse->responseData = new stdClass();
                                $objectResponse->responseData->formData = array(
                                    'name' => $arrayReturn[0]['firstName'] . ' ' . $arrayReturn[0]['lastName'],
                                    'title' => $arrayReturn[0]['title'] !== '--' ? $arrayReturn[0]['title'] : '',
                                    'valid' => $arrayReturn[0]['validDate'] !== '--' ? $arrayReturn[0]['validDate'] : '',
                                    'employeeId' => $arrayReturn[0]['employeeId'] !== '--' ? $arrayReturn[0]['employeeId'] : ''                                                                   
                                );
                            }
                            break;
                        
                        // Login ausführen
                        case 'idcardmanager.loginUser':
                            $arrayReturn = $this->_loginUser($objectRequest->requestData->formData);
                            
                            if ($arrayReturn instanceof Exception || $arrayReturn instanceof Error) {
                                self::writeErrorLog($arrayReturn);
                                if ($arrayReturn->getCode() === 5) {
                                    $objectResponse->errorMsg = $arrayReturn->getMessage();
                                } else {
                                    $objectResponse->errorMsg = 'Anmeldung fehlgeschlagen: Passwort oder Benutzername falsch!';
                                }
                            } else {
                                $objectResponse->responseData = array(
                                    'success' => 'true'
                                );
                                // Benutzername in Session speichern
                                $_SESSION['username'] = $arrayReturn['username'];
                                
                                // Session-ID generieren
                                $sSessionId = session_create_id();
                                session_id($sSessionId);
                            }
                            break;
                        
                        // Logout ausführen
                        case 'idcardmanager.logoutUser':
                            session_destroy();
                            break;
                        
                        // Benutzersuche ausführen
                        case 'idcardmanager.searchUsers':
                            $arrayReturn = $this->_searchADUser($objectRequest->requestData);
                            
                            if ($arrayReturn instanceof Exception || $arrayReturn instanceof Error) {
                                self::writeErrorLog($arrayReturn);
                                $objectResponse->errorMsg = $arrayReturn->getMessage();
                            } else {
                                $objectResponse->responseData = new stdClass();
                                $objectResponse->responseData->rows = $arrayReturn;
                            }
                            break;
                        
                        // Benutzerdaten bearbeiten
                        case 'idcardmanager.updateUserData';
                            $arrayReturn = $this->_updateADUser($objectRequest->requestData->formData);
                            
                            if ($arrayReturn instanceof Exception || $arrayReturn instanceof Error) {
                                self::writeErrorLog($arrayReturn);
                            } else {
                                $objectResponse->responseData = array(
                                    'success' => true
                                );
                            }
                            break;
                    }
                } catch (Exception $ex) {
                    self::writeErrorLog($ex);
                    $objectResponse->errorMsg = $ex->getMessage();
                }
                $objectResponses[] = $objectResponse;
            }
            // Antwort ausgeben
            print(json_encode($objectResponses));
        } else {
            echo file_get_contents('template/main.html');
        }
    }
    
    
    /**
     * Meldung ins Changelog schreiben
     * @param string $sMsg
     */
    public static function writeChangeLog($sMsg) {
        $dateNow = date('d.m.Y, H:i:s');
        file_put_contents(self::$sChangeLogPath, '['.$dateNow.'] '.$sMsg."\r\n", FILE_APPEND);
    }
    
    
    /**
     * Fehler ins Errorlog schreiben
     * @param Exception $exException
     */
    public static function writeErrorLog($exException) {
        $dateNow = date('d.m.Y, H:i:s');
        $sMsg = '['.$dateNow."]\r\n";
        $sMsg .= ' --> Fehler: '.$exException->getMessage()."\r\n";
        $sMsg .= ' --> Errorcode: '.$exException->getCode()."\r\n";
        $sMsg .= ' --> Aufgetreten in: '.$exException->getFile()."\r\n";
        $sMsg .= ' --> Auf Zeile: '.$exException->getLine()."\r\n";
        file_put_contents(self::$sErrorLogPath, $sMsg."\r\n", FILE_APPEND);
    }
    
    // --------------------------------------------------------------
    // PRIVATE MEMBERS
    // --------------------------------------------------------------
    /**
     * Personalnummer des Benutzers ermitteln
     * @param array $arrayUserInfo
     * @param int $intIndex
     * @return string
     */
    private function _getEmployeeId($arrayUserInfo, $intIndex) {
        // Ausweisnummer auslesen
        if (array_key_exists('employeeid', $arrayUserInfo[$intIndex])) {
            $sEmployeeId = $arrayUserInfo[$intIndex]['employeeid'][0];
        } else {
            $sEmployeeId = '--';
        }
        return utf8_encode($sEmployeeId);
    }
    
    
    /**
     * Vorname des Benutzers ermitteln
     * @param array $arrayUserInfo
     * @param int $intIndex
     * @return string
     */
    private function _getFirstName($arrayUserInfo, $intIndex) {
        // Vorname auslesen
        if (array_key_exists('givenname', $arrayUserInfo[$intIndex])) {
            $sGivenName = $arrayUserInfo[$intIndex]['givenname'][0];
        } else {
            $sGivenName = '--';
        }
        return utf8_encode($sGivenName);
    }
    
    
    /**
     * Bildpfad ermitteln
     * @param array $arrayUserInfo
     * @param int $intIndex
     * @return string
     */
    private function _getImgPath($arrayUserInfo, $intIndex) {
        require_once 'PHP/IDCardManager_ImageManipulator.php';
        $sFirstName = $this->_getFirstName($arrayUserInfo, $intIndex);
        $sLastName = $this->_getLastName($arrayUserInfo, $intIndex);
        
        // temporären Ordner gegebenenfalls erstellen
        if (!is_dir('userImages')) {
            mkdir('userImages', 0777);
        }
        
        // Anzeigebild auslesen
        if ($this->arrayLdap->imageFolder !== '' && isset($arrayUserInfo[$intIndex]['samaccountname'][0])) {
            if (is_file($this->arrayLdap->imageFolder.'\\'.$arrayUserInfo[$intIndex]['samaccountname'][0].'.jpg')) {
                // Portrait vom Bildordner in temporären Ordner kopieren
                copy($this->arrayLdap->imageFolder.'\\'.$arrayUserInfo[$intIndex]['samaccountname'][0].'.jpg', 'userImages/'.$arrayUserInfo[$intIndex]['samaccountname'][0].'.jpg');
                $sPicturePath = 'userImages/'.session_id().'/'.$arrayUserInfo[$intIndex]['samaccountname'][0].'.jpg';
            } else {
                // Pfad des Platzhalterbildes übergeben
                $sPicturePath = 'img/noimg.jpg';
            }
        } else {
            if (isset($arrayUserInfo[$intIndex]['thumbnailphoto'])) {
                $imgString = $arrayUserInfo[$intIndex]['thumbnailphoto'][0];
                IDCardManager_ImageManipulator::saveImage($sLastName, $sFirstName, $imgString);
                // Pfad des Bildes ermitteln
                $sPicturePath = 'userImages/'.session_id().'/'.$sLastName . '_' . $sFirstName . '.jpg';
            } else {
                // Pfad des Platzhalterbildes übergeben
                $sPicturePath = 'img/noimg.jpg';
            }
        }
        return utf8_encode($sPicturePath);
    }
    
    
    /**
     * Nachname des Benutzers ermitteln
     * @param array $arrayUserInfo
     * @param int $intIndex
     * @return string
     */
    private function _getLastName($arrayUserInfo, $intIndex) {
        if (array_key_exists('sn', $arrayUserInfo[$intIndex])) {
            $sLastName = $arrayUserInfo[$intIndex]['sn'][0];
        } else {
            $sLastName = '--';
        }
        return utf8_encode($sLastName);
    }
    
    
    /**
     * Suchmuster erstellen
     * @param object $objectFilter
     * @return string
     */
    private function _getPattern($objectFilter) {
        $sLastName = utf8_decode($objectFilter->lastName);
        $sFirstName = utf8_decode($objectFilter->firstName);
        $sEmployeeId = $objectFilter->employeeId;
        $sValidDate = $objectFilter->validDate;
        
        $pattern = '(&';
        
        if ($sLastName !== '') {
            $pattern .= '(sn='.$sLastName.')';
        }
        
        if ($sFirstName !== '') {
            $pattern .= '(givenName='.$sFirstName.')';
        }
        
        if ($sEmployeeId !== '') {
            $pattern .= '(employeeid='.$sEmployeeId.')';
        }
        
        if ($sValidDate !== '') {
            // Gültigkeitsdatum in valides Format umwandeln
            $nTimeBetween1601And1970 = 11644473600;
            $floatValidSek = floatval(date("U", strtotime($sValidDate)) + $nTimeBetween1601And1970);
            $floatValidNano = $floatValidSek * 1.E7;       
            $floatValid = sprintf('%.0f',$floatValidNano);
            
            $pattern .= '(accountexpires='.$floatValid.')';
        }   
        $pattern .= ')';
        
        return $pattern;
    }
    
    
    /**
     * Funktion des Benutzers ermitteln
     * @param array $arrayUserInfo
     * @param int $intIndex
     * @return string
     */
    private function _getTitle($arrayUserInfo, $intIndex) {
        if (array_key_exists('title', $arrayUserInfo[$intIndex])) {
            $sTitle = $arrayUserInfo[$intIndex]['title'][0];
        } else {
            $sTitle = '--';
        }
        return utf8_encode($sTitle);
    }
    
    
    /**
     * Active Directory durchsuchen
     * @param string $sPattern
     * @return array
     * @throws Exception
     */
    private function _getUserInfo($sPattern) {
        $con = ldap_connect($this->arrayLdap->ldapConnection);
        ldap_bind($con, $this->arrayLdap->ldapUsername, $this->arrayLdap->ldapPassword);
        
        // Active Directory durchsuchen
        $arrayUserSearchResult = ldap_search($con, $this->arrayLdap->dn, $sPattern);
        $arrayUserInfo = ldap_get_entries($con, $arrayUserSearchResult);
        
        if ($arrayUserInfo['count'] === 0) {
            throw new Exception('Die Suche lieferte keine Ergebnisse.', 0);
        } else {
            return $arrayUserInfo;
        }
    }
    
    
    /**
     * Gültigkeitsdatum ermitteln
     * @param array $arrayUserInfo
     * @param int $intIndex
     * @return date
     */
    private function _getValidDate($arrayUserInfo, $intIndex) {
        if (array_key_exists('accountexpires', $arrayUserInfo[$intIndex])) {
            if($arrayUserInfo[$intIndex]['accountexpires'][0] !== '0' && $arrayUserInfo[$intIndex]['accountexpires'][0] !== '9223372036854775807') {
                // Datum aus Wert von Active Directory ermitteln
                $floatAccExp = floatval($arrayUserInfo[$intIndex]['accountexpires'][0]);
                $floatDate = $floatAccExp/1.E7-11644473600;
                $intDate = intval($floatDate);
                $dateValidDate = date('d.m.Y', $intDate);
            } else {
                // 31. Dezember des laufenden Jahres wenn kein Datum gesetzt
                $dateValidDate = date('d.m.Y', strtotime('12/31'));
            }
        } else {
            $dateValidDate = date('d.m.Y', strtotime('12/31'));
        }
        return $dateValidDate;
    }
    
    
    /**
     * Benutzer einloggen
     * @param array $arrayUserData
     * @return \Throwable|array
     * @throws Exception
     */
    private function _loginUser($arrayUserData) {
        try{
            $sUsername = mb_strtolower($arrayUserData->username);
            $sPassword = $arrayUserData->password;
            
            if ($this->arrayLdap->groupDn !== '') {
                $sGroupDn = $this->arrayLdap->groupDn;
            } else {
                $sGroupDn = $this->arrayLdap->dn;
            }

            $con = ldap_connect($this->arrayLdap->ldapConnection);
            $arrayConParts = explode('.',$this->arrayLdap->ldapConnection);
            ldap_bind($con, $arrayConParts[1]."\\".$sUsername, $sPassword);

            $arrayGroupSearchResult = ldap_search(
                    $con,
                    $sGroupDn,
                    utf8_decode($this->arrayLdap->group)
                    );
            $arrayGroupInfo = ldap_get_entries($con, $arrayGroupSearchResult);

            // vollständigen Namen ermitteln
            $arrayNameSearchResult = ldap_search($con, $this->arrayLdap->dn, '(samaccountname='.$sUsername.')');
            $arrayUserInfo = ldap_get_entries($con, $arrayNameSearchResult);
            $sCommonName = $arrayUserInfo[0]['cn'][0];
            
            $boolIsMember = false;
            
            // Gruppe nach dem Benutzer durchsuchen
            foreach ($arrayGroupInfo[0]['member'] as $sMember) {
                if (mb_strpos($sMember, $sCommonName) !== false) {
                    $boolIsMember = true;
                    break;
                }
            }
            
            if (!$boolIsMember) {
                throw new Exception ('Zugriff verweigert! Der Benutzer gehört nicht zur autorisierten Gruppe.', 5);
            }
            
            $arrayReturn = array('username' => $sUsername);
        } catch (Throwable $ex) {
            $arrayReturn = $ex;
        }
        return $arrayReturn;
    }
    
    
    /**
     * Benutzer im ActiveDirectory suchen
     * @param object $objectFilter
     * @return \Throwable|array
     */
    private function _searchADUser($objectFilter) {
        try {
            $sPattern = $this->_getPattern($objectFilter);
            
            $arrayUserInfo = $this->_getUserInfo($sPattern);
            
            $arrayReturnData = [];
            
            for ($i = 0; $i < $arrayUserInfo['count']; $i++) {
                
                // System-Benutzer mit mDBUseDefaults = false ausfiltern
                if ($arrayUserInfo[$i]['mdbusedefaults'][0] === 'TRUE') {
                    $arrayUserResults = array(
                        'lastName' => $this->_getLastName($arrayUserInfo, $i),
                        'firstName' => $this->_getFirstName($arrayUserInfo, $i),
                        'title' => $this->_getTitle($arrayUserInfo, $i),
                        'validDate' => $this->_getValidDate($arrayUserInfo, $i),
                        'employeeId' => $this->_getEmployeeId($arrayUserInfo, $i),
                        'imgPath' => $this->_getImgPath($arrayUserInfo, $i)
                    );
                    $arrayReturnData[] = $arrayUserResults;
                }
            }           
            // Array alphabetisch sortieren
            usort($arrayReturnData, function($a, $b) {
                return $a['lastName'] < $b['lastName'] ? -1 : 1;
            });
        } catch (Throwable $ex) {
            $arrayReturnData = $ex;
        }
        return $arrayReturnData;
    }
    
    
    /**
     * Benutzerdaten im AD ändern
     * @param array $arrayUserData
     * @return boolean
     * @throws Exception
     */
    private function _updateADUser($arrayUserData) {
        $arrayUserName = explode(' ', $arrayUserData->name);
        
        // Gültigkeitsdatum in korrektes Format umwandeln
        $floatValidSek = floatval(date("U", strtotime($arrayUserData->valid)) + 11644473600);
        $floatValidNano = $floatValidSek * 1.E7;
        $floatValid = sprintf('%.0f',$floatValidNano);
        
        $arrayNewUserData = array(
            'employeeid' => $arrayUserData->employeeId,
            'accountexpires' => $floatValid,
            'title' => utf8_decode($arrayUserData->title)
        );
        
        $con = ldap_connect($this->arrayLdap->ldapConnection);
        ldap_bind($con, $this->arrayLdap->ldapUsername, $this->arrayLdap->ldapPassword);
        
        // Benutzer auslesen
        $firstName = utf8_decode($arrayUserName[0]);
        $lastName = utf8_decode($arrayUserName[1]);
        $arrayUserInfo = ldap_search($con, $this->arrayLdap->dn, '(&(givenName='.$firstName.')(sn='.$lastName.'))');
        $arrayFirstEntry = ldap_first_entry($con, $arrayUserInfo);
        
        // Daten ersetzen
        $sUserDn = ldap_get_dn($con, $arrayFirstEntry);
        $boolReplaceSuccessful = ldap_mod_replace($con, $sUserDn, $arrayNewUserData);
        
        if (!$boolReplaceSuccessful) {
            throw new Exception('Fehler beim Ändern der Benutzerdaten!', 0);
        }
        // Notieren, wer welchen Benutzer bearbeitet hat
        self::writeChangeLog('Der Benutzer '.$this->sUsername.
                ' hat die Daten von '.$firstName.' '.
                $lastName.' erfolreich bearbeitet.');
        return true;
    }
}