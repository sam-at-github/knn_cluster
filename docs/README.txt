knn_cluster.php: visualizing and getting basic stats on different type of KNN connected component based clustering.
Its *very* slow. The clustering alg needs to be rewritten and PHP arrays are slow which does not help. Not recommended for data sets over 10000pts. 10000pts -> 30mins and graph build is O(n^2).
Still, its useful for testing and visualizing small 2D and 3D data sets. Also its relatively easy to add new types of clusterings, and distanc metrics.

Typical invokations:
	#Get minimal help
		
		php ./knn_cluster.php --help
	
	#Cluster data file sampledata/ess/ess.txt with rcknn clustering visualize with knn, at a sweep of K values form 0 to k_max.
	#Note by def all output is to dirname of the input file.
		
		php ./knn_cluster.php -f=sampledata/ess/ess.txt -c=rcknn -v=knn --k_sweep=0
		
	#Do the same again cache the result and set k_max explicitly rather than default 
		
		php ./knn_cluster.php -f=sampledata/ess/ess.txt -c=rcknn -v=knn --k_sweep=0 --k_max=30 --cache
	
	#Use the previously cached graph, output stats, but not knn vision. Also output any stats output to stdout as well as file.
		
		php ./knn_cluster.php -f=sampledata/ess/ess.cache -c=rcknn -s --stdout --k_sweep=0
	
	#Use rules to get KNN clustering. The "rules" option to -c (clustering type) makes "rcknn" option or other values obselete.
	#Conditions Like RC are specified by -c=rules --co_rules=rcknn. Do points visualization output.
		
		php ./knn_cluster.php -f=sampledata/ess/ess.cache -c=rules -v=points --k_sweep=0
	
	#Density related like clustering with RCKNN too. Set alpha value to density related measure.
		
		php ./knn_cluster.php -f=sampledata/ess/ess.cache -c=rules --co_rules=rcknn,dense --co_dense_alpha=1.2 -v=points --k_sweep=0
	
	
	
