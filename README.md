IMPORTANT
============================================

#### We are looking for [Python Developers] (https://rtcamp.com/careers/python-developer/) to join our team. We offer work from home, so you can join EasyEngine team anywhere! _[Why Python?] (https://rtcamp.com/blog/easyengine-3-roadmap/#whypython)_

---

[![Stories in Ready](https://badge.waffle.io/rtcamp/easyengine.png?label=ready&title=Ready)](https://waffle.io/rtcamp/easyengine)
[![Stories in Progress](https://badge.waffle.io/rtcamp/easyengine.png?label=in%20progress&title=In%20Progress)](https://waffle.io/rtcamp/easyengine)

<img src="https://d3qt5vpr7p9rgn.cloudfront.net/wp-content/uploads/2013/08/easy-engine-logo-2-RS1.png" alt="EasyEngine Logo" align="right" />

[![Travis Build Status](https://travis-ci.org/rtCamp/easyengine.svg "Travis Build Status")] (https://travis-ci.org/rtCamp/easyengine)

EasyEngine (ee) is a python tool, which makes it easy to manage your wordpress sites running on nginx web-server.

**EasyEngine currently supports:**

- Ubuntu 12.04 & 14.04
- Debian 7


## Quick Start

```bash
wget -q http://rt.cx/ee && sudo bash ee     # install easyengine 3.0.0-beta
sudo ee site create example.com --wp     # Install required packages & setup WordPress on example.com
```

## Update EasyEngine


Update procedure for EasyEngine to latest version

#### For current installed version prior to 3.0.6
```bash
wget -qO ee rt.cx/ee && sudo bash ee
```

#### Current version is 3.0.6
```
ee update
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
ee site create example.com --wpsubdomin            # install wpmu-subdomain without any page caching
ee site create example.com --wpsubdomain --w3tc     # install wpmu-subdomain with w3-total-cache plugin
ee site create example.com --wpsubdomain --wpsc     # install wpmu-subdomain with wp-super-cache plugin
ee site create example.com --wpsubdomain --wpfc     # install wpmu-subdomain + nginx fastcgi_cache
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
| **NO Cache**       |  	  --wp     |	    --wpsubdir       |	     --wpsubdomain      |
| **WP Super Cache** |	  --wpsc     |	  --wpsubdir --wpsc  |  	--wpsubdomain --wpsc  |
| **W3 Total Cache** |    --w3tc     |	  --wpsubdir --w3tc  |  	--wpsubdomain --w3tc  |
| **Nginx cache**    |    --wpfc     |    --wpsubdir --wpfc  |  	--wpsubdomain --wpfc  |


## Useful Links
- [Documentation] (http://docs.rtcamp.com/easyengine/docs/)
- [FAQ] (http://docs.rtcamp.com/easyengine/faq/)
- [Conventions used] (http://rtcamp.com/wordpress-nginx/tutorials/conventions/)

## Donations

[![Donate](https://cloud.githubusercontent.com/assets/4115/5297691/c7b50292-7bd7-11e4-987b-2dc21069e756.png)]  (https://rtcamp.com/donate/?project=easyengine)

## License
[MIT] (http://opensource.org/licenses/MIT)
