<?php

namespace ActionMonitor\Monitors;

class PostTypeMonitor {
    private $postTypes;
    private $logger;

    public function __construct($postTypes, $logger) {
        $this->postTypes = $postTypes;
        $this->logger = $logger;
    }

    public function monitorPostTypeChanges() {
        foreach ($this->postTypes as $type => $details) {
            if ($this->hasChanged($details)) {
                $this->logChange($type, $details);
            }
        }
    }

    private function hasChanged($details) {
        // Check if any details have changed
        return $details['status'] !== 'published';
    }

    private function logChange($type, $details) {
        $this->logger->info("Post type {$type} has changed. Details: " . json_encode($details));
    }
}