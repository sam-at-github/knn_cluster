<?php

require_once('set_include_path.php');
require_once("KNNVector.php");
require_once("CliProgressUpdater.php");

/**
 * A K Nearest Neighbour graph.
 * This class is a container for nodes.
 * Not sure about the coupling of this class.
 */
class KNNGraph implements IteratorAggregate
{
  // Used for reading vectors in from file.
  const DIM_KEY = "DIM";
  const DIM_SEP = "=";
  const K_MAX_DEFAULT = 14;
  private $k_max;
  private $nodes = array();
  private $dim = null;
  private $handle_duplicates = false;
  private $labeled;
  private $vector_class = null;
  private $vector_options = array();

  /**
   * Construct a new KNNGraph object.
   * @param vectors Array<KNNVector>
   */
  public function __construct($data_file, $k_max = null, $handle_duplicates = false, $assume_labeled = false, $vector_class, Array $vector_options)
  {
    $this->k_max = $k_max;
    if($this->k_max === null)
    {
      $this->k_max = self::K_MAX_DEFAULT;
    }
    $this->vector_class = $vector_class;
    $this->vector_options = $vector_options;
    $this->nodes = $this->read_vectors($data_file);
    $this->dim = $this->nodes[0]->get_dim();
    $this->labeled = $this->nodes[0]->is_labeled();

    // We care now because we have to cluster data.
    if($this->k_max > sizeof($this->nodes))
    {
      throw new KNNGraphException("k_max provided to big for data!");
    }

    // Build graph was moved to KNNVector to remove a function call and up the eff.
    if($handle_duplicates)
    {
      KNNVector::build_knn_graph_duplicates($this->nodes);
    }
    else
    {
      KNNVector::build_knn_graph($this->nodes);
    }
  }

  /**
   * Sets k_max.
   * You would mainly want to use this to increase k_max.
   * On increase knn are added accordingly, so this may tak a long time to return.
   */
  public function set_k_max($k)
  {
    if($k <= 0)
    {
      throw new KNNGraphException("k must be +ve");
    }
    if($k > sizeof($this->nodes))
    {
      throw new KNNGraphException("k_max provided too big for data!");
    }

    if(isset($this->k_max) && ($k <= $this->k_max))
    {
      $this->k_max = $k;
      for($i = 0; $i < sizeof($this->nodes); $i++)
      {
        $curr = $this->nodes[$i];
        $curr->set_k($k);
      }
    }
    else
    {
      $this->k_max = $k;
      $this->graph_knn();
    }
  }

  /**
   * Get the current k_max value.
   */
  public function get_k_max()
  {
    return $this->k_max;
  }

  /**
   * get index-th node
   */
  public function get_node($i)
  {
    if($i < 0 || $i > sizeof($nodes))
    {
      return null;
    }
    else
    {
      return $this->nodes[$i];
    }
  }

  /**
   * Get size of data set.
   * @return int size of data set.
   */
  public function get_size()
  {
    return sizeof($this->nodes);
  }

  /**
   * get dim. dim id set in stone on init.
   * @return int the  dim.
   */
  public function get_dim()
  {
    return $this->dim;
  }

  /**
   * Get the class of the vector used in this KNNGraph.
   * KNNGraph is as gooder place as any to store this needed info.
   */
  public function get_vector_class()
  {
    return $this->vector_class;
  }

  /**
   * Get the options that were supplied to vectors when building this KNNGraph.
   * KNNGraph is as gooder place as any to store this needed info.
   */
  public function get_vector_options()
  {
    return $this->vector_options;
  }

  public function get_options()
  {
    $options = array(
      'size' => sizeof($this->nodes),
      'k_max' => $this->k_max,
      'handle_duplicates' => $this->handle_duplicates,
      'labeled' => $this->labeled,
      'dim' => $this->dim,
      'vector_class' => $this->vector_class,
      'vector_options' => $this->vector_options
    );
    return $options;
  }

   /**
    * Only required function for IteratorAggregate I.f.
    * ArrayIterator implements Iterator for you on the array.
    */
   public function getIterator()
   {
     return new ArrayIterator($this->nodes);
   }

  /**
   * Read in vectors from a text file.
   * If '#DIM=X' comment line is found then dim=X
   * If there are extra fields, first is a label rest is treated as one comment.
   *   I.e line -> <vector>[<label>[<comment>]]
   * Else if no '#DIM=X' comment line then
   *   if assume_labeled then
   *      dim = length of 1st line found - 1
   *      and last field is a label
   *      assume no comments.
   *   else
   *      dim = length of 1st line found
   *      no label, no commments.
   * The dim and labeled fields are set by this function.
   */
  public function read_vectors($file)
  {
    $vectors = array();
    $new_vector = null;
    $dim = null;

    // Read in data from file.
    $data = file($file);

    for($i = 0; $i < sizeof($data); $i++)
    {
      $data[$i] = trim($data[$i]);

      // Search the header (initial comment lines) for a ~ DIM = <number> and set dim if found.
      // Else dim is set as sizeof first non comment line found.
      while(($dim == null) && (substr($data[$i], 0, 1) == '#'))
      {
        $assign = explode(self::DIM_SEP, substr($data[$i], 1));
        if(trim($assign[0]) == self::DIM_KEY)
        {
          $value = (int) trim($assign[1]);
          if($value)
          {
            $dim = $value;
          }
          else
          {
            throw new Exception("invalid DIM '$value' found while reading in vector data");
          }
        }
        $i++;
        $data[$i] = trim($data[$i]);
      }

      // Comment lines ignored.
      while(substr($data[$i], 0, 1) == '#')
      {
        $i++;
        $data[$i] = trim($data[$i]);
      }

      $d = preg_split("/ +/", $data[$i]);

      // If dim is still null assume it is size of first line presumming *no* labels. dim is finally set after this.
      if($dim === null)
      {
        if($options['assume_labeled'])
        {
          trigger_error("No dimension set in vector data file while reading vector data. Assuming ends in one label", E_USER_NOTICE);
          $dim = sizeof($d) - 1;
        }
        else
        {
          trigger_error("No dimension set in vector data file while reading vector data. Assuming dim is size of 1st found", E_USER_NOTICE);
          $dim = sizeof($d);
        }
      }

      if(sizeof($d) < $dim)
      {
        throw new Exception("Inconsistent vector sizes found while reading in vector data");
      }

      // Create new KNNVector and set label(s).
      $vector = new $this->vector_class(array_slice($d, 0, $dim), $this->vector_options);
      $label = null;
      if(isset($d[$dim]))
      {
        $label = (int) $d[$dim];
        if($label <= 0)
        {
          throw new Exception("Sorry labels must be integers and > 0");
        }
      }
      $other_label = null;
      if(isset($d[$dim+1]))
      {
        $other_label = implode(" ", array_slice($d, $dim+1));
      }
      $new_vector = new KNNVector($vector, $this->k_max, $label, $other_label);
      array_push($vectors, $new_vector);
    }
    //foreach($vectors as $v){ print $v." ".$v->get_class_label()."\n";}
    return $vectors;
  }
}

/**
 * Token Exception for KNNGraph.
 * Only later 5.3 >= ? support chaining.
 */
class KNNGraphException extends Exception
{
  public function __construct($msg, $code = 0)
  {
    parent::__construct($msg, $code);
  }
}

/*Testing*/
//$x = new KNNGraph("../data/verify2/verify2.txt", $k_max = null, $handle_duplicates = false, "Vector", array());
?>
