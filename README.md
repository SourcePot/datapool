# Datapool

Datapool is a versatile modular web application.

## Advantages of a web application in contrast to a desktop application
- Runs on a wide range of devices. The web browser is the runtime environment.
- The user interface is always up-to-date thanks to the use of HTML as the living standard and modern web browsers. Reduced use of JavaScript. 
- Simple interaction with other web services.
- Established infrastructures available for data backup.

## Basic features
- Media-/File-Explorer: structured data and file storage based on selectors Group, Folder, Name, EntryId
- DataExplorer: process driven dataflow and dataprocessing
- MediaPlayer: creating and playing video playlists, see https://github.com/SourcePot/mediaplayer
- Calendar: calendar sheet holding single and recurring events which can be connected to the DataExplorer
- Forum and Chat: communication platform for the web application users
- Flexible RSS feed reader
- Remote client interface: connecting remote sensor platforms, see https://github.com/SourcePot/PIclient
- Role-based access control to apps and data: 16 user roles, e.g. web admin, config admin, member, registered, public etc.
- Interfaces: for adding your apps, receivers, transmitters (e.g. https://github.com/SourcePot/sms), processors etc.
- Comprehensive logger

## Use cases
The two typical use cases are process-driven data processing and a content management system. The functionality of complex spreadsheets can alternatively be easily implemented as a process data flow. Easily accessible intermediate results help to maintain an overview and to find and fix problems.

### Sample start page:

![Home app](/assets/img/datapool.png "Home app")

# Get Started
You need to host the web application through a web server or local host (e.g. your personal computer). The server can be set up on a wide range of systems such as Linux, UNIX, MS Windows. 

## Requirements
This software is designed to run on a web server, i.e. the user interface is the web browser.

The web application requires:
1. a **server** software (e.g. Apache, nginx installed on a web server or local computer), 
2. **PHP 8+** and 
3. a **database** (and a database user, which will be used by the web application). 

To run Datapool on your computer as local host, you could install XAMPP Apache + MariaDB + PHP + Perl (see https://www.apachefriends.org/). The following example uses XAMPP as the software environment.This works well as local host on a MS Windows computer or Linux system (e.g. Debian).

Personally, I use Composer to install the web application with all its dependencies and the folder structure. If you like to use Composer you will need to install the software on your computer or server, see https://getcomposer.org/download/ for details.

I tend to install the web application on my personal computer first. This serves as my local backup and can be used for final tests. In a later step, I copy the whole Datapool directory, with the exception of the `../src/setup/` and `../src/filespace/` directories, with all it's files to the web server using FTP (FileZilla).

## Installing the web application
1. Choose your target directory on your web server or your computer and run Composer `composer create-project sourcepot/datapool {add your target directory here}`. This will create, among other things, the `../src/www/`-subdirectory, which is the www-root and should be accessible through the network, i.e. by a client web browser. If you use XAMPP, locate the XAMPP directory, e.g. `.../xampp/htdocs/`. Your web applications' directories and files should be located there after successfully running Composer with this target directory.
2. Create a database and a corresponding database user. Set the database collation to **utf8mb4_unicode_ci**.

>[!NOTE]
>It may be that PHP extensions are missing on your system, for example. Composer will exit the script with an exception and tell you the name of the missing extension.

### Example code: adding missing extensions on the local host

This is based on a Debian 12 (bookworm) 64-bit system with a XAMPP installation.

```
sudo apt-get install php-xml
sudo apt-get install php-gd
sudo apt-get install php-zip
sudo apt-get install php-bcmath
sudo apt-get install php-curl   
```

## Connecting the web application with the database
1. Call the webpage through a web browser. This will create an error message since the database access needs to be set up. You can check the error logs which are located in the `../src/debugging/`-subdirectory. Each error generates a JSON-file containing the error details.
2. Calling the webpage creates the file `../src/setup/Database/connect.json` which contains the database user credentials. Use a text editor to update or match the credentials with the database user credentials.
3. If the database as well as the database user are set up correctly, and the user credentials used by Datapool match the database user, the web application should (when reloaded) show an empty web page with a menu bar at the top and the logger at the bottom of the web browser.

>[!NOTE]
>If errors occur when you first access the website, this may be due to insufficient access rights. Access rights may need to be adjusted for folders and files newly created during installation and initial access.

## Create your Admin account for your web application
1. Refresh the webpage. This will create an initial admin user account. 
2. Use the **Login** page to register your own new account.
3. Use the initial admin account to login and change your newly registered own account. Change your own account priviledges from `registered` to `admin` access level (**Admin &rarr; Account**). The initial admin credentials can be found in the `../src/setup/User/initAdminAccount.json` directory. 
4. Delete the initial admin user account.
5. Update the webmaster email address **Admin &rarr; Admin &rarr; Page settings &rarr; EmailWebmaster**. Allways use the &check; button to save changes.

>[!IMPORTANT]
>Remember to ensure security, you need to adjust all file permissions to the minimum necessary access level. Especially if you run the application on a publicly accessible server. Make sure that **only** the `../src/www/`-subdirectory is visible to the public and public write-access must be prohibited. 

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

You need to append the PEAR folder path relative to the selected PHP version to **include_path** e.g. 
```
.:/opt/plesk/php/8.4/share/pear
``` 
and **open_basedir** e.g. 
```
{WEBSPACEROOT}{/}{:}{TMP}{/}:/opt/plesk/php/8.4/share/pear
``` 

# Under the Hood
Datapool is based on an **object collection** `oc`, i.e. a collection of objects instantiated from the PHP-classes of the `../php/` folder. The object collection is created by the constructor of class `../php/Root.php` each time the web application is called by a client e.g. web browser.
`../php/Root.php` provides the collection to all instantiated classes which implement the method `loadOc(array $oc)`. Typically the classes have a private property `oc` which is set/updated by the loadOc method of the class.

The configuration file `../setup/objectList.csv` determines the order of creation of the objects. With the private property `registerVendorClasses` of class `../php/Root.php` vendor classes can be added to the object collection. Otherwise, an instance of a vendor class can (as usual) be created within the source code when required.

## Web page creation
The following flowchart shows the sequence of object instantiations, method calls and content creation. 

![Browser call flow](/assets/img/Browser_call_flow.png "Browser call flow")

Any class which implements the `SourcePot\Datapool\Interfaces\App` interface must provide a run method. The run method defines the app specific menu item, the app visibility and the method adds the app specific web page content. The following figure shows the run method of the calendar app `SourcePot\Datapool\GenericApps\Calendar→run()`. 

![Run method if an app where content is added](/assets/img/run_method.png "Run method if an app where content is added")

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

