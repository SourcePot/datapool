# Datapool
 Light Weight Content Management System
## Installation of the framework
1. Run composer command ```composer create-project sourcepot/datapool {TARGET DIRECTORY}```, the "www" subdirectory (which will be created when running the composer command) of the target directory needs to be accessable by a web browser.
2. Create a database for the framework including a user, e.g. named "webpage".
3. Set database Colation to "utf8_unicode_ci".
4. Call the webpage on a web browser, this will create an error message since the database access needs to be set. The error log can be found in the "debugging" subdirectory. Calling the webpage creates the file ../setup/Database/connect.json which contains the database access credentials. Use a text editor to update these credentials.
5. If the database access credentials are correct, all database table will be created when the webpage is called in the webbrowser again. This call will initiate a new admin user account. The user credentials of this admin account can be found in ../setup/User/initAdminAccount.json. You can use these credentials to log into the admin account on the webpage.
6. Open the webpage, select "Login" and login using the user credentials copy&paste from initAdminAccount.json. Update the initial admin account, add your name etc. The framework should now be ready to be used.
![Using credentials from initAdminAccount.json](https://github.com/SourcePot/datapool/blob/main/docs/initAdminAccount.jpg?raw=true)
