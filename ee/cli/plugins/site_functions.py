import os
from ee.core.fileutils import EEFileUtils


def setup_domain(self, data):

    ee_domain_name = data['site_name']
    ee_site_webroot = data['webroot']
    print("Creating {0}, please wait...".format(ee_domain_name))
    # write nginx config for file
    try:
        ee_site_nginx_conf = open('/etc/nginx/sites-available/{0}.conf'
                                  .format(ee_domain_name), 'w')

        self.app.render((data), 'virtualconf.mustache',
                        out=ee_site_nginx_conf)
        ee_site_nginx_conf.close()
    except IOError as e:
        print("Unable to create nginx conf for {2} ({0}): {1}"
              .format(e.errno, e.strerror))
    except Exception as e:
        print("{0}".format(e))

    # create symbolic link for
    EEFileUtils.create_symlink(['/etc/nginx/sites-available/{0}.conf'
                                .format(ee_domain_name),
                                '/etc/nginx/sites-enabled/{0}.conf'
                                .format(ee_domain_name)])

    # Creating htdocs & logs directory
    try:
        if not os.path.exists('{0}/htdocs'.format(ee_site_webroot)):
            os.makedirs('{0}/htdocs'.format(ee_site_webroot))
        if not os.path.exists('{0}/logs'.format(ee_site_webroot)):
            os.makedirs('{0}/logs'.format(ee_site_webroot))
    except Exception as e:
        print("{0}".format(e))

    EEFileUtils.create_symlink(['/var/log/nginx/{0}.access.log'
                                .format(ee_domain_name),
                                '{0}/logs/access.log'
                                .format(ee_site_webroot)])
    EEFileUtils.create_symlink(['/var/log/nginx/{0}.error.log'
                                .format(ee_domain_name),
                                '{0}/logs/error.log'
                                .format(ee_site_webroot)])


def setup_database(self, data):
    ee_domain_name = data['site_name']
    ee_random = (''.join(random.sample(string.ascii_uppercase +
                 string.ascii_lowercase + string.digits, 64)))
    ee_replace_dot = ee_domain_name.replace('.', '_')
