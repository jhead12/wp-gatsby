<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

function lintYaml($yamlFilePath) {
    try {
        // Load and parse the YAML file
        $yamlContent = file_get_contents($yamlFilePath);
        $parsedContent = Yaml::parse($yamlContent);

        echo "YAML is valid!\n";
    } catch (ParseException $e) {
        echo "YAML file contains an error: " . $e->getMessage() . "\n";
    }
}

// Usage
$yamlFilePath = 'path/to/your/file.yaml';
lintYaml($yamlFilePath);
