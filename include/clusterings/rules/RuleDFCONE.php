<?php

/**
 * A rule that ignores points lying at a great angle from the average vector of a point, if the average vector is of significant magnitude.
 * 3 static options deterine static behaviour of rule.
 * @bug $sample_k > KNNVector->get_k() causes exception coz not checked.
 * @see __construct().
 */
class RuleDFCONE
{
	const RULE_FCONE_DEFAULT_SIGNIF = 0.25;
	const RULE_FCONE_DEFAULT_SAMPLE_K = 10;
	private $signif = self::RULE_FCONE_DEFAULT_SIGNIF;
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
		return in_array( $option, array( 'signif', 'sample_k' ) );
	}
	
	
	
	
	public function get_options()
	{
		$options = array();
		$options['signif'] = $this->signif;
		$options['sample_k'] = $this->sample_k;
		return $options;
	}
	
	
	
	/**
	 * Rule based on two static thresholds, specifying an angle btwn the avg and to vectors, and a significance of the avg vector.
	 * Could be more efficent probly at cost of readability.
	 */
	public function rule( KNNVector $node, KNNVector $neighbour_node, Array $options )
	{
		$merge = true;
		// Get the average vector normalizd w.r.t the average distance.
		// Its abs the average cosine of difference vector from the average vector.  
		$avg_vec = $node->get_avg_vector_norm( $this->sample_k );
		$signif = $avg_vec->abs();
		// Calc the difference vector.
		$node_vec = $node->get_vector();
		$neighbour_vec = $neighbour_node->get_vector();
		$diff_vec = $neighbour_vec->sub( $node_vec );
			
		if( $signif > $this->signif )
		{
			// Cant get the cosine of angle between a vector and a zero vector!
			// So treat angle between 0 vec and something else as zero => should merge.
			if( ( $avg_vec->abs() > 0 ) && ( $diff_vec->abs() > 0 ) )
			{  
				$diff_cos = $avg_vec->cosine( $diff_vec );
				//If the cosine of the angle is than that of the average consine of angle between neighbours and the avg vector dont merge.
				if( $diff_cos < $signif )
				{
					$merge = false;
					//$a1 = (180/M_PI)*acos( $diff_cos ); $av = (180/M_PI)*acos( $signif ); print "Found neighb outside dyn cone: $a1 > $av\n";
					
				}
			}
		}
		//print $merge."\n"; print " $angle $signif  ".( ( $signif < $this->signif ) or ( $angle < $this->angle ) )."\n";
		// If the angle greater than the t_hold and:
		return $merge;
	}
}
?>
