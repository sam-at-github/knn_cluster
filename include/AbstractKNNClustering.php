<?php

require_once( 'set_include_path.php' );
require_once( "KNNVector.php" );
require_once( "AbstractKNNCluster.php" );



/**
 * An interface for visualization and simple access to a clustering of KNNVectors.
 * A cluster is just a collection of items, a clustering is just a collection of such collections.
 * The Iterator is declared to return KNNCluster types, which is just an array with useful access methods encpsulated into a class.
 * Note Interfaces cant implement interfaces in PHP - I dont think - so have to make abstract.
 * Theres a sort of pseudo I.f. built into this which is has_option(), get_option(s)(), set_option(), and invisibly build_clustering( ... , Array $options ).
 * The has_option() only checks whether the build_clustering() takes a given option and a clustering might only take the option at build time.
 * By psuedo convention all live time options must be settable at build time too.
 */
interface AbstractKNNClustering
{
	
	/**
	 * get the number of clusters / partitions.
	 */
	public function get_num_clusters();
	
	/**
	 * Feasible that all clusterings will have a k value.
	 * If not return the max set or null or 0 - type dependent.
	 */
	public function get_clustering_k();
	
	/**
	 * *All* clusterings inheriently have a k_max set by the graph they were built from.
	 * What this value means depends on type. But get_k_max() >= get_clustering_k().
	 */
	public function get_k_max();	
	
 	/**
 	 * Only required function for IteratorAggregate I.f.
 	 * ArrayIterator implements Iterator for you on the array.
 	 * MUST return KNNCluster types - no way to enforce that really in PHP but you MUST!
 	 * This is just the default no gaurentee sub classes even have an internal $clusters array.
 	 */
	public function clustering_getIterator();
	/*
	{
 		$clusters = array();
 		foreach( $this->clusters as $cluster )
 		{
 			array_push( $clusters, new KNNCluster( $cluster ) );
 		}
 		return new ArrayIterator( $clusters );
	}
	*/
	
	/**
	 * Dont use __toString in interfaces
	 */
	public function clustering_toString();
}
?>
