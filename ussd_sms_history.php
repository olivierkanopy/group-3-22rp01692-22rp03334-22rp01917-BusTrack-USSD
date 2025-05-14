<?php
// This script is for testing SMS history functionality
require_once 'includes/ErrorHandler.php';
require_once 'includes/Database.php';
require_once 'sms.php';

// Function to validate phone number
function validatePhoneNumber($phoneNumber) {
    $phoneNumber = trim($phoneNumber);
    if (empty($phoneNumber)) {
        return ['valid' => false, 'message' => 'Phone number is required'];
    }
    
    // Remove any spaces or special characters
    $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    // Check if it's a valid Rwanda number
    if (substr($phoneNumber, 0, 1) === '0') {
        $phoneNumber = '+250' . substr($phoneNumber, 1);
    } elseif (substr($phoneNumber, 0, 1) !== '+') {
        $phoneNumber = '+' . $phoneNumber;
    }
    
    // Validate the format (+250XXXXXXXXX)
    if (!preg_match('/^\+250[237]\d{8}$/', $phoneNumber)) {
        return ['valid' => false, 'message' => 'Invalid Rwanda phone number format'];
    }
    
    return ['valid' => true, 'phone' => $phoneNumber];
}

// Function to get SMS history for a phone number in USSD format
function getSMSHistoryForUSSD($phoneNumber) {
    try {
        // Validate phone number
        $validatedPhone = ErrorHandler::validatePhone($phoneNumber);
        if (is_string($validatedPhone) && strpos($validatedPhone, 'Error') === 0) {
            return $validatedPhone;
        }
        
        // Get database instance
        $db = Database::getInstance();
        if (!($db instanceof Database)) {
            return $db; // Error message from getInstance
        }
        
        // Query SMS history
        $result = $db->query(
            "SELECT message, sent_at FROM sms_history WHERE phone_number = ? ORDER BY sent_at DESC LIMIT 5",
            [$validatedPhone],
            "s"
        );
        
        if (!$result) {
            return ErrorHandler::handleError(
                ErrorHandler::DB_ERROR,
                "Failed to fetch SMS history",
                false
            );
        }
        
        if ($result->num_rows === 0) {
            return "No SMS history found for your number.\n\n0. Back to main menu";
        }
        
        $response = "SMS History:\n";
        $count = 1;
        
        while ($row = $result->fetch_assoc()) {
            try {
                $date = date("d/m H:i", strtotime($row['sent_at']));
                $message = substr($row['message'], 0, 30) . (strlen($row['message']) > 30 ? "..." : "");
                $response .= "$count. $date\n$message\n\n";
                $count++;
            } catch (Exception $e) {
                error_log("Date formatting error: " . $e->getMessage());
                continue;
            }
        }
        
        $response .= "0. Back to main menu";
        return $response;
        
    } catch (Exception $e) {
        return ErrorHandler::handleError(
            ErrorHandler::SYSTEM_ERROR,
            "Failed to process request",
            true,
            $e->getMessage()
        );
    }
}

// Handle USSD POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate USSD parameters
        $validationResult = ErrorHandler::validateUSSDParams($_POST);
        if ($validationResult !== true) {
            echo $validationResult;
            exit;
        }
        
        // Get POST parameters
        $phoneNumber = $_POST['phoneNumber'];
        
        // Get SMS history
        $response = "CON " . getSMSHistoryForUSSD($phoneNumber);
        
        // Output response
        header('Content-Type: text/plain');
        echo $response;
        
    } catch (Exception $e) {
        echo ErrorHandler::handleError(
            ErrorHandler::SYSTEM_ERROR,
            "Failed to process request",
            true,
            $e->getMessage()
        );
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo ErrorHandler::handleError(
        ErrorHandler::USSD_ERROR,
        "Invalid request method",
        true,
        "Method: " . $_SERVER['REQUEST_METHOD']
    );
}
?>