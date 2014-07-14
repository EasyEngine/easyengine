<img src="https://d3qt5vpr7p9rgn.cloudfront.net/wp-content/uploads/2013/08/easy-engine-logo-2-RS1.png" alt="EasyEngine Logo" align="right" />

[![Travis Build Status](https://travis-ci.org/rtCamp/easyengine.svg "Travis Build Status")] (https://travis-ci.org/rtCamp/easyengine)

EasyEngine (ee) is a linux shell-script collection, which makes it easy to manage your wordpress sites running on nginx web-server.

**EasyEngine currently supports:**

- Ubuntu 12.04 & 14.04
- Debian 6 & 7

## Quick Start

```bash
wget -qO ee rt.cx/ee && sudo bash ee     # install easyengine
ee stack install                         # install nginx, php, mysql, postfix 
ee site create example.com --wp          # create example.com and install wordpress on it
```

## Update EasyEngine

```bash
alias eeupdate="wget -qO eeup http://rt.cx/eeup && sudo bash eeup"

# Let's update EasyEngine (ee)
eeupdate
```

## More Site Creation Commands

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

## Cheatsheet - Site creation


|                    |  Single Site  | 	Multisite w/ Subdir  |	Multisite w/ Subdom  |
|--------------------|---------------|-----------------------|-----------------------|
| **NO Cache**       |  	  --wp     |	    --wpsubdir       |	     --wpsubdom      |
| **WP Super Cache** |	  --wpsc     |	  --wpsubdir --wpsc  |  	--wpsubdom --wpsc  |
| **W3 Total Cache** |    --w3tc     |	  --wpsubdir --w3tc  |  	--wpsubdom --w3tc  |
| **Nginx cache**    |    --wpfc     |    --wpsubdir --wpfc  |  	--wpsubdom --wpfc  |


## Useful Links
- [Documentation] (http://rtcamp.com/easyengine/docs/) 
- [FAQ] (http://rtcamp.com/easyengine/faq/)
- [Conventions used] (http://rtcamp.com/wordpress-nginx/tutorials/conventions/)

## Donations
- [Using PayPal] (https://rtcamp.com/donate/?project=easyengine)

## License

Same [GPL] (http://www.gnu.org/licenses/gpl-2.0.txt) that WordPress uses!
