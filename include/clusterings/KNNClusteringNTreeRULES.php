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
 * ~Everything except copied from KNN see that.
 * Descision was made not to just extend KNN to keep the inheritance hierarchy sensible I guess.
 * $arr[] is signif faster than array_push()!
 */
class KNNClusteringNTreeRULES extends NTreeClusteringVirtualHeight implements KSweepableClustering
{
	const CLUSTERING_TYPE = "rules";
	/** The location where rules modules are all kept. */
	const RULES_DIR = "rules";
	/** Prefix to clustering modules. */
	const RULES_NAME = "Rule";
	/** Dynamically loaded array full of rule objects. */
	private $rules = array();
	/** Pointer to the graph used in construction. */
	private $graph;
	


	
	/**
	 * Construct a new Clustering.
	 * @param $graph a KNNGraph from which clustering is built by collecting KNNVector pointers into clusters.
	 * @param $options options to the construction: k,k_max,rules
	 */
	public function __construct( KNNGraph $graph, Array $options = array() )
	{			
		parent::__construct( null );
		$this->graph = $graph;
		$rules = array();
		$k_max = null;
		$k = null;
		
		// Set the max k.
		if( isset( $options['k_max'] ) )
		{
			if( $options['k_max'] < 0 )
			{
				throw new OutOfBoundsException( "k_max must be non negative" );
			}
			if( $options['k_max'] > $graph->get_k_max() )
			{
				 throw new OutOfBoundsException( "k_max too large for graph" );
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
		//If invalid options die.
		if( ! empty( $options ) )
		{
			throw new InvalidArgumentException( "Unknown options '".implode( ",", array_keys( $options ) )."' given to clustering build" );
		}
		
		$this->build_clustering( $k_max );
		
		// If k option was passed in try and set it.
		if( isset( $k ) )
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
	 * Check whether this class or a module available to be loaded supports the option.
	 * The option will be to this class not to the the module so it has the form <module name>_<module>_option> if to the module.
	 * There must not be a collision between an option of this and the name of a module obivously - module are essentially options to this.
	 */
	public static function has_option( $option )
	{
		$rules_options = array( 'rules' );
		
		$has_option = in_array( $option, array( 'k', 'k_max' ) );
		
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
		$options['k'] = $this->get_option_k();
		
		foreach( $this->rules as $rule_name => $rule )
		{
			// The rule's name. 
			$options[$rule_name] = true;
			// All the rule's options, push not merge.
			array_push( $options, $rule->get_options() );
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
	 * Build the clustering with this as root.
	 * Loaded rules determine which edges in a KNN graph get cut.
	 */
	public function build_clustering( $k_max )
	{
		$progress = new CliProgressUpdater();
		$clusters = array();	
	
		// Set initial group of KNNClusteringNTreeKNNs. Leaf nodes are special.
		foreach( $this->graph as $node )
		{
			$cluster = new NTreeClustering( $node );
			unset( $cluster->cluster_label_knn_build );
			$clusters[] = $cluster;
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
					$rep_labels[] = $r;
				}
				$min_label = min( $rep_labels );
				
				foreach( $rep_labels as $label )
				{
					$links[$label] = $min_label;
				}
			}

			// Make the level of indirection 1 or 0 s.t. each label no point directly to its rep. 
			foreach( $links as $label => $nil )
			{
				if( $links[$label] < $label )
				{
					$links[$label] = $links[$links[$label]];
				}
			}

			// Find all clusters with the same representative - min - cluster and create a new cluster as the merger of them.
			foreach( $links as $label => $cluster )
			{
				// If the cluster is assigned to itself it is the representative cluster for all clusters found connected to it.
				// Those clusters will have this key in the links array - at the offset corresponding to the cluster.
				if( $label == $cluster )
				{
					$new_cluster = new NTreeClustering( null );
					$new_cluster->add_child( $clusters[$label] );
					$new_clusters[] = $new_cluster;
					$links[$label] = $new_cluster;
				}
				else
				{
					//links[$label] is less than $label and point to a $links[$cluster] is a cluster.
					$links[$cluster]->add_child( $clusters[$label] );
				}
			}

			$progress->update( $k_curr/$k_max );
			unset( $clusters );
			unset( $links );
			$clusters = $new_clusters;
			$k_curr++;
		}

		// The Clustering has height of what ever K value the last clustering reached + 1.
		// If the array of Clusterings $clusterings has size 1 means reached unity.
		// If not height = graph->k_max + 1.
		// So if unity reached the root of the tree has one child.
		// The children of the root always represents the height - 1 clustering i.e they are clusters in the k = height - 1 clustering.
		foreach( $clusters as $cluster )
		{
			$this->add_child( $cluster );
		}
	}	
	
	
	
	
	/**
	 * Helper to build_clustering() that collects label of clusters that are directly connected to $cluster and should be merged at the given k value.
	 * Those clusters will be merged into one cluster at the end of current iteration.
	 * @return $labels a set of labels set initially in build_clustering() that uniquely id a cluster. Note *not* the clusters rep label, its label.
	 */
	private function collect_connected_labels( NTreeClustering $cluster, $k )
	{
		// Init the labels, to contain the checked clusters label.
		$labels = array( $cluster->cluster_label_knn_build );
		$self_label = $cluster->cluster_label_knn_build;

		// Must use k value set for clustering not node's k value!
		foreach( $cluster->get_leaf_data() as $node )
		{
			for( $i = 0; $i < $k; $i++ )
			{
				$neighbour_node = $node->get_nn($i);
				$neighbour_cluster = $neighbour_node->get_root_cluster();
		
				// Dont check if cluster is already in $labels.
				//if( ! in_array( $neighbour_cluster->cluster_label_knn_build, $labels ) )
				//{
				if( ( $neighbour_cluster->cluster_label_knn_build != $self_label ) && ( ! in_array( $neighbour_cluster->cluster_label_knn_build, $labels ) ) )
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
						$labels[] = $neighbour_cluster->cluster_label_knn_build;
						//array_push( $labels, $neighbour_cluster->cluster_label_knn_build );
					}
				}
			}
		}
		return $labels;
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
