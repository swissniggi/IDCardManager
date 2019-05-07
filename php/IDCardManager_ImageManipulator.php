<?php

class IDCardManager_ImageManipulator {
    
    // --------------------------------------------------------------
    // PUBLIC MEMBERS
    // --------------------------------------------------------------
    public static function saveImage($sLastName, $sFirstName, $sImgString) {
        $img = imagecreatefromstring($sImgString);                       
        imagejpeg($img, realpath('userImages').'/'.$sLastName.'_'.$sFirstName.'.jpg');
        imagedestroy($img);
    }
    
    
    public static function deleteAllImages() {
        $arrayAllFiles = scandir(realpath('userImages'));
        $arrayImages = array_diff($arrayAllFiles, array('.', '..'));
        
        foreach ($arrayImages as $img) {
            unlink(realpath('userImages').'/'.$img);
        }
    }
}

