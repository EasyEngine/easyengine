![easyengine](http://rtcamp.com/wp-content/uploads/2013/08/easyengine-logo-858x232px.png "easyengine")

## Introduction

easyengine (ee) is a linux shell-script collection, which makes it easy to manage your wordpress-nginx sites on a debian/ubuntu server.

## Quick Start

```bash
curl -sL rt.cx/ee | sudo bash                    # install easyengine
ee system install                                # install nginx, php, mysql, postfix 
ee site create example.com --wp                  # create example.com and install wordpress on it
```

## Upgrading from easyengine 1.0 to 1.1 and above

```bash
/bin/bash <(curl -sL https://raw.github.com/rtCamp/easyengine/stable/usr/local/sbin/eeupdate)
```

## Site Creation Commands

### Standard WordPress Sites

```bash
ee site create example.com --wp                  # install wordpress without any page caching
ee site create example.com --w3tc                # install wordpress with w3-total-cache plugin 
ee site create example.com --wpsc                # install wordpress with wp-super-cache plugin 
ee site create example.com --wpfc                # install wordpress + nginx fastcgi_cache
```

### WordPress Multsite with subdirectory 

```bash
ee site create example.com --wpsubdir            # install wpmu-subdirectory without any page caching
ee site create example.com --wpsubdir --w3tc     # install wpmu-subdirectory with w3-total-cache plugin 
ee site create example.com --wpsubdir --wpsc     # install wpmu-subdirectory with wp-super-cache plugin 
ee site create example.com --wpsubdir --wpfc     # install wpmu-subdirectory + nginx fastcgi_cache
```

### WordPress Multsite with subdomain 

```bash
ee site create example.com --wpsubdom            # install wpmu-subdomain without any page caching
ee site create example.com --wpsubdom --w3tc     # install wpmu-subdomain with w3-total-cache plugin 
ee site create example.com --wpsubdom --wpsc     # install wpmu-subdomain with wp-super-cache plugin 
ee site create example.com --wpsubdom --wpfc     # install wpmu-subdomain + nginx fastcgi_cache
```

### Non-WordPress Sites
```bash
ee site create example.com --html     # create example.com for static/html sites
ee site create example.com --php      # create example.com with php support
ee site create example.com --mysql    # create example.com with php & mysql support
```

## Useful Links
- [Documentation] (http://rtcamp.com/easyengine/docs/) 
- [FAQ] (http://rtcamp.com/easyengine/faq/)
- [Conventions used] (http://rtcamp.com/wordpress-nginx/tutorials/conventions/)

## Donations
- [Using PayPal] (https://rtcamp.com/donate/?project=easyengine)

## License

Same [GPL] (http://www.gnu.org/licenses/gpl-2.0.txt) that WordPress uses!
