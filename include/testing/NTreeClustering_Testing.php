<?php

require_once( "../NTreeClustering.php" );

$a = new NTreeClustering( new KNNVector( new Vector( array(1,2) ), 5  ) );
$b = new NTreeClustering( new KNNVector( new Vector( array(3,4) ), 5  ) );
$c = new NTreeClustering();
$c->add_child( $a );
$c->add_child( $b );

$d = new NTreeClustering( new KNNVector( new Vector( array(5,6) ), 5  ) );
$f = new NTreeClustering();
$f->add_child( $d );

$g = new NTreeClustering( new KNNVector( new Vector( array(7,8) ), 5  ) );
$h = new NTreeClustering( new KNNVector( new Vector( array(9,10) ), 5  ) );
$i = new NTreeClustering( new KNNVector( new Vector( array(11,12) ), 5  ) );
$j = new NTreeClustering();
$j->add_child( $g );
$j->add_child( $h );
$j->add_child( $i );

$k = new NTreeClustering();
$k->add_child( $c );
$k->add_child( $f );
$k->add_child( $j );


print "stab a ".$a->get_stability()."\n";
print "stab b ".$b->get_stability()."\n";
print "stab c ".$c->get_stability()."\n\n";
print "stab d ".$d->get_stability()."\n";
print "stab f ".$f->get_stability()."\n\n";
print "stab k ".$k->get_stability()."\n";

print "\nGetters\n";
print "height ".$k->get_height().", size ".$k->get_size()." num_children ".$k->get_num_children()."\n"; 
print "get_num_children() range\n";
print $b->get_num_children()." - ".$k->get_num_children()."\n";

print "\nPrint the Root\n";
print $k."\n";


//test iterator
print "Test Iterator On NTreeNode\n";
foreach( $k as $kk )
{
	print "$kk \n";
}
print "\n";

print "Test get_leaves()\n";
$leaves = $k->get_leaves();
foreach( $leaves as $leaf )
{
	print "$leaf \n";
}
print "\n";

print "Test Iterate on a leaf.\n";
$leaves = $k->get_leaves();
foreach( $leaves as $leaf )
{
	foreach( $leaf as $l )
	{
		print "$l \n";
	}
}

print "Test get_leaf_data().\n";	
$data  = $k->get_leaf_data();
foreach( $data as $d )
{
	print $d."\n";
}
print "\n";

print "Test get_parent()\n";
print $a->get_parent()."\n";
print $a->get_parent()->get_parent()."\n";
print $a->get_parent()->get_parent()->get_parent()."\n";
print "\n";

print "Test get_knngraphclusteringadaptor() - getters.\n";
$adaptor = $k->get_knngraphclusteringadaptor( 0 );
print "size = ".$adaptor->get_size()." clusters ".$adaptor->get_num_clusters()."\n";
$adaptor = $k->get_knngraphclusteringadaptor( 1 );
print "size = ".$adaptor->get_size()." clusters ".$adaptor->get_num_clusters()."\n";
try
{
	$adaptor = $k->get_knngraphclusteringadaptor( 2 );	
	print "size = ".$adaptor->get_size()." clusters ".$adaptor->get_num_clusters()."\n";
}
catch( Exception $e ){ print $e->getMessage(); }
print "\n\n";

print "Test get_knngraphclusteringadaptor() -  get its iterator directly.\n";
$adaptor = $k->get_knngraphclusteringadaptor();
$it = $adaptor->getIterator();
$it->rewind();
while( $it->valid() ) //exactly what a forloop does.
{
	print "cluster:\n"; 
	$data = $it->current();
	foreach( $data as $d )
	{
		print $d."\n";
	}
	$it->next();
}

print "Test get_knngraphclusteringadaptor() - actual for loop\n";
foreach( $adaptor as $v )
{
	print "cluster:\n";
	foreach( $v as $d )
	{
		print $d."\n";
	}
}

$adaptor = $k->get_knngraphclusteringadaptor(0);
print "Test get_knngraphclusteringadaptor() - actual for loop at 0\n";
foreach( $adaptor as $v )
{
	print "cluster:\n";
	foreach( $v as $d )
	{
		print $d."\n";
	}
}
?>
