<?php

/** 
 * Std old RC rule.
 */
class RuleRCKNN
{

  public static function has_option($option)
  {
    return false;
  }

  public function get_options()
  {
    return array();
  }

  public function rule(KNNVector $node, KNNVector $neighbour_node, Array $options)
  {
    return $neighbour_node->is_knn($node, $options['k']);
  }
}
?>
