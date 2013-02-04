<?php

/**
 * A rule that ignores points lying at a great angle from the average vector of a point, if the average vector is significant.
 * 3 static options determine static behaviour of rule.
 * @bug $sample_k > KNNVector->get_k() causes exception coz not checked.
 * @see __construct().
 */
class RuleFCONE
{
	const RULE_FCONE_DEFAULT_SIGNIF = 0.5;
	const RULE_FCONE_DEFAULT_ANGLE = 90.0;
	const RULE_FCONE_DEFAULT_SAMPLE_K = 14;
	private $signif = self::RULE_FCONE_DEFAULT_SIGNIF;
	private $angle = self::RULE_FCONE_DEFAULT_ANGLE;
	private $sample_k = self::RULE_FCONE_DEFAULT_SAMPLE_K;
	
	
	
	/**
	 * Construct a new Rule. Options are:
	 *	signif: Threshold on the significance of the average vector.
 	 *	angle: Threshold on the angle between the average vector and the difference vector.
 	 *	sample_k: The sample_k to use.
 	 * If and only if the angle is less than option angle or significane less than option signif rule returns passes.
 	 */
	public function __construct( Array $options )
	{
		if( isset( $options['signif'] ) )
		{
			$this->signif = $options['signif'];
			unset( $options['signif'] );
		}
		if( isset( $options['angle'] ) )
		{
			$this->angle = $options['angle'];
			unset( $options['angle'] );
		}
		if( isset( $options['sample_k'] ) )
		{
			$this->sample_k = $options['sample_k'];
			unset( $options['sample_k'] );
		}
		//If invalid options die.
		if( ! empty( $options ) )
		{
			throw new InvalidArgumentException( "Unknown options '".implode( ",", array_keys( $options ) )."' given to constructor" );
		}
	}	
	
	
	
	
	public static function has_option( $option )
	{
		return in_array( $option, array( 'signif', 'angle', 'sample_k' ) );
	}
	
	
	
	
	public function get_options()
	{
		$options = array();
		$options['signif'] = $this->signif;
		$options['angle'] = $this->angle;
		$options['sample_k'] = $this->sample_k;
		return $options;
	}
	
	
	
	/**
	 * Rule based on two static thresholds, specifying an angle btwn the avg and to vectors, and a significance of the avg vector.
	 * Could be more efficent probly at cost of readability.
	 */
	public function rule( KNNVector $node, KNNVector $neighbour_node, Array $options )
	{
		$avg_vec = $node->get_avg_vector_norm( $this->sample_k );
		$node_vec = $node->get_vector();
		$neighbour_vec = $neighbour_node->get_vector();
		$diff_vec = $neighbour_vec->sub( $node_vec );
		$signif = $avg_vec->abs();
		
		// Cant get the cosine of angle between a vector and a zero vector.
		if( ( $avg_vec->abs() > 0 ) && ( $diff_vec->abs() > 0 ) )
		{  
			$angle = abs( ( 180/M_PI ) * acos( $avg_vec->cosine( $diff_vec ) ) );
		}
		else
		{
			// Otherwise the angle between the avg vector and the difference vector is 0 and the angle t_hold is irrelevent.
			$angle = 0;
		}	
		//print " $angle $signif  ".( ( $signif < $this->signif ) or ( $angle < $this->angle ) )."\n";
		// If the angle greater than the t_hold and:
		return ( ( $signif < $this->signif ) or ( $angle < $this->angle ) );
	}
}
?>
