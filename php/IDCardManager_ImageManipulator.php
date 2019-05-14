<?php

class IDCardManager_ImageManipulator {
    
    // --------------------------------------------------------------
    // PUBLIC MEMBERS
    // --------------------------------------------------------------
    /**
     * String in Bild umwandeln und speichern
     * @param string $sLastName
     * @param string $sFirstName
     * @param string $sImgString
     */
    public static function saveImage($sLastName, $sFirstName, $sImgString) {
        session_start();
        $img = imagecreatefromstring($sImgString);                       
        imagejpeg($img, 'userImages/'.session_id().'/'.$sLastName.'_'.$sFirstName.'.jpg');
        imagedestroy($img);
    }
    
    
    /**
     * alle Bilder im temporären Ordner löschen
     */
    public static function deleteAllImages() {
        session_start();
        $arrayAllFiles = scandir('userImages/'.session_id());
        $arrayImages = array_diff($arrayAllFiles, array('.', '..'));
        
        foreach ($arrayImages as $img) {
            unlink('userImages/'.$img);
        }
    }
}

