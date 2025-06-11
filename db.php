<?php
$dns = "mysql:host=localhost;dbname=unihousing";
$user = "root";
$pass = "";
try{
    $conn = new PDO($dns,$user,$pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    //  echo "connect data";
}catch(PDOException $e){
    echo $e->getMessage();
}

?>