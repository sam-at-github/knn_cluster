<?php

require_once('set_include_path.php');
require_once('lib_knn_cluster.php');
require_once('KNNGraph.php');
require_once('AbstractKNNClustering.php');
require_once('AbstractKNNCluster.php');
require_once('make_color_array.php');

/** 
 * Static class methods for plotting an AbstractKNNClustering through CLI I.f. to gnuplot.
 * Result: some messy code.
 * I cant decide whether to make this instantiable.
 * This code is dependent on gnuplot 4.4 and above! 
 * gnuplot is shotty seems poorly maintained, developed and designed.
 * But its still free and very good if your willing to spend hours(days) screwing around with it to get what you want.
 * @todo make instantiable - Validatation of options is a distinct phase.
 */
class KNNClusteringVision
{  
  /** Supported options go here.*/
  private static $default_options = array(
    'visual_k' => null,
    'use_cluster_k' => false,
    'min_color_track' => 1,      // How big cluster has to be to track - ntree only.
    'point_size_multiplier' => 1,
    'point_size' => 1,        //point size used in any point plot.
    'point_type' => 7,        //point type used in any point plot.
    'plot_size_x' => 1200, 
    'plot_size_y' => 1200,
    'xrange' => null,          //array(0,10),
    'yrange' => null,         //array(0,10),
    'vector_arrow_size' => 0.008,
    'vector_arrow_angle' => 24,
    'vector_arrow_filled' => true,
    'k_part_len' => 3,
    'plot_pre' => 'plot',
    'plot_post' => '.png',
    'script_pre' => 'script',
    'script_post' => '.gnuplot',
    'gnuplot_set' => array(),
    'gnuplot_unset' => array('tics', 'key', 'border', 'title')
    );

  /** at each successive level color corresponding to a logical same cluster should be the same.*/
  private static $cluster_colors = array();
  /** not really needed here, consistent, saves regeneration.*/
  private static $class_colors = array();

  /**
   * Generate a .png (only), a gnuplot script, and data files needed by gnuplot all into a provided directory.
   * Complexity comes from fact that we are executing thru the CLI, and the dependency between gnuplot script and datafile that must be generated separately;
   * Seeing as this thing knows its visualizing *K*NN vectors it is OK to let it use the k value of the AbstractKNNClustering,
   * Want to keep abstract but if the clustering does not have a coherent k value it can be null and fall back to points.
   * vision_output_dirname,vision_output_filename, dim, vision, k_max.
   */
  public static function visualize_clustering(AbstractKNNClustering $clustering, $output_dir, $output_file, $type, Array $input_options = array())
  {
    $cluster_file_fmt;
    $working_dir;
    $script_file;
    $png_file;
    /** The gnuplot script contents. Built up thoughout the method.*/
    $script_body = "";
    $options = array_merge(self::$default_options, $input_options);
    $dim = self::get_dim($clustering);
    $k_max = $clustering->get_k_max();
    $type = (string)$type; //grrrr.

    if(self::$cluster_colors == null)
    {
      trigger_error("No Cluster colors array is set. Generating one from current clustering", E_USER_NOTICE);
      KNNClusteringVision::make_clustering_colors($clustering);
    }

    // Check clustering type valid. Have to manually maintain this for now.
    if(! in_array($type, array('points', 'knn', 'rcknn', 'avg_vector')))
    {
      throw new InvalidArgumentException("Invalid vision type '$type'");
    }

    // The visualization output dir should be created based on what knn_vector knows - clustering type and vector options.
    // We must add clustering option visualization option identifying strings to differentiate.
    // e.g. a clustering might be looped vector type  with rcknnxyz. But then clustering itself has options k, alpha, foo_spin. Visualization can be point, knn ...
    // These options must be present in name of file so that k=3 and k=7 can exist in same dir for example.
    $cluster_file_fmt = "data_".$output_file."_cluster%06d.txt";
    $working_dir = "work_".$output_file;
    $script_file = $options['script_pre']."_".$output_file.$options['script_post'];
    $png_file = $options['plot_pre']."_".$output_file.$options['plot_post'];

    mkdir2($output_dir."/".$working_dir, true, true);

    // A prependage to make gnuplot output a png.
    // Used to generate png then commented out in script output.
    $png_script_header = "set output '$png_file'\nset terminal png large size ".$options['plot_size_x'].", ".$options['plot_size_y']."\n";  

    // Set title.
    $script_body .= "set title 'type = ".$output_file.", points = ".$clustering->get_size().", clusters = ".$clustering->get_num_clusters()."'\n";

    // Set 'set/'unset'able gnuplt commands.  
    foreach($options['gnuplot_unset'] as $unset)
    {
      $script_body .= "unset $unset \n";
    }
    foreach($options['gnuplot_set'] as $set)
    {
      $script_body .= "unset $set \n";
    }

    // Set x and y ranges.
    if(isset($options['xrange']))
    {
      $script_body .= "set xrange[".$options['xrange'][0].":".$options['xrange'][1]."]\n";
    }
    if(isset($options['yrange']))
    {
      $script_body .= "set yrange[".$options['yrange'][0].":".$options['yrange'][1]."]\n";
    }    

    // Set point options to a value.
    // This should not include the  prefix, it should have been removed in upper layer - time.
    $point_size_string = "";
    if(! empty($options['point_size_multiplier']) || ! empty($options['point_size']))
    {
      $m = (isset($options['point_size_multiplier']) ? $options['point_size_multiplier'] : 1);
      $p = (isset($options['point_size']) ? $options['point_size'] : 1);
      $point_size_string = "ps ".($p*$m);
    }
    $point_type_string = "";
    if(! empty($options['point_type']))
    {
      $point_type_string = "pt ".$options['point_type'];
    }

    //need to set styles
    foreach($clustering->clustering_getIterator() as $cluster_num => $cluster)
    {
      $cluster_label = $cluster->get_cluster_color_label();
      $script_body .= "set style line ".$cluster_label." lc rgb \"#".self::$cluster_colors[$cluster_label]."\" $point_size_string $point_type_string lw 1.8\n";
    }

    // Set the dimensionality of plot.
    if(($dim == 2) || ($dim == 1))
    {
      $script_body .= "plot ";
    }
    if($dim >= 3)
    {
      $script_body .= "splot ";
    }
    if($dim > 3)
    {
      trigger_error("Plotting > 3D data", E_USER_NOTICE);
    }

    // For each cluster
    // Print cluster to file in appropriate format for gnuplot script.
    // Add line to script plot the cluster, with appropriate settings.
    // options used: 
    //    type;
    //    visual_k; if not set default to clustering->get_k(). Mostly dont want to set this.
    //    k_max;
    //    use_cluster_k;
    //    vector and point settings;

    // eol for script lines
    $eol = "";
    foreach($clustering->clustering_getIterator() as $cluster_num => $cluster)
    {

      //write terminator for previous line.
      $script_body .= $eol;
      $eol = ",\\\n";    

      // Can only use cluster k if cluster has a logical k value.
      // Decided that all clusters now must have this method. If not logical return 0 and defaults to points.
      // $visual_k holds that k value use to print this cluster.
      $visual_k  = null;
      if($type != 'points')
      {
        if($options['use_cluster_k'])
        {
          $visual_k = $cluster->get_cluster_k();
        }        
        elseif($options['visual_k'])
        {
          $visual_k = $options['visual_k'];
        }
        else
        {
          $visual_k = $clustering->get_clustering_k();
        }

        if($visual_k > $clustering->get_k_max())
        {
          trigger_error("Resolved k value for visualization > k_max! Falling back to k_max", E_USER_NOTICE);
          $visual_k = $clustering->get_k_max();
        }
        if(($visual_k == 0))
        {
          trigger_error("Could not resolve K value for visualization! Falling back to points", E_USER_NOTICE);
          $type = 'points';
        }
      }

      // The data that goes in the file $cluster_file.
      // gnuplot script references $cluster_file.      
      $cluster_data = "";
      $cluster_file = $working_dir."/".sprintf($cluster_file_fmt, $cluster->get_cluster_color_label());  
      switch($type)
      {
        case 'points' :
        {
          // Script output part.
          $script_body .= "'$cluster_file' with points ls ".$cluster->get_cluster_color_label();

          // Corresponding datafile part.
          foreach($cluster->cluster_getIterator() as $knn_vector)
          {
            $cluster_data .= $knn_vector->__toString()."\n";
          }
          break;
        }      
        case 'knn' :
        {  
          // Script output part.        
          $script_body .= "'$cluster_file' with vectors ";          
          if($options['vector_arrow_size'])
          {
            $script_body .= "size ".$options['vector_arrow_size']." ";

            // Must provide both size and angle if spec any - why!? ans = GNUplot.
            if($options['vector_arrow_angle'])
            {
              $script_body .= ", ".$options['vector_arrow_angle']." ";
            }
            else
            {  
              $script_body .= ", 30";
            }
          }      
          if($options['vector_arrow_filled'])
          {
            $script_body .= "filled ";
          }        
          $script_body .= "ls ".$cluster->get_cluster_color_label();

          // Corresponding datafile part.
          foreach($cluster->cluster_getIterator() as $knn_vector)
          { 
            $cluster_data .= $knn_vector->toString_knn_vectors($visual_k);
          }
          break;
        }        
        case 'rcknn' :
        {
          // Script output part.
          $script_body .= "'$cluster_file' with vectors ";
          if($options['vector_arrow_size'])
          {
            $script_body .= "size ".$options['vector_arrow_size']." ";

            // Must provide both size and angle if spec any - why!? ans = GNUplot.
            if($options['vector_arrow_angle'])
            {
              $script_body .= ", ".$options['vector_arrow_angle']." ";
            }
            else
            {  
              $script_body .= ", 30";
            }
          }                
          if($options['vector_arrow_filled'])
          {
            $script_body .= "filled ";
          }          
          $script_body .= "ls ".$cluster->get_cluster_color_label();

          // Corresponding datafile part.
          foreach($cluster->cluster_getIterator() as $knn_vector)
          {
            $cluster_data .= $knn_vector->toString_rcknn_vectors($visual_k);
          }
          break;        
        }        
        case 'avg_vector' :
        {
          // Script output part.
          $script_body .= "'$cluster_file' with vectors ";        
          if($options['vector_arrow_size'])
          {
            $script_body .= "size ".$options['vector_arrow_size']." ";

            // Must provide both size and angle if spec any - why!? ans = GNUplot.
            if($options['vector_arrow_angle'])
            {
              $script_body .= ", ".$options['vector_arrow_angle']." ";
            }
            else
            {  
              $script_body .= ", 30";
            }
          }      
          if($options['vector_arrow_filled'])
          {
            $script_body .= "filled ";
          }        
          $script_body .= "ls ".$cluster->get_cluster_color_label();

          // Corresponding datafile part.
          foreach($cluster->cluster_getIterator() as $knn_vector)
          {
            $cluster_data .= $knn_vector->toString_avg_vector($visual_k);
          }          
          break;
        }        
        default :
        {
          throw new Exception("Invalid vision type '$type'");
        }
      }                        

      file_put_contents($output_dir."/".$cluster_file, $cluster_data."\n");

    } //end foreach cluster.

    $script_body .= "\n";

    //write the script file out with header to generate a .png image,
    //run the script, then write it out again without header.
    file_put_contents($output_dir."/".$script_file, $png_script_header.$script_body);
    //the shit gnuplot throws out on empty file dont matter. Just ugly.
    $retval = 0;
    $output = array();
    exec("cd $output_dir; gnuplot $script_file 2>/dev/null", $output, $retval); //in a subshell env.
    if($retval)
    {
      //hard to get a sensible error string from exec/gnuplot so none given.
      trigger_error("gnuplot returned error. Continuing but plots wont work.", E_USER_NOTICE);
    }
    $png_script_header = explode("\n", $png_script_header);
    $png_script_header[0] = "#".$png_script_header[0];
    $png_script_header[1] = "#".$png_script_header[1];
    $png_script_header = implode("\n", $png_script_header);
    file_put_contents($output_dir."/".$script_file, $png_script_header.$script_body);
  }

  /**
   * Show a points plot of the data classes - not the clustering.
   * We want to show the data set with classes of points colored as they are in any histograms.
   * Theres a hack for class labels that are zero because gnuplot cant handle "0" as a line reference.
   * Also assumes labels are numeric which is a bummer, but they should be anyway.
   * name, dim
   */
  public static function visualize_data(KNNGraph $graph, $output_dir, $output_name, $dim, Array $options = null)
  {
    $png_file;
    $script_file;
    $png_script_header;
    $script_body = "";  
    $point_size_string = "";
    $point_type_string = "";    
    $output_dir = $options['name']."_classes";

    mkdir2($output_dir, true, true);

    if(self::$class_colors == null)
    {
      self::make_class_colors_graph($graph);
    }

    $png_file = $options['plot_pre']."_".$options['name'].$options['plot_post'];
    $script_file = $options['script_pre']."_".$options['name'].$options['script_post'];
    $png_script_header = "set output '$png_file'\nset terminal png large size ".$options['plot_size_x'].", ".$options['plot_size_y']."\n";

    // Set point options to a value.
    // This should not include the  prefix, it should have been removed in upper layer - time.
    $point_size_string = "";
    if(! empty($options['point_size_multiplier']) || ! empty($options['point_size']))
    {
      $m = (isset($options['point_size_multiplier']) ? $options['point_size_multiplier'] : 1);
      $p = (isset($options['point_size']) ? $options['point_size'] : 1);
      $point_size_string = "ps ".($p*$m);
    }
    $point_type_string = "";
    if(! empty($options['point_type']))
    {
      $point_type_string = "pt ".$options['point_type'];
    }

    // Set x and y ranges.
    if(isset($options['xrange']))
    {
      $script_body .= "set xrange[".$options['xrange'][0].":".$options['xrange'][1]."]\n";
    }
    if(isset($options['yrange']))
    {
      $script_body .= "set yrange[".$options['yrange'][0].":".$options['yrange'][1]."]\n";
    }

    // Set arbitrary gnuplot 'set'/'unset'able commands.
    foreach($options['gnuplot_unset'] as $unset)
    {
      $script_body .= "unset $unset \n";
    }
    foreach($options['gnuplot_set'] as $set)
    {
      $script_body .= "unset $set \n";
    }

    //set colors for classes
    foreach(self::$class_colors as $class_label => $color)
    {
      //hack should warn.
      if(empty($class_label))
      {
        $class_label = 1;
      }

      $script_body .= "set style line $class_label lc rgb \"#".$color."\" $point_type_string $point_size_string\n";
    }

    // Set the dimensionality of plot.
    $dim = self::get_dim($clustering);
    if(($dim == 2) || ($dim == 1))
    {
      $script_body .= "plot ";
    }
    if($dim >= 3)
    {
      $script_body .= "splot ";
    }
    if($dim > 3)
    {
      trigger_error("Plotting > 3D data", E_USER_NOTICE);
    }

    // Separate out the classes.
    $classes = array();
    foreach($graph as $knn_vector)
    {
      if(! isset($classes[$knn_vector->get_class_label()]))
      {
        $classes[$knn_vector->get_class_label()] = "";
      }

      $classes[$knn_vector->get_class_label()] .= $knn_vector->__toString()."\n";
    }

    {
      $eol = "";
        foreach($classes as $class_label => $class)
        {
          $class_file =  $options['name']."_class".$class_label.".txt";
          file_put_contents($output_dir."/".$class_file, $class."\n"); 
          // Write terminator for previous line.
          $script_body .= $eol;
          $eol = ",\\\n";
          $script_body .= "'$class_file' with points ls $class_label";
        }
      $script_body .= "\n";
    }

    // Write the script file out with header to generate a .png image,
    // Run the script, then write it out again without header.
    file_put_contents($output_dir."/".$script_file, $png_script_header.$script_body);
    // The shit gnuplot throws out on empty file dont matter. Just ugly.
    system("cd $output_dir; gnuplot $script_file 2> /dev/null"); //in a subshell env.
    $png_script_header = explode("\n", $png_script_header);
    $png_script_header[0] = "#".$png_script_header[0];
    $png_script_header[1] = "#".$png_script_header[1];
    $png_script_header = implode("\n", $png_script_header);
    file_put_contents($output_dir."/".$script_file, $png_script_header.$script_body);
  }

  /*
  public static function histograms(AbstractKNNClustering $clustering, Array $options)
  {
    $histo_script_header_fmt = "#
set output 'plot_%s.png'
set terminal png large size 1200, 600
set style data histogram
set style histogram rowstacked gap 0.5
set style fill solid border -1
set xtics nomirror rotate by -45 offset 0,-1.5
set boxwidth 0.8 relative
unset key
plot '%s' ";

    if(self::$cluster_colors == null)
    {
      trigger_error("No Cluster colors array is set. Generating one from current clustering", E_USER_NOTICE);
      KNNClusteringVision::make_clustering_colors($clustering);
    }
    if(self::class_colors == null)
    {
      KNNClusteringVision::make_class_colors($clustering);
    }

    $cluster_script = "";  
    foreach($classes as $class_label => $nil)
    {
      //hack to fix gnupltos not liking zero class label which is the default not labeled label.
      $color = self::$class_colors[$class_label];
      if(empty($class_label))
      {
        $class_label = 1;
      }

      $cluster_script .= "set style line $class_label lc rgb \"#".$color."\"\n";
    }

    $cluster_script .= "set xlabel 'cluster' offset 0,-1\n";
    $cluster_script .= "set ylabel 'number of points from each class'\n";

    $cluster_script .= sprintf($histo_script_header_fmt, $output_file_part."_raw_clusters_histogram", $output_file_part."_raw_clusters_histogram.txt");

    $eol = "";
    $fixed_gnu = false;
    $i = 2;
    foreach($classes as $class_label => $nil)
    {
      $cluster_script .= $eol;

      //hack to fix gnupltos not liking zero class label which is the default not labeled label.
      if(empty($class_label))
      {
        $class_label = 1;
      }

      if(! $fixed_gnu)
      {
        $cluster_script .= " using ".($i).":xtic(1) ls $class_label";
        $fixed_gnu = true;
      }
      else
      {
        $cluster_script .= " '' using ".($i)." ls $class_label";
      }
      $i++;
      $eol = ", \\\n";
    }
    file_put_contents($output_dir."/script_".$output_file_part."_raw_clusters_histogram.gnuplot", $cluster_script);

    // Now run it!
    system("cd $output_dir; gnuplot $output_dir/script_".$output_file_part."_raw_clusters_histogram.gnuplot");

    // Output the gnuplot script to render the above gnuplot format classes histogram file and run it.
    $class_script = "";  
    foreach($clusters as $cluster_label => $nil)
    {
      //hack to fix gnupltos not liking zero class label which is the default not labeled label.
      $color = self::$cluster_colors[$cluster_label];
      $class_script .= "set style line $cluster_label lc rgb \"#".$color."\"\n";
    }

    $class_script .= "set xlabel 'classes' offset 0,-1\n";
    $class_script .= "set ylabel 'number of points from each cluster'\n";    

    $class_script .= sprintf($histo_script_header_fmt, $output_file_part."_raw_classes_histogram", $output_file_part."_raw_classes_histogram.txt");

    $eol = "";
    $fixed_gnu = false;
    $i = 2;
    foreach($clusters as $cluster_label => $nil)
    {
      $class_script .= $eol;
      if(! $fixed_gnu)
      {
        $class_script .= " using ".($i).":xtic(1) ls $cluster_label";
        $fixed_gnu = true;
      }
      else
      {
        $class_script .= " '' using ".($i)." ls $cluster_label";
      }
      $i++;
      $eol = ", \\\n";
    }
    file_put_contents($output_dir."/script_".$output_file_part."_raw_classes_histogram.gnuplot", $class_script);
    // Now run it!
    system("cd $output_dir; gnuplot $output_dir/script_".$output_file_part."_raw_classes_histogram.gnuplot");
  }
  */

  private static function get_dim(AbstractKNNClustering $clustering)
  {
    return 2;
  }

  /**
   * Convenience method to make a color array basically.
   * Dont know how many class there are from a clustering so this method.
   */
  public static function make_class_colors(AbstractKNNClustering $clustering)
  {  
    $class_colors = array();    
    $vectors = $clustering->cluster_getIterator();
    foreach($vectors as $v)
    {
      $vector_class = $v->get_class_label();                      
      // Set the class if not set.
      if(! isset($class_colors[$vector_class]))
      {
        $class_colors[$vector_class] = $vector_class;
      }
    }              

    $colors =  make_color_array(sizeof($class_colors) + 2);

    // No black no white.
    array_shift($colors);
    array_pop($colors);

    $class_colors = array_combine(array_keys($class_colors), $colors);

    self::$class_colors = $class_colors;
  }

  /**
   * Convenience method to make a color array basically.
   * Dont knwo how many class there are from a clustering so this method.
   */
  public static function make_class_colors_graph(KNNGraph $graph)
  {  
    $class_colors = array();    
    foreach($graph as $v)
    {
      $vector_class = $v->get_class_label();                      
      // Set the class if not set.
      if(! isset($class_colors[$vector_class]))
      {
        $class_colors[$vector_class] = $vector_class;
      }
    }              

    $colors =  make_color_array(sizeof($class_colors) + 2);

    // No black no white.
    array_shift($colors);
    array_pop($colors);

    $class_colors = array_combine(array_keys($class_colors), $colors);

    self::$class_colors = $class_colors;
  }  

  /**
   * Convenience method to make a color array basically.
   * the same array has to be used at multiple levels.
   */
  public static function make_clustering_colors(AbstractKNNClustering $clustering)
  {  
    $cluster_colors = array();    
    foreach($clustering->clustering_getIterator() as $cluster)
    {
      $class = $cluster->get_cluster_color_label();                      
      // Set the class if not set.
      if(! isset($cluster_colors[$class]))
      {
        $cluster_colors[$class] = $class;
      }
    }              

    $colors =  make_color_array(sizeof($cluster_colors) + 2);
    //print_r($colors);
    //$colors =  array_fill(0, sizeof($cluster_colors) + 2, "0080C0");
    // No black no white.
    array_shift($colors);
    array_pop($colors);

    $cluster_colors = array_combine(array_keys($cluster_colors), $colors);

    self::$cluster_colors = $cluster_colors;
  }

  /**
   * Make sure same size or larger.
   */  
  public static function set_class_colors(Array $class_colors)
  {
    self::$class_colors = $cluster_colors;
  }

  /**
   * Make sure same size or larger.
   */
  public static function set_cluster_colors(Array $cluster_colors)
  {
    self::$cluster_colors = $cluster_colors;
  }  
}
