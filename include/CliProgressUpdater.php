<?php

/**
 * Simple no state way to draw progress.
 */
class CliProgressUpdater
{
  public $max = 20;    

  function set_max($max)
  {
    $this->max = $max;
  }

  public function update($f)
  {
    $f =  (($f > 1.0) ? 1.0 : $f);
    $f =  (($f < 0) ? 0 : $f);  
    print "\r[";
    for($i = 0; $i < $this->max; $i++)
    {
      if($i < $f*$this->max)
      {
        print "#";
      }
      else
      {
        print "-";
      }
    }
    print "]";
  }
}

$x =  new CliProgressUpdater();
?>
