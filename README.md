phabricator-cas
===============

This is an extension for [Phabricator](http://phabricator.org/) which performs [jasig CAS](http://www.jasig.org/cas) authentication.

Installation
------------

Make sure you have installed [phpCAS](https://wiki.jasig.org/display/CASC/phpCAS) already. Recommend to use follow method to install:

    pear install  http://downloads.jasig.org/cas-clients/php/current.tgz

To install this library, simply clone this repository alongside your phabricator installation:

    cd /path/to/install
    git clone https://github.com/iodragon/phabricator-cas.git

Then, simply add the path to this library to your phabricator configuration:

    cd /path/to/install/phabricator
    ./bin/config set load-libraries '["phabricator-cas/src/"]'
    
When you next log into Phabricator as an Administrator, go to **Auth > Add Authentication Provider**.  
In the list, you should now see an entry called **CAS login**.  Enabling and config this provider should add a
new button to your login screen.
