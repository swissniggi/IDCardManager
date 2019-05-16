<?php

session_start();
require '../kireport/kireport_PDF.php';
require '../php/IDCardManager_Controller.php';

$arrayDataRows = [];
$arrayPrintedUsers = [];

try {
    // Get-Parameter auslesen
    $arrayGetData = filter_input_array(INPUT_GET);

    // Array mit den Benutzerdaten füllen
    for ($i = 0; $i < count($arrayGetData)/6; $i++) {
        $arrayDataRows[$i]['lastName'] = $arrayGetData['lastName'.$i];
        $arrayDataRows[$i]['firstName'] = $arrayGetData['firstName'.$i];
        $arrayDataRows[$i]['title'] = $arrayGetData['title'.$i];
        $arrayDataRows[$i]['validDate'] = $arrayGetData['validDate'.$i];
        $arrayDataRows[$i]['employeeId'] = $arrayGetData['employeeId'.$i];
        $arrayDataRows[$i]['imgPath'] = '../'.$arrayGetData['imgPath'.$i];

        // Vorname und Name für Logeintrag speichern
        $arrayPrintedUsers[] = $arrayGetData['firstName'.$i] . ' ' . $arrayGetData['lastName'.$i];
    }

    // Berichtskonfiguration auslesen
    $objectReportConfig = json_decode(file_get_contents(realpath('../reportConfig/report.json')));

    // Dimensionen des Personalausweises übergeben
    $objectReportConfig->size = array(86.4,54);

    $objectPDF = new kireport_PDF();

    // PDF mit Personalausweisen erstellen
    $objectPDF->createAcrobat($objectReportConfig, null, $arrayDataRows, 'Personalausweise.pdf');
    
    // Druckvorgang dokumentieren
    $sMsg = 'Der Benutzer '.$_SESSION['username'].
            ' hat die Personalausweise für folgende Benutzer gedruckt: ';
    foreach ($arrayPrintedUsers as $sPrintedUser) {
        $sMsg .= $sPrintedUser.', ';
    }
    IDCardManager_Controller::writeChangeLog($sMsg);
} catch (Throwable $ex) {
    IDCardManager_Controller::writeErrorLog($ex);
    
    // Fehlerseite aufrufen
    header('Location: template/error500.html');
}
