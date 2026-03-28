<?php
$conn = new mysqli("localhost", "root", "", "ap_voting_db");
if ($conn->connect_error) {
    die("Database Connection failed. Please run setup_db.php first to build the database.");
}
?>
