# Datapool

Datapool is a versatile modular web application. The basic functionalities are:
1. Media-/File-Explorer: structured data and file storage based on selectors Group, Folder, Name, EntryId
2. DataExplorer: process driven dataflow and dataprocessing
3. MediaPlayer: creating and playing video playlists, see https://github.com/SourcePot/mediaplayer
4. Calendar: calendar sheet holding single and recurring events which can be connected to the DataExplorer
5. Forum and Chat: communication platform for the web application users
6. RSS feed reader: flexible RSS feed rreader
7. Remote client interface: connecting remote sensor platforms, see https://github.com/SourcePot/PIclient
8. User role based app and data access control: 16 user roles, e.g. web admin, config admin, member, registered, public etc.
9. Interfaces: for adding your apps, receivers, transmitters (e.g. https://github.com/SourcePot/sms), processors etc.
10. Comprehensive logger

### Sample start page:

![Home app](/assets/img/datapool.png "Home app")

# Get Started
Hosting the web application on a web server or a local computer

## Requirements
This software is designed to run on a web server, i.e. the user interface is the web browser.

The web application requires:
1. a **server** (e.g. Apache, nginx installed on a web server or local computer), 
2. **PHP 8+** and 
3. a **database** (and a database user, which will be used by the web application). 

To run Datapool on your computer as local host, you could install XAMPP Apache + MariaDB + PHP + Perl (see https://www.apachefriends.org/). The following example uses XAMPP as the software environment. 

Personally, I use Composer to install the web application with all its dependencies and the folder structure. If you like to use Composer you will need to install the software on your computer or server, see https://getcomposer.org/download/ for details. 

I tend to install the web application on my personal computer first (this serves as my local backup). In a later step, I copy the whole Datapool directory with all it's files to the web server using FTP (FileZilla).

## Installing the web application
1. Choose your target directory on your web server or your computer and run Composer `composer create-project sourcepot/datapool {add your target directory here}`. This will create, among other things, the `../src/www/`-subdirectory, which is the www-root and should be accessible through the network, i.e. by a client web browser. If you use XAMPP, locate the XAMPP directory, e.g. `.../xampp/htdocs/`. Your web applications' directories and files should be located there after successfully running Composer with this target directory.
2. Create a database and a corresponding database user. Set the database collation to **utf8mb4_unicode_ci**.

## Connecting the web application with the database 
1. Call the webpage through a web browser. This will create an error message since the database access needs to be set up. You can check the error logs which are located in the `../src/debugging/`-subdirectory. Each error generates a JSON-file containing the error details.
2. Calling the webpage creates the file `../src/setup/Database/connect.json` which contains the database user credentials. Use a text editor to update or match the credentials with the database user credentials.
3. If the database as well as the database user are set up correctly, and the user credentials used by Datapool match the database user, the web application should (when reloaded) show an empty web page with a menu bar at the top and the logger at the bottom of the web browser. 

## Create your Admin account for your web application
1. Refresh the webpage. This will create an initial admin user account. 
2. Use the **Login** page to register your own new account.
3. Use the initial admin account to login and change your newly registered own account. Change your own account priviledges from `registered` to `admin` access level (**Admin &rarr; Account**). The initial admin credentials can be found in the `../src/setup/User/initAdminAccount.json` directory. 
4. Delete the initial admin user account.
5. Update the webmaster email address **Admin &rarr; Admin &rarr; Page settings &rarr; EmailWebmaster**. Allways use the &check; button to save changes.

>[!IMPORTANT]
>Remember to ensure security, you need to adjust all file permissions to the minimum necessary access level. Especially if you run the application on a publicly accessible server. Make sure that **only** the `../src/www/`-subdirectory is visible to the public and public write-access must be prohibited. 

### Example installation using `Composer` and setting up your first user account on a notebook computer running MS Windows, XAMPP server and MariaDB:
https://github.com/SourcePot/datapool/assets/115737488/10464f44-4518-45e0-8654-0bc19e9b1bb0

## Initial adjustments
After you have set up your admin account you should login and update the webmaster email address **Admin &rarr; Admin &rarr; Page settings &rarr; EmailWebmaster**. Allways use the &check; button to save changes.

## Dependencies: PEAR

PEAR may be required for processing office documents such as emails. If the upload of emails fails, the php script might have failed to include PEAR. The exception **"Failed opening required 'PEAR.php' (include_path='/var/www/vhosts/...** indicates a faulty dicetory setting for PEAR. Check if PEAR is installed and the location of the PEAR directory is set correctly on the server. If PEAR is installed, you can check the directory as follows:

```
pear config-get php_dir
> /usr/share/php
```

If you use PLESK for your server administration, you can add the correct path as follows in **Websites & Domains**, **PHP Settings for...**:

![Added the PEAR directory in PLESK](/assets/img/plesk_settings_pear.png "Added the PEAR directory in PLESK")

# Under the Hood
Datapool is based on an **object collection** `oc`, i.e. a collection of objects instantiated from the PHP-classes of the `../php/` folder. The object collection is created by the constructor of class `../php/Root.php` each time the web application is called by a client e.g. web browser.
`../php/Root.php` provides the collection to all instantiated classes which implement the method `loadOc(array $oc)`. Typically the classes have a private property `oc` which is set/updated by the loadOc method of the class.

The configuration file `../setup/objectList.csv` determines the order of creation of the objects. With the private property `registerVendorClasses` of class `../php/Root.php` vendor classes can be added to the object collection. Otherwise, an instance of a vendor class can (as usual) be created within the source code when required.

## Web page creation
The following flowchart shows the sequence of object instantiations, method calls and content creation. 

![Browser call flow](/assets/img/Browser_call_flow.png "Browser call flow")

Any class which implements the `SourcePot\Datapool\Interfaces\App` interface must provide a run method. The run method defines the app specific menu item, the app visibility and the method adds the app specific web page content. The following figure shows the run method of the calendar app `SourcePot\Datapool\GenericApps\Calendar→run()`. 

![Run method if an app where content is added](/assets/img/run_method.png "Run method if an app where content is added")

# Sample application
Many organizations run large complex software packages and flexibility is not necessarily their strong point. Simple customer specific adjustments or process changes are very time-consuming and expensive. Low-code development platforms or bots promise to bring back flexibility, but can themselves be a closed ecosystem. Datapool is a lightweight open source web application that gives control back to the user or smaller organizational units within an organization. Datapool can be configured to carry out periodic data crunching with processes defined at team or department level. Datapool can also bridge temporary gaps, for testing processes as well as in a production environment.

![Example application](/assets/img/ComparisonWithSAP.png "Example application")

Datapool was originally developed to process pdf documents. This can be invoices from service providers within a patent department. For such an application, in a frist step all invoice data is extracted, analyzed and compared with patent files. in a next step invoice data is processed in conjunction with UNYCOM and SAP. In this early production environment, Datapool processed approx. 1000 invoices per month. The data was compared with approx. 200k cost data records and 100k patent files. Processing took place 1-2 times per month.  

Moving data between different packages can be challenging.
This framework aims to fill the gap between the big software packages such as SAP and e.g. UNYCOM in the setup of an IPR or patent department. The software is designed to relieve people from mindless repetitive tasks, allowing them to focus on the valuable tasks.

![Comparison SPA UNYCOM Datapool](/assets/img/ExampleApplication.png "Comparison SPA UNYCOM Datapool")

The figure shows a typical application example of a company software ecosystem including SAP and UNYCOM. UNYCOM is used by patent departments of larger enterprises. UNYCOM manages patent files including cost records. There can be a substantial amount of incoming invoices. The payment is usually dealt with by SAP but the invoice data (content) as well as the documentation of the payment made through SAP needs to end up in the correct UNYCOM patent case.

This example requires the following steps:
1. Parsing: content extraction from the invoice. SAP relevant data as well as patent case specific data.
2. Matching an SAP accounting record with the patent case.
3. Mapping: adjusting data formats and types to create a UNYCOM compatible dataset.

*The Datapool framework can achieve precisely this in a very transparent way!*

## DataExplorer features
- Data sources can be media-files, pdf-documents, spreadsheet-files either uploaded manually or downloaded from an email inbox
- External devices can provide data or files through a client interface
- The result of the processing can be spreadsheet-files, zip-files, emails or SMS-messages
- Data processing can be controlled manually or by trigger derived from values or calendar events
- Processes can be easily designed and adopted via a graphical user interface
- Processes can easily be exported or imported to other systems running Datapool

![Graphical process designer](/assets/img/Example_data_flow.png "Graphical process designer")

## Data category Apps, e.g. Invoices
Data apps use the DataExplorer class `SourcePot\Datapool\Foundation\DataExplorer`. The data explorer provides a blank canvas to create data crunching processes graphically. This is done by adding canvas elements and by configuring their properties. A canvas element is a view of a database table. The database table view applies a selector `Content → Selector` (see the figure below). Features can be added to the canvas element such as *File upload* (e.g. for invoice documents, email etc.), pdf-parser and/or a processor. There is a set of basic processors to e.g. *match*, *map* or *forward* entries. There are also a basic processors to create pdf-documents, to send emails or SMS.

![Canvas element properties](/assets/img/CanvasElementProperties.png "Canvas element properties")

The DataExplorer has two modes: **view** and **edit** The figure below shows how to togle between **view** and **edit** mode. In edit mode each canvas element can be dragged, selected or deleted. To change canvas element properties the canvas element needs to be selected by clicking on the diamond shaped red button of the respective canvas element.

![Canvas element properties](/assets/img/DataExplorer.png "Canvas element properties")

