<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Correctly require the dbconnection file from one level up
require_once 'dbconnection.php';

echo "<h1>Database Connection Test</h1>";

try {
    // Use the same Database class as the rest of the application
    $db = new Database();
    $pdo = $db->getConnection();
    echo "<h3 style='color:green;'>Connected to the database successfully!</h3>";

    // Run a simple query to prove the connection is working
    $stmt = $pdo->query("SELECT UserID, UserCode, Role, BranchID FROM Accounts");

    echo "<p>Found users in the Accounts table:</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>
            <tr style='background-color:#f2f2f2;'><th>UserID</th><th>UserCode</th><th>Role</th><th>BranchID</th></tr>";

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['UserID']}</td><td>{$row['UserCode']}</td><td>{$row['Role']}</td><td>{$row['BranchID']}</td></tr>";
    }

    echo "</table>";

} catch (PDOException $e) {
    // This will now show the detailed error, which is crucial for debugging
    echo "<h3 style='color:red;'>Connection FAILED!</h3>";
    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>This error usually means one of the following:</p>
          <ul>
            <li>The server IP address in `dbconnection.php` is wrong.</li>
            <li>A firewall on the server computer is blocking incoming connections on port 3306.</li>
            <li>The MySQL user ('pharma_user') does not have permission to connect from your computer's IP address.</li>
            <li>The MySQL server is not running on the server computer.</li>
          </ul>";
}
?>