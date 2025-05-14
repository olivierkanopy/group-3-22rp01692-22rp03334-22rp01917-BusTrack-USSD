<?php
require_once 'includes/ErrorHandler.php';
require_once 'includes/Database.php';
require_once 'sms.php';
require_once 'ussd.php';


function handleSMSMenu($phoneNumber, $text) {
    try {
        // Initialize SMS handler
        $smsHandler = new Sms($phoneNumber);
        
        // Get SMS history from Africa's Talking
        $messages = $smsHandler->getHistory($phoneNumber);
        
        if (empty($messages)) {
            return "No SMS history found for your number.\n\n0. Back to main menu";
        }
        
        $response = "SMS History:\n";
        $count = 1;
        
        // Show last 5 messages
        $recentMessages = array_slice($messages, 0, 5);
        foreach ($recentMessages as $message) {
            try {
                $date = date("d/m H:i", strtotime($message['sent_at']));
                $messageText = substr($message['message'], 0, 30) . (strlen($message['message']) > 30 ? "..." : "");
                $response .= "$count. $date\n$messageText\n\n";
                $count++;
            } catch (Exception $e) {
                error_log("Date formatting error: " . $e->getMessage());
                continue;
            }
        }
        
        $response .= "0. Back to main menu";
        return $response;
        
    } catch (Exception $e) {
        error_log("SMS Menu Error: " . $e->getMessage());
        return ErrorHandler::handleError(
            ErrorHandler::SMS_ERROR,
            "Unable to fetch SMS history",
            false,
            $e->getMessage()
        );
    }
}

// Example usage in your main USSD handler:
if (isset($_POST['text']) && $_POST['text'] === "6") {
    $response = handleSMSMenu($_POST['phoneNumber'], $_POST['text']);
    header('Content-Type: text/plain');
    echo $response;
} else {
    // Handle other menu options
    // ... other menu options ...
}

?> 