<?php

require_once( "set_include_path.php" );
require_once( "NTreeClusteringVirtualHeight.php" );
require_once( "NTreeClustering.php" );
require_once( "KSweepableClustering.php" );
require_once( "LoadableAbstractKNNClustering.php" );
require_once( "CliProgressUpdater.php" );

/**
 * Represents a cluster in a heirarchical clustering.
 * ~Everything except build method copied from RCKNN see that.
 * Descision was made not to extend KNN vice versa to keep the inheritance hierarchy understandable I guess.
 * Probly should extend either way.
 */
class KNNClusteringNTreeKNN extends NTreeClusteringVirtualHeight implements LoadableAbstractKNNClustering, KSweepableClustering
{
	const CLUSTERING_TYPE = 'knn';
	/* pointer to graph contructed from. */
	private $graph;
	



	/**
	 * KNN Cluster the data.
	 * Cluster up the data based on recipricol-ality of first $k neighbours.
	 * The RCKNN tree only ever needs building once. Changing the only option, k, does not require a rebuild.
	 * Sets k_max to min( k_max, k_unity ) and adds children to this node.
	 * Options:
	 * 	k should not really set here but equivalent to calling set_option_k() once built.
	 *	k_max the maximum k to cluster to, actual max is min( graph->k_max, k_max ).
	 * @param $graph KNNGraph to cluster up.
	 * @param $options Array associative array of settings.
	 */
	public function __construct( KNNGraph $graph, Array $options = array() )
	{
		// Construct an internal type NTreeNode to hold children.
		parent::__construct( null );
		$this->graph = $graph;
		$k_max;
		$temp_k;

		// Set options none of them are needed after construction 
		// Set the k_max
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
			$temp_k = $options['k'];
			unset( $options['k'] );
		}
		//If invalid options die.
		if( ! empty( $options ) )
		{
			throw new InvalidArgumentException( "Unknown options given to constructor" );
		}
		
		$this->build_clustering( $k, $k_max );
	}
	


	
	/**
	 * Get the type of this clustering.
	 */
	public function get_type()
	{
		return self::CLUSTERING_TYPE;
	}
	
	
	
	
	/**
	 * Check if has init time option.
	 */
	public static function has_option( $option )
	{
		return in_array( $option, array( "k", "k_max" ) );
	}
	
	
	
	/**
	 * Get set options how this class sees fit.
	 * Dont return k_max; not important.
	 */
	public function get_options()
	{
		$options = array();
		$options['k'] = $this->get_option_k();
		return $options;
	}
	

	
	
	/**
	 * Get init time options.
	 * Actually will get k.
	 */
	public function get_option( $option_name )
	{
		$method = "get_option_".$option_name;
		if( ! method_exists( $this, $method ) )
		{
			return null;
		} 
		return $this->$method();
	}




	/**
	 * k refers to the height of the cluster*ing* NOT cluster.
	 * The height of a leaf node is zero T.f this return -1.
	 */
	public function get_option_k()
	{
		return $this->get_clustering_k();
	}
	
	
	
	/**
	 * k refers to the height of the cluster*ing* NOT cluster.
	 */
	public function get_option_k_max()
	{
		return $this->get_k_max();
	}
	
	
	
	
	/**
	 * Set the k option.
	 * Null is valid and means biggest logical value. If the k value is same as current nothing happens.
	 * Whether tree is virtual or not decided after build.
	 * @param k int k > 0 && k < graph->k_max.
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
	 * Cluster method required by interface, does nil here.
	 * The point was you change the post init time options of a clustering,
	 * it may need reclustering so you call this method to do that when youve set all the options.
	 */
	public function cluster()
	{}
	
	
	
	
	/**
	 * KNN Cluster the data.
	 * Cluster up the data based on linkage of first $k neighbours.
	 * The KNN tree only ever needs building once. Changing the only option, k, does not require a rebuild.
	 */
	private function build_clustering( $k, $k_max )
	{			
		$clusters = array();
		$progress = new CliProgressUpdater();
		
		// Set initial group of KNNClusteringNTreeKNNs. Leaf nodes are special.
		foreach( $this->graph as $node )
		{
			$cluster = new NTreeClustering( $node );
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
					$new_cluster = new NTreeClustering();
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

		// If k option was passed in try and set it.
		if( $k )
		{
			$this->set_option_k( $k );
		}
	}	
	
	
	
	
	/**
	 * Helper to build_clustering() that collects label of clusters that are directly connected to $cluster and should be merged at the given k value.
	 * Those clusters will be merged into one cluster at the end of current iteration.
	 * @return $labels a set of labels set initially in build_clustering() that uniquely id a cluster. Note *not* the clusters rep label, its label.
	 */
	private static function collect_connected_labels( NTreeClustering $cluster, $k )
	{
		// Init the labels, to contain the checked clusters label.
		$labels = array( $cluster->cluster_label_knn_build );

		// Must use k value set for clustering not node's k value!
		foreach( $cluster->get_leaf_data() as $node )
		{
			for( $i = 0; $i < $k; $i++ )
			{
				$neighbour_node = $node->get_nn($i);
				$neighbour_cluster = $neighbour_node->get_root_cluster();
		
				// Dont check if cluster is already in labels.
				if( ! in_array( $neighbour_cluster->cluster_label_knn_build, $labels ) )
				{
					array_push( $labels, $neighbour_cluster->cluster_label_knn_build );
				}
			}
		}
		return $labels;
	}
	

	 
	 
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
