<?php
/**
 * script/phone.php
 * Helpers to normalize and validate phone numbers to E.164 without external libs.
 */

function normalize_phone_e164($rawNumber, $defaultCountry = 'DE') {
    $s = trim((string)$rawNumber);
    if ($s === '') return '';

    // Keep only digits and plus sign
    $clean = preg_replace('/[^\d\+]/', '', $s);

    // Convert leading 00 to +
    if (strpos($clean, '00') === 0) {
        $clean = '+' . substr($clean, 2);
    }

    // If already in E.164-like format
    if (preg_match('/^\+\d{7,15}$/', $clean)) {
        return $clean;
    }

    // If starts with + but wrong length, treat as invalid
    if (strpos($clean, '+') === 0) {
        return false;
    }

    // Strip leading zeros (national trunk)
    $digits = preg_replace('/^0+/', '', $clean);
    if ($digits === '') return false;

    // Country calling code map (small sensible defaults)
    $cc = [
        'DE' => '49', 'AT' => '43', 'CH' => '41', 'US' => '1', 'GB' => '44',
        'FR' => '33', 'NL' => '31', 'BE' => '32', 'IT' => '39', 'ES' => '34'
    ];

    $countryCode = isset($cc[strtoupper($defaultCountry)]) ? $cc[strtoupper($defaultCountry)] : '49';

    $e164 = '+' . $countryCode . $digits;

    // Validate reasonable length
    if (preg_match('/^\+\d{7,15}$/', $e164)) {
        return $e164;
    }

    return false;
}

function is_valid_e164($number) {
    if (!is_string($number) || $number === '') return false;
    return (bool)preg_match('/^\+\d{7,15}$/', $number);
}

function parsePhone($rawNumber, $defaultCountry = 'DE') {
    /**
     * Parse and validate a phone number
     * Returns array with 'valid' and 'e164' keys
     */
    $s = trim((string)$rawNumber);
    
    if ($s === '') {
        return ['valid' => false, 'e164' => null];
    }
    
    // Normalize to E.164
    $e164 = normalize_phone_e164($s, $defaultCountry);
    
    if ($e164 === false) {
        return ['valid' => false, 'e164' => null];
    }
    
    return ['valid' => true, 'e164' => $e164];
}
