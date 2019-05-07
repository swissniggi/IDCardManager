<?php
class IDCardManager_Controller {
	
    protected $arrayLdap = [];
    protected $sUsername = null;
    protected static $sLogpath = 'C:/logs/changelog.txt';
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
     * Analysiert die Requests
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
                        
                        case 'idcardmanager.loadEditorData':                            
                            $arrayReturn = $this->_searchADUser($objectRequest->requestData);
                            
                            if ($arrayReturn instanceof Exception || $arrayReturn instanceof Error) {
                                $this->_writeLog($arrayReturn->getMessage());
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
                        
                        case 'idcardmanager.loginUser':
                            $arrayReturn = $this->_loginUser($objectRequest->requestData->formData);
                            
                            if ($arrayReturn instanceof Exception || $arrayReturn instanceof Error) {
                                self::writeLog($arrayReturn->getMessage());
                                if ($arrayReturn->getCode() === 123) {
                                    $objectResponse->errorMsg = $arrayReturn->getMessage();
                                } else {
                                    $objectResponse->errorMsg = 'Anmeldung fehlgeschlagen.';
                                }
                            } else {
                                $objectResponse->responseData = array(
                                    'success' => 'true'
                                );
                                // Benutzername in Session speichern
                                $_SESSION['username'] = $arrayReturn['username'];
                            }
                            break;
                        
                        case 'idcardmanager.logoutUser':
                            session_destroy();
                            break;
                        
                        case 'idcardmanager.searchUsers':
                            $arrayReturn = $this->_searchADUser($objectRequest->requestData);
                            
                            if ($arrayReturn instanceof Exception || $arrayReturn instanceof Error) {
                                self::writeLog($arrayReturn->getMessage());
                                $objectResponse->errorMsg = $arrayReturn->getMessage();
                            } else {
                                $objectResponse->responseData = new stdClass();
                                $objectResponse->responseData->rows = $arrayReturn;
                            }
                            break;
                        
                        case 'idcardmanager.updateUserData';
                            break;
                    }
                } catch (Exception $ex) {
                    self::writeLog($ex->getMessage());
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
    
    public static function writeLog($stringMsg) {
        $dateNow = date('d.m.Y, H:i:s');
        file_put_contents(self::$sLogpath, $dateNow.' '.$stringMsg."\r\n", FILE_APPEND);
    }
    
    // --------------------------------------------------------------
    // PRIVATE MENBERS
    // --------------------------------------------------------------
    private function _getEmployeeId($arrayUserInfo, $intIndex) {
        // Ausweisnummer auslesen
        if (array_key_exists('employeeid', $arrayUserInfo[$intIndex])) {
            $sEmployeeId = $arrayUserInfo[$intIndex]['employeeid'][0];
        } else {
            $sEmployeeId = '--';
        }
        return utf8_encode($sEmployeeId);
    }
    
    private function _getFirstName($arrayUserInfo, $intIndex) {
        // Vorname auslesen
        if (array_key_exists('givenname', $arrayUserInfo[$intIndex])) {
            $sGivenName = $arrayUserInfo[$intIndex]['givenname'][0];
        } else {
            $sGivenName = '--';
        }
        return utf8_encode($sGivenName);
    }
    
    private function _getImgPath($arrayUserInfo, $intIndex) {
        require_once 'PHP/IDCardManager_ImageManipulator.php';
        $sFirstName = $this->_getFirstName($arrayUserInfo, $intIndex);
        $sLastName = $this->_getLastName($arrayUserInfo, $intIndex);
        
        if (!is_dir('userImages')) {
            mkdir('userImages', 0777);
        }
        
        // Anzeigebild auslesen
        if (isset($this->arrayLdap->imageFolder) && isset($arrayUserInfo[$intIndex]['samaccountname'][0])) {
            if (is_file($this->arrayLdap->imageFolder.'\\'.$arrayUserInfo[$intIndex]['samaccountname'][0].'.jpg')) {
                copy($this->arrayLdap->imageFolder.'\\'.$arrayUserInfo[$intIndex]['samaccountname'][0].'.jpg', 'userImages/'.$arrayUserInfo[$intIndex]['samaccountname'][0].'.jpg');
                $sPicturePath = 'userImages/'.$arrayUserInfo[$intIndex]['samaccountname'][0].'.jpg';
            } else {
                // Pfad des Platzhalterbildes übergeben
                self::writeLog('Datei '.$this->arrayLdap->imageFolder.'\\'.$arrayUserInfo[$intIndex]['samaccountname'][0].'.jpg nicht gefunden.');
                $sPicturePath = 'img/noimg.jpg';
            }
        } else {
            if (isset($arrayUserInfo[$intIndex]['thumbnailphoto'])) {
                $imgString = $arrayUserInfo[$intIndex]['thumbnailphoto'][0];
                IDCardManager_ImageManipulator::saveImage($sLastName, $sFirstName, $imgString);
                // Pfad des Bildes ermitteln
                $sPicturePath = 'userImages/'.$sLastName . '_' . $sFirstName . '.jpg';
            } else {
                // Pfad des Platzhalterbildes übergeben
                $sPicturePath = 'img/noimg.jpg';
            }
        }
        return utf8_encode($sPicturePath);
    }
    
    private function _getLastName($arrayUserInfo, $intIndex) {
        if (array_key_exists('sn', $arrayUserInfo[$intIndex])) {
            $sLastName = $arrayUserInfo[$intIndex]['sn'][0];
        } else {
            $sLastName = '--';
        }
        return utf8_encode($sLastName);
    }
    
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
            // Datum in valides Format umwandeln
            $nTimeBetween1601And1970 = 11644473600;
            $floatValidSek = floatval(date("U", strtotime($sValidDate)) + $nTimeBetween1601And1970);
            $floatValidNano = $floatValidSek * 1.E7;       
            $floatValid = sprintf('%.0f',$floatValidNano);
            
            $pattern .= '(accountexpires='.$floatValid.')';
        }   
        $pattern .= ')';
        
        return $pattern;
    }
    
    private function _getTitle($arrayUserInfo, $intIndex) {
        if (array_key_exists('title', $arrayUserInfo[$intIndex])) {
            $sTitle = $arrayUserInfo[$intIndex]['title'][0];
        } else {
            $sTitle = '--';
        }
        return utf8_encode($sTitle);
    }
    
    private function _getUserInfo($sPattern) {
        $con = ldap_connect($this->arrayLdap->ldapConnection);
        ldap_bind($con, $this->arrayLdap->ldapUsername, $this->arrayLdap->ldapPassword);
        
        $arrayUserSearchResult = ldap_search($con, $this->arrayLdap->dn, $sPattern);
        $arrayUserInfo = ldap_get_entries($con, $arrayUserSearchResult);
        
        if ($arrayUserInfo['count'] === 0) {
            throw new Exception('Die Suche lieferte keine Ergebnisse.');
        } else {
            return $arrayUserInfo;
        }
    }
    
    private function _getValidDate($arrayUserInfo, $intIndex) {
        if (array_key_exists('accountexpires', $arrayUserInfo[$intIndex])) {
            if($arrayUserInfo[$intIndex]['accountexpires'][0] !== '0' && $arrayUserInfo[$intIndex]['accountexpires'][0] !== '9223372036854775807') {
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
    
    private function _loginUser($arrayUserData) {
        try{
            $sUsername = mb_strtolower($arrayUserData->username);
            $sPassword = $arrayUserData->password;

            $con = ldap_connect($this->arrayLdap->ldapConnection);
            $arrayConParts = explode('.',$this->arrayLdap->ldapConnection);
            ldap_bind($con, $arrayConParts[1]."\\".$sUsername, $sPassword);

            if ($this->arrayLdap->groupDn !== '') {
                $sGroupDn = $this->arrayLdap->groupDn;
            } else {
                $sGroupDn = $this->arrayLdap->dn;
            }

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
            foreach ($arrayGroupInfo[0]['member'] as $sMember) {
                if (mb_strpos($sMember, $sCommonName) !== false) {
                    $boolIsMember = true;
                    break;
                }
            }
            
            if (!$boolIsMember) {
                throw new Exception ('Zugriff verweigert!');
            }
            
            $arrayReturn = array('username' => $sUsername);
        } catch (Throwable $ex) {
            $arrayReturn = $ex;
        }
        return $arrayReturn;
    }
    
    private function _searchADUser($objectFilter) {
        try {
            $sPattern = $this->_getPattern($objectFilter);
            
            $arrayUserInfo = $this->_getUserInfo($sPattern);
            
            $arrayReturnData = [];
            
            for ($i = 0; $i < $arrayUserInfo['count']; $i++) {
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
            // Array alphabetisch sortieren
            usort($arrayReturnData, function($a, $b) {
                return $a['lastName'] < $b['lastName'] ? -1 : 1;
            });
        } catch (Throwable $ex) {
            $arrayReturnData = $ex;
        }
        return $arrayReturnData;
    }
    
    private function _updateADUser($arrayUserData) {
        
    }
}