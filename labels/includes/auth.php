<?php
/**
 * Labels & Intake Module - Authentication Guard
 * Protects all label views and API endpoints, allowing any signed user in.
 */

require_once __DIR__ . '/../../core/Auth.php';
AuthGuard::check();
