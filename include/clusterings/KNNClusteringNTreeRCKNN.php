<?php

require_once("set_include_path.php");
require_once("NTreeClusteringVirtualHeight.php");
require_once("NTreeClustering.php");
require_once("KSweepableClustering.php");
require_once("LoadableAbstractKNNClustering.php");
require_once("CliProgressUpdater.php");

/**
 * Represents a cluster in a heirarchical clustering.
 * Has a post init settable K option. If a clustering has an option setting it may effect the clustering.
 * Any hierarchical clustering can be treated as a series of levels of partitions of partitions of ... to the leafs.
 * @todo root pointer hack should be fixed.
 */
class KNNClusteringNTreeRCKNN extends NTreeClusteringVirtualHeight implements LoadableAbstractKNNClustering, KSweepableClustering
{
  const CLUSTERING_TYPE = 'rcknn';
  /* pointer to graph contructed from. */
  private $graph;

  /**
   * RCKNN Cluster the data.
   * Cluster up the data based on recipricol-ality of first $k neighbours.
   * The RCKNN tree only ever needs building once. Changing the only option, k, does not require a rebuild.
   * Sets k_max to min(k_max, k_unity) and adds children to this node.
   * Options:
   *   k should not really set here but equivalent to calling set_option_k() once built.
   *  k_max the maximum k to cluster to, actual max is min(graph->k_max, k_max).
   * @param $graph KNNGraph to cluster up.
   * @param $options Array associative array of settings.
   */
  public function __construct(KNNGraph $graph, Array $options = array())
  {
    // Construct an internal type NTreeNode to hold children.
    parent::__construct(null);
    $this->graph = $graph;
    $k_max;
    $k;

    // Set options none of them are needed after construction 
    // Set the k_max
    if(isset($options['k_max']))
    {
      if($options['k_max'] < 0)
      {
        throw new OutOfBoundsException("k_max must be non negative");
      }
      if($options['k_max'] > $graph->get_k_max())
      {
         throw new OutOfBoundsException("k_max too large for graph");
      }
      $k_max = $options['k_max'];
      unset($options['k_max']);
    }
    else
    {
      $k_max = $graph->get_k_max();
    }  
    // Set the k option. Set after clustering and may cause construct to fail if too big.
    if(isset($options['k']))
    {
      $k = $options['k'];
      unset($options['k']);
    }
    //If invalid options die.
    if(! empty($options))
    {
      throw new InvalidArgumentException("Unknown options given to constructor");
    }

    $this->build_clustering($k_max);

    // If k option was passed in try and set it.
    // Otherwise it is set by current height.
    if(isset($k))
    {
      $this->set_option_k($k);
    }
  }

  /**
   * Get the type of this clustering.
   */
  public function get_type()
  {
    return self::CLUSTERING_TYPE;
  }

  /**
   * Check if has init time option.
   */
  public static function has_option($option)
  {
    return in_array($option, array("k", "k_max"));
  }

  /**
   * Get set options how this class sees fit.
   * Dont return k_max; not important.
   */
  public function get_options()
  {
    $options = array();
    $options['k'] = $this->get_option_k();
    return $options;
  }

  /**
   * Get init time options.
   * Actually will get k.
   */
  public function get_option($option_name)
  {
    $method = "get_option_".$option_name;
    if(! method_exists($this, $method))
    {
      return null;
    } 
    return $this->$method();
  }

  /**
   * k refers to the height of the cluster*ing* NOT cluster.
   * The height of a leaf node is zero T.f this return -1.
   */
  public function get_option_k()
  {
    return $this->get_clustering_k();
  }

  /**
   * k refers to the height of the cluster*ing* NOT cluster.
   */
  public function get_option_k_max()
  {
    return $this->get_k_max();
  }

  /**
   * Set the k option.
   * Null is valid and means biggest logical value. If the k value is same as current nothing happens.
   * Whether tree is virtual or not decided after build.
   * @param k int k >= 0 && k < graph->k_max.
   */
  public function set_option_k($k)
  {    
    if($k < 0)
    {
      throw new OutofBoundsException("K must be non negative. Cannot set K to '".$k."'");
    }
    $this->set_height($k + 1);
  }

  /**
   * Cluster method required by interface, does nil here.
   * The point was you change the post init time options of a clustering,
   * it may need reclustering so you call this method to do that when youve set all the options.
   */
  public function cluster()
  {}

  /** 
   * Build the clustering with this as root.
   */
  private function build_clustering($k_max)
  {  
    // Set initial group of NTreeClustering. Leaf nodes are special.
    // Could even do a copy here. Would be great to set up copy on write mech.
    $progress = new CliProgressUpdater();
    $clusters = array();

    foreach($this->graph as $node)
    {
      array_push($clusters, new NTreeClustering($node));
    }    

    $k_curr = 1;
    while(($k_curr <= $k_max) && (sizeof($clusters) > 1))
    {
       // holds the k+1 clustering of clusters in $clusters.
      $new_clusters = array();
      foreach($clusters as $cluster)
      {
        // Parented clusters are already part of some other NTreeClustering/Partition at this level.
        if(! $cluster->get_parent())
        {
          // Create a new clustering to hold the component connected to this so far unseen cluster.
          $new_cluster = new NTreeClustering();
          self::dfs_add_rcknn_connected($k_curr, $cluster, $new_cluster);
          array_push($new_clusters, $new_cluster);
        }
      }

      // Ruff estimate of progress.
      $progress->update($k_curr/$k_max);
      unset($clusters);
      $clusters = $new_clusters;
      $k_curr++;
    }

    // The Clustering has height of what ever K value the last clustering reached + 1.
    // If the array of NTreeClusterings, $clusterings has size 1 means reached unity.
    // If not height = graph->k_max + 1.
    // So if unity reached the root of the tree has one child.
    // The children of the root always represents the height - 1 clustering i.e they are clusters in the k=  height - 1 clustering.
    foreach($clusters as $cluster)
    {
      $this->add_child($cluster);
    }
  }  

  /**
   * Implements the RCKNN merging of clusters.
   * Helper to build_clustering().
   */
  private static function dfs_add_rcknn_connected($k, NTreeClustering $cluster, NTreeClustering $new_cluster)
  {    
    $new_cluster->add_child($cluster);

    // D.F.S.
    foreach($cluster->get_leaf_data() as $node)
    {
      for($j = 0; $j < $k; $j++)
      {  
        $neighbour_node = $node->get_nn($j);
        $neighbour_cluster = $neighbour_node->get_root_cluster();

        // Is rcknn and its is not part of any other (could only be $new_cluster) cluster.
        if(($neighbour_cluster !== $new_cluster) && $neighbour_node->is_knn($node, $k))
        {
          self::dfs_add_rcknn_connected($k, $neighbour_cluster, $new_cluster);
        }
      }
    }
  }    

  /**
   * Implements the RCKNN merging of clusters in non DFS way. Speed is similar. 
   * Helper to build_clustering().
   */
  private static function add_rcknn_connected($k, NTreeClustering $cluster, NTreeClustering $new_cluster)
  {    
    $n_chk_clusters = array();
    $chk_clusters = array();
    // Sets the root cluster of the added cluster to caller.
    $new_cluster->add_child($cluster);
    array_push($chk_clusters, $cluster);

    while(! empty($chk_clusters))
    {
      foreach($chk_clusters as $curr_cluster)
      {

        foreach($curr_cluster->get_leaf_data() as $node)
        {
          for($j = 0; $j < $k; $j++)
          {  
            $neighbour_node = $node->get_nn($j);
            $neighbour_cluster = $neighbour_node->get_root_cluster();

            // Is rcknn and its is not part of any other (could only be $new_cluster) cluster.
            if(($neighbour_cluster !== $new_cluster) && $neighbour_node->is_knn($node, $k))
            {
              $new_cluster->add_child($neighbour_cluster);
              array_push($n_chk_clusters, $neighbour_cluster);
            }
          }
        }
      }
      $chk_clusters = $n_chk_clusters;
      $n_chk_clusters = array();
    }
  }  

/*
 * toString methods
 */  

  /**
   * Print a string representation of this KNN Graph.
   * Has to be either a cluster or clustering so chose -ing.
   */
  public function __toString()
  {
    //print "Inside ".__CLASS__."::".__METHOD__." object type = ".get_class($this)."\n";
    return $this->clustering_toString();
  }
}

/**
 * Token Exception class.
 * Only later >=5.3 support chaining.
 */
class KNNClusteringNTreeRCKNNException extends Exception
{
  public function __construct($msg, $code = 0)
  {
    parent::__construct($msg, $code);
  }
}
?>
