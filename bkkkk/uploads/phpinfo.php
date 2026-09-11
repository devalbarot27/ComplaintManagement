<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

//phpinfo();

//echo function_exists('mail') ? 'mail enabled' : 'mail disabled';

$to = "coserve7@elgi.com";
$subject = "Test Mail";
$message = "Testing PHP mail function";
$headers = "From: noreply@elgi.com";

if(mail($to,$subject,$message,$headers)){
    echo "Mail sent";
}else{
    echo "Mail failed";
}

