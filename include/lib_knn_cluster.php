<?php  

/**
 * Random shit that has no home, but is for knn_cluster specifically.
 */

  /**
   * Throw exception if files exists.
   * Not really needed but if you change data then run again could get mix up.
   */
  function mkdir2($output_dir, $force = false, $cache_dir = false)
  {  
    $rm = false;
    if(is_dir($output_dir))
    {
      $rm_script = "rm -rf $output_dir";

      print "The output directory '$output_dir' exists. Insisting it be moved.\n";

      if($force)
      {
        print "'Force' option is set, not asking removing with '$rm_script'\n";
        $rm = true;
      }
      else
      {
        if(cli_ask_yes_no("Do you want me try to remove it with '$rm_script'?"))
        {
          $rm = true;
        }  
        else
        {
          throw new Exception("Output dir '$output_dir' exists. Insisting it be moved\n");
        }
      }

      if($rm)
      {
        system($rm_script, $retval);
        if($retval)
        {
          throw new Exception("Could not create dir $output_dir");
        }
      }
    }

    system("mkdir $output_dir -p", $retval);
    if($retval)
    {
      throw new Exception("Could not create dir $output_dir");
    }

    if($cache_dir)
    {
  file_put_contents($output_dir."/CACHEDIR.TAG", "Signature: 8a477f597d28d172789f06886806bc55
# This file is a cache directory tag created by (".basename(__FILE__).").
# For information about cache directory tags, see: http://www.brynosaurus.com/cachedir/
# tar supports this standard.");
    }
  }
?>
