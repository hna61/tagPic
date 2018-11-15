<?php
/*
 * Image - Server
 *  
 *  Author(s): Andreas Heidemann (2018-11-13), 
 *  License: MIT  
 */
 
define ("VERSION", "0.1"); 
 
ini_set('display_errors', 1);
if (file_exists ("config-local.php")){
  require_once "config-local.php";
} 
require_once "config-base.php";



/*
 *  Hilfsfunktionen
 */ 

function logMe($logtext){
  $logfile = "../data/usage.log";
  file_put_contents($logfile, date("Y-m-d H:i:s") 
                              .", ". $_SERVER['REMOTE_ADDR']
                              . " : ". $logtext . "\r\n"
                    , FILE_APPEND );  
}

function startsWith($haystack, $needle){
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle){
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function getAcceptedImageTypes(){
  return "image/jpeg" ;
}

function getUser($user)
{
	$userFile = '../data/users.json';
	$userListJson = file_get_contents ($userFile);
	$userList = json_decode($userListJson, true);
	return $userList[$user];
}


function sendImg($image){
  $filePath = QZ_IMGDIR . basename($image);
  $info = getimagesize($filePath);
  if ($info && $info['mime']){
    logMe("lade " . $filePath);
    header("Content-Type: " . $info['mime']);  
    readfile ($filePath);
  } else {                     
    logMe("verzweifle an " . $filePath);
    http_response_code(404);
    echo ("Datei ". $filePath ." nicht vorhanden.");
  }   
}

function uploadImageFile($tmpFile, $newName, $newWidth, $newHeight){
    $exif = exif_read_data($tmpFile);
    if ($exif){
      $orientation = $exif["Orientation"];
      logMe("Orientation of " . $tmpFile . " is " . $orientation);
    }
    
    $widthFactor = $newWidth / $newHeight;
    
    list ($width,$height,$type) = getimagesize($tmpFile);
    
    $srcimg = imagecreatefromjpeg($tmpFile);
    if ($srcimg){
      switch($orientation){
        case 1: // nothing
        break;

        case 2: // horizontal flip
        //$resizeObj->flipImage($path,1);
        logMe("cannot do horizontal flip on ". $tmpFile);
        break;

        case 3: // 180 rotate left
        $srcimg = imagerotate($srcimg, 180, 0); 
        logMe(" do 180 rotate left on ". $tmpFile);
        break;

        case 4: // vertical flip
        //$resizeObj->flipImage($path,2);  
        logMe("cannot do vertical flip on ". $tmpFile);
        break;

        case 5: // vertical flip + 90 rotate right
        //$resizeObj->flipImage($path, 2);
        //$resizeObj->rotateImage($path, -90);  
        logMe("cannot do vertical flip + 90 rotate right on ". $tmpFile);
        break;

        case 6: // 90 rotate right
        $srcimg = imagerotate($srcimg, -90, 0);  
        $tmp = $width;
        $width = $height;
        $height = $tmp;
        logMe(" do 90 rotate right on ". $tmpFile);
        break;

        case 7: // horizontal flip + 90 rotate right
        //$resizeObj->flipImage($path,1);    
        //$resizeObj->rotateImage($path, -90); 
        logMe("cannot do horizontal flip + 90 rotate right on ". $tmpFile);
        break;

        case 8:    // 90 rotate left
        $srcimg = imagerotate($srcimg, 90, 0); 
        $tmp = $width;
        $width = $height;
        $height = $tmp;
        logMe(" do 90 rotate left on ". $tmpFile);
        break;
      }
      
      if ($width > ($height * $widthFactor)){
        $x = floor(($width-($height * $widthFactor))/2);
        $y = 0;
        $width = $height * $widthFactor;
      } else {
        $x = 0;
        $y = floor((($height) - floor($width / $widthFactor))/2);
        $height = floor($width / $widthFactor);
      }
      $resize = $newHeight / $height;
      
      $dstimg = imagecreatetruecolor ($newWidth, $newHeight);
      if ($srcimg && $dstimg && imagecopyresampled ($dstimg, $srcimg, 0, 0, $x, $y, $newWidth, $newHeight, $width, $height)){
        if (imagejpeg($dstimg, QZ_IMGDIR . $newName)){
          echo "OK " .$newName;  
          logMe ("Logo-Upload ". $newName);
        } else {  
          logMe ("FEHLER beim Speichern unter " .QZ_IMGDIR .  $newName);
          echo "FEHLER beim Speichern unter " .QZ_IMGDIR . $newName;
        }
      }
    }  else {
      logMe ("FEHLER beim Speichern von " . $newName);
      echo "FEHLER beim Speichern von " . $newName;
    }
}


/*
 *  Aufrufbare Server-Funktionen
 */ 


function do_getimage(){
    $imageFile =  $_REQUEST['image'];
    logMe ("Lade Bild: ". $imageFile);
    sendImg($imageFile);
}
$server['getimage'] = do_getimage;

function do_uploadImage(){
    // upload and resize
    $quiz = $_REQUEST['quiz'];
    $tmpFile = $_FILES['file']['tmp_name'];
    
    $newName = uniqid($quiz . '_') . '.jpg';
    while (file_exists(TP_IMGDIR . $newName)) {
       $newName = uniqid($quiz . '_') . '.jpg';
    }
    
    uploadImageFile($tmpFile, $newName, QZ_BILDGROESSE, QZ_BILDGROESSE);
}
$server['uploadImage'] = do_uploadImage;
      
function do_test(){
  logMe ("aufgerufen: test");
  echo "TEST aufgerufen\r\n";
}       
$server['test'] = do_test;


/*
 *  Verteile Aufruf auf die implementierenden Funktionen
 */
$method      = $_REQUEST['method'];

if ($method && $server[$method]){
     $server[$method](); 
}  else {
  logMe("FEHLER: falscher Aufruf des Servers, method='".$method."'");
  "FEHLER: falscher Aufruf des Servers, method='".$method."'";
}

?>
