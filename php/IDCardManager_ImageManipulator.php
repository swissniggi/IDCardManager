<?php

class IDCardManager_ImageManipulator {
    
    // --------------------------------------------------------------
    // PUBLIC MEMBERS
    // --------------------------------------------------------------
    public static function saveImage($sLastName, $sFirstName, $sImgString) {
        $img = imagecreatefromstring($sImgString);
        
        if (!is_dir('userImages')) {
            mkdir('userImages');
        }
        
        imagejpeg($img, 'userImages/'.$sLastName.'_'.$sFirstName.'.jpg');
        imagedestroy($img);
    }
    
    
    public static function deleteAllImages() {
        $arrayAllFiles = scandir('userImages');
        $arrayImages = array_diff($arrayAllFiles, array('.', '..'));
        
        foreach ($arrayImages as $img) {
            unlink('userImages/'.$img);
        }
    }
}

