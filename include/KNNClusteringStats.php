<?php

require_once('set_include_path.php');
require_once('lib_knn_cluster.php');
require_once('AbstractKNNClustering.php');
require_once('AbstractKNNCluster.php');

/**
 * Summary statisistics of a KNNClustering.
 * Decided to put these in a separate static library rather than in some KNNClustering base class because 
 * 1) there is no base class only an interface
 * 2) to not clog up the core logic of a Clustering or Cluster.
 * This way Clustering/Cluster provide core arbitrary accessors from which stats can be built.
 * Slightly slower blackbox instead of whitebox approach (I want friendship or multiple inheritance)s.
 * Theres not that much to this class, just two options, but keeping it consistent with Vision class.
 * Its intended that given full output you should/will generate whatever other stats/data formats you need from this output.
 */
class KNNClusteringStats
{

  private static $default_options = array(
    'clusters' => false,
    'data' => false,
    );

  public static function stats_clustering(AbstractKNNClustering $clustering, $output_dir, $output_file, $stdout = null, Array $input_options  =  array())
  {
    $f_out = null;
    $options = array_merge(self::$default_options, $input_options);  

    //$output_file or $output_dir may be null
    if($output_file && $output_dir)
    {
      //Dont remove the dir if exists, unlike vision.
      if(! is_dir($output_dir))
      {  
        mkdir2($output_dir, true, true);
      }
      $f_out = fopen($output_dir."/".$output_file.".txt", "w");
    }

    //Build output
    $str = "";
    $str .= self::clustering_toString($clustering);
    if($options['clusters'])
    {
      foreach($clustering->clustering_getIterator() as $cluster)
      {
        $str .= self::cluster_toString($cluster);
        if($options['data'])
        {
          $str .= "######Data Begin######\n";
          foreach($cluster->cluster_getIterator() as $node)
          {
            $str .= "$node \n";
          }
        }
      }
    }

    //Output to optionally file and/or stdout.
    if($f_out )
    {
      fwrite($f_out, $str);
      fclose($f_out);
    }
    if($stdout)
    {
      print $str;
    }
  }

/*
 * Cluster wise stats methods.
 */

  /**
   * Get the label and count of each class found in a cluster.
   * Note use of get_size since a given KNNVector may have size > 1.
   */  
  public static function get_cluster_labels(AbstractKNNCluster $cluster)
  {
    $classes = array();
    foreach($cluster->cluster_getIterator() as $node)
    {
      if(! isset($classes[$node->get_class_label()]))
      {  
        $classes[$node->get_class_label()] = $node->get_size();
      }
      else
      {
        $classes[$node->get_class_label()] += $node->get_size();
      }
    }
    return $classes;  
  }

  /**
   * Purity.
   */
  public static function get_cluster_purity(AbstractKNNCluster $cluster)
  {
    $classes = self::get_cluster_labels($cluster);
    return  max($classes) / $cluster->get_size();
  }

  /**
   * Get the (information) Entropy in the label make up of cluster.
   * The greater the Entropy the less homogeneous the cluster is.
   * A similar measure to purity in that it is homogenity based but inverse proportional.
   * H(X) = sum(P(x)/(log(P(x))), x ele X
   */
  public static function get_cluster_entropy(AbstractKNNCluster $cluster)
  {
    $classes = self::get_cluster_labels($cluster);
    $tot = $cluster->get_size();
    $h = 0;
    foreach($classes as $num)
    {
      $p = $num/$tot;
      $h += $p*(log(1/$p, 2));
    }
    return $h;
  }

  /**
   * Standard all cluster type toString. Allow for cluster type specific output.  
   */
  public static function cluster_toString(AbstractKNNClustering $cluster )
  {
    //*must* have preceding '#'.
    $size = $cluster->get_size();
    $str =  "######Cluster Begin######\n";
    $str .= "#label: ".$cluster->get_cluster_label()."\n";
    $str .= "#color label: ".$cluster->get_cluster_color_label()."\n";
    $str .= "#size: $size\n";
    $str .= "#purity: ".self::get_cluster_purity($cluster)."\n";
    $str .= "#entropy: ".self::get_cluster_entropy($cluster)."\n";
    $str .= "#contains_labels: ";
    $labels = self::get_cluster_labels($cluster);
    if($labels)
    {
      foreach($labels as $label => $count)
      {  
        $str .= "$label $count; ";
      }
    }
    $str .="\n";
    //Allow the specific type of cluster to output its own stuff. Must have same format.
    $str .= $cluster->cluster_toString();
    return $str;
  }

/**
 * Clustering methods.
 */

  /**
   * Get the total number of nodes in a clustering.
   * $clustering->get_cluster_size() should probly be part of AbstractKNNClustering I.f.
   */
  public static function get_clustering_size(AbstractKNNClustering $clustering)
  {
    //If $clusteing implements cluster I.f.
    $size = 0;
    if($clustering instanceof AbstractKNNCluster)
    {
      $size = $clustering->get_size();
    }
    else
    {
      foreach($clustering->clustering_getIterator() as $cluster)
      {
        $size += $cluster->get_size();
      }
    }
    return $size;
  }

  /**
   * Get the label and count of each class found in an entire clustering.
   * Possibly should be part of AbstractKNNClustering I.f. too.
   * @todo
   */
  public static function get_clustering_labels(AbstractKNNClustering $clustering)
  {
    return array();  
  }

  /**
   * Get the avg purity.
   * Convention is that a clustering with no clusters has zero purity.
   */
  public static function get_clustering_purity_unwghtd(AbstractKNNClustering $clustering)
  {
    $sum = 0;

    if($clustering->get_num_clusters() == 0)
    {
      return 0;
    }

    foreach($clustering->clustering_getIterator() as $cluster)
    {
      $sum += self::get_cluster_purity($cluster);
    }
    return $sum / $clustering->get_num_clusters();
  }

  /**
   * Get the avg purity, weight influence according to size.
   * Convention is that a clustering with no clusters has zero purity.
   */
  public static function get_clustering_purity_wghtd(AbstractKNNClustering $clustering)
  {
    $sum = 0;

    if($clustering->get_num_clusters() == 0)
    {
      return 0;
    }    

    foreach($clustering->clustering_getIterator() as $cluster)
    {
      $sum += max(self::get_cluster_labels($cluster));
    }
    return $sum / self::get_clustering_size($clustering);
  }  

  /**
   * Get the unbiased cluster purity -  a greedy match based thing.
   * Convention is that a clustering with no clusters has zero purity.
   */
  public static function get_clustering_unbiased_purity(AbstractKNNClustering $clustering)
  {
    $sum = 0;
    $labels_set = array();

    if($clustering->get_num_clusters() == 0)
    {
      return 0;
    }    

    foreach($clustering->clustering_getIterator() as $cluster)
    {
      array_push($labels_set, self::get_cluster_labels($cluster));
    }

    while($labels_set)
    {
      $max = 0;
      $max_index = 0;
      $max_class = 0;

      //Find the max intersection, and which cluster it belongs to.
      foreach($labels_set as $this_index => $set)
      {
        $this_max = 0;
        $this_class = 0;

        //find max intersect in curr cluster.
        foreach($set as $class => $num)
        {
          if($num > $this_max)
          {  
            $this_max = $num;
            $this_class = $class;
          }
        }

        //update max max.
        if($this_max > $max)
        {
          $max = $this_max;
          $max_index = $this_index;
          $max_class = $this_class;
        }
      }
      //print "max $max \n"; print "max index $max_index \n"; print "max class $max_class \n";

      $sum += $max;

      // Remove max intersect cluster from consideration
      array_splice($labels_set, $max_index, 1);
      // Remove max intersect class from consideration.
      for($i = 0; $i < count($labels_set); $i++)
      {
        if(isset($labels_set[$i][$max_class]))
        {
          $labels_set[$i][$max_class] = 0;
        }
      }
    }
    return $sum / self::get_clustering_size($clustering);;
  }

  /**
   * Get the unbiased *un*weighted cluster purity - a greedy match based thing.
   * Convention is that a clustering with no clusters has zero purity.
   * point weighted unbiased purity is heavily effected by big classes.
   * In some data sets you have clusters masively bigger than others.
   * It depends on what you want to measure as to what measure you should use.
   * Are bigger clusters more important? 
   * This measure assumes not.
   */
  public static function get_clustering_unbiased_purity_unwghtd(AbstractKNNClustering $clustering)
  {
    $sum = 0;
    $labels_set = array();

    if($clustering->get_num_clusters() == 0)
    {
      return 0;
    }    

    foreach($clustering->clustering_getIterator() as $cluster)
    {
      array_push($labels_set, self::get_cluster_labels($cluster));
    }

    while($labels_set)
    {
      $max = 0;
      $max_index = 0;
      $max_class = 0;

      //Find the max intersection, and which cluster it belongs to.
      foreach($labels_set as $this_index => $set)
      {
        $this_max = 0;
        $this_class = 0;

        //find max intersect in curr cluster.
        foreach($set as $class => $num)
        {
          if($num > $this_max)
          {  
            $this_max = $num;
            $this_class = $class;
          }
        }

        //update max max.
        if($this_max > $max)
        {
          $max = $this_max;
          $max_index = $this_index;
          $max_class = $this_class;
        }
      }
      //print "max $max \n"; print "max index $max_index \n"; print "max class $max_class \n";

      $sum += $max;

      // Remove max intersect cluster from consideration
      array_splice($labels_set, $max_index, 1);
      // Remove max intersect class from consideration.
      for($i = 0; $i < count($labels_set); $i++)
      {
        if(isset($labels_set[$i][$max_class]))
        {
          $labels_set[$i][$max_class] = 0;
        }
      }
    }
    return $sum / self::get_clustering_size($clustering);;
  }

  /**
  * Clustering interfaces toString method.
  * get_option_k() !!
  */
  public function clustering_toString( AbstractKNNClustering $clustering )
  {
    $str =  "######Clustering Begin######\n";
    $str .= "#size: ".self::get_clustering_size($clustering)."\n"; //should probly be in interface.
    $str .= "#num_clusters: ".$clustering->get_num_clusters()."\n";
    $str .= "#clustering_k: ".$clustering->get_clustering_k()."\n";
    $str .= "#avg_cluster_purity_wghtd: ".self::get_clustering_purity_wghtd($clustering)."\n";
    $str .= "#avg_cluster_purity_unwghtd: ".self::get_clustering_purity_unwghtd($clustering)."\n";
    $str .= "#unbiased_purity: ".self::get_clustering_unbiased_purity($clustering)."\n";    
    $str .= "#contains_labels: ";
    $labels = self::get_clustering_labels($clustering);
    if($labels)
    {
      foreach($labels as $label => $count)
      {  
        $str .= "$label $count;  ";
      }
    }
    $str .="\n";
    // Allow the specific type of clustering to output its own stuff. Must have same format.
    $str .= $clustering->clustering_toString();
    return $str;
  }

/*
 * Misc functions, not used.
 */

  /**
   * Make a string containing all options from get_options() plus type.
   */
  public static function make_clustering_type_string(LoadableAbstractKNNClustering $clustering)
  {
    $str = "";
    $str .= "_".$clustering->get_type();
    $str .= self::make_clustering_options_string($clustering->get_options());
    return $str;
  }

  /**
   * Make a string containing all options from get_options().
   * Tries shorten string as much as possible - still huge.
   * What ever the number is we want to pad >=1 zeros so 'ls' listings are in order.
   * Only prob what the max - 3 should be fine. and one decimal place id float.
   */
  private static function make_clustering_options_string(Array $options)
  {
    $str = "";

    foreach($options as $ckey => $cval)
    {
      //If an array (of options from some submodule of some clusterer) recursively get those
      if(is_array($cval))
      {
        $str .= self::make_clustering_options_string($cval);
      }
      else
      {
        $ckey = shorten_string($ckey);
        if(is_numeric($cval))
        {
          $cval = (string) $cval;

          if(ctype_digit($cval))
          {
            $str .= sprintf("_%s%03d", $ckey, $cval);  
          }
          //assume float
          else
          {
            $str .= sprintf("_%s%03.2f", $ckey, $cval);
          }
        }
        elseif(is_bool($cval))
        {
          //convention to show true only jsut suites me right now - shit.
          if($cval)
          {
            $str .= "_$ckey";
          }
        }
        else
        {
          $str .= "_$ckey$cval";
        }
      }
    }
    return $str;
  }  
}
?>
