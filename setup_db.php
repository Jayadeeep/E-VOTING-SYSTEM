<?php
$servername = "localhost";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password);

// Re-Create DB to ensure fresh schema
$conn->query("CREATE DATABASE IF NOT EXISTS ap_voting_db");
$conn->select_db("ap_voting_db");

// Drop old voters table to replace with detailed Registration table
$conn->query("DROP TABLE IF EXISTS voters");

// Create NEW Voters Table
$sql = "CREATE TABLE voters (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voter_id VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    dob DATE NOT NULL,
    gender VARCHAR(10) NOT NULL,
    address TEXT NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    aadhaar VARCHAR(12) NOT NULL UNIQUE,
    voted_for INT(6) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Candidates Table remains the same, ensure it exists
$sql = "CREATE TABLE IF NOT EXISTS candidates (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    party VARCHAR(50) NOT NULL,
    abbr VARCHAR(10) NOT NULL,
    color VARCHAR(10),
    logo VARCHAR(255),
    votes INT(6) DEFAULT 0
)";
$conn->query($sql);

// Insert initial empty candidates if they don't exist
$result = $conn->query("SELECT * FROM candidates");
if($result->num_rows == 0) {
    $conn->query("INSERT INTO candidates (name, party, abbr, color, logo, votes) VALUES 
        ('Dr. Srinivas Rao', 'Yuvajana Sramika Rythu Congress Party', 'YSRCP', '#10b981', 'https://upload.wikimedia.org/wikipedia/commons/e/e1/YSR_Congress_Party_Logo.svg', 0),
        ('Venkat Naidu', 'Telugu Desam Party', 'TDP', '#facc15', 'https://upload.wikimedia.org/wikipedia/commons/e/eb/Telugu_Desam_Party_logo.svg', 0),
        ('P. Kalyan', 'Jana Sena Party', 'JSP', '#ef4444', 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d6/Janasena_Party_Logo.svg/150px-Janasena_Party_Logo.svg.png', 0),
        ('R. Sharma', 'Bharatiya Janata Party', 'BJP', '#f97316', 'https://upload.wikimedia.org/wikipedia/en/thumb/1/1e/Bharatiya_Janata_Party_logo.svg/100px-Bharatiya_Janata_Party_logo.svg.png', 0),
        ('V. Reddy', 'Indian National Congress', 'INC', '#3b82f6', 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/45/Indian_National_Congress_hand_logo.svg/150px-Indian_National_Congress_hand_logo.svg.png', 0)
    ");
}

echo "<h1 style='color:green; font-family:sans-serif'>Database & Registration System Setup Complete!</h1>";
echo "<p style='font-family:sans-serif'>The new Voter ID schema with real-world parties is installed.</p>";
echo "<p style='font-family:sans-serif'>You can now go back to <a href='index.php'>index.php</a> to Register and Vote.</p>";
?>
