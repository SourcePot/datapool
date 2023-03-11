# Datapool
 Light Weight Content Management System
## Installing the framework
1. Run composer cthe ommand ``composer create-project sourcepot/datapool <TARGET DIRECTORY>`` on your server. The **../www/**-subdirectory within the <TARGET DIRECTORY> (which will be created when running the composer command) is the document root and should be accessible via the network, i.e. from a client web browser.
2. Create the database and a user, e.g. a user and database named "webpage" which will be used by the framework.
3. Set database collation to **utf8_unicode_ci**.
4. Call the webpage through a web browser. This will create an error message since the database access needs to be set up. Check the error log which can be found in the **../debugging/**-subdirectory, each error generates a .json file containing the details. Calling the webpage creates the file **../setup/Database/connect.json** which contains the database access credentials. Use a text editor to update the credentials in this file to enable the database access.
5. If the database access credentials are correct, all database table will be created when the webpage is called in the web browser again. This call will additionally create a first admin user account. The user credentials of this admin account can be found in **../setup/User/initAdminAccount.json**.
6. Open the webpage, select "Login" and login using the user credentials copy&paste from initAdminAccount.json.
![Using credentials from initAdminAccount.json](https://github.com/SourcePot/datapool/blob/main/docs/initAdminAccount.jpg?raw=true)
## Initial adjustments
The file **../setup/HTMLbuilder/init.json** contains some important web page settings, e.g. the webmaster email address (the key is ***emailWebmaster***). You should update this email address as soon as this file was created. The webmaster email address is used to create the first admin account. You can trigger the creation of a new admin account by deleting the admin entry from the user database table. A new admin account will be created if there is no admin account left in the user table.

The timezone setting, selected by key ***pageTimeZone*** of **../setup/HTMLbuilder/init.json**, is used by the script to initialize timezones in the context of user selections. It should be adjsuted to the timezone which is typical for the majority of the web page users. The web page title, selected by key ***pageTitle*** should be updated to your web page title.
