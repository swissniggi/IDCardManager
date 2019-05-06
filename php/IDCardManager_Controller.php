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
        
        $this->arrayLdap = json_decode(file_get_contents('../config/config.json'));
        
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
                            break;
                        
                        case 'idcardmanager.loginUser':
                            $objectResponse->data = new stdClass();
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
                        
                        case 'idcardmanager.searchUsers':
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
        file_put_contents(self::$sLogpath, $dateNow.' '.$stringMsg);
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
    
    private function _getImgPath($arrayUserInfo, $arrayFilter, $intIndex) {
        
    }
    
    private function _getLastName($arrayUserInfo, $intIndex) {
        if (array_key_exists('sn', $arrayUserInfo[$intIndex])) {
            $sLastName = $arrayUserInfo[$intIndex]['sn'][0];
        } else {
            $sLastName = '--';
        }
        return utf8_encode($sLastName);
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
                    utf8_encode($this->arrayLdap->group)
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
}