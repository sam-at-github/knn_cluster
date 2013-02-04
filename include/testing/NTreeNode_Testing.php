<?php

require_once( "NTreeNode.php" );

$a = new NTreeNode( 1 );
$b = new NTreeNode( 2 );
$c = new NTreeNode();
$c->add_child( $a );
$c->add_child( $b );

$d = new NTreeNode( 3 );
$e = new NTreeNode( 4 );
$f = new NTreeNode();
$f->add_child( $d );
$f->add_child( $e );

$g = new NTreeNode( 5 );
$h = new NTreeNode( 6 );
$i = new NTreeNode( 7 );
$j = new NTreeNode();
$j->add_child( $g );
$j->add_child( $h );
$j->add_child( $i );

$k = new NTreeNode();
$k->add_child( $c );
$k->add_child( $f );
$k->add_child( $j );

print "Print From Root\n";
print $k."\n\n";


//test iterator
print "Test Iterator On NTreeNode\n";
foreach( $k as $kk )
{
	print "$kk \n";
}

print "Test get_leaves()\n";
$leaves = $k->get_leaves();
foreach( $leaves as $leaf )
{
	print "$leaf \n";
}

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
	//print get_class( $d )."\n";
	print $d."\n";
}



print "Test get_num_children() range\n";
print $b->get_num_children()." to ".$k->get_num_children()."\n";

print "Test get_parent()\n";
print $a->get_parent()."\n";
print $a->get_parent()->get_parent()."\n";
print $a->get_parent()->get_parent()->get_parent()."\n";
