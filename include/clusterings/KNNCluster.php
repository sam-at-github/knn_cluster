<?php

require_once( "AbstractKNNCluster.php" );

class KNNCluster extends AbstractKNNCluster
{
	private static $cluster_count = 1;
	private $cluster = array();
	private $cluster_label = 1;
	private $cluster_k = 0;
	
	
	
	
	public function __construct( Array $cluster, $k = 0 )
	{
		$this->cluster_label = self::$cluster_count++;
		$this->cluster = $cluster;
		$this->cluster_k = $k;
	}
	
	
	
	
	public function cluster_getIterator()
	{
		return new ArrayIterator( $this->cluster );
	}
	
	
	
	
	public function get_size()
	{
		return sizeof( $this->cluster );
	}




	public function get_cluster_k()
	{
		return $this->cluster_k;
	}
	
	
	
	
	public function get_cluster_label()
	{
		return $this->cluster_label;
	}
	
	
	
	/**
	 * Not implemented return cluster_label which is sort of valid.
	 * I.e. it is a method of tracking the 'color'.
	 */
	public function get_cluster_color_label()
	{
		return $this->get_cluster_label();
	}
	
	
	
	public function set_cluster_label( $label )
	{
		$this->cluster_label = $label;
	}
	
	
	/*
	public function set_cluster_color_label( $label )
	{
		$this->cluster_color_label = $label;
	}
	*/
}
?>
