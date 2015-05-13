from ee.cli.plugins.stack import EEStackController
from ee.core.fileutils import EEFileUtils
from ee.core.mysql import *
from ee.core.shellexec import *
from ee.core.variables import EEVariables
from ee.cli.plugins.sitedb import *
from ee.core.aptget import EEAptGet
from ee.core.git import EEGit
from ee.core.logging import Log
from ee.core.services import EEService
import subprocess
from subprocess import CalledProcessError
import os
import random
import string
import sys
import getpass
import glob
import re


class SiteError(Exception):
    """Custom Exception Occured when setting up site"""
    def __init__(self, message):
        self.message = message

    def __str__(self):
        return repr(self.message)


def pre_run_checks(self):

    # Check nginx configuration
    Log.info(self, "Running pre-update checks, please wait...")
    try:
        Log.debug(self, "checking NGINX configuration ...")
        FNULL = open('/dev/null', 'w')
        ret = subprocess.check_call(["nginx", "-t"], stdout=FNULL,
                                    stderr=subprocess.STDOUT)
    except CalledProcessError as e:
        Log.debug(self, "{0}".format(str(e)))
        raise SiteError("nginx configuration check failed.")


def check_domain_exists(self, domain):
    if getSiteInfo(self, domain):
        return True
    else:
        return False


def setupdomain(self, data):

    ee_domain_name = data['site_name']
    ee_site_webroot = data['webroot'] if 'webroot' in data.keys() else ''

    # Check if nginx configuration already exists
    # if os.path.isfile('/etc/nginx/sites-available/{0}'
    #                   .format(ee_domain_name)):
    #     raise SiteError("nginx configuration already exists for site")

    Log.info(self, "Setting up NGINX configuration \t", end='')
    # write nginx config for file
    try:
        ee_site_nginx_conf = open('/etc/nginx/sites-available/{0}'
                                  .format(ee_domain_name), encoding='utf-8',
                                  mode='w')

        self.app.render((data), 'virtualconf.mustache',
                        out=ee_site_nginx_conf)
        ee_site_nginx_conf.close()
    except IOError as e:
        Log.debug(self, "{0}".format(e))
        raise SiteError("create nginx configuration failed for site")
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        raise SiteError("create nginx configuration failed for site")
    finally:
        # Check nginx -t and return status over it
        try:
            Log.debug(self, "Checking generated nginx conf, please wait...")
            FNULL = open('/dev/null', 'w')
            ret = subprocess.check_call(["nginx", "-t"], stdout=FNULL,
                                        stderr=subprocess.STDOUT)
            Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")
        except CalledProcessError as e:
            Log.debug(self, "{0}".format(str(e)))
            Log.info(self, "[" + Log.ENDC + Log.FAIL + "Fail"
                     + Log.OKBLUE + "]")
            raise SiteError("created nginx configuration failed for site."
                            " check with `nginx -t`")

    if 'proxy' in data.keys() and data['proxy']:
        return

    # create symbolic link for
    EEFileUtils.create_symlink(self, ['/etc/nginx/sites-available/{0}'
                                      .format(ee_domain_name),
                                      '/etc/nginx/sites-enabled/{0}'
                                      .format(ee_domain_name)])

    # Creating htdocs & logs directory
    Log.info(self, "Setting up webroot \t\t", end='')
    try:
        if not os.path.exists('{0}/htdocs'.format(ee_site_webroot)):
            os.makedirs('{0}/htdocs'.format(ee_site_webroot))
        if not os.path.exists('{0}/logs'.format(ee_site_webroot)):
            os.makedirs('{0}/logs'.format(ee_site_webroot))
        if not os.path.exists('{0}/conf/nginx'.format(ee_site_webroot)):
            os.makedirs('{0}/conf/nginx'.format(ee_site_webroot))

        EEFileUtils.create_symlink(self, ['/var/log/nginx/{0}.access.log'
                                          .format(ee_domain_name),
                                          '{0}/logs/access.log'
                                          .format(ee_site_webroot)])
        EEFileUtils.create_symlink(self, ['/var/log/nginx/{0}.error.log'
                                          .format(ee_domain_name),
                                          '{0}/logs/error.log'
                                          .format(ee_site_webroot)])
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        raise SiteError("setup webroot failed for site")
    finally:
        # TODO Check if directories are setup
        if (os.path.exists('{0}/htdocs'.format(ee_site_webroot)) and
           os.path.exists('{0}/logs'.format(ee_site_webroot))):
            Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")
        else:
            Log.info(self, "[" + Log.ENDC + "Fail" + Log.OKBLUE + "]")
            raise SiteError("setup webroot failed for site")


def setupdatabase(self, data):
    ee_domain_name = data['site_name']
    ee_random = (''.join(random.sample(string.ascii_uppercase +
                 string.ascii_lowercase + string.digits, 15)))
    ee_replace_dot = ee_domain_name.replace('.', '_')
    prompt_dbname = self.app.config.get('mysql', 'db-name')
    prompt_dbuser = self.app.config.get('mysql', 'db-user')
    ee_mysql_grant_host = self.app.config.get('mysql', 'grant-host')
    ee_db_name = ''
    ee_db_username = ''
    ee_db_password = ''

    if prompt_dbname == 'True' or prompt_dbname == 'true':
        try:
            ee_db_name = input('Enter the MySQL database name [{0}]: '
                               .format(ee_replace_dot))
        except EOFError as e:
            Log.debug(self, "{0}".format(e))
            raise SiteError("Unable to input database name")

    if not ee_db_name:
        ee_db_name = ee_replace_dot

    if prompt_dbuser == 'True' or prompt_dbuser == 'true':
        try:
            ee_db_username = input('Enter the MySQL database user name [{0}]: '
                                   .format(ee_replace_dot))
            ee_db_password = getpass.getpass(prompt='Enter the MySQL database'
                                             ' password [{0}]: '
                                             .format(ee_random))
        except EOFError as e:
            Log.debug(self, "{0}".format(e))
            raise SiteError("Unable to input database credentials")

    if not ee_db_username:
        ee_db_username = ee_replace_dot
    if not ee_db_password:
        ee_db_password = ee_random

    if len(ee_db_username) > 16:
        Log.debug(self, 'Autofix MySQL username (ERROR 1470 (HY000)),'
                  ' please wait')
        ee_db_username = (ee_db_name[0:6] + generate_random())

    # create MySQL database
    Log.info(self, "Setting up database\t\t", end='')
    Log.debug(self, "Creating database {0}".format(ee_db_name))
    try:
        if EEMysql.check_db_exists(self, ee_db_name):
            Log.debug(self, "Database already exists, Updating DB_NAME .. ")
            ee_db_name = (ee_db_name[0:6] + generate_random())
            ee_db_username = (ee_db_name[0:6] + generate_random())
    except MySQLConnectionError as e:
        raise SiteError("MySQL Connectivity problem occured")

    try:
        EEMysql.execute(self, "create database `{0}`"
                        .format(ee_db_name))
    except StatementExcecutionError as e:
        Log.info(self, "[" + Log.ENDC + Log.FAIL + "Failed" + Log.OKBLUE + "]")
        raise SiteError("create database execution failed")
    # Create MySQL User
    Log.debug(self, "Creating user {0}".format(ee_db_username))
    Log.debug(self, "create user `{0}`@`{1}` identified by ''"
              .format(ee_db_username, ee_mysql_grant_host))
    try:
        EEMysql.execute(self,
                        "create user `{0}`@`{1}` identified by '{2}'"
                        .format(ee_db_username, ee_mysql_grant_host,
                                ee_db_password), log=False)
    except StatementExcecutionError as e:
        Log.info(self, "[" + Log.ENDC + Log.FAIL + "Failed" + Log.OKBLUE + "]")
        raise SiteError("creating user failed for database")

    # Grant permission
    Log.debug(self, "Setting up user privileges")
    try:
        EEMysql.execute(self,
                        "grant all privileges on `{0}`.* to `{1}`@`{2}`"
                        .format(ee_db_name,
                                ee_db_username, ee_mysql_grant_host))
    except StatementExcecutionError as e:
        Log.info(self, "[" + Log.ENDC + Log.FAIL + "Failed" + Log.OKBLUE + "]")
        SiteError("grant privileges to user failed for database ")

    Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")

    data['ee_db_name'] = ee_db_name
    data['ee_db_user'] = ee_db_username
    data['ee_db_pass'] = ee_db_password
    data['ee_db_host'] = EEVariables.ee_mysql_host
    return(data)


def setupwordpress(self, data):
    ee_domain_name = data['site_name']
    ee_site_webroot = data['webroot']
    prompt_wpprefix = self.app.config.get('wordpress', 'prefix')
    ee_wp_user = self.app.config.get('wordpress', 'user')
    ee_wp_pass = self.app.config.get('wordpress', 'password')
    ee_wp_email = self.app.config.get('wordpress', 'email')
    # Random characters
    ee_random = (''.join(random.sample(string.ascii_uppercase +
                 string.ascii_lowercase + string.digits, 15)))
    ee_wp_prefix = ''
    # ee_wp_user = ''
    # ee_wp_pass = ''

    if 'wp-user' in data.keys() and data['wp-user']:
        ee_wp_user = data['wp-user']
    if 'wp-email' in data.keys() and data['wp-email']:
        ee_wp_email = data['wp-email']
    if 'wp-pass' in data.keys() and data['wp-pass']:
        ee_wp_pass = data['wp-pass']

    Log.info(self, "Downloading Wordpress \t\t", end='')
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    try:
        EEShellExec.cmd_exec(self, "wp --allow-root core"
                             " download")
    except CommandExecutionError as e:
        Log.info(self, "[" + Log.ENDC + Log.FAIL + "Fail" + Log.OKBLUE + "]")
        raise SiteError(self, "download wordpress core failed")

    Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")

    if not (data['ee_db_name'] and data['ee_db_user'] and data['ee_db_pass']):
        data = setupdatabase(self, data)
    if prompt_wpprefix == 'True' or prompt_wpprefix == 'true':
        try:
            ee_wp_prefix = input('Enter the WordPress table prefix [wp_]: ')
            while not re.match('^[A-Za-z0-9_]*$', ee_wp_prefix):
                Log.warn(self, "table prefix can only "
                         "contain numbers, letters, and underscores")
                ee_wp_prefix = input('Enter the WordPress table prefix [wp_]: '
                                     )
        except EOFError as e:
            Log.debug(self, "{0}".format(e))
            raise SiteError("input table prefix failed")

    if not ee_wp_prefix:
        ee_wp_prefix = 'wp_'

    # Modify wp-config.php & move outside the webroot

    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    Log.debug(self, "Setting up wp-config file")
    if not data['multisite']:
        Log.debug(self, "Generating wp-config for WordPress Single site")
        Log.debug(self, "bash -c \"php /usr/bin/wp --allow-root "
                  + "core config "
                  + "--dbname=\'{0}\' --dbprefix=\'{1}\' --dbuser=\'{2}\' "
                  "--dbhost=\'{3}\' "
                  .format(data['ee_db_name'], ee_wp_prefix,
                          data['ee_db_user'], data['ee_db_host'])
                  + "--dbpass= "
                  "--extra-php<<PHP \n {1}\nPHP\""
                  .format(data['ee_db_pass'],
                          "\n\ndefine(\'WP_DEBUG\', false);"))
        try:
            EEShellExec.cmd_exec(self, "bash -c \"php /usr/bin/wp --allow-root"
                                 + " core config "
                                 + "--dbname=\'{0}\' --dbprefix=\'{1}\' "
                                 "--dbuser=\'{2}\' --dbhost=\'{3}\' "
                                 .format(data['ee_db_name'], ee_wp_prefix,
                                         data['ee_db_user'], data['ee_db_host']
                                         )
                                 + "--dbpass=\'{0}\' "
                                   "--extra-php<<PHP \n {1}\nPHP\""
                                   .format(data['ee_db_pass'],
                                           "\n\ndefine(\'WP_DEBUG\', false);"),
                                   log=False
                                 )
        except CommandExecutionError as e:
                raise SiteError("generate wp-config failed for wp single site")
    else:
        Log.debug(self, "Generating wp-config for WordPress multisite")
        Log.debug(self, "bash -c \"php /usr/bin/wp --allow-root "
                  + "core config "
                  + "--dbname=\'{0}\' --dbprefix=\'{1}\' --dbhost=\'{2}\' "
                  .format(data['ee_db_name'], ee_wp_prefix, data['ee_db_host'])
                  + "--dbuser=\'{0}\' --dbpass= "
                  "--extra-php<<PHP \n {2} {3} {4}\nPHP\""
                  .format(data['ee_db_user'], data['ee_db_pass'],
                          "\ndefine(\'WP_ALLOW_MULTISITE\', "
                          "true);",
                          "\ndefine(\'WPMU_ACCEL_REDIRECT\',"
                          " true);",
                          "\n\ndefine(\'WP_DEBUG\', false);"))
        try:
            EEShellExec.cmd_exec(self, "bash -c \"php /usr/bin/wp --allow-root"
                                 + " core config "
                                 + "--dbname=\'{0}\' --dbprefix=\'{1}\' "
                                 "--dbhost=\'{2}\' "
                                 .format(data['ee_db_name'], ee_wp_prefix,
                                         data['ee_db_host'])
                                 + "--dbuser=\'{0}\' --dbpass=\'{1}\' "
                                   "--extra-php<<PHP \n {2} {3} {4}\nPHP\""
                                 .format(data['ee_db_user'],
                                         data['ee_db_pass'],
                                         "\ndefine(\'WP_ALLOW_MULTISITE\', "
                                         "true);",
                                         "\ndefine(\'WPMU_ACCEL_REDIRECT\',"
                                         " true);",
                                         "\n\ndefine(\'WP_DEBUG\', false);"),
                                 log=False
                                 )
        except CommandExecutionError as e:
                raise SiteError("generate wp-config failed for wp multi site")

    EEFileUtils.mvfile(self, os.getcwd()+'/wp-config.php',
                       os.path.abspath(os.path.join(os.getcwd(), os.pardir)))

    if not ee_wp_user:
        ee_wp_user = EEVariables.ee_user
        while not ee_wp_user:
            Log.warn(self, "Username can have only alphanumeric"
                     "characters, spaces, underscores, hyphens,"
                     "periods and the @ symbol.")
            try:
                ee_wp_user = input('Enter WordPress username: ')
            except EOFError as e:
                Log.debug(self, "{0}".format(e))
                raise SiteError("input wordpress username failed")
    if not ee_wp_pass:
        ee_wp_pass = ee_random

    if not ee_wp_email:
        ee_wp_email = EEVariables.ee_email
        while not ee_wp_email:
            try:
                ee_wp_email = input('Enter WordPress email: ')
            except EOFError as e:
                Log.debug(self, "{0}".format(e))
                raise SiteError("input wordpress username failed")

    try:
        while not re.match(r"^[A-Za-z0-9\.\+_-]+@[A-Za-z0-9\._-]+\.[a-zA-Z]*$",
                           ee_wp_email):
            Log.info(self, "EMail not Valid in config, "
                     "Please provide valid email id")
            ee_wp_email = input("Enter your email: ")
    except EOFError as e:
        Log.debug(self, "{0}".format(e))
        raise SiteError("input WordPress user email failed")

    Log.debug(self, "Setting up WordPress tables")

    if not data['multisite']:
        Log.debug(self, "Creating tables for WordPress Single site")
        Log.debug(self, "php /usr/bin/wp --allow-root core install "
                  "--url=\'{0}\' --title=\'{0}\' --admin_name=\'{1}\' "
                  .format(data['www_domain'], ee_wp_user)
                  + "--admin_password= --admin_email=\'{1}\'"
                  .format(ee_wp_pass, ee_wp_email))
        try:
            EEShellExec.cmd_exec(self, "php /usr/bin/wp --allow-root core "
                                 "install --url=\'{0}\' --title=\'{0}\' "
                                 "--admin_name=\'{1}\' "
                                 .format(data['www_domain'], ee_wp_user)
                                 + "--admin_password=\'{0}\' "
                                 "--admin_email=\'{1}\'"
                                 .format(ee_wp_pass, ee_wp_email),
                                 log=False)
        except CommandExceutionError as e:
            raise SiteError("setup wordpress tables failed for single site")
    else:
        Log.debug(self, "Creating tables for WordPress multisite")
        Log.debug(self, "php /usr/bin/wp --allow-root "
                  "core multisite-install "
                  "--url=\'{0}\' --title=\'{0}\' --admin_name=\'{1}\' "
                  .format(data['www_domain'], ee_wp_user)
                  + "--admin_password= --admin_email=\'{1}\' "
                  "{subdomains}"
                  .format(ee_wp_pass, ee_wp_email,
                          subdomains='--subdomains'
                          if not data['wpsubdir'] else ''))
        try:
            EEShellExec.cmd_exec(self, "php /usr/bin/wp --allow-root "
                                 "core multisite-install "
                                 "--url=\'{0}\' --title=\'{0}\' "
                                 "--admin_name=\'{1}\' "
                                 .format(data['www_domain'], ee_wp_user)
                                 + "--admin_password=\'{0}\' "
                                 "--admin_email=\'{1}\' "
                                 "{subdomains}"
                                 .format(ee_wp_pass, ee_wp_email,
                                         subdomains='--subdomains'
                                         if not data['wpsubdir'] else ''),
                                 log=False)
        except CommandExecutionError as e:
            raise SiteError("setup wordpress tables failed for wp multi site")

    Log.debug(self, "Updating WordPress permalink")
    try:
        EEShellExec.cmd_exec(self, " php /usr/bin/wp --allow-root "
                             "rewrite structure "
                             "/%year%/%monthnum%/%day%/%postname%/")
    except CommandExecutionError as e:
        raise SiteError("Update wordpress permalinks failed")

    """Install nginx-helper plugin """
    installwp_plugin(self, 'nginx-helper', data)

    """Install Wp Super Cache"""
    if data['wpsc']:
        installwp_plugin(self, 'wp-super-cache', data)

    """Install W3 Total Cache"""
    if data['w3tc'] or data['wpfc']:
        installwp_plugin(self, 'w3-total-cache', data)

    wp_creds = dict(wp_user=ee_wp_user, wp_pass=ee_wp_pass,
                    wp_email=ee_wp_email)

    return(wp_creds)


def setupwordpressnetwork(self, data):
    ee_site_webroot = data['webroot']
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    Log.info(self, "Setting up WordPress Network \t", end='')
    try:
        EEShellExec.cmd_exec(self, 'wp --allow-root core multisite-convert'
                             ' --title=\'{0}\' {subdomains}'
                             .format(data['www_domain'],
                                     subdomains='--subdomains'
                                     if not data['wpsubdir'] else ''))
    except CommandExecutionError as e:
        Log.info(self, "[" + Log.ENDC + Log.FAIL + "Fail" + Log.OKBLUE + "]")
        raise SiteError("setup wordpress network failed")
    Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")


def installwp_plugin(self, plugin_name, data):
    ee_site_webroot = data['webroot']
    Log.info(self, "Installing plugin {0}, please wait..."
             .format(plugin_name))
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    try:
        EEShellExec.cmd_exec(self, "php /usr/bin/wp plugin "
                             "--allow-root install "
                             "{0}".format(plugin_name))
    except CommandExecutionError as e:
        raise SiteError("plugin installation failed")

    try:
        EEShellExec.cmd_exec(self, "php /usr/bin/wp plugin "
                             "--allow-root activate "
                             "{0} {na}"
                             .format(plugin_name,
                                     na='--network' if data['multisite']
                                     else ''
                                     ))
    except CommandExecutionError as e:
        raise SiteError("plugin activation failed")


def uninstallwp_plugin(self, plugin_name, data):
    ee_site_webroot = data['webroot']
    Log.debug(self, "Uninstalling plugin {0}, please wait..."
              .format(plugin_name))
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    try:
        EEShellExec.cmd_exec(self, "php /usr/bin/wp plugin "
                             "--allow-root uninstall "
                             "{0}".format(plugin_name))
    except CommandExecutionError as e:
        raise SiteError("plugin uninstall failed")


def setwebrootpermissions(self, webroot):
    Log.debug(self, "Setting up permissions")
    try:
        EEFileUtils.chown(self, webroot, EEVariables.ee_php_user,
                          EEVariables.ee_php_user, recursive=True)
    except Exception as e:
        Log.debug(self, str(e))
        raise SiteError("problem occured while settingup webroot permissions")


def sitebackup(self, data):
    ee_site_webroot = data['webroot']
    backup_path = ee_site_webroot + '/backup/{0}'.format(EEVariables.ee_date)
    if not EEFileUtils.isexist(self, backup_path):
        EEFileUtils.mkdir(self, backup_path)
    Log.info(self, "Backup location : {0}".format(backup_path))
    EEFileUtils.copyfile(self, '/etc/nginx/sites-available/{0}'
                         .format(data['site_name']), backup_path)

    if data['currsitetype'] in ['html', 'php', 'proxy', 'mysql']:
        Log.info(self, "Backing up Webroot \t\t", end='')
        EEFileUtils.mvfile(self, ee_site_webroot + '/htdocs', backup_path)
        Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")

    configfiles = glob.glob(ee_site_webroot + '/*-config.php')
    # if configfiles and EEFileUtils.isexist(self, configfiles[0]):
    #     ee_db_name = (EEFileUtils.grep(self, configfiles[0],
    #                   'DB_NAME').split(',')[1]
    #                   .split(')')[0].strip().replace('\'', ''))
    if data['ee_db_name']:
        Log.info(self, 'Backing up database \t\t', end='')
        try:
            if not EEShellExec.cmd_exec(self, "mysqldump {0} > {1}/{0}.sql"
                                        .format(data['ee_db_name'],
                                                backup_path)):
                Log.info(self,
                         "[" + Log.ENDC + Log.FAIL + "Fail" + Log.OKBLUE + "]")
                raise SiteError("mysqldump failed to backup database")
        except CommandExecutionError as e:
            Log.info(self, "[" + Log.ENDC + "Fail" + Log.OKBLUE + "]")
            raise SiteError("mysqldump failed to backup database")
        Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")
        # move wp-config.php/ee-config.php to backup
        if data['currsitetype'] in ['mysql', 'proxy']:
            EEFileUtils.mvfile(self, configfiles[0], backup_path)
        else:
            EEFileUtils.copyfile(self, configfiles[0], backup_path)


def site_package_check(self, stype):
    apt_packages = []
    packages = []
    stack = EEStackController()
    stack.app = self.app
    if stype in ['html', 'proxy', 'php', 'mysql', 'wp', 'wpsubdir',
                 'wpsubdomain']:
        Log.debug(self, "Setting apt_packages variable for Nginx")

        if EEVariables.ee_platform_distro == 'debian':
            check_nginx = 'nginx-extras'
        else:
            check_nginx = 'nginx-custom'

        if not EEAptGet.is_installed(self, check_nginx):
            apt_packages = apt_packages + EEVariables.ee_nginx
        else:
            # Fix for Nginx white screen death
            if not EEFileUtils.grep(self, '/etc/nginx/fastcgi_params',
                                    'SCRIPT_FILENAME'):
                with open('/etc/nginx/fastcgi_params', encoding='utf-8',
                          mode='a') as ee_nginx:
                    ee_nginx.write('fastcgi_param \tSCRIPT_FILENAME '
                                   '\t$request_filename;')

    if stype in ['php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
        Log.debug(self, "Setting apt_packages variable for PHP")
        if not EEAptGet.is_installed(self, 'php5-fpm'):
            apt_packages = apt_packages + EEVariables.ee_php

    if stype in ['mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
        Log.debug(self, "Setting apt_packages variable for MySQL")
        if not EEShellExec.cmd_exec(self, "mysqladmin ping"):
            apt_packages = apt_packages + EEVariables.ee_mysql
            packages = packages + [["https://raw.githubusercontent.com/"
                                    "major/MySQLTuner-perl/master/"
                                    "mysqltuner.pl", "/usr/bin/mysqltuner",
                                    "MySQLTuner"]]

    if stype in ['php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
        Log.debug(self, "Setting apt_packages variable for Postfix")
        if not EEAptGet.is_installed(self, 'postfix'):
            apt_packages = apt_packages + EEVariables.ee_postfix

    if stype in ['wp', 'wpsubdir', 'wpsubdomain']:
        Log.debug(self, "Setting packages variable for WP-CLI")
        if not EEShellExec.cmd_exec(self, "which wp"):
            packages = packages + [["https://github.com/wp-cli/wp-cli/"
                                    "releases/download/v{0}/"
                                    "wp-cli-{0}.phar"
                                    .format(EEVariables.ee_wp_cli),
                                    "/usr/bin/wp", "WP-CLI"]]

    if self.app.pargs.hhvm:
        Log.debug(self, "Setting apt_packages variable for HHVM")
        if not EEAptGet.is_installed(self, 'hhvm'):
            apt_packages = apt_packages + EEVariables.ee_hhvm

        if os.path.isdir("/etc/nginx/common") and (not
           os.path.isfile("/etc/nginx/common/php-hhvm.conf")):
            data = dict()
            Log.debug(self, 'Writting the nginx configuration to '
                      'file /etc/nginx/common/php-hhvm.conf')
            ee_nginx = open('/etc/nginx/common/php-hhvm.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'php-hhvm.mustache',
                            out=ee_nginx)
            ee_nginx.close()

            Log.debug(self, 'Writting the nginx configuration to '
                      'file /etc/nginx/common/w3tc-hhvm.conf')
            ee_nginx = open('/etc/nginx/common/w3tc-hhvm.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'w3tc-hhvm.mustache', out=ee_nginx)
            ee_nginx.close()

            Log.debug(self, 'Writting the nginx configuration to '
                      'file /etc/nginx/common/wpfc-hhvm.conf')
            ee_nginx = open('/etc/nginx/common/wpfc-hhvm.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'wpfc-hhvm.mustache',
                            out=ee_nginx)
            ee_nginx.close()

            Log.debug(self, 'Writting the nginx configuration to '
                      'file /etc/nginx/common/wpsc-hhvm.conf')
            ee_nginx = open('/etc/nginx/common/wpsc-hhvm.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'wpsc-hhvm.mustache',
                            out=ee_nginx)
            ee_nginx.close()

        if os.path.isfile("/etc/nginx/conf.d/upstream.conf"):
            if not EEFileUtils.grep(self, "/etc/nginx/conf.d/upstream.conf",
                                          "hhvm"):
                with open("/etc/nginx/conf.d/upstream.conf", "a") as hhvm_file:
                    hhvm_file.write("upstream hhvm {\nserver 127.0.0.1:8000;\n"
                                    "server 127.0.0.1:9000 backup;\n}\n")

    # Check if Nginx is allready installed and Pagespeed config there or not
    # If not then copy pagespeed config
    if self.app.pargs.pagespeed:
        if (os.path.isfile('/etc/nginx/nginx.conf') and
           (not os.path.isfile('/etc/nginx/conf.d/pagespeed.conf'))):
            # Pagespeed configuration
            data = dict()
            Log.debug(self, 'Writting the Pagespeed Global '
                      'configuration to file /etc/nginx/conf.d/'
                      'pagespeed.conf')
            ee_nginx = open('/etc/nginx/conf.d/pagespeed.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'pagespeed-global.mustache',
                            out=ee_nginx)
            ee_nginx.close()

    return(stack.install(apt_packages=apt_packages, packages=packages,
                         disp_msg=False))


def updatewpuserpassword(self, ee_domain, ee_site_webroot):

    ee_wp_user = ''
    ee_wp_pass = ''
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))

    # Check if ee_domain is wordpress install
    try:
        is_wp = EEShellExec.cmd_exec(self, "wp --allow-root core"
                                     " version")
    except CommandExecutionError as e:
        raise SiteError("is wordpress site? check command failed ")

    # Exit if ee_domain is not wordpress install
    if not is_wp:
        Log.error(self, "{0} does not seem to be a WordPress site"
                  .format(ee_domain))

    try:
        ee_wp_user = input("Provide WordPress user name [admin]: ")
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "\nCould not update password")

    if ee_wp_user == "?":
        Log.info(self, "Fetching WordPress user list")
        try:
            EEShellExec.cmd_exec(self, "wp --allow-root user list "
                                 "--fields=user_login | grep -v user_login")
        except CommandExecutionError as e:
            raise SiteError("fetch wp userlist command failed")

    if not ee_wp_user:
        ee_wp_user = 'admin'

    try:
        is_user_exist = EEShellExec.cmd_exec(self, "wp --allow-root user list "
                                             "--fields=user_login | grep {0}$ "
                                             .format(ee_wp_user))
    except CommandExecutionError as e:
        raise SiteError("if wp user exists check command failed")

    if is_user_exist:
        try:
            ee_wp_pass = getpass.getpass(prompt="Provide password for "
                                         "{0} user: "
                                         .format(ee_wp_user))
        except Exception as e:
            Log.debug(self, "{0}".format(e))
            raise SiteError("failed to read password input ")

        try:
            EEShellExec.cmd_exec(self, "wp --allow-root user update {0}"
                                 "  --user_pass={1}"
                                 .format(ee_wp_user, ee_wp_pass))
        except CommandExecutionError as e:
            raise SiteError("wp user password update command failed")
        Log.info(self, "Password updated successfully")

    else:
        Log.error(self, "Invalid WordPress user {0} for {1}."
                  .format(ee_wp_user, ee_domain))


def display_cache_settings(self, data):
    if data['wpsc']:
        if data['multisite']:
            Log.info(self, "Configure WPSC:"
                     "\t\thttp://{0}/wp-admin/network/settings.php?"
                     "page=wpsupercache"
                     .format(data['site_name']))
        else:
            Log.info(self, "Configure WPSC:"
                     "\t\thttp://{0}/wp-admin/options-general.php?"
                     "page=wpsupercache"
                     .format(data['site_name']))

    if data['wpfc']:
        if data['multisite']:
            Log.info(self, "Configure nginx-helper:"
                     "\thttp://{0}/wp-admin/network/settings.php?"
                     "page=nginx".format(data['site_name']))
        else:
            Log.info(self, "Configure nginx-helper:"
                     "\thttp://{0}/wp-admin/options-general.php?"
                     "page=nginx".format(data['site_name']))

    if data['wpfc'] or data['w3tc']:
        if data['multisite']:
            Log.info(self, "Configure W3TC:"
                     "\t\thttp://{0}/wp-admin/network/admin.php?"
                     "page=w3tc_general".format(data['site_name']))
        else:
            Log.info(self, "Configure W3TC:"
                     "\t\thttp://{0}/wp-admin/admin.php?"
                     "page=w3tc_general".format(data['site_name']))

        if data['wpfc']:
            Log.info(self, "Page Cache:\t\tDisable")
        elif data['w3tc']:
            Log.info(self, "Page Cache:\t\tDisk Enhanced")
        Log.info(self, "Database Cache:\t\tMemcached")
        Log.info(self, "Object Cache:\t\tMemcached")
        Log.info(self, "Browser Cache:\t\tDisable")


def logwatch(self, logfiles):
    import zlib
    import base64
    import time
    from ee.core import logwatch

    def callback(filename, lines):
        for line in lines:
            if line.find(':::') == -1:
                print(line)
            else:
                data = line.split(':::')
                try:
                    print(data[0], data[1],
                          zlib.decompress(base64.decodestring(data[2])))
                except Exception as e:
                    Log.info(time.time(),
                             'caught exception rendering a new log line in %s'
                             % filename)

    l = logwatch.LogWatcher(logfiles, callback)
    l.loop()


def detSitePar(opts):
    """
        Takes dictionary of parsed arguments
        1.returns sitetype and cachetype
        2. raises RuntimeError when wrong combination is used like
            "--wp --wpsubdir" or "--html --wp"
    """
    sitetype, cachetype = '', ''
    typelist = list()
    cachelist = list()
    for key, val in opts.items():
        if val and key in ['html', 'php', 'mysql', 'wp',
                           'wpsubdir', 'wpsubdomain']:
            typelist.append(key)
        elif val and key in ['wpfc', 'wpsc', 'w3tc']:
            cachelist.append(key)

    if len(typelist) > 1 or len(cachelist) > 1:
        raise RuntimeError("could not determine site and cache type")
    else:
        if not typelist and not cachelist:
            sitetype = None
            cachetype = None
        elif (not typelist) and cachelist:
            sitetype = 'wp'
            cachetype = cachelist[0]
        elif typelist and (not cachelist):
            sitetype = typelist[0]
            cachetype = 'basic'
        else:
            sitetype = typelist[0]
            cachetype = cachelist[0]
    return (sitetype, cachetype)


def generate_random():
    ee_random10 = (''.join(random.sample(string.ascii_uppercase +
                   string.ascii_lowercase + string.digits, 10)))
    return ee_random10


def deleteDB(self, dbname, dbuser, dbhost):
    try:
        # Check if Database exists
        try:
            if EEMysql.check_db_exists(self, dbname):
                # Drop database if exists
                Log.debug(self, "dropping database `{0}`".format(dbname))
                EEMysql.execute(self,
                                "drop database `{0}`".format(dbname),
                                errormsg='Unable to drop database {0}'
                                .format(dbname))
        except StatementExcecutionError as e:
            Log.debug(self, "drop database failed")
            Log.info(self, "Database {0} not dropped".format(dbname))

        except MySQLConnectionError as e:
            Log.debug(self, "Mysql Connection problem occured")

        if dbuser != 'root':
            Log.debug(self, "dropping user `{0}`".format(dbuser))
            try:
                EEMysql.execute(self,
                                "drop user `{0}`@`{1}`"
                                .format(dbuser, dbhost))
            except StatementExcecutionError as e:
                Log.debug(self, "drop database user failed")
                Log.info(self, "Database {0} not dropped".format(dbuser))
            try:
                EEMysql.execute(self, "flush privileges")
            except StatementExcecutionError as e:
                Log.debug(self, "drop database failed")
                Log.info(self, "Database {0} not dropped".format(dbname))
    except Exception as e:
        Log.error(self, "Error occured while deleting database")


def deleteWebRoot(self, webroot):
    # do some preprocessing before proceeding
    webroot = webroot.strip()
    if (webroot == "/var/www/" or webroot == "/var/www"
       or webroot == "/var/www/.." or webroot == "/var/www/."):
        Log.debug(self, "Tried to remove {0}, but didn't remove it"
                  .format(webroot))
        return False

    if os.path.isdir(webroot):
        Log.debug(self, "Removing {0}".format(webroot))
        EEFileUtils.rm(self, webroot)
        return True
    else:
        Log.debug(self, "{0} does not exist".format(webroot))
        return False


def removeNginxConf(self, domain):
    if os.path.isfile('/etc/nginx/sites-available/{0}'
                      .format(domain)):
            Log.debug(self, "Removing Nginx configuration")
            EEFileUtils.rm(self, '/etc/nginx/sites-enabled/{0}'
                           .format(domain))
            EEFileUtils.rm(self, '/etc/nginx/sites-available/{0}'
                           .format(domain))
            EEService.reload_service(self, 'nginx')
            EEGit.add(self, ["/etc/nginx"],
                      msg="Deleted {0} "
                      .format(domain))


def doCleanupAction(self, domain='', webroot='', dbname='', dbuser='',
                    dbhost=''):
    """
       Removes the nginx configuration and database for the domain provided.
       doCleanupAction(self, domain='sitename', webroot='',
                       dbname='', dbuser='', dbhost='')
    """
    if domain:
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(domain)):
            removeNginxConf(self, domain)
    if webroot:
        deleteWebRoot(self, webroot)

    if dbname:
        if not dbuser:
            raise SiteError("dbuser not provided")
            if not dbhost:
                raise SiteError("dbhost not provided")
        deleteDB(self, dbname, dbuser, dbhost)


def operateOnPagespeed(self, data):

    ee_domain_name = data['site_name']
    ee_site_webroot = data['webroot']

    if data['pagespeed'] is True:
        if not os.path.isfile("{0}/conf/nginx/pagespeed.conf.disabled"
                              .format(ee_site_webroot)):
            Log.debug(self, 'Writting the Pagespeed common '
                      'configuration to file {0}/conf/nginx/pagespeed.conf'
                      'pagespeed.conf'.format(ee_site_webroot))
            ee_nginx = open('{0}/conf/nginx/pagespeed.conf'
                            .format(ee_site_webroot), encoding='utf-8',
                            mode='w')
            self.app.render((data), 'pagespeed-common.mustache',
                            out=ee_nginx)
            ee_nginx.close()
        else:
            EEFileUtils.mvfile(self, "{0}/conf/nginx/pagespeed.conf.disabled"
                               .format(ee_site_webroot),
                               '{0}/conf/nginx/pagespeed.conf'
                               .format(ee_site_webroot))

    elif data['pagespeed'] is False:
        if os.path.isfile("{0}/conf/nginx/pagespeed.conf"
                          .format(ee_site_webroot)):
            EEFileUtils.mvfile(self, "{0}/conf/nginx/pagespeed.conf"
                               .format(ee_site_webroot),
                               '{0}/conf/nginx/pagespeed.conf.disabled'
                               .format(ee_site_webroot))

    # Add nginx conf folder into GIT
    EEGit.add(self, ["{0}/conf/nginx".format(ee_site_webroot)],
              msg="Adding Pagespeed config of site: {0}"
              .format(ee_domain_name))
