# DataApps - entry processing
Every DataApp implements the App-interface and make use of the DataExplorer-class. The DataExplorer-class provides a graphical user interface for entries of the database table dataexplorer.

## DataExplorer: `Canvas element` data structure
The entries of the dataexplorer table have following structure (array-representation):
- **Source:** dataexplorer is the database table name
- **Group:** is set to `Canvas elements` for graphic elements of the user interface
- **Folder:** is the name of the PHP-class including the namespace of the DataApp the Canvas element belongs to
- **Name:** is the initial HTML tag content of the Canvas element
- **EntryId:** is the unique identifier
- **Content:** is an array that contains all data with relation to the Canvas element, e.g. the style information, the database selector with relatiuon to the Canvas element
- **Params:** is an array that contains the Canvas element meta information
- **Expires:** is the date when the entry will be deleted
- **Read:** is the access byte defining the read permission with regard to the entry
- **Write:** is the access byte defining the write permission with regard to the entry
- **Owner:** is the unique identifier of the party who created the entry

## DataExplorer: `Canvas element` data structure array-key Content
<img src="../../assets/img/canvas_elment_content.png" alt="Canvas element content example" style=""/>

