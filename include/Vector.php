<?php

/**
 * A Vector.
 * Although not implicit in name "Vector" this class gate keep converts all components to floats.
 * Implements euclidean distance measures between vectors. Only relavent to 2 methods.
*/
class Vector implements IteratorAggregate
{
  protected $vector = array();
  protected $dim = 0;

  /**
   * Constructs.
   * Your allowed to have array of size zero. just not useful. Cant be extended.
   * @param Array|int $init consruct from array or empty of size $init
   */
  function __construct($init)
  {
    if(is_array($init))
    {
      if(sizeof($init) == 0) 
      {
        throw new VectorException("Array input was empty");
      }      
      else
      {
        for($i = 0; $i < sizeof($init); $i++)
        {
          $this->vector[$i] = (float)$init[$i];
        }
        $this->dim = sizeof($init);
      }

    }
    else
    {
      $init = (int)$init;
      if($init <= 0)
      {
        throw new VectorException("Vector size must be +ve");
      }      
      else
      {
        $this->vector = array_fill(0, $init, 0.0);
        $this->dim = $init;
      }
    }
  }

  /**
   * Get value at $index.
   * @param int $index
   * @return float
   * @exception Index out of bounds.
   */
  public function get($index)
  {
    if(($index < 0) || ($index >= sizeof($this->vector)))
    {
      throw new VectorException("Index out of bounds");
    }

    return $this->vector[$index];
  }

  /**
   * Set $index to $value.
   * @param int $index
   * @param float $value
   * @exception Index out of bounds.
   */
  public function set($index, $value)
  {

    if(($index < 0) || ($index >= $this->dim))
    {
      throw new VectorException("Index out of bounds");
    }

    $this->vector[$index] = $value;
  }

  /**
   * Add Vector to this Vector and return result in new Vector.
   * Caution can add vector of differnet size.
   * Dupd code in add_to() for efficiency.
   * @param Vector $vec
   * @return Vector
   * @see add_to()
   */
  public function add(Vector $vec)
  {    
    if($this->dim != $vec->dim)
    {
      trigger_error(__METHOD__.": different sized Vectors.", E_USER_NOTICE);
      $min = min($this->dim, $vec->dim);
    }
    else
    {
      $min = $this->dim;
    }

    $v = clone $this;

    for($i = 0; $i < $min; $i++)
    {
      $v->vector[$i] += $vec->vector[$i];
    }
    return $v;
  }

  /**
   * Add to this Vector.
   * @param Vector $vec
   */
  public function add_to(Vector $vec)
  {
    if($this->dim != $vec->dim)
    {
      trigger_error(__METHOD__.": different sized Vectors.", E_USER_NOTICE);
      $min = min($this->dim, $vec->dim);
    }
    else
    {
      $min = $this->dim;
    }

    for($i = 0; $i < $min; $i++)
    {
      $this->vector[$i] += $vec->vector[$i];
    }
  }

  /**
   * Subtract Vector from this Vector and return result as new Vector.
   * @param  Vector $vec
   * @return Vector
   * @see sub_to()
   */
  public function sub(Vector $vec)
  {    
    if($this->dim != $vec->dim)
    {
      trigger_error(__METHOD__.": different sized Vectors.", E_USER_NOTICE);
      $min = min($this->dim, $vec->dim);
    }
    else
    {
      $min = $this->dim;
    }

    $v = clone $this;

    for($i = 0; $i < $min; $i++)
    {
      $v->vector[$i] -= $vec->vector[$i];
    }
    return $v;
  }

  /**
   * Subtract from this Vector.
   * @param Vector $vec
   */
  public function sub_to(Vector $vec)
  {
    if($this->dim != $vec->dim)
    {
      trigger_error(__METHOD__.": different sized Vectors.", E_USER_NOTICE);
      $min = min($this->dim, $vec->dim);
    }
    else
    {
      $min = $this->dim;
    }

    for($i = 0; $i < $min; $i++)
    {
      $this->vector[$i] -= $vec->vector[$i];
    }
  }

  /**
   * Multiply this Vector by $n, and return result as new Vector
   * @param float $n
   * @return Vector
   * @see mul_to()
   */
  public function mul($n)
  {
    $v = clone $this;

    for($i = 0; $i < $this->dim; $i++)
    {
      $v->vector[$i] *= $n;
    }

    return $v;
  }

  /**
   * Multiply this Vector by $n
   * @param float $n
   */
  public function mul_to($n)
  {
    for($i = 0; $i < $this->dim; $i++)
    {
      $this->vector[$i] *= $n;
    }
  }

  /**
   * Get the distance to another vector.
   * Not so useful but extending Vector types will override this.
   * They dont have to override all (sub|add|mul)[_to], just this if they provide only a differnet distance metric.
   * Provided this method is used to get the metric of course.
   * @param Vector $vector
   * @return float
   */
  public function distance(Vector $vec)
  {
    $sum = 0.0;
    $c = 0.0;
    for($i = 0; $i < $this->dim; $i++)
    {
      $c = $this->vector[$i] - $vec->vector[$i];
      $sum += $c*$c;
    }
    return sqrt($sum);
  }

  /**
   * Get the distance to another vector squared.
   * Not so useful but extending Vector types will override this.
   * They dont have to override all (sub|add|mul)[_to], just this if they provide only a differnet distance metric.
   * Provided this method is used to get the metric of course.
   * @param Vector $vector
   * @return float
   */
  public function distance_squared(Vector $vec)
  {
    return 0;
    $sum = 0.0;
    $c = 0.0;
    for($i = 0; $i < $this->dim; $i++)
    {
      $c = $this->vector[$i] - $vec->vector[$i];
      $sum += $c*$c;
    }
    return $sum;
  }

  /**
   * Euclidean ATM.
   */
  public function abs()
  {
    $sum = 0.0;
    for($i = 0; $i < $this->dim; $i++)
    {
      $sum += $this->vector[$i]*$this->vector[$i];
    }
    return sqrt($sum);
  }

  /**
   * Get the dimensionality of this Vector.
   */
  public function dim()
  {
    return $this->dim;
  }

  /**
   * === to dim() for BW compat.
   */
  public function get_dim()
  {
    return $this->dim;
  }

  /**
   * Find the dot product of this Vector with another Vector.
   * @exception Vectors do not have the same dimensionality.
   */
  public function dot(Vector $vec)
  {  
    $dot = 0;

    if($vec->dim() != $this->dim())
    {
      throw new VectorException("Cannot calculate dot product of vectors with different dimensionality");
    }

    $dim = $this->dim();
    for($i = 0; $i < $dim; $i++)
    {
      $dot += $this->get($i) * $vec->get($i);
    }

    return $dot;
  }

  /**
   * Find the cross product of this Vector with another Vector.
   * @exception Either of the vector does not have dimensionality of 3.
   */
  public function cross(Vector $vec)
  {
  }

  /**
   * Get the cosine of the angle btwn this vector and another.
   * Angle is from this to the other, positive std - anti clockwise.
   */
  public function cosine(Vector $vec)
  {
    if($this->abs() == 0 || $vec->abs() == 0)
    {
      throw new RangeException("Cannot find cosine of angle from/to a zero vector");
    }
    $cos_factor = $this->dot($vec);
    $cos_factor = $cos_factor / ($vec->abs() * $this->abs());
    return $cos_factor;
  }

  /**
   * Get the sine of the angle btwn this vector and another.
   * Angle is from this to the other, positive std - anti clockwise.
   */
  /*
  public function sine(Vector $vec)
  {}
  */

  /**
   * Required definition of interface IteratorAggregate.
   * Using AggergateIterator OK on primitive type.
  */
  public function getIterator()
  {
      return new ArrayIterator($this->vector);
  }

  /**
  *
  */
  public function get_vector()
  {
    return new Vector($this->vector);
  }

  /**
   * Get an array version of Vector just like what was passed in.
   */
  public function to_array()
  {
     $arr = array();
     foreach($this->vector as $comp)
     {
       array_push($arr, $comp);
     }
     return $arr;
  }

  /**
   * Print the Vector as a string.
   * Prepends the vector string with a single space.
   */
  public function __toString()
  {
    $retval = "";
    foreach($this->vector as $k => $v)
    {
      $retval .= "$v ";
    }
    return $retval;
  }
}

/**
 * Token Exception type for Vector class.
 */
class VectorException extends Exception
{
  public function __construct($msg, $code = 0)
  {
    parent::__construct($msg, $code);
  }
}

/* Simple Testing
$x = new Vector(array(1,2));
$y = new Vector(2);
$z = $x->add($y);
print $x.$y.$z."\n";
$y->add_to($x);
print $x.$y.$z."\n";
$y->sub_to($x);
print $x.$y.$z."\n";
$y->mul_to(2);
print $x.$y.$z."\n";
$z = $x->sub($z);
print $x.$y.$z."\n";

print "dot:\n";
$v = new Vector(array(1 ,sqrt(2)));
$u = clone $v;
print $v->dot($u)."\n";
print_r($u->to_array());

print "cosine:\n";
$v = new Vector(array(0, 1));
$u = new Vector(array(1, 0));
print $v->cosine($u)."\n";
print $u->cosine($v)."\n";
$v = new Vector(array(1, 1));
$u = new Vector(array(1, 1));
print $v->cosine($u)."\n";
print $u->cosine($v)."\n";
$v = new Vector(array(1, 0));
$u = new Vector(array(sqrt(2)/1, sqrt(2)/1));
print $v->cosine($u)."\n";
print $u->cosine($v)."\n";
$v = new Vector(array(0, 0));
$u = new Vector(array(0, 0));
print $v->cosine($u)."\n";
print $u->cosine($v)."\n";
*/
?>
