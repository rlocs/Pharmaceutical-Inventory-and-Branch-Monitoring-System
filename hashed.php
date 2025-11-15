<?php
$password = 'staff1';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>
