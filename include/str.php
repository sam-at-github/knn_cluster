<?php

define('SHORTEN_STRING_REMOVE', " _-aeiouAEIOU");

function shorten_string($str, $rep_str = SHORTEN_STRING_REMOVE)
{
  $replace = str_split($rep_str);
  $str = (string) $str;
  $first_letter  = substr($str, 0, 1);
  $rest = substr($str, 1);
  $new_string = $first_letter.str_replace($replace, "", $rest);
  return $new_string;
}
?>
