# Datapool
Many organizations run large complex software packages and flexibility is not necessarily their strong point. Simple customer specific adjustments or process changes are very time-consuming and expensive. Low-code development platforms or bots promise to bring back flexibility, but can themselves be a closed ecosystem. Datapool is a lightweight open source web application that gives control back to the user or smaller organizational units within an organization. Datapool can be configured to carry out periodic data crunching with processes defined at team or department level. Datapool can also bridge temporary gaps, for testing processes as well as in a production environment.

<img src="./assets/img/ComparisonWithSAP.png" alt="Example application"/>

Datapool was originally developed to process invoices (pdf documents) within a patent department, in which in a frist step all invoice data is extracted, analyzed and compared with patent files. in a next step invoice data is processed in conjunction with UNYCOM and SAP. In this early production environment, Datapool processed approx. 1000 invoices per month. The data was compared with approx. 200k cost data records and 100k patent files. Processing took place 1-2 times per month.  

## Sample application
Moving data between different packages can be challenging.
This framework aims to fill the gap between the big software packages such as SAP and e.g. UNYCOM in the setup of an IPR or patent department. The software is designed to relieve people from mindless repetitive tasks, allowing them to focus on the valuable tasks.

<img src="./assets/img/ExampleApplication.png" alt="Example application" style="width:90%;"/>

The figure shows a typical application example of a company software ecosystem including SAP and UNYCOM. UNYCOM is used by patent departments of larger enterprises. UNYCOM manages patent files including cost records. There can be a substantial amount of incoming invoices. The payment is usually dealt with by SAP but the invoice data (content) as well as the documentation of the payment made through SAP needs to end up in the correct UNYCOM patent case.

This example requires the following steps:
1. Parsing: content extraction from the invoice. SAP relevant data as well as patent case specific data.
2. Matching an SAP accounting record with the patent case.
3. Mapping: adjusting data formats and types to create a UNYCOM compatible dataset.

*The Datapool framework can achieve exactly this in a very transparent manner!*

## Features
- Very lightweight web-application with data-processing, calendar, user account and rights management, a multimedia app and forum
- Data sources can be media-files, pdf-documents, spreadsheet-files either uploaded manually or downloaded from an email inbox
- External devices can provide data or files through a client interface
- The result of the processing can be spreadsheet-files, zip-files, emails or SMS-messages
- Data processing can be controlled manually or by trigger derived from values or calendar events
- Processes can be easily designed and adopted via a graphical user interface
- Processes can easily be exported or imported to other systems running Datapool

<img src="./assets/img/Example_data_flow.png" alt="Graphical process designer" style=""/>

## Hosting the web-application

### Requirements
This software is designed to run on a web server, i.e. the user interface is the web browser. It requires **PHP 8+** and a **database**. Depending on the application requirements access to an email account might be required.

### Installation 
For the installation and creation of the first user account please refer to the video below.
1. Choose your target directory on your web server or your computer and run composer `composer create-project sourcepot/datapool {add your target directory here}`. This will create, among other things, the `../www/`-subdirectory, which is the www-root and should be accessible via the network, i.e. from a client web browser.
2. Create a database and a corresponding database user. Set the database collation to **utf8mb4_unicode_ci**.
3. Call the webpage through a web browser. This will create an error message since the database access needs to be set up. (Check the error log which can be found in the `../debugging/`-subdirectory.  Each error generates a JSON-file containing the error details.) 
4. Calling the webpage creates the file `../setup/Database/connect.json` which contains the database access credentials. Use a text editor to update or match the credentials with the database user credentials. 
5. Refresh the webpage. This will create an initial admin user account. The user credentials of this account can be found in `../setup/User/initAdminAccount.json`. Register your own account and use the initial admin user account to change your own account to admin access level. Delete the initial admin user account after you have set up your own admin account.
6. Make sure **only** the `../www/`-subdirectory is visible to the public.

Example installation using `Composer` on a notebook computer running MS Windows, XAMPP server and MariaDB:

https://github.com/SourcePot/datapool/assets/115737488/10464f44-4518-45e0-8654-0bc19e9b1bb0

### Initial adjustments
After you have set up your admin account you should login and update the webmaster email address **Admin &rarr; Admin &rarr; Page settings &rarr; EmailWebmaster**. Allways use the &check; button to save changes.

## Architecture
Datapool is based on an **object collection** or `oc`, i.e. a collection of objects instantiated from PHP-classes in the `../php/` folder. The object collection is created by the constructor of class `../php/Root.php` each time the web-application is called.
`../php/Root.php` provides the collection to all instantiated classes which implement the method `init(array $oc)`. Typically the classes have a private property `oc` which is set/updated by the init method of the class.

The configuration file `../setup/objectList.csv` determines the order of creation of the objects. With the private property `registerVendorClasses` of class `../php/Root.php` vendor classes can be added to the object collection. Otherwise, an instance of a vendor class can (as usual) be created within the source code when required.

### Web page creation
The following flowchart shows the sequence of object instantiations, method calls and content creation. 

<img src="./assets/img/Browser_call_flow.png" alt="Browser call flow"/>

Any class which implements the `SourcePot\Datapool\Interfaces\App` interface must provide a run method. The run method defines the app specific menu item, the app visibility and the method adds the app specific web page content. The following figure shows the run method of the calendar app `SourcePot\Datapool\GenericApps\Calendar→run()`. 

<img src="./assets/img/run_method.png" alt="Run method if an app where content is added" style="width:100%"/>

### Data category Apps, e.g. Invoices
Data apps use the DataExplorer class `SourcePot\Datapool\Foundation\DataExplorer`. The data explorer provides a blank canvas to create data crunching processes graphically. This is done by adding canvas elements and by configuring their properties. A canvas element is a view of a database table. The database table view applies a selector `Content → Selector` (see the figure below). Features can be added to the canvas element such as *File upload* (e.g. for invoice documents, email etc.), pdf-parser and/or a processor. There is a set of basic processors to e.g. *match*, *map* or *forward* entries. There are also a basic processors to create pdf-documents, to send emails or SMS.

<img src="./assets/img/CanvasElementProperties.png" alt="Canvas element properties"/>

The DataExplorer has two modes: **view** and **edit** The figure below shows how to togle between **view** and **edit** mode. In edit mode each canvas element can be dragged, selected or deleted. To change canvas element properties the canvas element needs to be selected by clicking on the diamond shaped red button of the respective canvas element.

<img src="./assets/img/DataExplorer.png" alt="Canvas element properties"/>

# Useful hints

## Dependencies: PEAR

PEAR may be required for processing office documents such as emails. If the upload of emails fails, the php script might have failed to include PEAR. The exception **"Failed opening required 'PEAR.php' (include_path='/var/www/vhosts/...** indicates a faulty dicetory setting for PEAR. Check if PEAR is installed and the location of the PEAR directory is set correctly on the server. If PEAR is installed, you can check the directory as follows:

```
pear config-get php_dir
> /usr/share/php
```

If you use PLESK for your server administration, you can add the correct path as follows in **Websites & Domains**, **PHP Settings for...**:

<img src="./assets/img/plesk_settings_pear.png" alt="Added the PEAR directory in PLESK"/>



