<?php
require 'vendor/autoload.php';
use AfricasTalking\SDK\AfricasTalking;

class Sms
{
    protected $phone;
    protected $AT;
    private $username = "sandbox";
    private $apiKey = "atsk_1f1f8c535eab0d215366edf90b4eef988caabbfc77fe886ce0a2dd20e44b39e1c31f9764";
    private $shortCode = "1692";

    function __construct($phone)
    {
        // Format the phone number for Africa's Talking
        $this->phone = $this->formatPhoneNumber($phone);
        
        try {
            // Initialize Africa's Talking SDK with SSL verification disabled for testing
            $options = [
                'verify' => false, // Disable SSL verification for testing
                'debug' => true // Enable debug mode
            ];
            
            $this->AT = new AfricasTalking($this->username, $this->apiKey, $options);
            
            // Log successful initialization
            error_log("Africa's Talking SDK initialized successfully for phone: " . $this->phone);
            
        } catch (Exception $e) {
            error_log("Failed to initialize Africa's Talking SDK: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function formatPhoneNumber($phone) {
        // Remove any non-digit characters except the + sign
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // If it starts with 0, replace with +250 (Rwanda)
        if (substr($phone, 0, 1) === '0') {
            $phone = '+250' . substr($phone, 1);
        }
        
        // If it starts with 7, add +250 (Rwanda)
        if (strlen($phone) === 9 && substr($phone, 0, 1) === '7') {
            $phone = '+250' . $phone;
        }
        
        // If it doesn't have a + prefix but starts with country code
        if (substr($phone, 0, 1) !== '+' && substr($phone, 0, 3) === '250') {
            $phone = '+' . $phone;
        }
        
        // Validate the Rwanda phone number format
        if (!preg_match('/^\+250[237][0-9]{8}$/', $phone)) {
            error_log("Invalid Rwanda phone number format: " . $phone);
            // Return the original number if validation fails
            return $phone;
        }
        
        // Log the formatted number
        error_log("Formatted Rwanda phone number: " . $phone);
        
        return $phone;
    }
    
    public function getHistory($phoneNumber = null)
    {
        try {
            error_log("Fetching SMS history for phone: " . ($phoneNumber ?? $this->phone));
            
            $sms = $this->AT->sms();
            
            // Use fetchMessages to get SMS history
            $result = $sms->fetchMessages([
                'lastReceivedId' => 0 // Get all messages
            ]);
            
            error_log("SMS History Response: " . json_encode($result));
            
            if (isset($result['data']) && isset($result['data']->SMSMessageData)) {
                $messages = $result['data']->SMSMessageData->Messages ?? [];
                
                // If phone number is provided, filter messages for that number
                if ($phoneNumber) {
                    $phoneNumber = $this->formatPhoneNumber($phoneNumber);
                    $messages = array_filter($messages, function($msg) use ($phoneNumber) {
                        return $msg->from === $phoneNumber || $msg->to === $phoneNumber;
                    });
                }
                
                // Format messages for display
                $formattedMessages = [];
                foreach ($messages as $msg) {
                    $formattedMessages[] = [
                        'message' => $msg->text,
                        'sent_at' => $msg->date,
                        'from' => $msg->from,
                        'to' => $msg->to,
                        'status' => $msg->status
                    ];
                }
                
                return $formattedMessages;
            }
            
            return [];
            
        } catch (Exception $e) {
            error_log("Error fetching SMS history: " . $e->getMessage());
            return [];
        }
    }
    
    public function sendSMS($message, $recipients)
    {
        try {
            // Format the recipient number if it's not already formatted
            $recipients = $this->formatPhoneNumber($recipients);
            
            // Log the SMS attempt
            error_log("Attempting to send SMS to: " . $recipients);
            error_log("Message content: " . $message);
            
            $sms = $this->AT->sms();
            
            // Prepare the SMS data
            $options = [
                'to' => $recipients,
                'message' => $message,
                'from' => $this->shortCode
            ];
            
            // Log the SMS options
            error_log("SMS options: " . json_encode($options));
            
            // Send the SMS
            $result = $sms->send($options);
            
            // Log the complete response
            error_log("Complete SMS Response: " . json_encode($result));
            
            // Check if we have a MessageData response
            if (isset($result['data']) && isset($result['data']->SMSMessageData)) {
                $messageData = $result['data']->SMSMessageData;
                error_log("SMS MessageData: " . json_encode($messageData));
                
                // Check for successful recipients
                if (isset($messageData->Recipients) && !empty($messageData->Recipients)) {
                    return [
                        'status' => 'success',
                        'data' => $result
                    ];
                }
            }
            
            // If we get here, something went wrong
            error_log("SMS send failed: No valid message data received");
            return [
                'status' => 'error',
                'message' => 'Failed to send SMS: No valid message data received'
            ];
            
        } catch (Exception $e) {
            $errorMsg = "SMS Send Error: " . $e->getMessage();
            error_log($errorMsg);
            return [
                'status' => 'error',
                'message' => $errorMsg
            ];
        }
    }
}