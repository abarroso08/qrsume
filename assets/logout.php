<?php
session_start();
session_destroy();
header("Location: ../assets/login.php");
exit();
