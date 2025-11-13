<?php
$password = '$2y$10$3nLdC.EivbE2d5kQrjLwA.xY8Q1gGZqJkQ.U.p5fE.jB/zG.9i5.S';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>
