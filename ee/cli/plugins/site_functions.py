import os
import random
import string
import sys
import getpass
from ee.core.fileutils import EEFileUtils
from ee.core.mysql import EEMysql
from ee.core.shellexec import EEShellExec
from ee.core.variables import EEVariables
from ee.core.logging import Log


def SetupDomain(self, data):

    ee_domain_name = data['site_name']
    ee_site_webroot = data['webroot']
    self.app.log.info("Creating {0} ...".format(ee_domain_name))
    # write nginx config for file
    try:
        ee_site_nginx_conf = open('/etc/nginx/sites-available/{0}.conf'
                                  .format(ee_domain_name), 'w')

        self.app.render((data), 'virtualconf.mustache',
                        out=ee_site_nginx_conf)
        ee_site_nginx_conf.close()
    except IOError as e:
        Log.error(self, "Unable to create nginx conf for {2} ({0}): {1}"
                  .format(e.errno, e.strerror, ee_domain_name))
        sys.exit(1)
    except Exception as e:
        Log.error(self, "{0}".format(e))
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
        Log.error(self, "{0}".format(e))
        sys.exit(1)

    EEFileUtils.create_symlink(self, ['/var/log/nginx/{0}.access.log'
                                      .format(ee_domain_name),
                                      '{0}/logs/access.log'
                                      .format(ee_site_webroot)])
    EEFileUtils.create_symlink(self, ['/var/log/nginx/{0}.error.log'
                                      .format(ee_domain_name),
                                      '{0}/logs/error.log'
                                      .format(ee_site_webroot)])


def SetupDatabase(self, data):
    ee_domain_name = data['site_name']
    ee_random = (''.join(random.sample(string.ascii_uppercase +
                 string.ascii_lowercase + string.digits, 15)))
    ee_replace_dot = ee_domain_name.replace('.', '_')
    prompt_dbname = self.app.config.get('mysql', 'db-name')
    prompt_dbuser = self.app.config.get('mysql', 'db-user')
    ee_mysql_host = self.app.config.get('mysql', 'grant-host')
    ee_db_name = ''
    ee_db_username = ''
    ee_db_password = ''

    if prompt_dbname == 'True' or prompt_dbname == 'true':
        try:
            ee_db_name = input('Enter the MySQL database name [{0}]:'
                               .format(ee_replace_dot))
        except EOFError as e:
            Log.error(self, "{0} {1}".format(e.errorno, e.strerror))
            sys.exit(0)

    if not ee_db_name:
        ee_db_name = ee_replace_dot

    if prompt_dbuser == 'True' or prompt_dbuser == 'true':
        try:
            ee_db_username = input('Enter the MySQL database user name [{0}]: '
                                   .format(ee_replace_dot))
            ee_db_password = input('Enter the MySQL database password [{0}]: '
                                   .format(ee_random))
        except EOFError as e:
            Log.error(self, "{0} {1}".format(e.errorno, e.strerror))
            sys.exit(1)

    if not ee_db_username:
        ee_db_username = ee_replace_dot
    if not ee_db_password:
        ee_db_password = ee_random

    if len(ee_db_username) > 16:
        self.app.log.info('Autofix MySQL username (ERROR 1470 (HY000)),'
                          ' please wait...')
        ee_random10 = (''.join(random.sample(string.ascii_uppercase +
                       string.ascii_lowercase + string.digits, 10)))
        ee_db_name = (ee_db_name[0:6] + ee_random10)

    # create MySQL database
    self.app.log.info("Setting Up Database ...")
    EEMysql.execute(self, "create database {0}"
                    .format(ee_db_name))

    # Create MySQL User
    EEMysql.execute(self,
                    "create user {0}@{1} identified by '{2}'"
                    .format(ee_db_username, ee_mysql_host, ee_db_password))

    # Grant permission
    EEMysql.execute(self,
                    "grant all privileges on {0}.* to {1}@{2}"
                    .format(ee_db_name, ee_db_username, ee_mysql_host))
    data['ee_db_name'] = ee_db_name
    data['ee_db_user'] = ee_db_username
    data['ee_db_pass'] = ee_db_password
    data['ee_db_host'] = ee_mysql_host
    return(data)


def SetupWordpress(self, data):
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
    ee_wp_user = ''
    ee_wp_pass = ''

    self.app.log.info("Downloading Wordpress...")
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    EEShellExec.cmd_exec(self, "wp --allow-root core download")

    data = SetupDatabase(self, data)
    if prompt_wpprefix == 'True' or prompt_wpprefix == 'true':
        try:
            ee_wp_prefix = input('Enter the WordPress table prefix [wp_]: '
                                 .format(ee_replace_dot))
            while re.match('^[A-Za-z0-9_]*$', ee_wp_prefix):
                self.app.log.warn("table prefix can only "
                                  "contain numbers, letters, and underscores")
                ee_wp_prefix = input('Enter the WordPress table prefix [wp_]: '
                                     )
        except EOFError as e:
            Log.error(self, "{0} {1}".format(e.errorno, e.strerror))
            sys.exit(1)

    if not ee_wp_prefix:
        ee_wp_prefix = 'wp_'

    # Modify wp-config.php & move outside the webroot

    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    self.app.log.debug("Setting Up WordPress Configuration...")
    if not data['multisite']:
        EEShellExec.cmd_exec(self, "wp --allow-root core config "
                             + "--dbname={0} --dbprefix={1} --dbuser={2} "
                             .format(data['ee_db_name'], ee_wp_prefix,
                                     data['ee_db_user'])
                             + "--dbpass={0}".format(data['ee_db_pass']))
    else:
        EEShellExec.cmd_exec(self, "php /usr/bin/wp --allow-root core config "
                             + "--dbname={0} --dbprefix={1} "
                             .format(data['ee_db_name'], ee_wp_prefix)
                             + "--dbuser={0} --dbpass={1} "
                               "--extra-php<<PHP \n {var1} {var2} \nPHP"
                             .format(data['ee_db_user'], data['ee_db_pass'],
                                     var1=
                                     "\n define('WP_ALLOW_MULTISITE', true);",
                                     var2=
                                     "\n define('WPMU_ACCEL_REDIRECT', true);")
                             )

    EEFileUtils.mvfile(self, './wp-config.php', '../')

    if not ee_wp_user:
        ee_wp_user = EEVariables.ee_user
        while not ee_wp_user:
            self.app.log.warn("Usernames can have only alphanumeric"
                              "characters, spaces, underscores, hyphens,"
                              "periods and the @ symbol.")
            ee_wp_user = input('Enter WordPress username: ')

    if not ee_wp_pass:
        ee_wp_pass = ee_random

    if not ee_wp_email:
        ee_wp_email = EEVariables.ee_email
        while not ee_wp_email:
            ee_wp_email = input('Enter WordPress email: ')

    self.app.log.debug("Setting up WordPress Tables, please wait...")

    if not data['multisite']:
        EEShellExec.cmd_exec(self, "php /usr/bin/wp --allow-root core install "
                             "--url={0} --title={0} --admin_name={1} "
                             .format(data['www_domain'], ee_wp_user)
                             + "--admin_password={0} --admin_email={1}"
                             .format(ee_wp_pass, ee_wp_email),
                             errormsg="Unable to setup WordPress Tables")
    else:
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

    self.app.log.debug("Updating WordPress permalink, please wait...")
    EEShellExec.cmd_exec(self, " php /usr/bin/wp --allow-root "
                         "rewrite structure "
                         "/%year%/%monthnum%/%day%/%postname%/",
                         errormsg="Unable to Update WordPress permalink")

    """Install nginx-helper plugin """
    InstallWP_Plugin(self, 'nginx-helper', data)

    """Install Wp Super Cache"""
    if data['wpsc']:
        InstallWP_Plugin(self, 'wp-super-cache', data)

    """Install W3 Total Cache"""
    if data['w3tc'] or data['wpfc']:
        InstallWP_Plugin(self, 'w3-total-cache', data)

    wp_creds = dict(wp_user=ee_wp_user, wp_pass=ee_wp_pass,
                    wp_email=ee_wp_email)

    return(wp_creds)


def SetupWordpressNetwork(self, data):
    ee_site_webroot = data['webroot']
    EEFileUtils.chdir(self, '{0}/htdocs/'.format(ee_site_webroot))
    EEShellExec.cmd_exec(self, 'wp --allow-root core multisite-convert'
                         '--title=')


def InstallWP_Plugin(self, plugin_name, data):
    ee_site_webroot = data['webroot']
    self.app.log.debug("Installing plugin {0}".format(plugin_name))
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


def SetWebrootPermissions(self, webroot):
    self.app.log.debug("Setting Up Permissions...")
    EEFileUtils.chown(self, webroot, EEVariables.ee_php_user,
                      EEVariables.ee_php_user, recursive=True)
