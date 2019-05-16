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
        $img = imagecreatefromstring($sImgString);                       
        imagejpeg($img, 'userImages/'.session_id().'/'.$sLastName.'_'.$sFirstName.'.jpg');
        imagedestroy($img);
    }
    
    
    /**
     * alle Bilder im temporären Ordner löschen
     */
    public static function deleteAllImages() {
        $imgDir = 'userImages/'.session_id();
        
        if (is_dir($imgDir)) {
            $arrayAllFiles = scandir($imgDir);
            $arrayImages = array_diff($arrayAllFiles, array('.', '..'));

            foreach ($arrayImages as $img) {
                unlink($imgDir.'/'.$img);
            }
            // temporären Ordner entfernen
            rmdir($imgDir);
        }
    }
}

