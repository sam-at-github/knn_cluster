<?php

/**
 * Get, set and query options methods.
 * This is ruffly a pseudo interface for modules.
 * has_option(), get_option(s)(), [set_option(), cluster()]
 * __construct(..., Array $options)
 * An object should construct to a useful state and no need setters after that to init it.
 * All post init settable options must be settable at init.
 *  All post init settable options must be returned by has_option(), get_option(s)().
 * Option to add method hat_settable_option() later.
 */
interface LoadableAbstractKNNClustering
{
  public function __construct(KNNGraph $graph, Array $options);

  /**
   * Get the type of this clustering.
   * Should be unique.
   */
  public function get_type();

  /**
   * Check whether this Clustering has a constructor option by the name of $option.
   * If the clustering is resolved at runtime whether the clustering supports an option also has to be resolved at runtime.
   */
  public static function has_option($option_name);

  /**
   * Use has_option() to see if option is valid. 
   * A convenience basis method.
   */
  public function get_option($option_name);

  /**
   * Get set options how this class sees fit.
   * Should generally be settable options only.
   */
  public function get_options();

  /**
   * optional set_option($name, $value);
   */

  /**
   * Clustering the data. Should be called after instantiation and possibly setting some options.
   * Only needed where a clustering has post init settable options.
   */
  public function cluster();
}
