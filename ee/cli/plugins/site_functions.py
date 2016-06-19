from ee.cli.plugins.stack import EEStackController
from ee.core.fileutils import EEFileUtils
from ee.core.mysql import *
from ee.core.shellexec import *
from ee.core.sslutils import SSL
from ee.core.variables import EEVariables
from ee.cli.plugins.sitedb import *
from ee.core.aptget import EEAptGet
from ee.core.git import EEGit
from ee.core.logging import Log
from ee.core.sendmail import EESendMail
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
import platform


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

    #for debug purpose
   # for key, value in data.items() :
   #     print (key, value)

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
        if not data['php7']:
            self.app.render((data), 'virtualconf.mustache',
                          out=ee_site_nginx_conf)
        else:
            self.app.render((data), 'virtualconf-php7.mustache',
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


    # create symbolic link for
    EEFileUtils.create_symlink(self, ['/etc/nginx/sites-available/{0}'
                                      .format(ee_domain_name),
                                      '/etc/nginx/sites-enabled/{0}'
                                      .format(ee_domain_name)])

    if 'proxy' in data.keys() and data['proxy']:
        return

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
    data['ee_mysql_grant_host'] = ee_mysql_grant_host
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

    Log.info(self, "Downloading WordPress \t\t", end='')
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    try:
        if EEShellExec.cmd_exec(self, "wp --allow-root core"
                             " download"):
            pass
        else:
            Log.info(self, "[" + Log.ENDC + Log.FAIL + "Fail" + Log.OKBLUE + "]")
            raise SiteError("download WordPress core failed")
    except CommandExecutionError as e:
        Log.info(self, "[" + Log.ENDC + Log.FAIL + "Fail" + Log.OKBLUE + "]")
        raise SiteError(self, "download WordPress core failed")

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
        Log.debug(self, "bash -c \"php {0} --allow-root "
                  .format(EEVariables.ee_wpcli_path)
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
            if EEShellExec.cmd_exec(self, "bash -c \"php {0} --allow-root"
                                 .format(EEVariables.ee_wpcli_path)
                                 + " core config "
                                 + "--dbname=\'{0}\' --dbprefix=\'{1}\' "
                                 "--dbuser=\'{2}\' --dbhost=\'{3}\' "
                                 .format(data['ee_db_name'], ee_wp_prefix,
                                         data['ee_db_user'], data['ee_db_host']
                                         )
                                 + "--dbpass=\'{0}\' "
                                   "--extra-php<<PHP \n {1} {redissalt}\nPHP\""
                                   .format(data['ee_db_pass'],
                                           "\n\ndefine(\'WP_DEBUG\', false);",
                                           redissalt="\n\ndefine( \'WP_CACHE_KEY_SALT\', \'{0}:\' );"
                                                      .format(ee_domain_name) if data['wpredis']
                                                      else ''),
                                   log=False
                                 ):
                pass
            else :
                raise SiteError("generate wp-config failed for wp single site")
        except CommandExecutionError as e:
                raise SiteError("generate wp-config failed for wp single site")
    else:
        Log.debug(self, "Generating wp-config for WordPress multisite")
        Log.debug(self, "bash -c \"php {0} --allow-root "
                  .format(EEVariables.ee_wpcli_path)
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
            if EEShellExec.cmd_exec(self, "bash -c \"php {0} --allow-root"
                                 .format(EEVariables.ee_wpcli_path)
                                 + " core config "
                                 + "--dbname=\'{0}\' --dbprefix=\'{1}\' "
                                 "--dbhost=\'{2}\' "
                                 .format(data['ee_db_name'], ee_wp_prefix,
                                         data['ee_db_host'])
                                 + "--dbuser=\'{0}\' --dbpass=\'{1}\' "
                                   "--extra-php<<PHP \n {2} {3} {4} {redissalt}\nPHP\""
                                 .format(data['ee_db_user'],
                                         data['ee_db_pass'],
                                         "\ndefine(\'WP_ALLOW_MULTISITE\', "
                                         "true);",
                                         "\ndefine(\'WPMU_ACCEL_REDIRECT\',"
                                         " true);",
                                         "\n\ndefine(\'WP_DEBUG\', false);",
                                         redissalt="\n\ndefine( \'WP_CACHE_KEY_SALT\', \'{0}:\' );"
                                                      .format(ee_domain_name) if data['wpredis']
                                                      else ''),
                                 log=False
                                 ):
                pass
            else:
                raise SiteError("generate wp-config failed for wp multi site")
        except CommandExecutionError as e:
                raise SiteError("generate wp-config failed for wp multi site")

    #EEFileUtils.mvfile(self, os.getcwd()+'/wp-config.php',
    #                   os.path.abspath(os.path.join(os.getcwd(), os.pardir)))

    try:
        import shutil

        Log.debug(self, "Moving file from {0} to {1}".format(os.getcwd()+'/wp-config.php',os.path.abspath(os.path.join(os.getcwd(), os.pardir))))
        shutil.move(os.getcwd()+'/wp-config.php',os.path.abspath(os.path.join(os.getcwd(), os.pardir)))
    except Exception as e:
        Log.error(self, 'Unable to move file from {0} to {1}'
                      .format(os.getcwd()+'/wp-config.php', os.path.abspath(os.path.join(os.getcwd(), os.pardir))),False)
        raise SiteError("Unable to move wp-config.php")


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
                raise SiteError("input WordPress username failed")
    if not ee_wp_pass:
        ee_wp_pass = ee_random

    if not ee_wp_email:
        ee_wp_email = EEVariables.ee_email
        while not ee_wp_email:
            try:
                ee_wp_email = input('Enter WordPress email: ')
            except EOFError as e:
                Log.debug(self, "{0}".format(e))
                raise SiteError("input WordPress username failed")

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
        Log.debug(self, "php {0} --allow-root core install "
                  .format(EEVariables.ee_wpcli_path)
                  + "--url=\'{0}\' --title=\'{0}\' --admin_name=\'{1}\' "
                  .format(data['www_domain'], ee_wp_user)
                  + "--admin_password= --admin_email=\'{1}\'"
                  .format(ee_wp_pass, ee_wp_email))
        try:
            if EEShellExec.cmd_exec(self, "php {0} --allow-root core "
                                 .format(EEVariables.ee_wpcli_path)
                                 + "install --url=\'{0}\' --title=\'{0}\' "
                                 "--admin_name=\'{1}\' "
                                 .format(data['www_domain'], ee_wp_user)
                                 + "--admin_password=\'{0}\' "
                                 "--admin_email=\'{1}\'"
                                 .format(ee_wp_pass, ee_wp_email),
                                 log=False):
                pass
            else:
                raise SiteError("setup WordPress tables failed for single site")
        except CommandExecutionError as e:
            raise SiteError("setup WordPress tables failed for single site")
    else:
        Log.debug(self, "Creating tables for WordPress multisite")
        Log.debug(self, "php {0} --allow-root "
                  .format(EEVariables.ee_wpcli_path)
                  + "core multisite-install "
                  "--url=\'{0}\' --title=\'{0}\' --admin_name=\'{1}\' "
                  .format(data['www_domain'], ee_wp_user)
                  + "--admin_password= --admin_email=\'{1}\' "
                  "{subdomains}"
                  .format(ee_wp_pass, ee_wp_email,
                          subdomains='--subdomains'
                          if not data['wpsubdir'] else ''))
        try:
            if EEShellExec.cmd_exec(self, "php {0} --allow-root "
                                 .format(EEVariables.ee_wpcli_path)
                                 + "core multisite-install "
                                 "--url=\'{0}\' --title=\'{0}\' "
                                 "--admin_name=\'{1}\' "
                                 .format(data['www_domain'], ee_wp_user)
                                 + "--admin_password=\'{0}\' "
                                 "--admin_email=\'{1}\' "
                                 "{subdomains}"
                                 .format(ee_wp_pass, ee_wp_email,
                                         subdomains='--subdomains'
                                         if not data['wpsubdir'] else ''),
                                 log=False):
                pass
            else:
                raise SiteError("setup WordPress tables failed for wp multi site")
        except CommandExecutionError as e:
            raise SiteError("setup WordPress tables failed for wp multi site")

    Log.debug(self, "Updating WordPress permalink")
    try:
        EEShellExec.cmd_exec(self, " php {0} --allow-root "
                             .format(EEVariables.ee_wpcli_path)
                             + "rewrite structure "
                             "/%year%/%monthnum%/%day%/%postname%/")
    except CommandExecutionError as e:
        raise SiteError("Update wordpress permalinks failed")

    """Install nginx-helper plugin """
    installwp_plugin(self, 'nginx-helper', data)
    if data['wpfc']:
        plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":1,"enable_map":0,"enable_log":0,"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,"purge_homepage_on_del":1,"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,"purge_archive_on_new_comment":0,"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,"purge_page_on_new_comment":1,"purge_page_on_deleted_comment":1,"cache_method":"enable_fastcgi","purge_method":"get_request","redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}'
        setupwp_plugin(self, 'nginx-helper', 'rt_wp_nginx_helper_options', plugin_data, data)
    elif data['wpredis']:
        plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":1,"enable_map":0,"enable_log":0,"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,"purge_homepage_on_del":1,"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,"purge_archive_on_new_comment":0,"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,"purge_page_on_new_comment":1,"purge_page_on_deleted_comment":1,"cache_method":"enable_redis","purge_method":"get_request","redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}'
        setupwp_plugin(self, 'nginx-helper', 'rt_wp_nginx_helper_options', plugin_data, data)

    """Install Wp Super Cache"""
    if data['wpsc']:
        installwp_plugin(self, 'wp-super-cache', data)

    """Install Redis Cache"""
    if data['wpredis']:
        installwp_plugin(self, 'redis-cache', data)

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
        if EEShellExec.cmd_exec(self, 'wp --allow-root core multisite-convert'
                             ' --title=\'{0}\' {subdomains}'
                             .format(data['www_domain'],
                                     subdomains='--subdomains'
                                     if not data['wpsubdir'] else '')):
            pass
        else:
            Log.info(self, "[" + Log.ENDC + Log.FAIL + "Fail" + Log.OKBLUE + "]")
            raise SiteError("setup WordPress network failed")

    except CommandExecutionError as e:
        Log.info(self, "[" + Log.ENDC + Log.FAIL + "Fail" + Log.OKBLUE + "]")
        raise SiteError("setup WordPress network failed")
    Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")


def installwp_plugin(self, plugin_name, data):
    ee_site_webroot = data['webroot']
    Log.info(self, "Installing plugin {0}, please wait..."
             .format(plugin_name))
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    try:
        EEShellExec.cmd_exec(self, "php {0} plugin "
                             .format(EEVariables.ee_wpcli_path)
                             + "--allow-root install "
                             "{0}".format(plugin_name))
    except CommandExecutionError as e:
        raise SiteError("plugin installation failed")

    try:
        EEShellExec.cmd_exec(self, "php {0} plugin "
                             .format(EEVariables.ee_wpcli_path)
                             + "--allow-root activate "
                             "{0} {na}"
                             .format(plugin_name,
                                     na='--network' if data['multisite']
                                     else ''
                                     ))
    except CommandExecutionError as e:
        raise SiteError("plugin activation failed")

    return 1


def uninstallwp_plugin(self, plugin_name, data):
    ee_site_webroot = data['webroot']
    Log.debug(self, "Uninstalling plugin {0}, please wait..."
              .format(plugin_name))
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    Log.info(self, "Uninstalling plugin {0}, please wait..."
             .format(plugin_name))
    try:
        EEShellExec.cmd_exec(self, "php {0} plugin "
                             .format(EEVariables.ee_wpcli_path)
                             + "--allow-root deactivate "
                             "{0}".format(plugin_name))

        EEShellExec.cmd_exec(self, "php {0} plugin "
                             .format(EEVariables.ee_wpcli_path)
                             + "--allow-root uninstall "
                             "{0}".format(plugin_name))
    except CommandExecutionError as e:
        raise SiteError("plugin uninstall failed")

def setupwp_plugin(self, plugin_name, plugin_option, plugin_data, data):
    ee_site_webroot = data['webroot']
    Log.info(self, "Setting plugin {0}, please wait..."
             .format(plugin_name))
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))

    if not data['multisite']:
        try:
            EEShellExec.cmd_exec(self, "php {0} "
                                 .format(EEVariables.ee_wpcli_path)
                                 + "--allow-root option update "
                                 "{0} \'{1}\' --format=json".format(plugin_option, plugin_data))
        except CommandExecutionError as e:
            raise SiteError("plugin setup failed")
    else:
        try:
            EEShellExec.cmd_exec(self, "php {0} "
                                 .format(EEVariables.ee_wpcli_path)
                                 + "--allow-root network meta update 1 "
                                  "{0} \'{1}\' --format=json"
                                 .format(plugin_option, plugin_data
                                         ))
        except CommandExecutionError as e:
            raise SiteError("plugin setup failed")


def setwebrootpermissions(self, webroot):
    Log.debug(self, "Setting up permissions")
    try:
        EEFileUtils.chown(self, webroot, EEVariables.ee_php_user,
                          EEVariables.ee_php_user, recursive=True)
    except Exception as e:
        Log.debug(self, str(e))
        raise SiteError("problem occured while setting up webroot permissions")


def sitebackup(self, data):
    ee_site_webroot = data['webroot']
    backup_path = ee_site_webroot + '/backup/{0}'.format(EEVariables.ee_date)
    if not EEFileUtils.isexist(self, backup_path):
        EEFileUtils.mkdir(self, backup_path)
    Log.info(self, "Backup location : {0}".format(backup_path))
    EEFileUtils.copyfile(self, '/etc/nginx/sites-available/{0}'
                         .format(data['site_name']), backup_path)

    if data['currsitetype'] in ['html', 'php', 'proxy', 'mysql']:
        if data['php7'] is True and not data['wp']:
            Log.info(self, "Backing up Webroot \t\t", end='')
            EEFileUtils.copyfiles(self, ee_site_webroot + '/htdocs', backup_path + '/htdocs')
            Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")
        else:
            Log.info(self, "Backing up Webroot \t\t", end='')
            EEFileUtils.mvfile(self, ee_site_webroot + '/htdocs', backup_path)
            Log.info(self, "[" + Log.ENDC + "Done" + Log.OKBLUE + "]")

    configfiles = glob.glob(ee_site_webroot + '/*-config.php')
    if not configfiles:
        #search for wp-config.php inside htdocs/
        Log.debug(self, "Config files not found in {0}/ "
                          .format(ee_site_webroot))
        if data['currsitetype'] in ['mysql']:
            pass
        else:
            Log.debug(self, "Searching wp-config.php in {0}/htdocs/ "
                                   .format(ee_site_webroot))
            configfiles = glob.glob(ee_site_webroot + '/htdocs/wp-config.php')

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
            if data['php7'] is True and not data['wp']:
                EEFileUtils.copyfile(self, configfiles[0], backup_path)
            else:
                EEFileUtils.mvfile(self, configfiles[0], backup_path)
        else:
            EEFileUtils.copyfile(self, configfiles[0], backup_path)


def site_package_check(self, stype):
    apt_packages = []
    packages = []
    stack = EEStackController()
    stack.app = self.app
    if stype in ['html', 'proxy', 'php', 'mysql', 'wp', 'wpsubdir',
                 'wpsubdomain', 'php7']:
        Log.debug(self, "Setting apt_packages variable for Nginx")

        # Check if server has nginx-custom package
        if not (EEAptGet.is_installed(self, 'nginx-custom') or  EEAptGet.is_installed(self, 'nginx-mainline')):
            # check if Server has nginx-plus installed
            if EEAptGet.is_installed(self, 'nginx-plus'):
                # do something
                # do post nginx installation configuration
                Log.info(self, "NGINX PLUS Detected ...")
                apt = ["nginx-plus"] + EEVariables.ee_nginx
                #apt_packages = apt_packages + EEVariables.ee_nginx
                stack.post_pref(apt, packages)
            elif EEAptGet.is_installed(self, 'nginx'):
                Log.info(self, "EasyEngine detected a previously installed Nginx package. "
                                "It may or may not have required modules. "
                                "\nIf you need help, please create an issue at https://github.com/EasyEngine/easyengine/issues/ \n")
                apt = ["nginx"] + EEVariables.ee_nginx
                #apt_packages = apt_packages + EEVariables.ee_nginx
                stack.post_pref(apt, packages)
            else:
                apt_packages = apt_packages + EEVariables.ee_nginx
        else:
            # Fix for Nginx white screen death
            if not EEFileUtils.grep(self, '/etc/nginx/fastcgi_params',
                                    'SCRIPT_FILENAME'):
                with open('/etc/nginx/fastcgi_params', encoding='utf-8',
                          mode='a') as ee_nginx:
                    ee_nginx.write('fastcgi_param \tSCRIPT_FILENAME '
                                   '\t$request_filename;\n')

    if self.app.pargs.php and self.app.pargs.php7:
        Log.error(self,"INVALID OPTION: PHP 7.0 provided with PHP 5.0")

    if not self.app.pargs.php7 and stype in ['php', 'mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
        Log.debug(self, "Setting apt_packages variable for PHP")
        if (EEVariables.ee_platform_codename == 'trusty' or EEVariables.ee_platform_codename == 'xenial'):
            if not EEAptGet.is_installed(self, 'php5.6-fpm'):
                apt_packages = apt_packages + EEVariables.ee_php5_6 + EEVariables.ee_php_extra
        else:
            if not EEAptGet.is_installed(self, 'php5-fpm'):
                apt_packages = apt_packages + EEVariables.ee_php

    if self.app.pargs.php7 and stype in [ 'mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
        if (EEVariables.ee_platform_codename == 'trusty' or EEVariables.ee_platform_codename == 'xenial'):
            Log.debug(self, "Setting apt_packages variable for PHP 5.6")
            if not EEAptGet.is_installed(self, 'php5.6-fpm'):
                apt_packages = apt_packages + EEVariables.ee_php5_6
            Log.debug(self, "Setting apt_packages variable for PHP 7.0")
            if not EEAptGet.is_installed(self, 'php7.0-fpm'):
                apt_packages = apt_packages + EEVariables.ee_php7_0 + EEVariables.ee_php_extra
        else:
            Log.warn(self, "PHP 7.0 not available for your system.")
            Log.info(self,"Creating site with PHP 5.6")
            if not EEAptGet.is_installed(self, 'php5-fpm'):
                Log.info(self, "Setting apt_packages variable for PHP")
                Log.debug(self, "Setting apt_packages variable for PHP")
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
    if self.app.pargs.wpredis:
        Log.debug(self, "Setting apt_packages variable for redis")
        if not EEAptGet.is_installed(self, 'redis-server'):
            apt_packages = apt_packages + EEVariables.ee_redis

        if os.path.isfile("/etc/nginx/nginx.conf") and (not
           os.path.isfile("/etc/nginx/common/redis.conf")):

            data = dict()
            Log.debug(self, 'Writting the nginx configuration to '
                      'file /etc/nginx/common/redis.conf')
            ee_nginx = open('/etc/nginx/common/redis.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'redis.mustache',
                            out=ee_nginx)
            ee_nginx.close()

        if os.path.isfile("/etc/nginx/nginx.conf") and (not
           os.path.isfile("/etc/nginx/common/redis-hhvm.conf")):

            data = dict()
            Log.debug(self, 'Writting the nginx configuration to '
                      'file /etc/nginx/common/redis-hhvm.conf')
            ee_nginx = open('/etc/nginx/common/redis-hhvm.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'redis-hhvm.mustache',
                            out=ee_nginx)
            ee_nginx.close()

        if os.path.isfile("/etc/nginx/conf.d/upstream.conf"):
            if not EEFileUtils.grep(self, "/etc/nginx/conf.d/"
                                    "upstream.conf",
                                    "redis"):
                with open("/etc/nginx/conf.d/upstream.conf",
                          "a") as redis_file:
                    redis_file.write("upstream redis {\n"
                                     "    server 127.0.0.1:6379;\n"
                                     "    keepalive 10;\n}")

        if os.path.isfile("/etc/nginx/nginx.conf") and (not
           os.path.isfile("/etc/nginx/conf.d/redis.conf")):
            with open("/etc/nginx/conf.d/redis.conf", "a") as redis_file:
                redis_file.write("# Log format Settings\n"
                                 "log_format rt_cache_redis '$remote_addr $upstream_response_time $srcache_fetch_status [$time_local] '\n"
                                 "'$http_host \"$request\" $status $body_bytes_sent '\n"
                                 "'\"$http_referer\" \"$http_user_agent\"';\n")

    if self.app.pargs.hhvm:
        if platform.architecture()[0] is '32bit':
            Log.error(self, "HHVM is not supported by 32bit system")
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

    if self.app.pargs.php7:
        if (EEVariables.ee_platform_distro == 'debian' or EEVariables.ee_platform_codename == 'precise'):
            Log.error(self,"PHP 7.0 is not supported in your Platform")

        Log.debug(self, "Setting apt_packages variable for PHP 7.0")
        if not EEAptGet.is_installed(self, 'php7.0-fpm'):
            apt_packages = apt_packages + EEVariables.ee_php7_0 + EEVariables.ee_php_extra

        if os.path.isdir("/etc/nginx/common") and (not
           os.path.isfile("/etc/nginx/common/php7.conf")):
            data = dict()
            Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/locations-php7.conf')
            ee_nginx = open('/etc/nginx/common/locations-php7.conf',
                                    encoding='utf-8', mode='w')
            self.app.render((data), 'locations-php7.mustache',
                                    out=ee_nginx)
            ee_nginx.close()

            Log.debug(self, 'Writting the nginx configuration to '
                      'file /etc/nginx/common/php7.conf')
            ee_nginx = open('/etc/nginx/common/php7.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'php7.mustache',
                            out=ee_nginx)
            ee_nginx.close()

            Log.debug(self, 'Writting the nginx configuration to '
                      'file /etc/nginx/common/w3tc-php7.conf')
            ee_nginx = open('/etc/nginx/common/w3tc-php7.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'w3tc-php7.mustache', out=ee_nginx)
            ee_nginx.close()

            Log.debug(self, 'Writting the nginx configuration to '
                                'file /etc/nginx/common/wpcommon-php7.conf')
            ee_nginx = open('/etc/nginx/common/wpcommon-php7.conf',
                                    encoding='utf-8', mode='w')
            self.app.render((data), 'wpcommon-php7.mustache',
                                    out=ee_nginx)
            ee_nginx.close()

            Log.debug(self, 'Writting the nginx configuration to '
                      'file /etc/nginx/common/wpfc-php7.conf')
            ee_nginx = open('/etc/nginx/common/wpfc-php7.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'wpfc-php7.mustache',
                            out=ee_nginx)
            ee_nginx.close()

            Log.debug(self, 'Writting the nginx configuration to '
                      'file /etc/nginx/common/wpsc-php7.conf')
            ee_nginx = open('/etc/nginx/common/wpsc-php7.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'wpsc-php7.mustache',
                            out=ee_nginx)
            ee_nginx.close()

        if os.path.isfile("/etc/nginx/nginx.conf") and (not
            os.path.isfile("/etc/nginx/common/redis-php7.conf")):
            data = dict()
            Log.debug(self, 'Writting the nginx configuration to '
                     'file /etc/nginx/common/redis-php7.conf')
            ee_nginx = open('/etc/nginx/common/redis-php7.conf',
                            encoding='utf-8', mode='w')
            self.app.render((data), 'redis-php7.mustache',
                            out=ee_nginx)
            ee_nginx.close()

        if os.path.isfile("/etc/nginx/conf.d/upstream.conf"):
            if not EEFileUtils.grep(self, "/etc/nginx/conf.d/upstream.conf",
                                          "php7"):
                with open("/etc/nginx/conf.d/upstream.conf", "a") as php_file:
                    php_file.write("upstream php7 {\nserver 127.0.0.1:9070;\n}\n"
                                    "upstream debug7 {\nserver 127.0.0.1:9170;\n}\n")


    # Check if Nginx is allready installed and Pagespeed config there or not
    # If not then copy pagespeed config
#    if self.app.pargs.pagespeed:
#        if (os.path.isfile('/etc/nginx/nginx.conf') and
#           (not os.path.isfile('/etc/nginx/conf.d/pagespeed.conf'))):
            # Pagespeed configuration
#            data = dict()
#            Log.debug(self, 'Writting the Pagespeed Global '
#                      'configuration to file /etc/nginx/conf.d/'
#                      'pagespeed.conf')
#            ee_nginx = open('/etc/nginx/conf.d/pagespeed.conf',
#                            encoding='utf-8', mode='w')
#            self.app.render((data), 'pagespeed-global.mustache',
#                            out=ee_nginx)
#            ee_nginx.close()

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
        raise SiteError("is WordPress site? check command failed ")

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

            while not ee_wp_pass:
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

    if data['wpredis']:
        if data['multisite']:
            Log.info(self, "Configure redis-cache:"
                     "\thttp://{0}/wp-admin/network/settings.php?"
                     "page=redis-cache".format(data['site_name']))
        else:
            Log.info(self, "Configure redis-cache:"
                     "\thttp://{0}/wp-admin/options-general.php?"
                     "page=redis-cache".format(data['site_name']))
        Log.info(self, "Object Cache:\t\tEnable")

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
                           'wpsubdir', 'wpsubdomain','php7']:
            typelist.append(key)
        elif val and key in ['wpfc', 'wpsc', 'w3tc', 'wpredis']:
            cachelist.append(key)

    if len(typelist) > 1 or len(cachelist) > 1:
        if len(cachelist) > 1:
            raise RuntimeError("Could not determine cache type.Multiple cache parameter entered")
        elif False not in [x in ('php','mysql','html') for x in typelist]:
            sitetype = 'mysql'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('php7','mysql','html') for x in typelist]:
            sitetype = 'mysql'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('php','mysql') for x in typelist]:
            sitetype = 'mysql'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('php7','mysql') for x in typelist]:
            sitetype = 'mysql'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('html','mysql') for x in typelist]:
            sitetype = 'mysql'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('php','html') for x in typelist]:
            sitetype = 'php'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('php7','html') for x in typelist]:
            sitetype = 'php7'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('wp','wpsubdir') for x in typelist]:
            sitetype = 'wpsubdir'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('wp','wpsubdomain') for x in typelist]:
            sitetype = 'wpsubdomain'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('wp','php7') for x in typelist]:
            sitetype = 'wp'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('wpsubdir','php7') for x in typelist]:
            sitetype = 'wpsubdir'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        elif False not in [x in ('wpsubdomain','php7') for x in typelist]:
            sitetype = 'wpsubdomain'
            if not cachelist:
                cachetype = 'basic'
            else:
                cachetype = cachelist[0]
        else:
            raise RuntimeError("could not determine site and cache type")
    else:
        if not typelist and not cachelist:
            sitetype = None
            cachetype = None
        elif (not typelist or "php7" in typelist) and cachelist:
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


def deleteDB(self, dbname, dbuser, dbhost, exit=True):
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
        Log.error(self, "Error occured while deleting database", exit)


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


#def operateOnPagespeed(self, data):

#    ee_domain_name = data['site_name']
#    ee_site_webroot = data['webroot']

#    if data['pagespeed'] is True:
#        if not os.path.isfile("{0}/conf/nginx/pagespeed.conf.disabled"
#                              .format(ee_site_webroot)):
#            Log.debug(self, 'Writting the Pagespeed common '
#                      'configuration to file {0}/conf/nginx/pagespeed.conf'
#                      'pagespeed.conf'.format(ee_site_webroot))
#            ee_nginx = open('{0}/conf/nginx/pagespeed.conf'
#                            .format(ee_site_webroot), encoding='utf-8',
#                            mode='w')
#            self.app.render((data), 'pagespeed-common.mustache',
#                            out=ee_nginx)
#            ee_nginx.close()
#        else:
#            EEFileUtils.mvfile(self, "{0}/conf/nginx/pagespeed.conf.disabled"
#                               .format(ee_site_webroot),
#                               '{0}/conf/nginx/pagespeed.conf'
#                               .format(ee_site_webroot))

#    elif data['pagespeed'] is False:
#        if os.path.isfile("{0}/conf/nginx/pagespeed.conf"
#                          .format(ee_site_webroot)):
#            EEFileUtils.mvfile(self, "{0}/conf/nginx/pagespeed.conf"
#                               .format(ee_site_webroot),
#                               '{0}/conf/nginx/pagespeed.conf.disabled'
#                               .format(ee_site_webroot))
#
#    # Add nginx conf folder into GIT
#    EEGit.add(self, ["{0}/conf/nginx".format(ee_site_webroot)],
#              msg="Adding Pagespeed config of site: {0}"
#              .format(ee_domain_name))

def cloneLetsEncrypt(self):
    letsencrypt_repo = "https://github.com/letsencrypt/letsencrypt"
    if not os.path.isdir("/opt"):
        EEFileUtils.mkdir(self,"/opt")
    try:
        Log.info(self, "Downloading {0:20}".format("LetsEncrypt"), end=' ')
        EEFileUtils.chdir(self, '/opt/')
        EEShellExec.cmd_exec(self, "git clone {0}".format(letsencrypt_repo))
        Log.info(self, "{0}".format("[" + Log.ENDC + "Done"
                                            + Log.OKBLUE + "]"))
        return True
    except Exception as e:
        Log.debug(self, "[{err}]".format(err=str(e.reason)))
        Log.error(self, "Unable to download file, LetsEncrypt")
        return False

def setupLetsEncrypt(self, ee_domain_name):
    ee_wp_email = EEVariables.ee_email
    while not ee_wp_email:
        try:
            ee_wp_email = input('Enter WordPress email: ')
        except EOFError as e:
            Log.debug(self, "{0}".format(e))
            raise SiteError("input WordPress username failed")

    if not os.path.isdir("/opt/letsencrypt"):
        cloneLetsEncrypt(self)
    EEFileUtils.chdir(self, '/opt/letsencrypt')
    EEShellExec.cmd_exec(self, "git pull")

    if os.path.isfile("/etc/letsencrypt/renewal/{0}.conf".format(ee_domain_name)):
        Log.debug(self, "LetsEncrypt SSL Certificate found for the domain {0}"
                 .format(ee_domain_name))
        ssl= archivedCertificateHandle(self,ee_domain_name,ee_wp_email)
    else:
        Log.warn(self,"Please Wait while we fetch SSL Certificate for your site.\nIt may take time depending upon network.")
        ssl = EEShellExec.cmd_exec(self, "./letsencrypt-auto certonly --webroot -w /var/www/{0}/htdocs/ -d {0} -d www.{0} "
                                .format(ee_domain_name)
                                + "--email {0} --text --agree-tos".format(ee_wp_email))
    if ssl:
        Log.info(self, "Let's Encrypt successfully setup for your site")
        Log.info(self, "Your certificate and chain have been saved at "
                            "/etc/letsencrypt/live/{0}/fullchain.pem".format(ee_domain_name))
        Log.info(self, "Configuring Nginx SSL configuration")

        try:
            Log.info(self, "Adding /var/www/{0}/conf/nginx/ssl.conf".format(ee_domain_name))

            sslconf = open("/var/www/{0}/conf/nginx/ssl.conf"
                                      .format(ee_domain_name),
                                      encoding='utf-8', mode='w')
            sslconf.write("listen 443 ssl http2;\n"
                                     "ssl on;\n"
                                     "ssl_certificate     /etc/letsencrypt/live/{0}/fullchain.pem;\n"
                                     "ssl_certificate_key     /etc/letsencrypt/live/{0}/privkey.pem;\n"
                                     "ssl_protocols TLSv1.2 TLSv1.1 TLSv1;\n"
                                     "ssl_ciphers EECDH+AESGCM:EECDH+AES;\n"
                                     "ssl_ecdh_curve secp384r1;\n"
                                     .format(ee_domain_name))
            sslconf.close()
            # updateSiteInfo(self, ee_domain_name, ssl=True)

            EEGit.add(self, ["/etc/letsencrypt"],
              msg="Adding letsencrypt folder")

        except IOError as e:
            Log.debug(self, str(e))
            Log.debug(self, "Error occured while generating "
                              "ssl.conf")
    else:
        Log.error(self, "Unable to setup, Let\'s Encrypt", False)
        Log.error(self, "Please make sure that your site is pointed to \n"
                        "same server on which you are running Let\'s Encrypt Client "
                        "\n to allow it to verify the site automatically.")

def renewLetsEncrypt(self, ee_domain_name):

    ee_wp_email = EEVariables.ee_email
    while not ee_wp_email:
        try:
            ee_wp_email = input('Enter email address: ')
        except EOFError as e:
            Log.debug(self, "{0}".format(e))
            raise SiteError("Input WordPress email failed")

    if not os.path.isdir("/opt/letsencrypt"):
        cloneLetsEncrypt(self)
    EEFileUtils.chdir(self, '/opt/letsencrypt')
    EEShellExec.cmd_exec(self, "git pull")

    Log.info(self, "Renewing SSl cert for https://{0}".format(ee_domain_name))

    ssl = EEShellExec.cmd_exec(self, "./letsencrypt-auto --renew-by-default certonly --webroot -w /var/www/{0}/htdocs/ -d {0} -d www.{0} "
                                .format(ee_domain_name)
                                + "--email {0} --text --agree-tos".format(ee_wp_email))
    mail_list = ''
    if not ssl:
        Log.error(self,"ERROR : Cannot RENEW SSL cert !",False)
        if (SSL.getExpirationDays(self,ee_domain_name)>0):
                    Log.error(self, "Your current cert will expire within " + str(SSL.getExpirationDays(self,ee_domain_name)) + " days.",False)
        else:
                    Log.error(self, "Your current cert already EXPIRED !",False)

        EESendMail("easyengine@{0}".format(ee_domain_name), ee_wp_email, "[FAIL] SSL cert renewal {0}".format(ee_domain_name),
                       "Hey Hi,\n\nSSL Certificate renewal for https://{0} was unsuccessful.".format(ee_domain_name) +
                       "\nPlease check easyengine log for reason. Your SSL Expiry date : " +
                            str(SSL.getExpirationDate(self,ee_domain_name)) +
                       "\n\nFor support visit https://easyengine.io/support/ .\n\nYour's faithfully,\nEasyEngine",files=mail_list,
                        port=25, isTls=False)
        Log.error(self, "Check logs for reason "
                      "`tail /var/log/ee/ee.log` & Try Again!!!")

    EEGit.add(self, ["/etc/letsencrypt"],
              msg="Adding letsencrypt folder")
    EESendMail("easyengine@{0}".format(ee_domain_name), ee_wp_email, "[SUCCESS] SSL cert renewal {0}".format(ee_domain_name),
                       "Hey Hi,\n\nYour SSL Certificate has been renewed for https://{0} .".format(ee_domain_name) +
                       "\nYour SSL will Expire on : " +
                            str(SSL.getExpirationDate(self,ee_domain_name)) +
                       "\n\nYour's faithfully,\nEasyEngine",files=mail_list,
                        port=25, isTls=False)

#redirect= False to disable https redirection
def httpsRedirect(self,ee_domain_name,redirect=True):
    if redirect:
        if os.path.isfile("/etc/nginx/conf.d/force-ssl-{0}.conf.disabled".format(ee_domain_name)):
                EEFileUtils.mvfile(self, "/etc/nginx/conf.d/force-ssl-{0}.conf.disabled".format(ee_domain_name),
                                  "/etc/nginx/conf.d/force-ssl-{0}.conf".format(ee_domain_name))
        else:
            try:
                Log.info(self, "Adding /etc/nginx/conf.d/force-ssl-{0}.conf".format(ee_domain_name))

                sslconf = open("/etc/nginx/conf.d/force-ssl-{0}.conf"
                                      .format(ee_domain_name),
                                      encoding='utf-8', mode='w')
                sslconf.write("server {\n"
                                     "\tlisten 80;\n" +
                                     "\tserver_name www.{0} {0};\n".format(ee_domain_name) +
                                     "\treturn 301 https://{0}".format(ee_domain_name)+"$request_uri;\n}" )
                sslconf.close()
                # Nginx Configation into GIT
            except IOError as e:
                Log.debug(self, str(e))
                Log.debug(self, "Error occured while generating "
                              "/etc/nginx/conf.d/force-ssl-{0}.conf".format(ee_domain_name))

        Log.info(self, "Added HTTPS Force Redirection for Site "
                         " http://{0}".format(ee_domain_name))
        EEGit.add(self,
                  ["/etc/nginx"], msg="Adding /etc/nginx/conf.d/force-ssl-{0}.conf".format(ee_domain_name))
    else:
        if os.path.isfile("/etc/nginx/conf.d/force-ssl-{0}.conf".format(ee_domain_name)):
             EEFileUtils.mvfile(self, "/etc/nginx/conf.d/force-ssl-{0}.conf".format(ee_domain_name),
                                  "/etc/nginx/conf.d/force-ssl-{0}.conf.disabled".format(ee_domain_name))
             Log.info(self, "Disabled HTTPS Force Redirection for Site "
                         " http://{0}".format(ee_domain_name))

def archivedCertificateHandle(self,domain,ee_wp_email):
    Log.warn(self,"You already have an existing certificate for the domain requested.\n"
                        "(ref: /etc/letsencrypt/renewal/{0}.conf)".format(domain) +
                        "\nPlease select an option from below?"
                    "\n\t1: Reinstall existing certificate"
                    "\n\t2: Keep the existing certificate for now"
                    "\n\t3: Renew & replace the certificate (limit ~5 per 7 days)"
                        "")
    check_prompt = input("\nType the appropriate number [1-3] or any other key to cancel: ")
    if not os.path.isfile("/etc/letsencrypt/live/{0}/cert.pem".format(domain)):
            Log.error(self,"/etc/letsencrypt/live/{0}/cert.pem file is missing.".format(domain))
    if check_prompt == "1":
        Log.info(self,"Please Wait while we reinstall SSL Certificate for your site.\nIt may take time depending upon network.")
        ssl = EEShellExec.cmd_exec(self, "./letsencrypt-auto certonly --reinstall --webroot -w /var/www/{0}/htdocs/ -d {0} -d www.{0} "
                                .format(domain)
                                + "--email {0} --text --agree-tos".format(ee_wp_email))
    elif check_prompt == "2" :
        Log.info(self,"Using Existing Certificate files")
        if not (os.path.isfile("/etc/letsencrypt/live/{0}/fullchain.pem".format(domain)) or
                    os.path.isfile("/etc/letsencrypt/live/{0}/privkey.pem".format(domain))):
            Log.error(self,"Certificate files not found. Skipping.\n"
                           "Please check if following file exist\n\t/etc/letsencrypt/live/{0}/fullchain.pem\n\t"
                           "/etc/letsencrypt/live/{0}/privkey.pem".format(domain))
        ssl = True

    elif check_prompt == "3":
        Log.info(self,"Please Wait while we renew SSL Certificate for your site.\nIt may take time depending upon network.")
        ssl = EEShellExec.cmd_exec(self, "./letsencrypt-auto --renew-by-default certonly --webroot -w /var/www/{0}/htdocs/ -d {0} -d www.{0} "
                                .format(domain)
                                + "--email {0} --text --agree-tos".format(ee_wp_email))
    else:
        Log.error(self,"Operation cancelled by user.")

    if os.path.isfile("{0}/conf/nginx/ssl.conf"
                              .format(domain)):
        Log.info(self, "Existing ssl.conf . Backing it up ..")
        EEFileUtils.mvfile(self, "/var/www/{0}/conf/nginx/ssl.conf"
                             .format(domain),
                             '/var/www/{0}/conf/nginx/ssl.conf.bak'
                             .format(domain))

    return ssl
