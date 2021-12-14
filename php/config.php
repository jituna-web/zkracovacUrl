<?php 
    $domain = "localhost:8889/url/"; 
    $host = "localhost:8889";
    $user = "mamp"; 
    $pass = ""; 
    $db = "urlshortener"; 

    $conn = mysqli_connect($host, $user, $pass, $db);
    if(!$conn){
        echo "Připojení k databízi se nezdařilo".mysqli_connect_error();
    }
?>