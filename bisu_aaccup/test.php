<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "PHP is working!<br>";
echo "Current directory: " . getcwd() . "<br>";

if (file_exists('config/db.php')) {
    echo "config/db.php EXISTS<br>";
    include 'config/db.php';
    echo "config/db.php INCLUDED<br>";
} else {
    echo "config/db.php NOT FOUND<br>";
}

if (file_exists('nav.php')) {
    echo "nav.php EXISTS<br>";
} else {
    echo "nav.php NOT FOUND<br>";
}

echo "CSS file: " . (file_exists('css/style.css') ? "EXISTS" : "NOT FOUND") . "<br>";
echo "JS file: " . (file_exists('js/script.js') ? "EXISTS" : "NOT FOUND") . "<br>";

?>
