# DataApps
Every DataApp implements the App-interface and make use of the DataExplorer-class. The DataExplorer-class provides a graphical user interface for entries of the database table dataexplorer.

# Database table entries
E.g. the DataApp Invoices class implements the App-interface and provides the DataExplorer. Canvas elements generated by Invoices are stored in the `dataexplorer` table of the database as described above.
The Invoices class stores entries in the `invoices` table of the database. These entries represent the data processed by using the Invoices class. The entries contain structured information mainly in the `Content`-column or `Content`-array-key respectivly.
Additionally a maximum of one file can be linked to an entry. This file can be any kind of file, e.g. a zip-, mp4-, csv-file etc. pdf-File can be parsed and the text copied to `Content`.

# DataExplorer: `Canvas element` data structure
The entries of the `dataexplorer` table have following structure (array-representation):
- **Source:** dataexplorer is the database table name
- **Group:** is set to `Canvas elements` for graphic elements of the user interface
- **Folder:** is the name of the PHP-class including the namespace of the DataApp the Canvas element belongs to
- **Name:** is the initial HTML tag content of the Canvas element
- **EntryId:** is the unique identifier
- **Content:** is an array that contains all data with relation to the Canvas element, e.g. the style information, the database selector with relation to the Canvas element and Widgets such as the linked processor
- **Params:** is an array that contains the Canvas element meta information
- **Expires:** is the date when the entry will be deleted
- **Read:** is the access byte defining the read permission with regard to the entry
- **Write:** is the access byte defining the write permission with regard to the entry
- **Owner:** is the unique identifier of the party who created the entry

## DataExplorer: `Canvas element` data structure array-key Content
<img src="../../../assets/img/canvas_element_content.png" alt="Canvas element content example" style=""/>

# Processor classes
Processor classes implement the interface `\SourcePot\Datapool\Interfaces\Processor`. Each `Canvas element` can be linked to one processor.
The processor runs on the data selected by `Canvas Element[Content][Selector]`. A processor linked to a `Canvas element` is linked to one entry, which stores parameters and multiple entries storing processing rules.
Entries storing parameters and rules are stored in the database table named the same as the processor class, e.g. mapentries. As an example following screenshot of the mapentries table:

<img src="../../../assets/img/mapentries_table_example.png" alt="Part of the database table mapentries" style=""/>

## Processor classes - entry data structure
The screenshot above shows a part of the database table of a processor class. The following describes the data structure of an entry of such a table:
- **Source:** is the name of the processor class lower case and without the namespace
- **Group:** is set to `..Params` or `...Rules` depending if the entry stores parameters or a processing rule
- **Folder:** contains the class with namespace of the data app the corresponding `Canvas element` the parameter or rule belongs to
- **Name:** contains the EntryId of the `Canvas element` the parameter or rule belongs to
- **EntryId:** is the unique identifier
- **Content:** is an array that contains the parameter or rule
- **Params:** is an array that contains meta information such as when the entry was created or changed
- **Expires:** is the date when the entry will be deleted
- **Read:** is the access byte defining the read permission with regard to the entry
- **Write:** is the access byte defining the write permission with regard to the entry
- **Owner:** is the unique identifier of the party who created the entry