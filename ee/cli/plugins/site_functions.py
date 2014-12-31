import os
import random
import string
import sys
import getpass
from ee.core.fileutils import EEFileUtils
from ee.core.mysql import EEMysql


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
              .format(e.errno, e.strerror, ee_domain_name))
        sys.exit(1)
    except Exception as e:
        print("{0}".format(e))
        sys.exit(1)

    # create symbolic link for
    EEFileUtils.create_symlink(self, ['/etc/nginx/sites-available/{0}.conf'
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
        sys.exit(1)

    EEFileUtils.create_symlink(self, ['/var/log/nginx/{0}.access.log'
                                      .format(ee_domain_name),
                                      '{0}/logs/access.log'
                                      .format(ee_site_webroot)])
    EEFileUtils.create_symlink(self, ['/var/log/nginx/{0}.error.log'
                                      .format(ee_domain_name),
                                      '{0}/logs/error.log'
                                      .format(ee_site_webroot)])


def setup_database(self, data):
    ee_domain_name = data['site_name']
    ee_random = (''.join(random.sample(string.ascii_uppercase +
                 string.ascii_lowercase + string.digits, 15)))
    ee_replace_dot = ee_domain_name.replace('.', '_')
    prompt_dbname = self.app.config.get('mysql', 'db-name')
    prompt_dbuser = self.app.config.get('mysql', 'db-user')
    ee_mysql_host = self.app.config.get('mysql', 'grant-host')
    print(ee_random)

    if prompt_dbname == 'True':
        try:
            ee_db_name = input('Enter the MySQL database name [{0}]:'
                               .format(ee_replace_dot))
        except EOFError as e:
            print("{0} {1}".format(e.errorno, e.strerror))
            sys.exit(0)

        if not ee_db_name:
            ee_db_name = ee_replace_dot

    if prompt_dbuser:
        try:
            ee_db_username = input('Enter the MySQL database user name [{0}]: '
                                   .format(ee_replace_dot))
            ee_db_password = input('Enter the MySQL database password [{0}]: '
                                   .format(ee_random))
        except EOFError as e:
            print("{0} {1}".format(e.errorno, e.strerror))
            sys.exit(1)

        if not ee_db_username:
            ee_db_username = ee_replace_dot
        if not ee_db_password:
            ee_db_password = ee_random

        if len(ee_db_name) > 16:
            print('Autofix MySQL username (ERROR 1470 (HY000)), please wait...'
                  )
            ee_random10 = (''.join(random.sample(string.ascii_uppercase +
                           string.ascii_lowercase + string.digits, 10)))
            ee_db_name = (ee_db_name[0:6] + ee_random10)

        # create MySQL database
        EEMysql.execute(self, "create database \'{0}\'"
                        .format(ee_db_name))

        # Create MySQL User
        EEMysql.execute(self,
                        "create user \'{0}\'@\'{1}\' identified by \'{2}\'"
                        .format(ee_db_username, ee_mysql_host, ee_db_password))

        # Grant permission
        EEMysql.execute(self,
                        "grant all privileges on \'{0}\'.* to \'{1}\'@\'{2}\'"
                        .format(ee_db_name, ee_db_username, ee_db_password))
