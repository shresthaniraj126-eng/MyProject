<?php
$conn = new mysqli("localhost", "root", "", "hairdec");
if(!$conn){
    die("Connection not established".mysqli_connect_error());
}

