<?php
$rootPath = __DIR__;
$zipPath = __DIR__ . '/build/multiboard_v5.zip';

// Ensure build directory exists
if (!file_exists(__DIR__ . '/build')) {
    mkdir(__DIR__ . '/build', 0777, true);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Cannot open zip file: $zipPath\n");
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    // Skip directories (they would be added automatically)
    if (!$file->isDir()) {
        // Get real and relative path for current file
        $filePath = $file->getRealPath();

        // Calculate relative path from project root
        $relativePathInProject = substr($filePath, strlen($rootPath) + 1);

        // Normalize slashes
        $relativePathInProject = str_replace('\\', '/', $relativePathInProject);

        // Skip build directory, hidden files, and this script
        if (
            strpos($relativePathInProject, 'build/') === 0 ||
            strpos($file->getFilename(), '.') === 0 ||
            $file->getFilename() === 'create_zip.php'
        ) {
            continue;
        }

        // Add current file to archive with 'multiboard/' prefix
        $zip->addFile($filePath, 'multiboard/' . $relativePathInProject);
    }
}

$zip->close();
echo "Zip file created successfully: $zipPath\n";
