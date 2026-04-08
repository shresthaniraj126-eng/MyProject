<?php
session_start();
if($_SESSION['type']=="user"){
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<Style>    
body::-webkit-scrollbar {
    display: none;
}
body{
    padding: 0;
    margin: 0;
    width: 100%;
    height: 100vh;
    display: flex;
}

.navbar{
    position: relative;
    display: flex;
    flex-direction: column;

    width: 15%;
    height: 100%;
    border-right: 1px solid;
}


.navbar a{
    position: relative;
    padding: 10px;
    text-decoration: none;
    font-size: 20px;
    border: 1px solid;
}
iframe{
    width: 85%;
}
</Style>
<body>
    <div class="navbar">
        <h1>User Panel</h1>
        <a href="userHome.php" target="Display">Home</a>
        <a href="bookingview.php" target="Display">Bookings</a>
        <button onclick="window.open('logout.php','_self')"style="padding:10px;background:#dc3545;color:white;border:none;border-radius:4px; margin-top:380px;">Logout</button>

    </div>

    <iframe frameborder="0" name="Display" src="userHome.php"></iframe>
        
    </div>
</body>
</html>
<?php
}
else{
    header("Location: ../merchant.php");
}
?>