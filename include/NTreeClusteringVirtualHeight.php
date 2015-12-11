<?php

require_once('NTreeClustering.php');

/**
 * Overloads some method of NTreeClustering, to implement a virtual height, adds method set_height().
 * Allows dynamic setting of the height at which children are returned.
 * Caveat is virtual height of zero is valid but iterator returns array() coz thats what NTreeClustering of height 0 is.
 * In normal KNN set_option_k() uses this to iterate over the clusters at a level k.
 */  
abstract class NTreeClusteringVirtualHeight extends NTreeClustering
{
  private $virtual_children = array();
  private $is_virtual = false;
  private $virtual_height = null;  

  /**
   * Construct a new NTreeClusteringVirtualHeight object.
   * Initial state is not virtual
   */
  protected function __construct($data)
  {
    parent::__construct($data);
  }

  /**
   * Get the number of children in the collection.
   * We almost always additionally need want to get the size of a collection not just iterat of it.
   * @overrides
   */
  public function get_num_clusters()
  {
    if($this->is_virtual())
    {
      return sizeof($this->virtual_children);
    }
    else
    {
      return parent::get_num_clusters();
    }
  }

  /**
   * 
   */
  public function get_clustering_k()
  {
    if($this->is_virtual())
    {
      return $this->virtual_height - 1;
    }
    else
    {
      return parent::get_clustering_k();
    }  
  }

  /**
   * Get Iterator over the contained clusters.
   * @overrides.
   */
  public function clustering_getIterator()
  {
    if($this->is_virtual())
    {
      return new ArrayIterator($this->virtual_children);
    }
    else
    {
      return parent::clustering_getIterator();
    }
  }

  /**
   * Required by KSweepable
   */
  public function set_option_k($k)
  {
    $this->set_height($k + 1);
  }

  /**
   * Set the height possibly virtual.
   * height of zero is valid but ...
   * @param $height valid height is in range zero to underlying height. 
   */
  protected function set_height($height)
  {  
    if(($height === null) || ($height == $this->get_height()))
    {
      $this->unvirtualize();
    }
    elseif($height >= $this->get_height() )
    {
       throw new OutOfBoundsException("Height too large for NTreeClustering height: ".$height." > ".$this->get_height());
    }
    elseif($height <= 0)
    {
      throw new OutOfBoundsException("Height must be non negative");
    }
    else
    {
      if($height == 0)
      {
          $this->virtual_children = array();
      }    
      else
      {
        //print "Getting virt children at $height\n";
        $this->virtual_children = self::dfs_get_virtual_children($this, $height);
      }

      //Only place this switch happens.
      $this->is_virtual = true;
      $this->virtual_height = $height;
    }
  }

  /**
   * Whether this clustering represents itself or the clustering at some k level under it.
   * Is encoding state in the var like this good practice? Id say no it is not. Just look at C error int codes...
   */
  public function is_virtual()
  {
    return $this->is_virtual;
  }

  /**
   * Set the clustering back to normal
   */
   public function unvirtualize()
   {
     $this->is_virtual = false;
   }

  /**
   * Collect all the descendents below a node, with height *less* than a given height,
   * That is those descendents are direct childlren of a virtual node with the height.
   * If height is zero returns an empty array because node of hieght zero have no children.
   * Warning if height is -ve or greater than underlying height reverts to 0 and height respectively. Check this at higher level.
   * @param $tree NTreeClustering
   * @param $height the virtual height.
   * @exception the height to find is greater or equal to height of the initial node.
   */
  private function dfs_get_virtual_children(NTreeClustering $tree, $height)
  {
    $collected_children = array();

    // Clusterings of height 0 return empty arrays on get_children().
    if($height < 0)
    {
      $collected_children =  array();
    }
    elseif($tree->get_height() < $height)
    {
      // Height of current tree is less than height.
      // This else is entered into *once* on the node with the highest height in the branch.
      $collected_children = array($tree);
    }    
    else
    {
      // Recursive call on childs, $tree height is still -ge to height we want.
      foreach($tree->get_children() as $subtree)
      {
        $collected_children = array_merge($collected_children, self::dfs_get_virtual_children($subtree, $height));    
      }
    }

    return $collected_children;
  }    
}
?>
