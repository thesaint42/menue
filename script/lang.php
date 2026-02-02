<?php
/**
 * script/lang.php - Language helper function
 */

$LANGUAGE = $LANGUAGE ?? 'de'; // Default to German

function _t($key, $default = '') {
    global $LANGUAGE;
    
    $langFile = __DIR__ . '/lang/' . $LANGUAGE . '.php';
    if (!file_exists($langFile)) {
        $langFile = __DIR__ . '/lang/de.php';
    }
    
    $translations = include $langFile;
    return $translations[$key] ?? $default ?? $key;
}

/**
 * Set language
 */
function setLanguage($lang) {
    global $LANGUAGE;
    if (in_array($lang, ['de', 'en'])) {
        $LANGUAGE = $lang;
        $_SESSION['language'] = $lang;
    }
}

/**
 * Get current language
 */
function getLanguage() {
    global $LANGUAGE;
    return $LANGUAGE;
}

// Set from session or browser
if (isset($_SESSION['language'])) {
    setLanguage($_SESSION['language']);
} elseif (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
} else {
    // Browser language detection
    $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    if (in_array($browser_lang, ['de', 'en'])) {
        setLanguage($browser_lang);
    }
}
