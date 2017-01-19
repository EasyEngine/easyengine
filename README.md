#[EasyEngine](https://easyengine.io/)

[![Travis Build Status](https://travis-ci.org/EasyEngine/easyengine.svg)](https://travis-ci.org/EasyEngine/easyengine) [![Join EasyEngine Slack Channel](http://slack.easyengine.io/badge.svg)](http://slack.easyengine.io/)

**Update:** [We are working on next major release (v4) which will be in PHP and based on WP-CLI](https://easyengine.io/blog/easyengine-v4-development-begins/).

<img src="https://d3qt5vpr7p9rgn.cloudfront.net/wp-content/uploads/2013/08/easy-engine-logo-2-RS1-240x184.png" alt="EasyEngine Logo" align="right" />

EasyEngine (ee) is a python tool, which makes it easy to manage your wordpress sites running on nginx web-server.

**EasyEngine currently supports:**

- Ubuntu 12.04 & 14.04 & 16.04
- Debian 7 & 8

**Port Requirements:**

| Name  | Port Number | Inbound | Outbound  |
|:-----:|:-----------:|:-------:|:---------:|
|SSH    |22           | ✓       |✓          |
|HTTP    |80           | ✓       |✓          |
|HTTPS/SSL    |443           | ✓       |✓          |
|EE Admin    |22222           | ✓       |          |
|GPG Key Server    |11371           |        |✓          |

## Quick Start

```bash
wget -qO ee rt.cx/ee && sudo bash ee     # Install easyengine 3
sudo ee site create example.com --wp     # Install required packages & setup WordPress on example.com
```

## Update EasyEngine


Update procedure for EasyEngine to latest version

#### For current installed version prior to 3.0.6
```bash
wget -qO ee rt.cx/ee && sudo bash ee

```
#### If current version is after than 3.0.6
```
ee update
```

## More Site Creation Commands

### Standard WordPress Sites

```bash
ee site create example.com --type=wp                  # install wordpress without any page caching
ee site create example.com --cache=w3tc                # install wordpress with w3-total-cache plugin
ee site create example.com --cache=wpsc                # install wordpress with wp-super-cache plugin
ee site create example.com --cache=wpfc                # install wordpress + nginx fastcgi_cache
ee site create example.com --cache=wpredis            # install wordpress + nginx redis_cache
ee site create example.com --type=wp --php=7.0      # install wordpress without any page caching(PHP Version: 7.0)
```

### WordPress Multsite with subdirectory

```bash
ee site create example.com --type=wpsubdir            # install wpmu-subdirectory without any page caching
ee site create example.com --type=wpsubdir --cache=w3tc     # install wpmu-subdirectory with w3-total-cache plugin
ee site create example.com --type=wpsubdir --cache=wpsc     # install wpmu-subdirectory with wp-super-cache plugin
ee site create example.com --type=wpsubdir --cache=wpfc     # install wpmu-subdirectory + nginx fastcgi_cache
ee site create example.com --type=wpsubdir --cache=wpredis  # install wpmu-subdirectory + nginx redis_cache
```

### WordPress Multsite with subdomain

```bash
ee site create example.com --type=wpsubdomain            # install wpmu-subdomain without any page caching
ee site create example.com --type=wpsubdomain --cache=w3tc     # install wpmu-subdomain with w3-total-cache plugin
ee site create example.com --type=wpsubdomain --cache=wpsc     # install wpmu-subdomain with wp-super-cache plugin
ee site create example.com --type=wpsubdomain --cache=wpfc     # install wpmu-subdomain + nginx fastcgi_cache
ee site create example.com --type=wpsubdomain --cache=wpredis  # install wpmu-subdomain + nginx redis_cache
ee site create example.com --type=wpsubdomain --php=7.0 # install wpmu-subdomain without any page caching (PHP Version: 7.0)
```

### Non-WordPress Sites
```bash
ee site create example.com --type=html     # create example.com for static/html sites
ee site create example.com --type=php      # create example.com with php support
ee site create example.com --type=mysql    # create example.com with php & mysql support
```


## Cheatsheet - Site creation


|                    |  Single Site  | 	Multisite w/ Subdir  |	Multisite w/ Subdom     |
|--------------------|---------------|-----------------------|--------------------------|
| **NO Cache**       |  --wp         |	--wpsubdir           |	--wpsubdomain           |
| **WP Super Cache** |	--wpsc       |	--wpsubdir --wpsc    |  --wpsubdomain --wpsc    |
| **W3 Total Cache** |  --w3tc       |	--wpsubdir --w3tc    |  --wpsubdomain --w3tc    |
| **Nginx cache**    |  --wpfc       |  --wpsubdir --wpfc    |  --wpsubdomain --wpfc    |
| **Redis cache**    |  --wpredis    |  --wpsubdir --wpredis |  --wpsubdomain --wpredis |

## Useful Links
- [Documentation] (http://docs.rtcamp.com/easyengine/)
- [FAQ] (http://docs.rtcamp.com/easyengine/faq.html)
- [Conventions used] (http://rtcamp.com/wordpress-nginx/tutorials/conventions/)
- [EasyEngine Premium Support] (https://rtcamp.com/products/easyengine-premium-support/)

## Donations

[![PayPal-Donate](https://cloud.githubusercontent.com/assets/4115/5297691/c7b50292-7bd7-11e4-987b-2dc21069e756.png)]  (https://rtcamp.com/donate/?project=easyengine)

[![BitCoin-Donate](https://bitpay.com/img/donate-button.svg)](https://bitpay.com/417008/donate)

---

## License
[MIT] (http://opensource.org/licenses/MIT)
