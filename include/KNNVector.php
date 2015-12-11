<?php

require_once('set_include_path.php');
require_once('Vector.php');

/**
 * A Vector and its set of k nearest neighbours. A KNNVector is a vector and has some Vectors.
 * There are 4 methods by which the contents of a KNNVectors neighbours can be modified:
 *  build_knn_graph(), update_knn(), set_k(), reset_k().
 * An exception is thrown if you try to access a neighbour that has not been set.
 * To set neighbours you have to call build_knn_graph().
 * Self is not represented in neighbour list. I.e. the zeroth neighbour is not self.
 * K is the number of neighbours, but neighbours are indexed starting from zero generally.
 */
class KNNVector
{
  const EMPTY_LABEL = 0;
  /** Arbitrary labels useful in clustering. */
  private $class_label = null;
  private $other_label = null;
  /** The KNN of this node. */
  private $knn = [];
  /** The distances to the KNN */
  private $dist = [];
  /** Size of K in $knn */
  private $k = null;
  private $cluster = null;
  private $vector = null;
  /** The vectors dim. Duplicated for eff.*/
  private $dim = 0;
  /** For duplicates. $size > 1 iff duplicate*/
  private $size = 1;

  public function __construct(Vector $vector, $k, $label = null, $other_label = null)
  {
    // Sets K and intializes stuff.
    $this->reset_knn($k);
    $this->other_label = $other_label;
    $this->class_label = $label;
    $this->vector = $vector;
    $this->dim = $vector->dim();
    //Init knn
  }

/*
 * label functions:
 */
  public function is_labeled()
   {
     return ($this->class_label !== null);
   }

  public function get_class_label()
  {
    return $this->class_label;
  }

  public function get_other_label()
  {
    return $this->other_label;
  }

/**
 * Cluster functions. These are a bit of an appendage.
 * Ideally should subclass KNNVector to add this but hey.
 */

  /**
   * Get the cluster this vector is assigned to.
   * Must be set with set_cluster().
   * Yes it would be nice if ~KNNClusterNode extended KNNVector maybe ... this works.
   */
  public function get_cluster()
  {
    return $this->cluster;
  }

  /**
   * Get the root cluster.
   * get_cluster() always returns a leaf. This is more efficient.
   */
  public function get_root_cluster()
  {
    if(isset($this->root_cluster))
    {
      return $this->root_cluster;
    }
    else
    {
      throw new Exception("Root cluster not set!");
    }
  }

  /**
   * Set the cluster - the leaf node in a clustering this Vector currently belongs to in most situations.
   */
  public function set_cluster(AbstractKNNClustering $cluster)
  {
    $this->cluster = $cluster;
  }

  /**
   * Set the root cluster - the current clustering this Vector currently belongs to in most situations.
   */
  public function set_root_cluster(AbstractKNNClustering $cluster)
  {
    $this->root_cluster = $cluster;
  }

/**
 * As a vector.
 */

  public function get_dim()
  {
    return $this->dim;
  }

  public function dim()
  {
    return $this->dim;
  }

  /**
   * Get clone of the vector
   */
   public function get_vector()
   {
     return clone $this->vector;
   }

/*
 * All KNN Mang, set and access functions.
 */

  /**
   * Insert KNNVector $vec into knn if it is closer than some value in knn currently.
   * Making this as efficient as possible is important.
   * Trusts the vector passed in is not self and is not already in knn.
   * @param KNNVector $vec the potential neighbour.
   * @return bool whether the vector was inserted into knn of this KNNVector.
   */
  public function update_knn(KNNVector $vec)
  {
    $dist = $this->vector->distance($vec);

    //Insertion.
    $k_end = $this->k - 1;
    for($m = $k_end; $m >= -1 ; $m -= 1)
    {
      if($dist >= $this->dist[$m])
      {
        $this->dist[$m+1] = $dist;
        $this->knn[$m+1] = $vec;
        break;
      }
      $this->dist[$m+1] = $this->dist[$m];
      $this->knn[$m+1] = $this->knn[$m];
    }
    return;
  }

  /**
   * Brute force bulid a KNN graph. The KNN graph is stored implicitly in th KNNVectors.
   * Only to_nodes are considered as adjacent nodes to $nodes. Only $nodes is connected up.
   * This is so the method can be reused for other stuff.
   * @input $nodes array of nodes to set KNN of.
   * @input $to_nodes array of nodes to consider as potential neighbours of node in $nodes
   * @input $updater ProgressUpdater optional updater.
   */
  public static function build_knn_graph(Array $nodes, Array $to_nodes = null, $updater = null)
  {
    $updater = $updater ? $updater : new CliProgressUpdater();
    $to_nodes = $to_nodes ? $to_nodes : $nodes;
    $num_nodes = count($nodes);
    $num_to_nodes = count($to_nodes);
    $k_end = $nodes[0]->k - 1;
    $dim = $nodes[0]->dim;

    for($i = 0; $i < $num_nodes; $i += 1)
    {
      $curr = $nodes[$i];
      for($j = 0; $j < $num_to_nodes; $j += 1)
      {
        if ($i == $j) {
           continue;
        }
        $vec = $to_nodes[$j];
        $dist = $curr->vector->distance($vec->vector);

        // $m >= -1 is a small optimization
        $end_k = $j > $k_end ? $k_end : $j;
        for($m = $end_k; $m >= -1 ; $m -= 1)
        {
          if($dist >= $curr->dist[$m])
          {
            $curr->dist[$m+1] = $dist;
            $curr->knn[$m+1] = $vec;
            break;
          }
          $curr->dist[$m+1] = $curr->dist[$m];
          $curr->knn[$m+1] = $curr->knn[$m];
        }
      }
      $updater->update($i/sizeof($nodes));
    }
  }

  /**
    * Identical points are represented by a single point,
    * Points are given a a size >= 1.
    * the singularity will have itself in the first size -1 knn.
    * a point point neighbouring a singularity will have upto size of the same point in its knn.
    * singularities are all found when checking the very first node.
    * THIS RELIES ON THE INPUT DATA BEING SORTED.
   */
  public static function build_knn_graph_duplicates(Array $nodes)
  {
    //#0
    $p = new CliProgressUpdater();
    $num_nodes = count($nodes);
    $k_end = $nodes[0]->k - 1;
    $dim = $nodes[0]->dim;

    for($i = 0; $i < $num_nodes; $i += 1)
    {
      $curr = $nodes[$i];
      for($j = 0; $j < $num_nodes; $j += 1)
      {
        if($j != $i)
        {
          $vec = $nodes[$j];
          $dist = $curr->vector->distance($vec->vector);

          // If dist 0 => vec is duplicate of curr: remove vec, insert duplicate in current.
          if($dist == 0)
          {
            // Removal and Insertion procedure.
            //print "duplicate to current at $i $j!\n";
            ($i < $j) or exit("$i $j AAHAHAAAHHHH\n");
            array_splice($nodes, $j, 1);
            $j -= 1;
            $num_nodes -= 1;
            $curr->size += 1;
            $curr->update_knn($curr);
          }
          else
          {
            $offset = $vec->size;
            // Iterate down thru knn until find one <= to current neighbour.
            // Then insert at that nodes position + 1.
            // Dont worry about overflows.
            $m_off = $k_end+$offset;
            for($m = $k_end; $m >= -1 ; $m -= 1)
            {
              if($dist > $curr->dist[$m])
              {
                if($offset == 1)
                {
                  $curr->dist[$m_off] = $dist;
                  $curr->knn[$m_off] = $vec;
                  break;
                }

                // Fill out the size last knn, accounts for duplicates.
                // Dont worry about overflow.
                for($n = $m+1; $n <= $m_off; $n++)
                {
                  $curr->dist[$n] = $dist;
                  $curr->knn[$n] = $vec;
                }
                break;
              }
              elseif($dist == $curr->dist[$m])
              {
                //print "eq\n";
                $rep = $curr->knn[$m];

                $same = true;
                for($n = 0; $n < $dim; $n += 1)
                {
                  if(($rep->vector->get($n) != $vec->vector->get($n)))
                  {
                    $same = false;
                    break;
                  }
                }

                // If duplicate update rep, remove dup, still insert rep into curr's knn.
                if($same)
                {
                  // Removal and Insertion procedure.
                  //print "duplicate in knn at $i $j\n";
                  array_splice($nodes, $j, 1);
                  $j -= 1;
                  $num_nodes -= 1;
                  $rep->size += 1;
                  $rep->update_knn($rep);
                  //only need to  insert one coz cant be duplicate
                  $curr->dist[$m+1] = $dist;
                  $curr->knn[$m+1] = $rep;
                  ($i < $j) or exit("$i $j AAHAHAAAHHHH\n");
                }
                else
                {
                  for($n = $m+1; $n <= $m_off; $n++)
                  {
                    $curr->dist[$n] = $dist;
                    $curr->knn[$n] = $vec;
                  }
                }
                break;
              }
              $curr->dist[$m_off] = $curr->dist[$m];
              $curr->knn[$m_off] = $curr->knn[$m];
              $m_off -= 1;
            }
          }
        }
      }
      $p->update($i/sizeof($nodes));
    }
  }

  /**
   * Set the number of neighbours this KNNVector may have.
   * Internally neighbours are trimmed or extra *null* neighbours are added.
   * Any extra neighbours are *not* found and added.
   * @see reset_knn().
   */
  public function set_k($k)
  {
    if($k < 0)
    {
      throw new KNNVectorException("k value must be +ve");
    }
    if($k <= $this->k)
    {
      // Why would you do this?
      $this->knn = array_slice($this->knn, 0, $k+1);
      $this->dist = array_slice($this->dist, 0, $k+1);
    }
    else
    {
      //comparison to (float) PHP_INT_MAX faster than INF?
      $this->knn = array_merge($this->knn, array_fill(0, ($k - $this->k) + 1, null));
      $this->dist = array_merge($this->dist, array_fill(0, ($k - $this->k) + 1, (float) PHP_INT_MAX));
    }
    $this->dist[-1] = -1.0;
    $this->k = $k;
  }

  /**
   * Set all neighbour pointers to null.
   * Arrays are filled to one greater than their size.
   * this is not necessary but is consistent with how they are used in build_knn_graph().
   * I.e. the last element is shifted of the end of the array.
   * -1 is set to a value <= to any possible dist to a neighbour also for optimization used in build_knn_graph().
   */
  public function reset_knn($k)
  {
    if($k <= 0)
    {
      throw new KNNVectorException("k value must be +ve");
    }
    if($k == $this->k)
    {
      return;
    }
    //comparison to (float) PHP_INT_MAX faster than INF?
    // slower: //$this->knn = SplFixedArray::fromArray($this->knn);
    $this->knn = array_fill(0, $k+1, null);
    $this->dist = array_fill(0, $k+1, (float) PHP_INT_MAX);
    $this->dist[-1] = -1.0;
    $this->k = $k;
  }

  /**
   * Check whether a KNNVector within the k NN of this KNNVector.
   * if k is 1 and node is 1st NN then returns true.
   */
  public function is_knn(KNNVector $vect, $k)
  {
    if($k < 1)
    {
      throw new OutOfBoundsException("K '$k' is too small");
    }
    if($k > $this->get_k())
    {
      throw new OutOfBoundsException("K '$k' too large for KNNVector");
    }

    for($i = 0; $i < $k; $i++)
    {
      if($this->knn[$i] === $vect)
      {
        return true;
      }
    }
    return false;
  }

  /**
   * Get the k value, or number of potential neighbours this KNNVector has.
   */
  public function get_k()
  {
    return $this->k;
  }

  /**
   * Get the ith + 1 nearest neighbour.
   */
  public function get_nn($index)
  {
    if(($index >= $this->k) || ($index < 0))
    {
      throw new KNNVectorException("Index out of bounds");
    }
    if(! isset($this->knn[$index]))
    {
      throw new KNNVectorException("Null Neighbour! Means this vector has not been filled out with k neighbours");
    }
    return $this->knn[$index];
  }

  /**
   * Get the number of points this KNNVector represents
   * > 1 iff this KNNVEctor represents duplicates, 1 otherwise
   */
  public function get_size()
  {
    return $this->size;
  }

  /**
   * Is this is a duplicate
   */
  public function is_duplicate()
  {
    return ($this->size > 1);
  }

  /**
   * Get the ith + 1 nearest neighbour.
   */
  public function get_dist($index)
  {
    if(($index >= $this->k) || ($index < 0))
    {
      throw new KNNVectorException("Index out of bounds");
    }
    if(! isset($this->knn[$index]))
    {
      throw new KNNVectorException("Null Neighbour! Means this vector has not been filled out with k neighbours");
    }
    return $this->dist[$index];
  }

  /**
   * Get average distance to $sample_k nearest.
   */
  public function get_avg_k_dist($sample_k)
  {
    if(($sample_k > $this->k) || ($sample_k <= 0))
    {
      throw new KNNVectorException("Index out of bounds");
    }
    $sum = 0;
    for($i = 0; $i < $sample_k; $i++)
    {
       $sum += $this->get_dist($i);
    }
    return $sum/$sample_k;
  }

  /**
   * Get the average of the k nearest neighbour vectors, *with* *origin* *at* *$this*.
   * So note the origin is at this. If you want to not make it so add to this.
   */
  public function get_avg_vector($sample_k)
  {
    if(($sample_k > $this->k) || ($sample_k <= 0))
    {
      throw new KNNVectorException("Index out of bounds");
    }

    $avg_vector = new Vector($this->dim);

    for($i = 0; $i < $sample_k; $i++)
    {
       $avg_vector->add_to($this->knn[$i]->vector);
    }

    $avg_vector->mul_to(1/$sample_k);
    $avg_vector->sub_to($this->vector);
    return $avg_vector;
  }

  /**
   * Returns the average vector normalized w.r.t the average distance to the $sample_k neighbours.
   * Gives a value in range [0:1].
   * Convenience method. Use this alot so makes sure you dont fuck up the calculation.
   */
  public function get_avg_vector_norm($sample_k)
  {
    $avg_vector = $this->get_avg_vector($sample_k);
    $avg_dist = $this->get_avg_k_dist($sample_k);

    //if the vector sample_kth neighbours are all same.
    if($avg_dist == 0)
    {
      //will be zero vector.
      return $avg_vector;
    }
    $avg_vector->mul_to(1 / $avg_dist);
    return $avg_vector;
  }

  /**
   * Convenience method. Use this alot so makes sure you dont fuck up the calculation.
   */
  public function get_avg_vector_significance($sample_k)
  {
    return $this->get_avg_vector_norm($sample_k)->abs();
  }

  /**
   * MODIFIED 25/04/11 nomralized not work on high dim data wihth Euclidean!
   * But this one suks on low dim data!
   * This is not really density it is avarage dist to neighbours.
   */
  public function get_density($sample_k)
  {
    return $this->get_avg_k_dist($sample_k);
  }

  /**
   * Get the local density at this point according to a dim normalized knn based measure.
   */
  public function get_density_dim($sample_k)
  {
    $avg_k_dist = $this->get_avg_k_dist($sample_k);
    return 1/pow($avg_k_dist, $this->dim);
  }

/*
 * toString methods
 */

  /**
   * Print the Vector as a string.
   * Prepends the vector string with a single space.
   */
  public function __toString()
  {
    $retval = "";
    foreach($this->vector as $k => $v)
    {
      $retval .= "$v ";
    }
    return $retval;
  }

  /**
   * Not used - stupid.
   */
  public function knnvector_toString($type, $k)
  {
    $method = 'toString_'.$type;
    if(! method_exists($this, $method))
    {
      throw new KNNVectorException("No such toString method $type");
    }
    $this->$method($k);
  }

  /**
   * Return a string rep of the average vector of the sample_k nearest neighbours.
   */
  public function toString_avg_vector($sample_k)
  {
    $a_vector = $this->get_avg_vector($sample_k);
    return $this." ".$a_vector."\n";
  }

  /**
   * Return a string rep of the average vector of the sample_k nearest neighbours, normalized to avg_k_dist.
   * Dividing by the distance to the sample_kth nearest neighbour should go some way to showing signif of avg_vector given local density.
   */
  public function toString_avg_vector_norm($sample_k)
  {
    $an_vector = $this->get_avg_vector_norm($sample_k);
    return $this." ".$an_vector."\n";
  }

  /**
   * Get a string rep of normalized scalar value at a vector offset of the magnitude of the average vector.
   */
  public function toString_edge_points($sample_k, $multiplier = 1)
  {
    $an_vector = $this->get_avg_vector_norm($sample_k);
    $an_vector->mul_to($multiplier);
    return $this." ".$an_vector->abs()."\n";
  }

  /**
   * Get a string represenation of this KNNVectors k nearest neighbours.
   * @return String the k nearest neighbours of this KNNVector if knn are set. One per line.
   */
  public function toString_knn_points($k)
  {
    $retval ="";

    if($k > $this->k)
    {
      throw new KNNVectorException("Index out of bounds");
    }

    for($i = 0; $i < $k; $i++)
    {
      if($this->knn[$i] !== null)
      {
        $retval .= $this->knn[$i]->vector."\n";
      }
      else
      {
        $retval .= "null\n";
      }
    }
    return $retval;
  }

  /**
   * Get a string rep of this KNNVector in a format same as gnuplot's vector data rep.
   * @return String the k nearest neighbours of this KNNVector if knn are set. One per line.
   */
  public function toString_knn_vectors($k)
  {
    $retval = "";

    if($k > $this->k)
    {
      throw new KNNVectorException("Index out of bounds");
    }

    for($i = 0; $i < $k; $i++)
    {
      if($this->knn[$i] !== null)
      {
        $new_vec = $this->knn[$i]->vector->sub($this->vector);
        $retval .= $this.$new_vec."\n";
      }
      else
      {
        $retval .= "null\n";
      }
    }
    return $retval;
  }

  /**
   * Get a string represenation of this KNNVectors k nearest neighbours.
   * @return String the k nearest neighbours of this KNNVector if knn are set. One per line.
   */
  public function toString_rcknn_points($k)
  {
    $retval ="";

    if($k > $this->k)
    {
      throw new KNNVectorException("Index out of bounds");
    }

    for($i = 0; $i < $k; $i++)
    {
      $neighbour = $this->get_nn($i); //throws.
      if($neighbour->is_knn($this, $k))
      {
        $retval .= $neighbour->vector."\n";
      }
    }
    return $retval;
  }

  /**
   * Get a string rep of this KNNVector in a format same as gnuplot's vector data rep.
   * @return String the k nearest neighbours of this KNNVector if knn are set. One per line.
   */
  public function toString_rcknn_vectors($k)
  {
    $retval ="";

    if($k > $this->k)
    {
      throw new KNNVectorException("Index out of bounds");
    }

    for($i = 0; $i < $k; $i++)
    {
      $neighbour = $this->get_nn($i); //throws.
      if($neighbour->is_knn($this, $k))
      {
        $new_vec = $this->knn[$i]->vector->sub($this->vector);
        $retval .= $this.$new_vec."\n";
      }
    }
    return $retval;
  }

  /**
   * Get a string rep of this KNNVector with absolute distance to each appended to each.
   * @return String the k nearest neighbours of this KNNVector if knn are set. One per line.
   */
  public function toString_knn_abs($k)
  {
    $retval ="";

    if($k > $this->k)
    {
      throw new KNNVectorException("Index out of bounds");
    }

    for($i = 0; $i < $this->k; $i++)
    {
      if($this->knn[$i] !== null)
      {
        $retval .= $this->knn[$i]." ".$this->vector->distance($this->knn[$i])."\n";
      }
      else
      {
        $retval .= "null\n";
      }
    }
    return $retval;
  }
}

/**
 * Token Exception class.
 * Only later >=5.3 support chaining.
 */
class KNNVectorException extends Exception
{
  public function __construct($msg, $code = 0)
  {
    parent::__construct($msg, $code);
  }
}
?>
