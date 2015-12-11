<?php 

require_once("set_include_path.php");
require_once("Vector.php");

/**
 * Vector using the LP 1st norm
 * It more efficeint to implement the useful LP norms separately.
 * Only need override to methods distance(), and abs()
 * Note cosine() uses abs() so what effect?
 */
class VectorLP1 extends Vector
{

  /**
   * Constructs.
   * @param Array|int $init consruct from array or empty of size $init
   */
  function __construct($init, Array $options = array())
  {
    parent::__construct($init);
    if(! empty($options))
    {
      throw new InvalidArgumentException("Unknown options '".implode(",", array_keys($options))."' given to  build");
    }
  }

  /**
   * Take abs to be the abs of the distance between vector and zero vector.
   */
  public function abs()
  {
    $abs = 0.0;
    for($i = 0; $i < $this->dim; $i++)
    {
      $abs += abs($this->vector[$i]); 
    }
    return $abs;
  }

  /**
   * Distance between two vectors.
   */
  public function distance(Vector $vec)
  {
    $dist = 0.0;
    for($i = 0; $i < $this->dim; $i++)
    {
      $dist += abs($this->vector[$i] - $vec->vector[$i]);
    }
    return $dist;
  } 

}

/* //Simple Testing 
$x = new VectorLP1(array(0.3,0.5));
$y = new VectorLP1(array(0.4,0.6));
print $y->dim()."\n";
print $x->dim()."\n";

print "z = 0.7 1.1\n";
$z = $x->add($y);
print "$x\n$y\n$z\n\n";

print "z = 1.0 1.6\n";
$z = $x->add($z);
print "$x\n$y\n$z\n\n";

print "z = 1.3 2.1\n";
$z = $x->add($z);
print "$x\n$y\n$z\n\n";

print "z = -0.1 -0.1\n";
$z = $x->sub($y);
print "$x\n$y\n$z\n\n";

print "z = 0.1 0.1\n";
$z = $y->sub($x);
print "$x\n$y\n$z\n\n";

print "y = 0.7 1.1\n";
$y->add_to($x);
print "$x\n$y\n$z\n\n";

print "y = 0.4 0.6\n";
$y->sub_to($x);
print "$x\n$y\n$z\n\n";

print "y = 0.8 1.2\n";
$y->mul_to(2);
print "$x\n$y\n$z\n\n";

print "distance:\n";
print "$x\n$y\n$z\n\n";
print $x->distance($x)."\n";
print $x->distance($y)."\n";
print $x->distance($z)."\n";
print "\n";
print $y->distance($x)."\n";
print $y->distance($y)."\n";
print $y->distance($z)."\n";
print "\n";
print $z->distance($x)."\n";
print $z->distance($y)."\n";
print $z->distance($z)."\n";
print "\n";

print "abs:\n";
print $x->abs()."\n";
print $y->abs()."\n";
print $z->abs()."\n";
*/
/*
$x = new VectorLP1(array(0.1,0.1), 1);
$y = new VectorLP1(array(0.9,0.9));
print $y."\n";
print $x."\n";

print "distance:\n";
print $x->distance($y);
print $y->distance($x);

print "\nsubs:\n";
print $x->sub($y);
print $y->sub($x);

print "\nsub to:\n";
$x->sub_to($y);
$y->sub_to($x);
print $x.$y;
*/

?>
