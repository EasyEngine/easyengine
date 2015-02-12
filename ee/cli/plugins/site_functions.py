from ee.cli.plugins.stack import EEStackController
from ee.core.fileutils import EEFileUtils
from ee.core.mysql import EEMysql
from ee.core.shellexec import EEShellExec
from ee.core.variables import EEVariables
from ee.core.aptget import EEAptGet
from ee.core.logging import Log
import os
import random
import string
import sys
import getpass
import glob
import re


def setupdomain(self, data):

    ee_domain_name = data['site_name']
    ee_site_webroot = data['webroot']
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
        Log.error(self, "\nUnable to create NGINX configuration")
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "\nUnable to create NGINX configuration")
    Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")

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
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "\nUnable to setup webroot")

    EEFileUtils.create_symlink(self, ['/var/log/nginx/{0}.access.log'
                                      .format(ee_domain_name),
                                      '{0}/logs/access.log'
                                      .format(ee_site_webroot)])
    EEFileUtils.create_symlink(self, ['/var/log/nginx/{0}.error.log'
                                      .format(ee_domain_name),
                                      '{0}/logs/error.log'
                                      .format(ee_site_webroot)])
    Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")


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
            Log.error(self, "Unable to input database name")

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
            Log.error(self, "Unable to input database credentials")

    if not ee_db_username:
        ee_db_username = ee_replace_dot
    if not ee_db_password:
        ee_db_password = ee_random

    if len(ee_db_username) > 16:
        Log.debug(self, 'Autofix MySQL username (ERROR 1470 (HY000)),'
                  ' please wait')
        ee_random10 = (''.join(random.sample(string.ascii_uppercase +
                       string.ascii_lowercase + string.digits, 10)))
        ee_db_username = (ee_db_name[0:6] + ee_random10)
    # create MySQL database
    Log.info(self, "Setting up database\t\t", end='')
    Log.debug(self, "Creating databse {0}".format(ee_db_name))
    EEMysql.execute(self, "create database {0}"
                    .format(ee_db_name), errormsg="Cannot create database")

    # Create MySQL User
    Log.debug(self, "Creating user {0}".format(ee_db_username))
    Log.debug(self, "create user {0}@{1} identified by ''"
              .format(ee_db_username, ee_mysql_grant_host))
    EEMysql.execute(self,
                    "create user {0}@{1} identified by '{2}'"
                    .format(ee_db_username, ee_mysql_grant_host,
                            ee_db_password),
                    errormsg="Cannot setup database user", log=False)

    # Grant permission
    Log.debug(self, "Setting up user privileges")
    EEMysql.execute(self,
                    "grant all privileges on {0}.* to {1}@{2}"
                    .format(ee_db_name, ee_db_username, ee_mysql_grant_host),
                    errormsg="Cannot setup database user privileges")
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

    Log.info(self, "Downloading Wordpress \t\t", end='')
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    EEShellExec.cmd_exec(self, "wp --allow-root core download")
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
            Log.error(self, "Unable to input table prefix")

    if not ee_wp_prefix:
        ee_wp_prefix = 'wp_'

    # Modify wp-config.php & move outside the webroot

    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    Log.debug(self, "Setting up wp-config file")
    if not data['multisite']:
        Log.debug(self, "Generating wp-config for WordPress Single site")
        Log.debug(self, "bash -c \"php /usr/bin/wp --allow-root "
                  + "core config "
                  + "--dbname={0} --dbprefix={1} --dbuser={2} "
                  .format(data['ee_db_name'], ee_wp_prefix,
                          data['ee_db_user'])
                  + "--dbpass= "
                  "--extra-php<<PHP \n {1}\nPHP\""
                  .format(data['ee_db_pass'],
                          "\n\ndefine(\'WP_DEBUG\', false);"))
        EEShellExec.cmd_exec(self, "bash -c \"php /usr/bin/wp --allow-root "
                             + "core config "
                             + "--dbname={0} --dbprefix={1} --dbuser={2} "
                             .format(data['ee_db_name'], ee_wp_prefix,
                                     data['ee_db_user'])
                             + "--dbpass={0} "
                               "--extra-php<<PHP \n {1}\nPHP\""
                               .format(data['ee_db_pass'],
                                       "\n\ndefine(\'WP_DEBUG\', false);"),
                               log=False
                             )
    else:
        Log.debug(self, "Generating wp-config for WordPress multisite")
        Log.debug(self, "bash -c \"php /usr/bin/wp --allow-root "
                  + "core config "
                  + "--dbname={0} --dbprefix={1} "
                  .format(data['ee_db_name'], ee_wp_prefix)
                  + "--dbuser={0} --dbpass= "
                  "--extra-php<<PHP \n {2} {3} {4}\nPHP\""
                  .format(data['ee_db_user'], data['ee_db_pass'],
                          "\ndefine(\'WP_ALLOW_MULTISITE\', "
                          "true);",
                          "\ndefine(\'WPMU_ACCEL_REDIRECT\',"
                          " true);",
                          "\n\ndefine(\'WP_DEBUG\', false);"))
        EEShellExec.cmd_exec(self, "bash -c \"php /usr/bin/wp --allow-root "
                             + "core config "
                             + "--dbname={0} --dbprefix={1} "
                             .format(data['ee_db_name'], ee_wp_prefix)
                             + "--dbuser={0} --dbpass={1} "
                               "--extra-php<<PHP \n {2} {3} {4}\nPHP\""
                             .format(data['ee_db_user'], data['ee_db_pass'],
                                     "\ndefine(\'WP_ALLOW_MULTISITE\', "
                                     "true);",
                                     "\ndefine(\'WPMU_ACCEL_REDIRECT\',"
                                     " true);",
                                     "\n\ndefine(\'WP_DEBUG\', false);"),
                             log=False
                             )
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
                Log.error(self, "Unable to input WordPress user name")

    if not ee_wp_pass:
        ee_wp_pass = ee_random

    if not ee_wp_email:
        ee_wp_email = EEVariables.ee_email
        while not ee_wp_email:
            try:
                ee_wp_email = input('Enter WordPress email: ')
            except EOFError as e:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Unable to input WordPress user email")

    Log.debug(self, "Setting up WordPress tables")

    if not data['multisite']:
        Log.debug(self, "Creating tables for WordPress Single site")
        Log.debug(self, "php /usr/bin/wp --allow-root core install "
                  "--url={0} --title={0} --admin_name={1} "
                  .format(data['www_domain'], ee_wp_user)
                  + "--admin_password= --admin_email={1}"
                  .format(ee_wp_pass, ee_wp_email))
        EEShellExec.cmd_exec(self, "php /usr/bin/wp --allow-root core install "
                             "--url={0} --title={0} --admin_name={1} "
                             .format(data['www_domain'], ee_wp_user)
                             + "--admin_password={0} --admin_email={1}"
                             .format(ee_wp_pass, ee_wp_email),
                             errormsg="Unable to setup WordPress Tables",
                             log=False)
    else:
        Log.debug(self, "Creating tables for WordPress multisite")
        Log.debug(self, "php /usr/bin/wp --allow-root "
                  "core multisite-install "
                  "--url={0} --title={0} --admin_name={1} "
                  .format(data['www_domain'], ee_wp_user)
                  + "--admin_password= --admin_email={1} "
                  "{subdomains}"
                  .format(ee_wp_pass, ee_wp_email,
                          subdomains='--subdomains'
                          if not data['wpsubdir'] else ''))
        EEShellExec.cmd_exec(self, "php /usr/bin/wp --allow-root "
                             "core multisite-install "
                             "--url={0} --title={0} --admin_name={1} "
                             .format(data['www_domain'], ee_wp_user)
                             + "--admin_password={0} --admin_email={1} "
                             "{subdomains}"
                             .format(ee_wp_pass, ee_wp_email,
                                     subdomains='--subdomains'
                                     if not data['wpsubdir'] else ''),
                             errormsg="Unable to setup WordPress Tables")

    Log.debug(self, "Updating WordPress permalink")
    EEShellExec.cmd_exec(self, " php /usr/bin/wp --allow-root "
                         "rewrite structure "
                         "/%year%/%monthnum%/%day%/%postname%/",
                         errormsg="Unable to Update WordPress permalink")

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
    EEShellExec.cmd_exec(self, 'wp --allow-root core multisite-convert'
                         ' --title={0} {subdomains}'
                         .format(data['www_domain'], subdomains='--subdomains'
                                 if not data['wpsubdir'] else ''))
    Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")


def installwp_plugin(self, plugin_name, data):
    ee_site_webroot = data['webroot']
    Log.debug(self, "Installing plugin {0}".format(plugin_name))
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    EEShellExec.cmd_exec(self, "php /usr/bin/wp plugin --allow-root install "
                         "{0}".format(plugin_name),
                         errormsg="Unable to Install plugin {0}"
                         .format(plugin_name))

    EEShellExec.cmd_exec(self, "php /usr/bin/wp plugin --allow-root activate "
                         "{0} {na}"
                         .format(plugin_name,
                                 na='--network' if data['multisite'] else ''),
                         errormsg="Unable to Activate plugin {0}"
                         .format(plugin_name))


def uninstallwp_plugin(self, plugin_name, data):
    ee_site_webroot = data['webroot']
    Log.debug(self, "Uninstalling plugin {0}".format(plugin_name))
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    EEShellExec.cmd_exec(self, "php /usr/bin/wp plugin --allow-root uninstall "
                         "{0}".format(plugin_name),
                         errormsg="Unable to UnInstall plugin {0}"
                         .format(plugin_name))


def setwebrootpermissions(self, webroot):
    Log.debug(self, "Setting up permissions")
    EEFileUtils.chown(self, webroot, EEVariables.ee_php_user,
                      EEVariables.ee_php_user, recursive=True)


def sitebackup(self, data):
    ee_site_webroot = data['webroot']
    backup_path = ee_site_webroot + '/backup/{0}'.format(EEVariables.ee_date)
    if not EEFileUtils.isexist(self, backup_path):
        EEFileUtils.mkdir(self, backup_path)
    Log.info(self, "Backup location : {0}".format(backup_path))
    EEFileUtils.copyfile(self, '/etc/nginx/sites-available/{0}'
                         .format(data['site_name']), backup_path)

    if data['currsitetype'] in ['html', 'php', 'mysql']:
        Log.info(self, "Backing up Webroot \t\t", end='')
        EEFileUtils.mvfile(self, ee_site_webroot + '/htdocs', backup_path)
        Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")

    configfiles = glob.glob(ee_site_webroot + '/*-config.php')

    if configfiles and EEFileUtils.isexist(self, configfiles[0]):
        ee_db_name = (EEFileUtils.grep(self, configfiles[0],
                      'DB_NAME').split(',')[1]
                      .split(')')[0].strip().replace('\'', ''))
        Log.info(self, 'Backing up database \t\t', end='')
        EEShellExec.cmd_exec(self, "mysqldump {0} > {1}/{0}.sql"
                             .format(ee_db_name, backup_path),
                             errormsg="\nFailed: Backup Database")
        Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")
        # move wp-config.php/ee-config.php to backup
        if data['currsitetype'] in ['mysql']:
            EEFileUtils.mvfile(self, configfiles[0], backup_path)
        else:
            EEFileUtils.copyfile(self, configfiles[0], backup_path)


def site_package_check(self, stype):
    apt_packages = []
    packages = []
    stack = EEStackController()
    stack.app = self.app
    if stype in ['html', 'php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
        Log.debug(self, "Setting apt_packages variable for Nginx")
        if not EEAptGet.is_installed(self, 'nginx-common'):
            apt_packages = apt_packages + EEVariables.ee_nginx

    if stype in ['php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
        Log.debug(self, "Setting apt_packages variable for PHP")
        if not EEAptGet.is_installed(self, 'php5-fpm'):
            apt_packages = apt_packages + EEVariables.ee_php

    if stype in ['mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
        Log.debug(self, "Setting apt_packages variable for MySQL")
        if not EEShellExec.cmd_exec(self, "mysqladmin ping"):
            apt_packages = apt_packages + EEVariables.ee_mysql

    if stype in ['php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
        Log.debug(self, "Setting apt_packages variable for Postfix")
        if not EEAptGet.is_installed(self, 'postfix'):
            apt_packages = apt_packages + EEVariables.ee_postfix

    if stype in ['wp', 'wpsubdir', 'wpsubdomain']:
        Log.debug(self, "Setting packages variable for WP-CLI")
        if not EEShellExec.cmd_exec(self, "which wp"):
            packages = packages + [["https://github.com/wp-cli/wp-cli/"
                                    "releases/download/v0.17.1/"
                                    "wp-cli.phar", "/usr/bin/wp",
                                    "WP-CLI"]]
    return(stack.install(apt_packages=apt_packages, packages=packages,
                         disp_msg=False))


def updatewpuserpassword(self, ee_domain, ee_site_webroot):

    ee_wp_user = ''
    ee_wp_pass = ''
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))

    # Check if ee_domain is wordpress install
    is_wp = EEShellExec.cmd_exec(self, "wp --allow-root core"
                                 " version",
                                 errormsg="{0} : Unable to check if wp install"
                                 .format(ee_domain))

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
        EEShellExec.cmd_exec(self, "wp --allow-root user list "
                             "--fields=user_login | grep -v user_login",
                             errormsg="Unable to Fetch users list")

    if not ee_wp_user:
        ee_wp_user = 'admin'

    is_user_exist = EEShellExec.cmd_exec(self, "wp --allow-root user list "
                                         "--fields=user_login | grep {0}$ "
                                         .format(ee_wp_user))

    if is_user_exist:
        try:
            ee_wp_pass = getpass.getpass(prompt="Provide password for "
                                         "{0} user: "
                                         .format(ee_wp_user))
        except Exception as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Could not update password")

        if len(ee_wp_pass) > 8:
            EEShellExec.cmd_exec(self, "wp --allow-root user update {0}"
                                 "  --user_pass={1}"
                                 .format(ee_wp_user, ee_wp_pass))
            Log.info(self, "Password updated successfully")
        else:
            Log.error(self, "Password Unchanged. Hint : Your password must be "
                      "8 characters long")
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
