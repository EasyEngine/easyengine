# [EasyEngine](https://easyengine.io/)

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
ee site create example.com --wp                  # install wordpress without any page caching
ee site create example.com --w3tc                # install wordpress with w3-total-cache plugin
ee site create example.com --wpsc                # install wordpress with wp-super-cache plugin
ee site create example.com --wpfc                # install wordpress + nginx fastcgi_cache
ee site create example.com --wpredis             # install wordpress + nginx redis_cache
```

### WordPress Multsite with subdirectory

```bash
ee site create example.com --wpsubdir            # install wpmu-subdirectory without any page caching
ee site create example.com --wpsubdir --w3tc     # install wpmu-subdirectory with w3-total-cache plugin
ee site create example.com --wpsubdir --wpsc     # install wpmu-subdirectory with wp-super-cache plugin
ee site create example.com --wpsubdir --wpfc     # install wpmu-subdirectory + nginx fastcgi_cache
ee site create example.com --wpsubdir --wpredis  # install wpmu-subdirectory + nginx redis_cache
```

### WordPress Multsite with subdomain

```bash
ee site create example.com --wpsubdomain            # install wpmu-subdomain without any page caching
ee site create example.com --wpsubdomain --w3tc     # install wpmu-subdomain with w3-total-cache plugin
ee site create example.com --wpsubdomain --wpsc     # install wpmu-subdomain with wp-super-cache plugin
ee site create example.com --wpsubdomain --wpfc     # install wpmu-subdomain + nginx fastcgi_cache
ee site create example.com --wpsubdomain --wpredis  # install wpmu-subdomain + nginx redis_cache
```

### Non-WordPress Sites
```bash
ee site create example.com --html     # create example.com for static/html sites
ee site create example.com --php      # create example.com with php support
ee site create example.com --mysql    # create example.com with php & mysql support
```

### HHVM Enabled Sites
```bash
ee site create example.com --wp --hhvm           # create example.com WordPress site with HHVM support
ee site create example.com --php --hhvm          # create example.com php site with HHVM support
```

### PageSpeed Enabled Sites
```bash
ee site create example.com --wp --pagespeed      # create example.com WordPress site with PageSpeed support
ee site create example.com --php --pagespeed     # create example.com php site with PageSpeed support
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
- [Documentation](https://easyengine.io/docs/)
- [FAQ](https://easyengine.io/faq/)
- [Conventions used](https://easyengine.io/wordpress-nginx/tutorials/conventions/)

## Community Guides
- [Develop and Deploy with EasyEngine + VVV + Wordmove](https://github.com/joeguilmette/ee-vvv-wordmove)

## Donations

[![PayPal-Donate](https://cloud.githubusercontent.com/assets/4115/5297691/c7b50292-7bd7-11e4-987b-2dc21069e756.png)](http://rt.cx/eedonate)
[![BitCoin-Donate](https://bitpay.com/img/donate-button.svg)](https://bitpay.com/417008/donate)

## License
[MIT](http://opensource.org/licenses/MIT)
