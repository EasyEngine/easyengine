<?php

/**
 * Helper file contains function to parse a given file and return all DEFINEed values in input file
 * @ref - http://stackoverflow.com/questions/645862/regex-to-parse-define-contents-possible
 */

function is_constant($token) {
    return $token == T_CONSTANT_ENCAPSED_STRING || $token == T_STRING ||
    $token == T_LNUMBER || $token == T_DNUMBER;
}

function strip($value) {
    return preg_replace('!^([\'"])(.*)\1$!', '$2', $value);
}

function get_defines($filename) {
    $wpconf = file_get_contents($filename);
    $tokens = token_get_all($wpconf);
    $token = reset($tokens);
    while ($token) {
        if (is_array($token)) {
            if ($token[0] == T_WHITESPACE || $token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT) {
                // do nothing
            } else if ($token[0] == T_STRING && strtolower($token[1]) == 'define') {
                $state = 1;
            } else if ($state == 2 && is_constant($token[0])) {
                $key = $token[1];
                $state = 3;
            } else if ($state == 4 && is_constant($token[0])) {
                $value = $token[1];
                $state = 5;
            }
        } else {
            $symbol = trim($token);
            if ($symbol == '(' && $state == 1) {
                $state = 2;
            } else if ($symbol == ',' && $state == 3) {
                $state = 4;
            } else if ($symbol == ')' && $state == 5) {
                $defines[strip($key)] = strip($value);
                $state = 0;
            }
        }
        $token = next($tokens);
    }
    return $defines;
}

//print_r(get_defines('/var/www/cp.rtcamp.info/htdocs/wp-config.php'));

//foreach ( as $k => $v) {
//    echo "'$k' => '$v'\n";
//}
?>