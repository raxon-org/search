{{Package.Raxon.Search:Main:import.page(flags(), options())}}
/**
1. app raxon/search generate raxon
1. app raxon/search import wiki -version=0.0.28 -target=/mnt/Vps3/Mount/Data/Wiki
2. app raxon/search import page -version=0.0.28 -list=/mnt/Vps3/Mount/Data/Wiki/1.json -model_dir=/mnt/Disk2/Media/Search/0.0.28/
2. app raxon/search import page -version=0.0.30 -list=/mnt/Vps3/Mount/Data/Doxygen/1.json -model_dir=/mnt/Disk2/Media/Search/0.0.30/
3. app raxon/search embedding word -version=0.0.30 -model_dir=/mnt/Disk2/Media/Search/0.0.30/
4. app raxon/search embedding sentence piece -version=0.0.30 -model_dir=/mnt/Disk2/Media/Search/0.0.30/ -amount=128
5. app raxon/search word extract -version=0.0.30 -model_dir=/mnt/Disk2/Media/Search/0.0.30/
6. app raxon/search sentence extract -version=0.0.30 -model_dir=/mnt/Disk2/Media/Search/0.0.30/
7. app raxon/search paragraph extract -version=0.0.30 -model_dir=/mnt/Disk2/Media/Search/0.0.30/
8. app raxon/search document extract -version=0.0.30 -model_dir=/mnt/Disk2/Media/Search/0.0.30/
*/