<?php

interface KSweepableClustering
{
  /** 
   * k_max is upper bounded by the graph that the clustering was built from.
   * It may be less then that id the clustering stopped for any arbitrary reason.
   * Attempting to set_option_k() to greater than k_max should cause Exception to be thrown.
   */
  public function set_option_k($k);
}
