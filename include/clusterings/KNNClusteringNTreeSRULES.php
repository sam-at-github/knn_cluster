<?php

require_once( "set_include_path.php" );
require_once( "NTreeClusteringVirtualHeight.php" );
require_once( "KSweepableClustering.php" );
require_once( "CliProgressUpdater.php" );

foreach( glob( dirname( __FILE__ )."/rules/Rule*.php" ) as $file_name )
{
    require_once( $file_name );
}

/**
 * Represents a cluster in a heirarchical clustering.
 * Copied form RULES
 * Descision was made not to just extend RULES to keep the inheritance hierarchy semantically sensible I guess.
 * @todo 3pma to npma
 * @todo fix stupid design; should be a special root node and all others Ntree not all specials!
 */
class KNNClusteringNTreeSRULES extends NTreeClusteringVirtualHeight implements KSweepableClustering
{
	const CLUSTERING_TYPE = "srules";
	/** The location where rules modules are all kept.*/
	const RULES_DIR = "rules";
	/** Prefix to clustering modules. */
	const RULES_NAME = "Rule";
	const DEFAULT_STAB_THOLD = 0.95;
	const SMALL_CLUSTER = 12;
	const SMALL_CLUSTER_STABLE_K = 10;	
	/** 
	 * An array full of rule objects.
	 * Currently storing in each object even though same for each - describes the build not really the object.
	 * Needed for get_options().
	 */
	private $rules = array();
	private $stab_thold = self::DEFAULT_STAB_THOLD;
	private $use_3pma = false;
	
	
	
	
	/**
	 * Construct a new Clustering.
	 * @param $graph a KNNGraph from which clustering is built by collecting KNNVector pointers into clusters.
	 * @param $options options to the construction: k,k_max,rules
	 */
	public function __construct( KNNGraph $graph, Array $options = array() )
	{			
		// Construct an internal type NTreeNode to hold children.
		parent::__construct( null );
		$this->graph = $graph;
		$k = null;
		$k_max = null;
		
		// Set the max k
		if( isset( $options['k_max'] ) )
		{
			if( ( $options['k_max'] < 0 ) || ( $options['k_max'] > $graph->get_k_max() ) )
			{
				throw new OutOfBoundsException( "k_max '".$options['k_max']."' invalid" );
			}
			$k_max = $options['k_max'];
			unset( $options['k_max'] );
		}
		else
		{
			$k_max = $graph->get_k_max();
		}
		
		// Set the k option. Set after clustering and may cause construct to fail if too big.
		if( isset( $options['k'] ) )
		{
			$k = $options['k'];
			unset( $options['k'] );
		}
		
		// Set cluster stability threshold
		if( isset( $options['stab_thold'] ) )
		{
			if( ( $options['stab_thold'] <= 0 ) || ( $options['stab_thold'] > 1 ) )
			{
				throw new OutOfBoundsException( "k_max '".$options['k_max']."' invalid" );
			}
			$this->stab_thold = $options['stab_thold'];
			unset( $options['stab_thold'] );
		}
		
		// Set whether to use 3pma
		if( isset( $options['use_3pma'] ) )
		{
			if( $options['use_3pma'] )
			{
				$this->use_3pma = true;
			}
			unset( $options['use_3pma'] );
		}
			
		
		// Load all rules and set any option for them passed in.
		// If option rules is not set you get plain old knn.
		if( isset( $options['rules'] ) )
		{
			// --co_rules=rcknn,dense
			// --co_dense_alpha=1.2
			$rule_names =  preg_split( "/,\s*/", $options['rules'] );

			foreach( $rule_names as $rule_name )
			{
				// Handle empty rule - rules="".
				if( ! strlen( $rule_name ) )
				{
					continue;
				}				
				
				$rule_class = self::RULES_NAME.$rule_name;
				/** collected options to pass to constructor of rule. */
				$rule_options = array();
				$new_rule = null;
				
				// Check the class exists.
				// Should use instance of and have rule extend a Rule object s.a.t ensure I.f.
				if( ! class_exists( $rule_class ) )
				{
					throw new InvalidArgumentException( "Unknown Rule '$rule_name' specified" );
				}
				
				// Collect and unset options belonging to the rule.
				// --co_<rulename>_<optionname>=<value>	
				// Options to modules have the form <module_name_part>_<option_name> E.g. dense_alpha and dense_sample_k
				foreach( $options as $option_name => $option_value )
				{
					
					$module_option_array = array();
					preg_match( "/^([^_]+)_(.+)/", $option_name, $module_option_array );
		 			if( sizeof( $module_option_array ) == 3 )
					{
						if( $module_option_array[1] == $rule_name )
						{
							$rule_options[$module_option_array[2]] = $option_value;
							unset( $options[$option_name] );
						}
					}
				}
				$new_rule = new $rule_class( $rule_options );
				$this->rules[$rule_name] =  $new_rule;
			}
			unset( $options['rules'] );
		}
		
		// If invalid options die.
		if( ! empty( $options ) )
		{
			throw new InvalidArgumentException( "Unknown options '".implode( ",", array_keys( $options ) )."' given to clustering build" );
		}
		
		$this->build_clustering( $k_max );
		
		if( $k )
		{
			$this->set_option_k( $k );
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
	 * Check whether this class or a module avaiable to be loaded supports the option.
	 * The option will be to this class not to the the module so it has the form <module name>_<module>_option> if to the module.
	 * There must not be a collision between an option of this and the name of a module obivously - module are essentially options to this.
	 */
	public static function has_option( $option )
	{
		$rules_options = array( 'rules' );
		
		$has_option = in_array( $option, array( 'k', 'k_max', 'stab_thold', 'use_3pma' ) );
		
		if( ! $has_option )
		{	
			$has_option = in_array( $option, $rules_options );
			if( ! $has_option )
			{
				// Options to modules have the form <module_name_part>_<option_name> E.g. dense_alpha and dense_sample_k
				$module_option_array = array();
				preg_match( "/^([^_]+)_(.+)/", $option, $module_option_array );
	 			if( sizeof( $module_option_array ) == 3 )
				{
					$class_name = self::RULES_NAME.strtoupper( $module_option_array[1] );
					$module_option = $module_option_array[2];
					// Rule classes were all pre included on load.
					if( class_exists( $class_name ) )
					{
						$has_option = $class_name::has_option( $module_option );
					}
				}
			}
		}
		return $has_option;
	}

	
	
	
	/**
	 * Get set options how this class sees fit.
	 * Should generally be settable options only.
	 * @returns an array of arrays of options and options.
	 */
	public function get_options()
	{
		$options = array();
		$options['stab_thold'] = $this->stab_thold;
		$options['use_3pma'] = $this->use_3pma;
		$options['k'] = $this->get_option_k();
				
		foreach( $this->rules as $rule_name => $rule )
		{
			// The rule's name. 
			$options[$rule_name] = $rule->get_options();
		}
		return $options;
	}	
		 				
	 	
	
	
	/**
	 * Get the maximum k value the clustering can support.
	 */
	public function get_option_k_max()
	{
		return $this->get_height() - 1;
	}
	



	/**
	 * k refers to the height of the cluster*ing* NOT cluster.
	 */
	public function get_option_k()
	{
		return $this->get_clustering_k();
	}
	
	
	
	
	/**
	 * Set the k option.
	 * The only restriction is that k > 0 && k < graph->k_max.
	 * Null is valid and means biggest logical value. If the k value is same as current nothing happens.
	 * Whether tree is virtual or not decided after build.
	 */
	public function set_option_k( $k )
	{		
		if( $k < 0 )
		{
			throw new OutofBoundsException( "K must be non negative. Cannot set K to '".$k."'" );
		}
		$this->set_height( $k + 1 );
	}
	
	
	
	
	/**
	 * cluster method required by interface, does nil here.
	 * The point was you change the live time options of a clustering it may need reclustering so you call this method to do that when youve set all the options.
	 */
	public function cluster()
	{}
	
	
	

	/**
	 * Get the maximum possible k this clustering can cluster to.
	 * That is the maximum value of param to set_option_k().
	 */
	public function get_k_max()
	{
		return $this->get_height() - 1;
	}
	
	
	
	
	/**
	 * Build the clustering with this as root.
	 * Loaded rules determine which edges in a KNN graph get cut.
	 * adds two undeclared fields to clusters cluster_label_knn_build, is_stable.
	 */
	public function build_clustering( $k_max )
	{			
		$progress = new CliProgressUpdater();
		$stable_clusters = array();
		$clusters = array();
		
		// Initialize leaf node KNNClusteringNTree clusters.
		// Set them un_stable.
		foreach( $this->graph as $node )
		{
			//Parsing all these option to the individual nodes is BS need a redesign.
			$cluster = new NTreeClustering( $node );
			$cluster->is_stable = false;
			unset( $cluster->cluster_label_knn_build );
			array_push( $clusters, $cluster );
		}	
		
		$k_curr = 1;
		while( ( $k_curr <= $k_max ) && ( sizeof( $clusters ) > 1 ) )
		{
			/** Holds the k+1 clustering of clusters in $clusters. */
			$new_clusters = array();
			$links = array();
			
			// Initialize each clusters link to itself.
			foreach( $clusters as $label => $cluster )
			{
				$cluster->cluster_label_knn_build = $label;
				$links[$label] = $label;
			}
					
			// Find the clusters, store result in $links array.
			foreach( $clusters as $cluster )
			{	
				// Get the labels of the clusters that are directly linked to / should be merged with, current cluster at current k value.
				// The current cluster's label is returned in the list.
				// Then get the set of reps clusters those clusters are linked to currently.
				// Then find the minimum of them and reassign all other reps to found this min rep value.
				$collected_labels = self::collect_connected_labels( $cluster, $k_curr );	
				$rep_labels = array();
				
				foreach( $collected_labels as $label )
				{
					$r = $label;
					while( $links[$r] < $r )
					{
						$r = $links[$r];
					}
					array_push( $rep_labels, $r );
				}
				$min_label = min( $rep_labels );
				
				foreach( $rep_labels as $label )
				{
					$links[$label] = $min_label;
				}
			}

			// Make each index point directly to its representative.
			// Not to a nieghbour that points a neighbour that points to the representative for e.g.
			// in other words make the level of indirection 1 or 0.
			foreach( $links as $label => $nil )
			{
				if( $links[$label] < $label )
				{
					$links[$label] = $links[$links[$label]];
				}
			}

			// Find all clusters with the same representative cluster,
			// and create a new cluster as the merger of them.
			foreach( $links as $label => $cluster )
			{
				// If the cluster is assigned to itself it is the representative cluster for all clusters found connected to it.
				// Those clusters will have this key in the links array - at the offset corresponding to the cluster.
				if( $label == $cluster )
				{
					$new_cluster = new NTreeClustering( null );
					$new_cluster->add_child( $clusters[$label] );
					array_push( $new_clusters, $new_cluster );
					$links[$label] = $new_cluster;
				}
				else
				{
					//links[$label] is less than $label and point to a $links[$cluster] is a cluster.
					$links[$cluster]->add_child( $clusters[$label] );
				}
			}
			
			// Unset the current links and cluster for next iteration.
			unset( $clusters );
			unset( $links );
			$clusters = array();
			
			// Remove stable clusters.
			foreach( $new_clusters as $cluster ) 
			{
				if( ! self::is_stable( $cluster, $k_curr ) )
				{
					array_push( $clusters, $cluster );
				}
				// else the cluster is not considered in the next run at all.
				// The cluster must be marked so it can be ignored.
				else
				{
					$cluster->is_stable = true;
					array_push( $stable_clusters, $cluster );
				}
			}
			
			$progress->update( $k_curr/$k_max );
			$k_curr++;				
		}

		// The Clustering has height of what ever K value the last clustering reached + 1.
		// If the array of Clusterings $clusterings has size 1 means reached unity.
		// If not height = graph->k_max + 1.
		// So if unity reached the root of the tree has one child.
		// The children of the root always represents the height - 1 clustering i.e they are clusters in the k = height - 1 clustering.
		foreach( $stable_clusters as $cluster )
		{
			$this->add_child( $cluster );
		}
		// No clusters may be stable but k_max may have been reached thus merge unstable ans stable clusters.
		foreach( $clusters as $cluster )
		{
			$this->add_child( $cluster );
		}
	}
		
	
	
	
	private function is_stable( NTreeClustering $cluster, $k )
	{
		$stable = false;	
		
		if( $this->use_3pma )
		{
			$stab = $cluster->get_stability_points_3pma();
		}
		else
		{
			$stab = $cluster->get_stability_points();
		}
		$size = $cluster->get_size();
		
		if( ( $stab >= $this->stab_thold && $size > self::SMALL_CLUSTER ) || ( ( $stab >= $this->stab_thold ) && $k >= self::SMALL_CLUSTER_STABLE_K ) )
		{
			//print "\nFound stable cluster k = $k, stab = $stab, size = $size\n";
			$stable = true;
		}
		return $stable;
	}

	
	
	
	/**
	 * Helper to build_clustering() that collects label of clusters that are directly connected to $cluster and should be merged at the given k value.
	 * Those clusters will be merged into one cluster at the end of current iteration.
	 * MUST check that neighbour clusters being considered are not stable.
	 * @return $labels a set of labels set initially in build_clustering() that uniquely id a cluster. Note *not* the clusters rep label, its label.
	 */
	private function collect_connected_labels( NTreeClustering $cluster, $k )
	{
		// Init the labels, to contain the checked clusters label.
		$labels = array( $cluster->cluster_label_knn_build );

		if( $cluster->is_stable )
		{
			throw new Exception( "WTF cant cluster stable cluster!" );
		}

		// Must use k value set for clustering not node's k value!
		foreach( $cluster->get_leaf_data() as $node )
		{
			for( $i = 0; $i < $k; $i++ )
			{
				$neighbour_node = $node->get_nn($i);
				$neighbour_cluster = $neighbour_node->get_root_cluster();
				
				if( $neighbour_cluster->is_stable ) 
				{
					continue;
				}
		
				// Dont check if cluster is already in $labels.
				if( ! in_array( $neighbour_cluster->cluster_label_knn_build, $labels ) )
				{
					// Run all rules and only merge if all rules pass.
					$merge = true;
					foreach( $this->rules as $rule )
					{
						$merge = $rule->rule( $node, $neighbour_node, array( 'k' => $i + 1 ) );
						if( ! $merge )
						{
							break;
						}
					}
				
					if( $merge )
					{
						array_push( $labels, $neighbour_cluster->cluster_label_knn_build );
					}
				}
			}
		}
		return $labels;
	}


/*
 * toString methods.
 */	
	

	/**
	 * Print a string representation of this KNN Graph.
	 * Has to be either a cluster or clustering so chose -ing.
	 */
	public function __toString()
	{
		return $this->clustering_toString();
	}
}




/**
 * Token Exception class.
 * Only later >=5.3 support chaining.
 */
class KNNClusteringNTreeKNNException extends Exception
{
	public function __construct( $msg, $code = 0 )
	{
		parent::__construct( $msg, $code );
	}
}
?>
