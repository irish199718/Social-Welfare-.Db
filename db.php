
<?php
$conn = mysqli_connect("localhost", "root", "", "gudes_library_database");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

 echo "Database connected successfully!";
?>