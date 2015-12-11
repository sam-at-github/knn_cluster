<?php

require_once('KNNVector.php');
require_once('AbstractKNNCluster.php');
require_once('AbstractKNNClustering.php');

/**
 * Represents a cluster in a heirarchical clustering.
 * @todo KNNVector root pointer hack should be fixed.
 * @todo cluster_color_label is just the cluster label of the max child.
 *  @todo relationship between K and height is confusing.
 * This class is really at least 2 or 3 classes mixed into one:
 *    NTree with root pointer option
 *    Implementation of Cluster, Clustering and a proper binding to KNNVector.
 * Dont have time to fix it.
 * Note for a node of height 0; cluster_k=0, clustering_k=-1, and so on.
 * I.e. clustering_k = height - 1.
 */
class NTreeClustering implements AbstractKNNClustering, AbstractKNNCluster
{
  private static $cluster_count = 1;
  //Unfortunately cant private this build tree needs it - i think.
  protected $children = array(); //AKA clusters.
  //These fields hold the clusterings state.
  private $is_leaf = false;
  private $parent = null;
  private $height = 0;
  private $size = 0;
  private $cluster_label = 0;
  protected $cluster_color_label = 0;
  private $max_child = null;
  private $max_child_size = 0;

  /**
   *
   */
   protected function __construct(KNNVector $data = null)
   {
     $this->cluster_label = self::$cluster_count++;
     $this->cluster_color_label = $this->cluster_label;

     if($data)
     {
       //Changed from size = 1 to handle potential duplicate rep KNNVectors.
       $this->size = $data->get_size();
       $this->is_leaf = true;
       $this->children = array($data);
       $this->data = $data;
       $data->set_cluster($this);
      $data->set_root_cluster($this);
     }
     else
     {
       $this->size = 0;
       $this->is_leaf = false;
     }
   }

  /**
   * Get number of children/clusters this node has.
   * Required by AbstractKNNClustering.
   */
  public function get_num_clusters()
  {
    if($this->is_leaf)
    {  
      return 0;
    }
    else
    {
      return sizeof($this->children);
    }
  }

  /**
   * Get the maximum possible k this clustering can cluster to.
   * Depends on build time option k_max, the graph and the actual clustering.
   * Required by AbstractKNNClustering.
   */
  public function get_k_max()
  {
    return $this->get_height() - 1;    
  }

  /**
   * Get the K value of this clustering.
   * Required by AbstractKNNClustering.
   */
  public function get_clustering_k()
  {
    return $this->get_height() - 1;
  }

  /**
  * Get the size - number of leaves under this.
  * Required by AbstractKNNCluster.
  */
  public function get_size()
  {
    return $this->size;
  }

  /**
  * Get k of cluster.
  * Required by AbstractKNNCluster.
  */
  public function get_cluster_k()
  {
    return $this->get_height();
  }

  /**
   * Get the clusters unique label.
   * Clusters must have unique labels
   * Required by AbstractKNNCluster.
   */
  public function get_cluster_label()
  {
    return $this->cluster_label;
  }

  /**
   * Get the label of the cluster we think the cluster really is.
   * This might be called get max childs label
   * The color label will be unique at a any mixture of heights as long as in different branches - not children.
   * Required by AbstractKNNCluster.
   */
  public function get_cluster_color_label()
  {
    return $this->cluster_color_label;
  }

  /**
   * Returns the parent node of current if exists else returns null.
   */
  public function get_parent()
  {
    return $this->parent;
  }

  /**
   * Get the root node of this node. If this is root returns this.
   */
  public function get_root()
  {
    $root = $this;
    while($root->parent != null)
    {
      $root = $root->get_parent();
    }
    return $root;
  }

  /**
   * Get height of node.
   */
  public function get_height()
  {
    return $this->height;
  }  

  /**
   * Same as iterator.
   */
  protected function get_children()
  {
     if($this->is_leaf)
     {  
       return array();
     }
     else
     {
       return $this->children;
     }
  }

  /**
   * Get the maximum child of this cluster.
   */  
  public function get_max_child()
  {
    return $this->max_child;
  }  

  /**
   * Returns an array containing all leaf node data.
   * Whats is in the leaf node data is arbitrary.
   */
  public function get_leaves()
  {
    if($this->is_leaf)
    {
      return array($this);
    }
    else
    {
      $nodes = array();
      foreach($this->children as $child)
      {
        $nodes = array_merge($nodes, $child->get_leaves());
      }
      return $nodes;
    }
  }

  /**
   * Get the data from one or many leaf nodes. 
   * get_leaves() only returns nodes themselves.
   * Can iterate on leaf to get value but for convenience.
   * @returns Array containing all data in leaves under this.
   */
  public function get_leaf_data()
  {
    if($this->is_leaf)
    {
      return array($this->children[0]);
    }
    else
    {
      $data = array();
      $leaves = $this->get_leaves();
      foreach($leaves as $leaf)
      {
        $data = array_merge($data, $leaf->get_leaf_data());
      }
    }
    return $data;
  }

  /**
   * Get data from strictly a leaf node.
   */
  public function get_data()
  {
    if(! $this->is_leaf)
    {
      throw new NTreeClusteringException("Called ".__METHOD__." on non leaf");
    }
    return $this->children[0];
  }    

   /**
    * Only required function for IteratorAggregate I.f.
    * ArrayIterator implements Iterator for you on the array.
    * Required by AbstractKNNClustering.
    */
   public function clustering_getIterator()
   {
     if($this->is_leaf)
     {  
       return new ArrayIterator(array());
     }
     else
     {
       return new ArrayIterator($this->children);
     }
   }

  /**
   * Get an iterator over KNNVectors in this cluster.
   * Required by AbstractKNNCluster.
   */
  public function cluster_getIterator()
  {
    return new ArrayIterator($this->get_leaf_data());
  }  

  /**
   * Is this node a leaf.
   */
  protected function is_leaf()
  {
    return $this->is_leaf;
  }

   /** 
    * Add a child node to this node. Order is arbitrary in a N-Tree.
    */
  protected function add_child(NTreeClustering $cluster)
  {
    if($this->is_leaf)
    {
      throw new NTreeClusteringException("Cannot add child nodes to leaf nodes!");
    }
    if($cluster === $this)
    {
      throw new NTreeClusteringException("Trying to add self as child of self!");
    }

    $this->update_color_label($cluster);

    //update tree structure.
    $this->children[] = $cluster;
    $cluster->parent = $this;

    //this is more efficient than chaining every time.
    foreach($cluster->get_leaf_data() as $node)
    {
      $node->set_root_cluster($this);
    }

    //update max child
    if(sizeof($this->children) == 0)
    {
      $this->max_child = $cluster;
      $this->max_child_size = $cluster->size;
    }
    else
    {
      if($cluster->size > $this->max_child_size)
      {
        $this->max_child = $cluster;
        $this->max_child_size = $cluster->size;
      }
    }

    //update height.        
    $this->height = max(($cluster->height + 1), $this->height);  

    //update ancestor nodes.
    if($this->parent)
    {
      $this->parent->add_update($this, $cluster->size);
    }
    $this->size += $cluster->size;  
  }

  /**
  * Maintain heights and sizes after inserts on childs.
  */
  protected function add_update($child, $growth)
  {
    $this->height = max(($child->height + 1), $this->height);
    $this->size += $growth;
    if($this->parent)
    {
      $this->parent->add_update($this, $growth);
    } 
  }

  /**
  * Update the color match of this cluster helper to 
  * We want to say this cluster is somehow more like one of the clusters below, even though really new unless has one child.
  * There are different criteria to choose the best child so this method can be overridden.
  * Uses largest. Uses the term color coz generic realtes to graphing.
  * @todo data hiding.
  */
  protected function update_color_label(NTreeClustering $child)
  {
    // *zero* means this is called *before* inserting $shild in add_child() code.
    if($this->get_num_clusters() == 0)
    {
      $this->cluster_color_label = $child->cluster_color_label;
    }
    elseif($child->size > $this->max_child_size)
    {
      $this->cluster_color_label = $child->cluster_color_label;
    }
  }

/*
 * Stats purity stability.
 * Hard to implement this functionality else where.
 */  

  /**
  * Get the total stability of this cluster.
  * A cluster is total stable iff it has only one child and is not a leaf.
  * Any number returned greater than zero means total stable, but further childs are counted too.
  * @return the length of the chain of clusters that have one child including this one. 
  */
  public function get_stability_total()
  {
    $stab = 0;
    $node = $this; 

    while((! $node->is_leaf) && ($node->get_num_clusters() == 1))
    {
      $stab++;
      $node = $node->children[0];
    }
    return $stab;     
  }

  /**
   * Get the stability of this node.
   */
  public function get_stability_points()
  {
    $stab = 0;
    if($this->is_leaf)
     {
       $stab = 0;
     }
     else
     {
       $stab = $this->max_child_size / $this->size;
     }
     return $stab;
   }

  /**
  * get the average stabiltiy over the last n childs.
  * If the cluster has less than n childs then
  * the n-m pa is returned instead. - NOP.
  */
  public function get_stability_points_3pma()
  {
    if($this->height == 0)
    {
      return $this->get_stability_points();
    }
    elseif($this->height == 1)
    {
      return ($this->get_stability_points() + $this->max_child->get_stability_points()) / 2;
    }
    else
    {
      $stab1 = $this->get_stability_points();
      $stab2 = $this->max_child->get_stability_points();
      $stab3 = $this->max_child->max_child->get_stability_points();
      return ($stab1 + $stab2 + $stab3) / 3;
    }
  }

  /**
  * Faster method to tell is stable total.
  */
  public function is_stability_total()
  {     
    if($this->get_num_clusters() == 1)
    {
      return 1;
    }
    else
    {
      return 0;
    }
  }  

/*
 * Cluster*ing* wise stability - aggregate.
 */

  /**
   * Get cluster*ing* total stability, total.
   * Is the ratio of clusters that are stable total.
   * this has 4 childs but sum of their child is 6  => 4/6.
   * I.e. the totcahng in the number of clusters
   */
  public function get_clustering_stability_total()
  {
    $sum = 0;

    if($this->get_num_clusters() == 0)
    {
      return 0;
    }

    foreach($this as $cluster)
    {
      if($cluster->is_leaf)
      {
        $sum++;
      }
      else
      {
        $sum += $cluster->get_num_clusters();
      }
    }
    return $this->get_num_clusters()/$sum;
  }

  /**
   * get clustering stability as avg points stab of clusters.
   * sum((|Cik-1|/|Cik|)) / |C|
   */
  public function get_clustering_stability_points_unwghtd()
  {
    $sum = 0;

    if($this->get_num_clusters() == 0)
    {
      return 0;
    }

    foreach($this as $cluster)
    {
      $sum += $cluster->get_stability_points();
    }
    return $sum/$this->get_num_clusters();
  }

  /**
   * get stability in total points change, weight by the size of a cluster.
   * sum((|Cik-1|/|Cik|)*|Cik|) / N
   */
  public function get_clustering_stability_points_wghtd()
  {
    $sum = 0;
    foreach($this as $cluster)
    {
      if(! $cluster->is_leaf)
      {
        $sum += $cluster->max_child_size;
      }
    }
    return $sum / $this->size;  
  }

  /**
   * required by AbstractKNNClustering.
   */
  public function clustering_toString()
  {
    return "";
  }

  /**
   * required by AbstractCluster.
   */
  public function cluster_toString()
  {
    return "";
  }

  /**
   * Prints indented recursive string rep of an entire tree.
   */
  public function tree_toString($indent = "")
  {
    $str = $indent."height=".$this->get_height().", num_children=".$this->get_num_clusters().", size=".$this->get_size()." \n";
    $indent .= "\t";

    if($this->is_leaf)
    {
      $str .= $indent.$this->get_data()."\n";
    }
    else
    {
      foreach($this->children as $child)
      {
        $str .= $child->tree_toString($indent);
      }
    }
    return $str;
  }
}

/**
 * Token Exception class.
 * Only later >=5.3 support chaining.
 */
class NTreeClusteringException extends Exception
{
  public function __construct($msg, $code = 0)
  {
    parent::__construct($msg, $code);
  }
}
?>
