<?php

require_once('set_include_path.php');
require_once("KNNVector.php");

/**
 * This class is pretty much a convenient container for useful functions on an array of KNNVectors that represents a cluster.
 * such as get_purity() etc.
 * The AbstractKNNClustering abstract classes Iterator return these - there is no way to declare that in php though.
 */
interface AbstractKNNCluster
{

  /**
   *
   */
  public function cluster_getIterator(); //{ return new ArrayIterator($this->cluster); }

  /**
   * Get number of vectors in cluster.
   */
  public function get_size(); //{ return sizeof($this->cluster); }

  /**
   * Get the k of the cluster.
   * OK it is precievable maybe a cluster in a *KNN* graph has no logical k,
   * In that case return 0.
   */
   public function get_cluster_k();

   /**
    * Get the clusters unique label.
    * Clusters must have unique labels.
    * MUST be integers >= 1, for gnuplot usages. 
    * SHOULD use increments of 1 and not exceed the total number of points in a graph. 04/11, why? gnuplot something?
    */
   public function get_cluster_label();

  /**
   * Get the label of the cluster we think the cluster really is.
   * Think of "color" as a special type of equivalence between clusters for arbitrary use.
   * E.g. in hierarchical clustering a cluster and its max child may have the same color label indicating they are the same.
   * MUST be integers >= 1, for gnuplot usages. 
   * SHOULD use increments of and not exceed the total number of points in a graph. 04/11, why? gnuplot something?
   */
  public function get_cluster_color_label();

  /**
   * print what ever stats you want out
   */
  public function cluster_toString();
}
?>
