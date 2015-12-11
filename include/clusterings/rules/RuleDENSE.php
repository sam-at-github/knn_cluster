<?php

/**
 * Repstream density related measure.
 * @bug $sample_k > KNNVector->get_k() causes exception coz not checked.
 */
class RuleDENSE
{
  const RULE_DENSE_DEFAULT_ALPHA = 1.4;
  const RULE_DENSE_DEFAULT_SAMPLE_K = 10;
  private $alpha = self::RULE_DENSE_DEFAULT_ALPHA;
  private $sample_k = self::RULE_DENSE_DEFAULT_SAMPLE_K;

  public function __construct(Array $options)
  {
    if(isset($options['alpha']))
    {
      $this->alpha = $options['alpha'];
      unset($options['alpha']);
    }
    if(isset($options['sample_k']))
    {
      $this->sample_k = $options['sample_k'];
      unset($options['sample_k']);
    }
    //If invalid options die.
    if(! empty($options))
    {
      throw new InvalidArgumentException("Unknown options '".implode(",", array_keys($options))."' given to constructor");
    }
  }  

  public static function has_option($option)
  {
    return in_array($option, array('alpha', 'sample_k'));
  }

  public function get_options()
  {
    $options = array();
    $options['alpha'] = $this->alpha;
    $options['sample_k'] = $this->sample_k;
    return $options;
  }

  public function rule(KNNVector $node, KNNVector $neighbour_node, Array $options)
  {
    $node_density = $node->get_density($this->sample_k);
    $neighbour_density = $neighbour_node->get_density($this->sample_k);
    if($node_density > $neighbour_density)
    {
      $max = $node_density;
      $min = $neighbour_density;
    }
    else
    {
      $max = $neighbour_density;
      $min = $node_density;
    }
    //if they are both 0.0 for example
    if($min == $max)
    {
      return true;
    }
    //fine in php.
    elseif($min == 0)
    {
      return false;
    }
    else
    {
      return ( (max($node_density, $neighbour_density) / min($node_density, $neighbour_density)) <= $this->alpha);
    }
  }
}
?>
