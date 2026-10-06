<?php
/**
 * IQA Metal Warehouse Systems - System Setup & Trade Configuration Wizard
 * Entry point for initial setup and live reconfiguration.
 */

require_once __DIR__ . '/src/SetupController.php';

$controller = new SetupController();
$controller->handle();

// If system is protected and not unlocked, render administrator verification challenge
if ($controller->isProtected() && !$controller->isUnlocked()) {
    $curr_company = $controller->getCompany();
    $unlock_error = $controller->getUnlockError();
    require __DIR__ . '/views/challenge.php';
    exit();
}

// Render the setup wizard shell and step views
extract($controller->getViewData());
require __DIR__ . '/views/wizard.php';