<?php
session_start();
session_destroy();
header("Location: ../userLogIn.html?message=logged_out");
exit();
