<?php
/**
 * CAPTCHA Functions
 * 
 * Enhanced math-based CAPTCHA to prevent bot attacks
 * Uses mixed English words and Arabic numerals for increased difficulty
 * Accepts both numeric and English word answers
 */

// Number to English word mapping (0-20)
$numberWords = [
    0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
    5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
    10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
    14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen',
    18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty'
];

// English word to number mapping (reverse lookup)
$wordToNumber = array_flip($numberWords);

/**
 * Convert number to English word
 * 
 * @param int $num Number to convert
 * @return string English word
 */
function numberToWord($num) {
    global $numberWords;
    return $numberWords[$num] ?? (string)$num;
}

/**
 * Convert English word to number
 * 
 * @param string $word English word
 * @return int|null Number or null if not found
 */
function wordToNumber($word) {
    global $wordToNumber;
    $word = strtolower(trim($word));
    return $wordToNumber[$word] ?? null;
}

/**
 * Generate a CAPTCHA question with mixed format
 * One number as English word, one as Arabic numeral
 * 
 * @return array ['question' => string, 'answer' => int]
 */
function generateCaptcha() {
    // Generate two random numbers (1-10 for simplicity)
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    
    // Random operation: addition or subtraction
    $operations = ['+', '-'];
    $operation = $operations[array_rand($operations)];
    
    // Calculate answer
    if ($operation === '+') {
        $answer = $num1 + $num2;
    } else {
        // Ensure result is positive (for better UX)
        if ($num1 < $num2) {
            $temp = $num1;
            $num1 = $num2;
            $num2 = $temp;
        }
        $answer = $num1 - $num2;
    }
    
    // Randomly decide which number to show as English word
    // 0 = first number as word, 1 = second number as word
    $wordPosition = rand(0, 1);
    
    if ($wordPosition === 0) {
        // First number as English word, second as Arabic numeral
        $display1 = numberToWord($num1);
        $display2 = (string)$num2;
    } else {
        // First number as Arabic numeral, second as English word
        $display1 = (string)$num1;
        $display2 = numberToWord($num2);
    }
    
    // Generate question text with mixed format
    $question = "$display1 $operation $display2 = ?";
    
    // Store answer in session
    $_SESSION['captcha_answer'] = $answer;
    $_SESSION['captcha_time'] = time(); // For expiration (optional)
    
    return [
        'question' => $question,
        'answer' => $answer
    ];
}

/**
 * Get current CAPTCHA question (or generate new one)
 * 
 * @return string Question text
 */
function getCaptchaQuestion() {
    if (!isset($_SESSION['captcha_question'])) {
        $captcha = generateCaptcha();
        $_SESSION['captcha_question'] = $captcha['question'];
    }
    return $_SESSION['captcha_question'];
}

/**
 * Validate CAPTCHA answer
 * Accepts both Arabic numerals and English words as valid answers
 * 
 * @param string $userAnswer User's answer (can be number or English word)
 * @return bool True if correct, false otherwise
 */
function validateCaptcha($userAnswer) {
    // Check if answer exists in session
    if (!isset($_SESSION['captcha_answer'])) {
        return false;
    }
    
    $correctAnswer = (int)$_SESSION['captcha_answer'];
    $userAnswer = trim($userAnswer);
    
    // Try to parse user answer
    $parsedAnswer = null;
    
    // First, check if it's a numeric string
    if (is_numeric($userAnswer)) {
        $parsedAnswer = (int)$userAnswer;
    } else {
        // Try to convert English word to number
        $parsedAnswer = wordToNumber($userAnswer);
    }
    
    // Validate answer
    $isValid = ($parsedAnswer !== null && $parsedAnswer === $correctAnswer);
    
    // Clear CAPTCHA after validation (one-time use)
    unset($_SESSION['captcha_answer']);
    unset($_SESSION['captcha_question']);
    unset($_SESSION['captcha_time']);
    
    return $isValid;
}

/**
 * Generate new CAPTCHA (for refresh functionality)
 * 
 * @return string New question text
 */
function refreshCaptcha() {
    unset($_SESSION['captcha_answer']);
    unset($_SESSION['captcha_question']);
    unset($_SESSION['captcha_time']);
    
    $captcha = generateCaptcha();
    return $captcha['question'];
}





