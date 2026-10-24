<?php
$servername = "projects.local"; // I should change this to my actual server name
$username = "root";
$password = "";
$dbname = "Workshop_2"; // I should change this to my actual database name

$dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
$conn = new PDO($dsn, $username, $password, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
]);