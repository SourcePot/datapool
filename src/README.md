# Folder structure and description
- **fonts:** this folder contains TrueType fonts used by the symbol login form
- **fpdf:** this folder contains the FPDF PHP class for pdf-file generation
- **php:** this folder contains the PHP classes which form the object collection of Datapool. Class Root of this folder (`SourcePot\Datapool\Root`) builds the object collection, it creates and initializes all classes
- **setup:** this folder contains json- and csv-configuration files
- **www:** this folder is the network document root and contains files that can be accessed from the network. When any of the php-scripts is called, it will create the PHP-class `SourcePot\Datapool\Root`
- filespace: contains a sub-directory for each database table and within these sub-directories files linked to the database entries of these tables. The file name is comprised of the `EntryId` and the suffix `.file`
- tmp_private: contains user specific temporary files
- debugging: contains debugging information, e.g. exception trace files in json-format