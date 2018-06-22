<?php
require_once(__DIR__ . '/lib.php');

if ($argc < 2) {
    writeline('Arguments too few');
    exit;
}

$fileName = $argv[1];
if (!file_exists($fileName)) {
    writeline('File not found');
    exit;
}

$mysqlHost = readlineDefault('MySQL Host (localhost): ', 'localhost');
$mysqlUser = readlineDefault('MySQL Username (root): ', 'root');
$mysqlPass = readlineDefault('MySQL Password: ', '', true);

$connection = mysqli_connect($mysqlHost, $mysqlUser, $mysqlPass);
if (!$connection) {
    writeline('Unable to connect');
    exit;
}

$result = mysqli_query($connection, "SHOW VARIABLES LIKE 'secure_file_priv'");
if (!$result) {
    writeline('Unable to retrieve mysql temp directory path');
    exit;
}
$data = mysqli_fetch_array($result);
if (!$data) {
    writeline('Unable to retrieve mysql temp directory path');
    exit;
}

$mysqlTempPath = $data['Value'];

do {
    $dbName = randomDatabaseName();
    $query = mysqli_query($connection, 'USE ' . $dbName);
} while ($query);

writeline('Creating temp database...');
$query = mysqli_query($connection, 'CREATE DATABASE ' . $dbName);
if (!$query) {
    writeline('Unable to create database');
    exit;
}
writeline("Temp database is created: " . $dbName);
writeline("\033[1;31m**Please note that any unexpected shutdown of program will not remove temp database, and you have to drop it by yourself**\033[0m");

try {
    $query = mysqli_query($connection, 'USE ' . $dbName);
    if (!$query) {
        writeline('Unable to select database');
        throw new Exception();
    }
    $query = mysqli_query($connection, 'SET NAMES utf8');
    if (!$query) {
        writeline('Unable to set names utf8');
        throw new Exception();
    }

    writeline('Importing to database...');
    $file = fopen($fileName, 'r');
    if (!$file) {
        writeline('Unable to open file');
        exit;
    }
    
    writeline('Please type your MySQL password again');
    exec("mysql -h {$mysqlHost} -u {$mysqlUser} -p {$dbName} < {$fileName}");

    $tables = [];
    $result = mysqli_query($connection, 'show tables');
    while ($table = mysqli_fetch_array($result)) {
        $tables[] = $table[0];
    }

    writeline('Writing csv files...');

    $dirName = getDirName($fileName);

    foreach ($tables as $table) {
        $csvFileName = $table . '.csv';
        $targetCsvPath = $dirName . '/' . $csvFileName;
        writeline($targetCsvPath);

        $headers = [];
        $result = mysqli_query($connection, 'DESCRIBE ' . $table);
        if (!$result) {
            throw new Exception('Unable to add fiend names of table ' . $table);
        }
        while ($fieldRecord = mysqli_fetch_array($result)) {
            $headers[] = '"' . $fieldRecord['Field'] . '"';
        }
        $headerLine = implode(',', $headers) . PHP_EOL;

        $csvFile = fopen($targetCsvPath, 'w');
        fwrite($csvFile, $headerLine);

        $tempFullPath = $mysqlTempPath . $table . '.csv';
        $tempFullPath = str_replace("\\", "/", $tempFullPath);

        if (file_exists($tempFullPath) && !unlink($tempFullPath)) {
            throw new Exception('Unable to delete already existing temp file ' . $targetCsvPath);
        }

        $result = mysqli_query($connection, "SELECT *
        FROM {$table}
        INTO OUTFILE '{$tempFullPath}'
        FIELDS TERMINATED BY ','
        ENCLOSED BY '\"'
        LINES TERMINATED BY '\n'");

        if (!$result) {
            throw new Exception('Unable to export table ' . $table . ' to ' . $targetCsvPath . ' - ' . mysqli_error($connection));
        }

        if (!file_exists($tempFullPath)) {
            throw new Exception('Unable to get csv temp file of' . $tempFullPath);
        }

        $tempFile = fopen($tempFullPath, 'r');
        while (!feof($tempFile)) {
            fwrite($csvFile, fread($tempFile, 2048));
        }
        fclose($csvFile);
        fclose($tempFile);

        if (file_exists($tempFullPath)) {
            unlink($tempFullPath);
        }
    }

    dropDB($connection, $dbName);

    writeline("\033[0;32mSuccessful\033[0m");
} catch (Exception $ex) {
    writeline("\033[0;31mOperation was interrupted: " . $ex->getMessage() . "\033[0m");
    dropDB($connection, $dbName);
} catch (Error $ex) {
    writeline("\033[0;31mOperation was interrupted: " . $ex->getMessage() . "\033[0m");
    dropDB($connection, $dbName);
}

mysqli_close($connection);