<?php
class IDCardManager_Controller {
	
    protected $arrayLdap = [];
    protected $sUsername = null;
    protected $sLogpath = 'C:/changelog.txt';
    // --------------------------------------------------------------
    // CONSTRUCTOR
    // --------------------------------------------------------------
    public function __construct() {
        session_start();
        
        $this->arrayLdap = json_decode(file_get_contents('config/config.json'));
        
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
                    }
                } catch (Exception $ex) {
                    $this->writeLog($ex->getMessage());
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
        file_put_contents($dateNow.' '.$stringMsg);
    }
    
    // --------------------------------------------------------------
    // PRIVATE MENBERS
    // --------------------------------------------------------------
    private function _loginUser($objectUserData) {
        
    }
}