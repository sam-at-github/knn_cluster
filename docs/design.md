# Overview
The main components of the system are:

  * *knn_cluster.php*: Reads user options in to internal format and instantiates whats needed.
  * *KNNGraph*: Just a KNNG built at some K value. A clustering is built off of it and it should contain a graph. The KNNG is accessible independently. A KNNGraph is represented by a collection of interrelated KNNVectors. A KNNVector is just a vector and its list of KNNs. Clusterings are built directly as partitions of KNNVectors.
  * *Vectors*: One of few types, implementing an interface (most importantly Vector->distance(Vector $x)).
  * *KNNClusterings*: A partition of KNNVectors.
  * *Visualization and Stats*: Subsystems that take a clustering and output plots or stats.

# Design Notes

## File names
File output formats and file names are important, and actually complex to get right. There are arbitrarily many types of KNNClusterings. For each type statistical and graphical data will be written out to file. It can get confusing with so many types. There's also arbitrarily many types of vector, types of vision, and types of stats. Providing a consistent way to label this is very helpful. End of the day, how files/dirs organized is a design decision, theres no perfect answer. The file hierarchy chosen is:

    <output_dir>/<name>"_"<clustering_type>"_"("vision"|"stats")/<name><full_options_except_k>/[<type_specific_start>]<full_options_with_k>[<type_specific_end>]
    <full_options_except_k>_->_<clustering_type_string_except_k>"_"<vector_type_string>"_"<vision_type_string>
    <full_options_with_k>_->_<clustering_type_string_including_k>"_"<vector_type_string>"_"<vision_type_string>

## Class and Responsibilities (brief)

*ClassName: ClusterKNN (knn_cluster.php)*

  * Responsibility: Interprets user inputs and organizes modules to perform action based on them
  * Rationale: Something has to do it. This is the core tool
  * PseudoCode:

        read options in from CLI and file
        merge together
        find options for modules
        check options are valid
        load graph
        if cache option then cache graph
        if cluster option then cluster graph
        if visualization option then plot clustering
        if summarization options then output stats of clustering
        do this iteratively of k_sweep option
        do this for a number

*ClassName: KNNGraph*

  * Responsibility: Provide access to collection of KNNVectors that together describe a KNN graph
  * Responsibility: Initially build the KNN graph
  * Rationale: Need a container. It was a design decision to implement the KNN graph as a collection of KNNVectors

*ClassName: AbstractKNNCluster*

  * Responsibility: Provide access to a collection of KNNVectors that have been clustered together by some clustering method.
  * Responsibility: Hold basic and general level descriptive information about the cluster.
  * Rationale: There are many way to implement a clustering method. Need uniform interface to a cluster.

*ClassName: AbstractKNNClustering*

  * Responsibility: Provide access to a collection of AbstractKNNCluster types that have been found according to some clustering method.
  * Responsibility: Hold basic and general level descriptive information about the clustering.
  * Rationale: There are many way to implement a clustering method. Need uniform interface to a clustering.

*ClassName: LoadableAbstractKNNClustering*

  * Responsibility: Instantiate a clustering, contain parameters describing how clustering was built.
  * Rationale: AbstractKNNClustering need not have this.

*ClassName: NTreeClustering*

  * Responsibility: An implementation of an AbstractKNNClustering that represents a clustering as an NTree.
  * Rationale: Maintains a hierarchical clustering which may provide useful info.
  * Rationale: Basis for NTreeClusteringVirtualHeight.
  * Rationale: Basis for implementing stability measures.

*ClassName: NTreeClusteringVirtualHeight*

  * Responsibility: Implement KSweepable i.e. the ability to dynamically change the K option to a clustering method.
  * Responsibility: Change the interface the AbstractKNNClustering part of the interface.
  * Anti Responsibility: Change the interface the NTreeClustering part of the interface.
  * Anti Responsibility: Change the interface the AbstractKNNCluster part of the interface.
  * Rationale: KSweep a major UC.

@todo: document the rest here.
