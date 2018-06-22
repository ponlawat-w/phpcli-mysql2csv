<?php
function readlineDefault($text, $default, $password = false) {
    echo $text;
    if ($password) {
        echo "\033[30;40m";
    }
    $value = readline();
    if ($password) {
        echo "\033[0m";
    }
    return trim($value) ? $value : $default;
}

function randomDatabaseName($length = 8) {
    $charset = 'abcdefghijklmnopqrstuvwxyz';
    $dbname = '';
    for ($i = 0; $i < $length; $i++) {
        $dbname .= $charset[rand(0, strlen($charset) - 1)];
    }

    return $dbname;
}

function writeline($text) {
    echo $text . PHP_EOL;
}

function dropDB($connection, $dbname) {
    $result = mysqli_query($connection, 'DROP DATABASE ' . $dbname);
    if ($result) {
        writeline("\033[1;31mTemp database is dropped\033[0m");
    } else {
        writeline("\033[1;31mTemp database has not been dropped ({$dbname})\033[0m");
    }
}

function getDirName($fileName) {
    $fileNameExploded = explode('/', $fileName);
    $extExploded = explode('.', $fileNameExploded[count($fileNameExploded) - 1]);
    array_splice($extExploded, count($extExploded) - 1, 1);
    $dirName = implode('.', $extExploded);

    if (file_exists($dirName)) {
        if (!is_dir($dirName)) {
            throw new Exception('"' . $dirName . '" already exists and it is not directory');
        }
    } else {
        if (!mkdir($dirName)) {
            throw new Exception('Cannot create directory');
        }
    }

    return $dirName;
}