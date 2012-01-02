<?php
/*********************************************************************
    class.tools.php

    Toolset functions.

    Glenn Plas <glenn@byte-consult.be>
    Copyright (c)  2011-2012

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
    $Id: $
**********************************************************************/
class Tools {
    /* Some helper functions */
    public static function preg_test($regex) {
        // Test regexes and throw exceptions if they don't parse well
        if (sprintf("%s",@preg_match($regex,'')) == '') {
            $error = error_get_last();
            throw new Exception($error['message']);
        } else {
            return true;
        }
    }

    /* Test for hex string (like RFID numbers or other hex strings ) */
    function isHexadecimalString ( $str ) {
        if ( preg_match("/^[a-f0-9]{1,}$/is", $str) ) {
            return true;
        } else {
            return false;
        }
    }

    /* good old perl stuff */
    public static function mychomp(&$string) {
        // Perl is dearly missed
        if (is_array($string)) {
            foreach($string as $i => $val) {
                $endchar = chomp($string[$i]);
            }
        } else {
            $endchar = substr("$string", strlen("$string") - 1, 1);
            $string = substr("$string", 0, -1);
        }
        return $endchar;
    }

    // This is made to clean message_id lines from nasty stuff
    public static function cleanrefs($buffer) {

        $buffer=trim($buffer);

        $search = array(
                '/(\s)+/s',  // shorten multiple whitespace sequences
                '/(\s)+\>/s', //strip whitespaces after tags, except space
                '/(\s)+\</s', //strip whitespaces before tags, except space
                '/(\n)/s',  // remove newline
                '/(\t)/s',  // remove tabs
                );  
        $replace = array(
                ' ',
                '>',   
                ' <',
                ' ',
                ' '
                );
        $buffer = preg_replace($search, $replace, $buffer);

        return $buffer;
    }
}

/* Test this static class 

$test_chop="Oops, I have tooo";
echo sprintf("%s\n", $test_chop);
echo sprintf("I chopped of an '%s' with a static function from '%s'\n", Tools::mychomp($test_chop), $test_chop);
*/
?>
