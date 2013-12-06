![easyengine](http://rtcamp.com/wp-content/uploads/2013/08/easyengine-logo-858x232px.png "easyengine")

# easyengine

easyengine (ee) is a linux shell-script collection, which makes it easy to manage your wordpress-nginx sites on an Debian Wheezy server.
This one is a fork of ubuntu oriented one you can found at https://github.com/rtCamp/easyengine or http://rtcamp.com/easyengine/

## Quick Start

```bash
git clone https://github.com/Mermouy/easyengine.git #Clone this repo
cd easyengine                                       # Go that new folder
chmod +x install.sh                                 # Make the install script executable
nano easyengine/conf/ee.conf                        # Edit configuration to follow your needs *optional
./install.sh                                        # Install easyengine
ee system install                                   # Install nginx, php, mysql, postfix
ee system install allmdb                            # Install nginx, php, mariaDB (keeping exim4)
ee site create wp basic example.com                 # install wordpress on example.com
```

## Need more info?

You can follow instructions on original authors's website. I've just modify the script to install on Debian Wheezy and added option to install MariaDB instead of MySql. 

Check out the [wiki] (http://rtcamp.com/easyengine/docs/) and [faq] (http://rtcamp.com/easyengine/faq/) page

