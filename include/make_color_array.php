<?php

//print_r(make_color_array(9));

/**
 * Try to come up with colors as numerically spread out as possible.
 */
function make_color_array($n)
{
  //you might set this to 16 and change sprintf accordingly.
  $pow = 256;
  $n = (int)$n;

  $color_space = array();

  $d = (int)(pow($n, (1/3)));
  $red = $green = $blue = $d;
  $r = $n - pow($d, 3);

  if($r != 0)
  {
    ++$blue;
    if($green*$red*$blue < $n)
    {
      ++$red;
    }
    if($green*$red*$blue < $n)
    {
      ++$green;
    }
  }

  $red = (($red > 1) ? (int)(($pow - 1)/($red - 1)) : ($pow - 1));
  $green = (($green > 1) ? (int)(($pow - 1)/($green - 1)) : ($pow - 1));
  $blue = (($blue > 1) ? (int)(($pow - 1)/($blue - 1)) : ($pow - 1));

  $h = 0;
  for($i = 0; $i < $pow; $i += $red)
  {
    for($j = 0; $j < $pow; $j += $green)
    {
      for($k = 0; $k < $pow; $k += $blue)
      {
        $color = sprintf("%02X%02X%02X", $i, $j, $k);
        array_push($color_space, $color);
        if(++$h >= $n)
        {
          return $color_space;
        }
      }
    }
  }
}

?>
