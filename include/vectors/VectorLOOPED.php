<?php 

require_once( "set_include_path.php" );
require_once( "Vector.php" );


/**
 * Vector with looped distance metric.
 * Space is a *positive* cube - not rectangular.
 * Default cube size is 1.0.
 */
class VectorLOOPED extends Vector
{
	private $cube_size = 1.0;
	


	/**
	 * Constructs.
	 * @param Array|int $init consruct from array or empty of size $init
	 */
	function __construct( $init, Array $options = array() )
	{
		parent::__construct( $init );
		$this->set_options( $options );	
		for( $i = 0; $i < $this->dim; $i++ )
		{
			$this->vector[$i] = $this->cube( $this->vector[$i] );
		}
	}
	
	
	
	private function set_options( $options )
	{
		if( isset( $options['cube_size'] ) )
		{
			if( $options['cube_size'] <= 0 )
			{
				throw new VectorException( "Cube size must be +ve" );
			}
			
			$this->cube_size = $options['cube_size'];
			unset( $options['cube_size'] );
		}
		if( ! empty( $options ) )
		{
			throw new InvalidArgumentException( "Unknown options '".implode( ",", array_keys( $options ) )."' given to  build" );
		}
	}



	/**
	 * Set $index to $value.
	 * ensures the value is within cube range.
	 * @param int $index
	 * @param float $value
	 */
	public function set( $index, $value )
	{
		$value = $this->cube( $value );
		parent::set( $index, $value );
	}
		
	
	
	/**
	 * Add Vector to this Vector and return result in new Vector.
	 * Caution can add vector of differnet size.
	 * Dupd code in add_to() for efficiency.
	 * @param Vector $vec
	 * @return Vector
	 * @see add_to()
	 */
	public function add( Vector $vec )
	{		
		$min = min( $this->dim, $vec->dim );
		
		if( $min != $this->dim )
		{
			trigger_error( __METHOD__.": different sized Vectors.", E_USER_NOTICE );
		}
		
		$new_vec = clone $this;

		for( $i = 0; $i < $min; $i++ )
		{
			$new_vec->vector[$i] = $this->cube( $this->vector[$i] + $vec->vector[$i] );
		}
		return $new_vec;
	}	



	/**
	 * Add to this vector.
	 * @param Vector $vec
	 */
	public function add_to( VectorLOOPED $vec )
	{
		$min = min( $this->dim, $vec->dim );
		
		if( $min != $this->dim )
		{
			trigger_error( __METHOD__.": different sized Vectors.", E_USER_NOTICE );
		}

		for( $i = 0; $i < $min; $i++ )
		{
			$this->vector[$i] = $this->cube( $this->vector[$i] + $vec->vector[$i] );
		}
	}
		
	

	/**
	 * Subtract Vector from this Vector and return result as new Vector.
	 * There are two possible differences. Convention is always use the smallest one.
	 * b + ( a - b ) = a
	 * @param  Vector $vec
	 * @return Vector
	 * @see sub_to()
	 */
	public function sub( Vector $vec )
	{		
		$min = min( $this->dim, $vec->dim );
		
		if( $min != $this->dim ) 
		{
			trigger_error( __METHOD__.": different sized Vectors.", E_USER_NOTICE );
		} 
		
		$new_vec = clone $this;	

		for( $i = 0; $i < $min; $i++ )
		{
			//(a-b)
			$component = $this->vector[$i] - $vec->vector[$i];

			if( $component > 0 ) //+ve means
			{

				//If (a-b) is big +ve, get from b to a thru -ve wall
				if( $component > $new_vec->cube_size/2.0 )
				{				
					$new_vec->vector[$i] = $component - $new_vec->cube_size;
				}
				//else get from b to a +ve way
				else
				{
					$new_vec->vector[$i] = $component;
				}
			}
			else //0 or -ve.
			{
				//If (a-b) is big -ve get from b to thru +ve wall
				if( $component <  ( - $new_vec->cube_size/2.0 ) )
				{
					$new_vec->vector[$i] = $new_vec->cube_size + $component;
				}
				//else get from b to a -ve way
				else
				{
					$new_vec->vector[$i] = $component;
				}
			}
		}
		return $new_vec;
	}	
	


	/**
	 * Subtract from this Vector.
	 * This is a bit screwy. On init/adding/setting/mult VectorLOOPED always sets the value within the cube.
	 * This function can return a -ve vector. It always uses the closest option. 
	 * @param Vector $vec
	 */
	public function sub_to( VectorLOOPED $vec )
	{
		$min = min( $this->dim, $vec->dim );
		
		if( $min != $this->dim )
		{
			trigger_error( __METHOD__.": different sized Vectors.", E_USER_NOTICE );
		}

		for( $i = 0; $i < $min; $i++ )
		{
		
			$component = $this->vector[$i] - $vec->vector[$i];

			//dont use set()! Wraps!
			if( $component > 0 ) //+ve means
			{

				//if component is big +ve get there -ve way.
				if( $component > $this->cube_size/2.0 )
				{				
					$this->vector[$i] = $component - $this->cube_size;
				}
				//else get there +ve way
				else
				{
					$this->vector[$i] = $component;
				}
			}
			else //0 or -ve.
			{
				//if component is big negative get there +ve
				if( $component < -$this->cube_size/2.0 )
				{
					$this->vector[$i] = $this->cube_size + $component;
				}
				//else get there -ve
				else
				{
					$this->vector[$i] = $component;
				}
			}
		}
	}
	
	

	/**
	 * Multiply this Vector by $n, and return result as new Vector
	 * @param float $n
	 * @return Vector
	 * @see mul_to()
	 */
	public function mul( $n )
	{
		$v = clone $this;
		
		for( $i = 0; $i < $this->dim(); $i++ )
		{
			$v->vector[$i] = $this->cube( $this->vector[$i] * $n );
		}
		
		return $v;
	}	



	/**
	 * Multiply this Vector by $n.
	 * @param float $n
	 */
	public function mul_to( $n )
	{
		for( $i = 0; $i < $this->dim(); $i++ )
		{
			$this->vector[$i] = $this->cube( $this->vector[$i] * $n );
		}
	}



	/**
	 * Looped distance metric so left of cluster links to right top to bottom etc.
	 * Needed this to properly test dimensionality effects with surface effects present.
	 * Very much like sub_to().
	 * @param Vector $v
	 */
	public function distance( Vector $v )
	{
		$temp = $this->sub( $v );	
		return $temp->abs();
	}



	/**
	 * Wraps - or 'cubes' - a value about the cube_size.
	 * E.g. suppose cs = 3 v = -3.5 then we want 2.5
	 * -3.5-(-6) = 2.5
	 * suppose cs = 3 v = 3.5 then we want 0.5
	 * 3.5 - 3  = 0.5
	 * @param float $value.
	 * @return float cubed value.
	 */
	public function cube( $value )
	{
		return (float) $value - floor( $value / $this->cube_size ) * $this->cube_size;
	}
}


/* Simple Testing
$x = new VectorLOOPED( array( 0.3,0.5 ), array( 'cube_size' => 1 ) );
$y = new VectorLOOPED( array( 0.4,0.6 ), array( 'cube_size' => 1 ) );
print $y->dim()."\n";
print $x->dim()."\n";

print "z = 0.7 0.1\n";
$z = $x->add( $y );
print "$x\n$y\n$z\n\n";

print "z = 0.0 0.6\n";
$z = $x->add( $z );
print "$x\n$y\n$z\n\n";

print "z = 0.3 0.1\n";
$z = $x->add( $z );
print "$x\n$y\n$z\n\n";

print "z = -0.1 -0.1\n";
$z = $x->sub( $y );
print "$x\n$y\n$z\n\n";

print "z = 0.1 0.1\n";
$z = $y->sub( $x );
print "$x\n$y\n$z\n\n";

print "y = 0.7 0.1\n";
$y->add_to( $x );
print "$x\n$y\n$z\n\n";

print "y = 0.4 -0.4\n";
$y->sub_to( $x );
print "$x\n$y\n$z\n\n";

print "y = 0.8 0.2\n";
$y->mul_to( 2 );
print "$x\n$y\n$z\n\n";
*/

/*
$x = new VectorLOOPED( array( 0.1,0.1 ), 1 );
$y = new VectorLOOPED( array( 0.9,0.9 ) );
print $y."\n";
print $x."\n";

print "distance:\n";
print $x->distance( $y );
print $y->distance( $x );

print "\nsubs:\n";
print $x->sub( $y );
print $y->sub( $x );

print "\nsub to:\n";
$x->sub_to( $y );
$y->sub_to( $x );
print $x.$y;
*/

?>
