<?php
/**
 * CSRF Protection Functions
 * 
 * Generates and validates CSRF tokens to prevent Cross-Site Request Forgery attacks
 */

// Prevent function redeclaration
if (!function_exists('generateCSRFToken')) {
    /**
     * Generate a CSRF token and store it in session
     * @return string The CSRF token
     */
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('getCSRFToken')) {
    /**
     * Get the current CSRF token
     * @return string The CSRF token
     */
    function getCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            return generateCSRFToken();
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Validate a CSRF token
     * @param string $token The token to validate
     * @return bool True if token is valid, false otherwise
     */
    function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrfField')) {
    /**
     * Generate a CSRF token hidden input field
     * @return string HTML input field with CSRF token
     */
    function csrfField() {
        $token = getCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verifyCSRFToken')) {
    /**
     * Verify CSRF token from POST request
     * @return bool True if valid, false otherwise
     */
    function verifyCSRFToken() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true; // CSRF only applies to state-changing requests (POST)
        }
        
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        return validateCSRFToken($token);
    }
}
