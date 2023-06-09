# Motivation
In some way this software package is a result of desperation. 
Many big organizations run big software packages and flexibility is not necessarily their strong point. Simple customer specific adjustments are very expensive and might not survive the next software update. Even more challenging might be the task of moving data between the packages.
This framework aims to fill the gap between the big software packages such as SAP and e.g. UNYCOM in the setup of an IPR or patent department.  The software is designed to relieve people from mindless repetitive tasks, allowing them to focus on the more valuable tasks.

![Example application, import of invoices with including cost records](/assets/img/ExampleApplication.png "Invoice import")

The figure shows a typical application example in a company software setup including SAP and UNYCOM. UNYCOM is used by patent departments of larger enterprises. UNYCOM manages patent files including cost records. There can be a substantial amount of incoming invoices.  The payment is usually dealt with by SAP by the invoice data (content) as well as the documentation of the payment made through SAP needs to end up in the correct UNYCOM patent case. This requires the following:
1. Parsing: Content extraction from the invoice. SAP relevant data as well as patent case specific data.
2. Matching an SAP accounting record with the patent case.
3. Mapping: Adjusting data formats and types to create a UNYCOM compatible dataset.
The Datapool framework can just achieve this.

# Requirements
This software is designed to run on a server, i.e. the user interface is the web browser. It requires PHP and a database. Depending on the application requirements access to an email account might be required.

# Fist steps
## Installing the framework
1. Run composer ``composer create-project sourcepot/datapool {add your target directory here}`` on your server. This will create among other things the **../www/**-subdirectory, which is the document root and should be accessible via the network, i.e. from a client web browser.
2. Create the database and a database user, e.g. a user and database named "webpage".
3. Set the database collation to **utf8_unicode_ci**.
4. Call the webpage through a web browser. This will create an error message since the database access needs to be set up. Check the error log which can be found in the **../debugging/**-subdirectory.  Each error generates a JSON-file containing the error details. Calling the webpage also creates the file **../setup/Database/connect.json** which contains the database access credentials. Use a text editor to update or match the credentials with the database user credentials. 
5. Refresh the webpage. This will create an initial admin user account. The user credentials of this account can be found in **../setup/User/initAdminAccount.json**.  With the credentials from this file you should be able the login for the first time.

## Initial adjustments
The file **../setup/Backbone/init.json** contains some important web page settings, e.g. the webmaster email address (the key is ***emailWebmaster***). You should update this email address as soon as this file is created. The webmaster email address will be used to create the initial admin account. You can trigger the creation of a new admin account by deleting all admin entries from the user database table.