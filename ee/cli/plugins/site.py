"""EasyEngine site controller."""
from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.variables import EEVariables
from ee.core.domainvalidate import ValidateDomain
from ee.core.fileutils import EEFileUtils
from ee.cli.plugins.site_functions import *
from ee.core.services import EEService
from ee.cli.plugins.sitedb import *
from ee.core.git import EEGit
from subprocess import Popen
import sys
import os
import glob
import subprocess


def ee_site_hook(app):
    # do something with the ``app`` object here.
    from ee.core.database import init_db
    init_db()


class EESiteController(CementBaseController):
    class Meta:
        label = 'site'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = ('Performs website specific operations')
        arguments = [
            (['site_name'],
                dict(help='Website name')),
            ]

    @expose(hide=True)
    def default(self):
        self.app.args.print_help()

    @expose(help="Enable site example.com")
    def enable(self):
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        Log.info(self, "Enable domain {0:10}".format(ee_domain), end='')
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            EEFileUtils.create_symlink(self,
                                       ['/etc/nginx/sites-available/{0}'
                                        .format(ee_domain),
                                        '/etc/nginx/sites-enabled/{0}'
                                        .format(ee_domain)])
            updateSiteInfo(self, ee_domain, enabled=True)
            Log.info(self, "[" + Log.ENDC + "OK" + Log.OKBLUE + "]")
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="Disable site example.com")
    def disable(self):
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        Log.info(self, "Disable domain {0:10}".format(ee_domain), end='')
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            if not os.path.isfile('/etc/nginx/sites-enabled/{0}'
                                  .format(ee_domain)):
                Log.debug(self, "Site {0} already disabled" + ee_domain)
                Log.info(self, "[" + Log.FAIL + "Failed" + Log.OKBLUE+"]")
            else:
                EEFileUtils.remove_symlink(self,
                                           '/etc/nginx/sites-enabled/{0}'
                                           .format(ee_domain))
                updateSiteInfo(self, ee_domain, enabled=False)
                Log.info(self, "[" + Log.ENDC + "OK" + Log.OKBLUE + "]")
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="Get example.com information")
    def info(self):
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_db_name = ''
        ee_db_user = ''
        ee_db_pass = ''
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            check_site = getSiteInfo(self, ee_domain)
            if check_site is None:
                Log.error(self, " Site {0} does not exist.".format(ee_domain))
            else:
                sitetype = check_site.site_type
                cachetype = check_site.cache_type

            ee_site_webroot = EEVariables.ee_webroot + ee_domain
            access_log = (ee_site_webroot + '/logs/access.log')
            error_log = (ee_site_webroot + '/logs/error.log')
            configfiles = glob.glob(ee_site_webroot + '/*-config.php')
            if configfiles:
                if EEFileUtils.isexist(self, configfiles[0]):
                    ee_db_name = (EEFileUtils.grep(self, configfiles[0],
                                  'DB_NAME').split(',')[1]
                                  .split(')')[0].strip().replace('\'', ''))
                    ee_db_user = (EEFileUtils.grep(self, configfiles[0],
                                  'DB_USER').split(',')[1]
                                  .split(')')[0].strip().replace('\'', ''))
                    ee_db_pass = (EEFileUtils.grep(self, configfiles[0],
                                  'DB_PASSWORD').split(',')[1]
                                  .split(')')[0].strip().replace('\'', ''))

            data = dict(domain=ee_domain, webroot=ee_site_webroot,
                        accesslog=access_log, errorlog=error_log,
                        dbname=ee_db_name, dbuser=ee_db_user,
                        dbpass=ee_db_pass, type=sitetype + " " + cachetype +
                        " ({0})".format("enabled"
                                        if check_site.is_enabled else
                                        "disabled"))
            self.app.render((data), 'siteinfo.mustache')
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="Monitor example.com logs")
    def log(self):
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_site_webroot = EEVariables.ee_webroot + ee_domain

        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            logfiles = glob.glob(ee_site_webroot + '/logs/*.log')
            logwatch(self, logfiles)
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="Edit Nginx configuration of example.com")
    def edit(self):
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            EEShellExec.invoke_editor(self, '/etc/nginx/sites-available/{0}'
                                      .format(ee_domain))
            if (EEGit.checkfilestatus(self, "/etc/nginx",
               '/etc/nginx/sites-available/{0}'.format(ee_domain))):
                EEGit.add(self, ["/etc/nginx"], msg="Edit website: {0}"
                          .format(ee_domain))
                # Reload NGINX
                EEService.reload_service(self, 'nginx')
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="Display Nginx configuration of example.com")
    def show(self):
        # TODO Write code for ee site edit command here
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            Log.info(self, "Display NGINX configuration for {0}"
                     .format(ee_domain))
            f = open('/etc/nginx/sites-available/{0}'.format(ee_domain), "r")
            text = f.read()
            Log.info(self, Log.ENDC + text)
            f.close()
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="Change directory to site webroot")
    def cd(self):

        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            ee_site_webroot = EEVariables.ee_webroot + ee_domain
            EEFileUtils.chdir(self, ee_site_webroot)
            try:
                subprocess.call(['bash'])
            except OSError as e:
                Log.debug(self, "{0}{1}".format(e.errno, e.strerror))
                Log.error(self, " cannot change directory")


class EESiteCreateController(CementBaseController):
    class Meta:
        label = 'create'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = ('this commands set up configuration and installs '
                       'required files as options are provided')
        arguments = [
            (['site_name'],
                dict(help='domain name for the site to be created.')),
            (['--html'],
                dict(help="create html site", action='store_true')),
            (['--php'],
                dict(help="create php site", action='store_true')),
            (['--mysql'],
                dict(help="create mysql site", action='store_true')),
            (['--wp'],
                dict(help="create wordpress single site",
                     action='store_true')),
            (['--wpsubdir'],
                dict(help="create wordpress multisite with subdirectory setup",
                     action='store_true')),
            (['--wpsubdomain'],
                dict(help="create wordpress multisite with subdomain setup",
                     action='store_true')),
            (['--w3tc'],
                dict(help="create wordpress single/multi site with w3tc cache",
                     action='store_true')),
            (['--wpfc'],
                dict(help="create wordpress single/multi site with wpfc cache",
                     action='store_true')),
            (['--wpsc'],
                dict(help="create wordpress single/multi site with wpsc cache",
                     action='store_true')),
            ]

    @expose(hide=True)
    def default(self):
        # self.app.render((data), 'default.mustache')
        # Check domain name validation
        data = ''
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_site_webroot = EEVariables.ee_webroot + ee_domain

        # Check if doain previously exists or not
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            Log.error(self, " site {0} already exists"
                      .format(ee_domain))

        # setup nginx configuration for site
        # HTML
        if (self.app.pargs.html and not (self.app.pargs.php or
            self.app.pargs.mysql or self.app.pargs.wp or self.app.pargs.w3tc
            or self.app.pargs.wpfc or self.app.pargs.wpsc or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=True,  basic=False, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot)
            stype = 'html'
            cache = 'basic'

        # PHP
        if (self.app.pargs.php and not (self.app.pargs.html or
            self.app.pargs.mysql or self.app.pargs.wp or self.app.pargs.w3tc
            or self.app.pargs.wpfc or self.app.pargs.wpsc or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot)
            stype = 'php'
            cache = 'basic'
        # MySQL
        if (self.app.pargs.mysql and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.wp or self.app.pargs.w3tc
            or self.app.pargs.wpfc or self.app.pargs.wpsc or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot,
                        ee_db_name='', ee_db_user='', ee_db_pass='',
                        ee_db_host='')
            stype = 'mysql'
            cache = 'basic'
        # WP
        if ((self.app.pargs.wp or self.app.pargs.w3tc or self.app.pargs.wpfc or
            self.app.pargs.wpsc) and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):

            if (self.app.pargs.wp and not (self.app.pargs.w3tc
               or self.app.pargs.wpfc or self.app.pargs.wpsc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=True, wp=True, w3tc=False,
                            wpfc=False, wpsc=False, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wp'
                cache = 'basic'

            if (self.app.pargs.w3tc and not
               (self.app.pargs.wpfc or self.app.pargs.wpsc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=True,
                            wpfc=False, wpsc=False, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wp'
                cache = 'w3tc'

            if (self.app.pargs.wpfc and not
               (self.app.pargs.wpsc or self.app.pargs.w3tc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=True, wpsc=False, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wp'
                cache = 'wpfc'

            if (self.app.pargs.wpsc and not
               (self.app.pargs.w3tc or self.app.pargs.wpfc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=False, wpsc=True, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wp'
                cache = 'wpsc'

        # WPSUBDIR
        if (self.app.pargs.wpsubdir and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or
           self.app.pargs.wpsubdomain or self.app.pargs.wp)):

            if (self.app.pargs.wpsubdir and not (self.app.pargs.w3tc
               or self.app.pargs.wpfc or self.app.pargs.wpsc)):
                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=True, wp=True, w3tc=False,
                            wpfc=False, wpsc=False, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wpsubdir'
                cache = 'basic'

            if (self.app.pargs.w3tc and not
               (self.app.pargs.wpfc or self.app.pargs.wpsc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=True,
                            wpfc=False, wpsc=False, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wpsubdir'
                cache = 'w3tc'

            if (self.app.pargs.wpfc and not
               (self.app.pargs.wpsc or self.app.pargs.w3tc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=True, wpsc=False, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wpsubdir'
                cache = 'wpfc'

            if (self.app.pargs.wpsc and not
               (self.app.pargs.w3tc or self.app.pargs.wpfc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=False, wpsc=True, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wpsubdir'
                cache = 'wpsc'

        # WPSUBDOAIN
        if (self.app.pargs.wpsubdomain and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or
           self.app.pargs.wpsubdir or self.app.pargs.wp)):

            if (self.app.pargs.wpsubdomain and not (self.app.pargs.w3tc
               or self.app.pargs.wpfc or self.app.pargs.wpsc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=True, wp=True, w3tc=False,
                            wpfc=False, wpsc=False, multisite=True,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wpsubdomain'
                cache = 'basic'

            if (self.app.pargs.w3tc and not
               (self.app.pargs.wpfc or self.app.pargs.wpsc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=True,
                            wpfc=False, wpsc=False, multisite=True,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wpsubdomain'
                cache = 'w3tc'

            if (self.app.pargs.wpfc and not
               (self.app.pargs.wpsc or self.app.pargs.w3tc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False, basic=False, wp=True, w3tc=False,
                            wpfc=True, wpsc=False, multisite=True,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wpsubdomain'
                cache = 'wpfc'

            if (self.app.pargs.wpsc and not
               (self.app.pargs.w3tc or self.app.pargs.wpfc)):

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False, basic=False, wp=True, w3tc=False,
                            wpfc=False, wpsc=True, multisite=True,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='')
                stype = 'wpsubdomain'
                cache = 'wpsc'

        if not data:
            self.app.args.print_help()
            self.app.close(1)

        # Check rerequired packages are installed or not
        ee_auth = site_package_check(self, stype)
        # setup NGINX configuration, and webroot
        setupdomain(self, data)
        # Setup database for MySQL site
        if 'ee_db_name' in data.keys() and not data['wp']:
            data = setupdatabase(self, data)
            try:
                eedbconfig = open("{0}/ee-config.php".format(ee_site_webroot),
                                  'w')
                eedbconfig.write("<?php \ndefine('DB_NAME', '{0}');"
                                 "\ndefine('DB_USER', '{1}'); "
                                 "\ndefine('DB_PASSWORD', '{2}');"
                                 "\ndefine('DB_HOST', '{3}');\n?>"
                                 .format(data['ee_db_name'],
                                         data['ee_db_user'],
                                         data['ee_db_pass'],
                                         data['ee_db_host']))
                eedbconfig.close()
                stype = 'mysql'
            except IOError as e:
                Log.debug(self, "{2} ({0}): {1}"
                          .format(e.errno, e.strerror, ee_domain))
                Log.error(self, " Unable to create ee-config.php for ")

        # Setup WordPress if Wordpress site
        if data['wp']:
            ee_wp_creds = setupwordpress(self, data)
        # Service Nginx Reload
        EEService.reload_service(self, 'nginx')

        EEGit.add(self, ["/etc/nginx"],
                  msg="{0} created with {1} {2}"
                  .format(ee_www_domain, stype, cache))
        # Setup Permissions for webroot
        setwebrootpermissions(self, data['webroot'])
        if ee_auth and len(ee_auth):
            for msg in ee_auth:
                Log.info(self, Log.ENDC + msg)

        if data['wp']:
            Log.info(self, Log.ENDC + "WordPress admin user :"
                     " {0}".format(ee_wp_creds['wp_user']))
            Log.info(self, Log.ENDC + "WordPress admin user password : {0}"
                     .format(ee_wp_creds['wp_pass']))

        display_cache_settings(self, data)
        addNewSite(self, ee_www_domain, stype, cache, ee_site_webroot)
        Log.info(self, "Successfully created site"
                 " http://{0}".format(ee_domain))


class EESiteUpdateController(CementBaseController):
    class Meta:
        label = 'update'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = ('This command updates websites configuration to '
                       'another as per the options are provided')
        arguments = [
            (['site_name'],
                dict(help='domain name for the site to be updated')),
            (['--password'],
                dict(help="update to password for wordpress site user",
                     action='store_true')),
            (['--html'],
                dict(help="update to html site", action='store_true')),
            (['--php'],
                dict(help="update to php site", action='store_true')),
            (['--mysql'],
                dict(help="update to mysql site", action='store_true')),
            (['--wp'],
                dict(help="update to wordpress single site",
                     action='store_true')),
            (['--wpsubdir'],
                dict(help="update to wpsubdir site", action='store_true')),
            (['--wpsubdomain'],
                dict(help="update to  wpsubdomain site", action='store_true')),
            (['--w3tc'],
                dict(help="update to w3tc cache", action='store_true')),
            (['--wpfc'],
                dict(help="update to wpfc cache", action='store_true')),
            (['--wpsc'],
                dict(help="update to wpsc cache", action='store_true')),
            ]

    @expose(help="Update site type or cache")
    def default(self):
        data = ''
        (ee_domain,
         ee_www_domain, ) = ValidateDomain(self.app.pargs.site_name)
        ee_site_webroot = EEVariables.ee_webroot + ee_domain

        check_site = getSiteInfo(self, ee_domain)

        if check_site is None:
            Log.error(self, " Site {0} does not exist.".format(ee_domain))
        else:
            oldsitetype = check_site.site_type
            oldcachetype = check_site.cache_type

        if (self.app.pargs.password and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or self.app.pargs.wp or
            self.app.pargs.w3tc or self.app.pargs.wpfc or self.app.pargs.wpsc
           or self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):

            updatewpuserpassword(self, ee_domain, ee_site_webroot)
            self.app.close(0)

        if (self.app.pargs.html and not (self.app.pargs.php or
            self.app.pargs.mysql or self.app.pargs.wp or self.app.pargs.w3tc
            or self.app.pargs.wpfc or self.app.pargs.wpsc or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):
            Log.error(self, " Cannot update {0} {1} to html"
                      .format(ee_domain, oldsitetype))

        # PHP
        if (self.app.pargs.php and not (self.app.pargs.html or
            self.app.pargs.mysql or self.app.pargs.wp or self.app.pargs.w3tc
            or self.app.pargs.wpfc or self.app.pargs.wpsc or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):

            if oldsitetype != 'html':

                Log.error(self, " Cannot update {0} {1} to php"
                          .format(ee_domain, oldsitetype))

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot,
                        currsitetype=oldsitetype, currcachetype=oldcachetype)
            stype = 'php'
            cache = 'basic'

        # MySQL
        if (self.app.pargs.mysql and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.wp or self.app.pargs.w3tc
            or self.app.pargs.wpfc or self.app.pargs.wpsc or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):

            if oldsitetype not in ['html', 'php']:
                Log.error(self, " Cannot update {0}, {1} to mysql"
                          .format(ee_domain, oldsitetype))

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot,
                        ee_db_name='', ee_db_user='', ee_db_pass='',
                        ee_db_host='', currsitetype=oldsitetype,
                        currcachetype=oldcachetype)
            stype = 'mysql'
            cache = 'basic'

        # WP
        if ((self.app.pargs.wp or self.app.pargs.w3tc or self.app.pargs.wpfc or
            self.app.pargs.wpsc) and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):
            if (self.app.pargs.wp and not (self.app.pargs.w3tc
               or self.app.pargs.wpfc or self.app.pargs.wpsc)):

                if ((oldsitetype not in ['html', 'php', 'mysql', 'wp'])
                   or (oldsitetype == 'wp' and oldcachetype == 'basic')):

                    Log.error(self, "Cannot update {0} {1} {2} to wp basic"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=True, wp=True, w3tc=False,
                            wpfc=False, wpsc=False, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)
                stype = 'wp'
                cache = 'basic'

            if (self.app.pargs.w3tc and not
               (self.app.pargs.wpfc or self.app.pargs.wpsc)):

                if (oldsitetype not in ['html', 'php', 'mysql', 'wp']
                   or (oldsitetype == 'wp' and oldcachetype == 'w3tc')):
                    Log.error(self, "Cannot update {0}, {1} {2} to wp w3tc"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=True,
                            wpfc=False, wpsc=False, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

                stype = 'wp'
                cache = 'w3tc'

            if (self.app.pargs.wpfc and not
               (self.app.pargs.wpsc or self.app.pargs.w3tc)):

                if (oldsitetype not in ['html', 'php', 'mysql', 'wp']
                   or (oldsitetype == 'wp' and oldcachetype == 'wpfc')):
                    Log.error(self, "Cannot update {0}, {1} {2} to wp wpfc"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=True, wpsc=False, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)
                stype = 'wp'
                cache = 'wpfc'

            if (self.app.pargs.wpsc and not
               (self.app.pargs.w3tc or self.app.pargs.wpfc)):

                if (oldsitetype not in ['html', 'php', 'mysql', 'wp']
                   or (oldsitetype == 'wp' and oldcachetype == 'wpsc')):
                    Log.error(self, "Cannot update {0}, {1} {2} to wp wpsc"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=False, wpsc=True, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)
                stype = 'wp'
                cache = 'wpsc'

        # WPSUBDIR
        if (self.app.pargs.wpsubdir and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or
           self.app.pargs.wpsubdomain or self.app.pargs.wp)):
            if (self.app.pargs.wpsubdir and not (self.app.pargs.w3tc
               or self.app.pargs.wpfc or self.app.pargs.wpsc)):

                if (oldsitetype not in ['html', 'php', 'mysql', 'wp',
                                        'wpsubdir']
                   or (oldsitetype == 'wpsubdir' and oldcachetype == 'basic')):
                    Log.error(self, " Cannot update {0}, {1} {2} "
                              "to wpsubdir basic"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=True, wp=True, w3tc=False,
                            wpfc=False, wpsc=False, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)
                stype = 'wpsubdir'
                cache = 'basic'

            if (self.app.pargs.w3tc and not
               (self.app.pargs.wpfc or self.app.pargs.wpsc)):

                if (oldsitetype not in ['html', 'php', 'mysql', 'wp',
                                        'wpsubdir']
                   or (oldsitetype == 'wpsubdir' and oldcachetype == 'w3tc')):
                    Log.error(self, " Cannot update {0} {1} {2}"
                              "to wpsubdir w3tc"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=True,
                            wpfc=False, wpsc=False, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

                stype = 'wpsubdir'
                cache = 'w3tc'

            if (self.app.pargs.wpfc and not
               (self.app.pargs.wpsc or self.app.pargs.w3tc)):

                if (oldsitetype not in ['html', 'php', 'mysql', 'wp',
                                        'wpsubdir']
                   or (oldsitetype == 'wpsubdir' and oldcachetype == 'wpfc')):
                    Log.error(self, "Cannot update {0} {1} {2}"
                              " to wpsubdir wpfc"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=True, wpsc=False, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)
                stype = 'wpsubdir'
                cache = 'wpfc'

            if (self.app.pargs.wpsc and not
               (self.app.pargs.w3tc or self.app.pargs.wpfc)):

                if (oldsitetype not in ['html', 'php', 'mysql', 'wp',
                                        'wpsubdir']
                   or (oldsitetype == 'wpsubdir' and oldcachetype == 'wpsc')):
                    Log.error(self, " Cannot update {0} {1} {2}"
                              " to wpsubdir wpsc"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=False, wpsc=True, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)
                stype = 'wpsubdir'
                cache = 'wpsc'

        if (self.app.pargs.wpsubdomain and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or
           self.app.pargs.wpsubdir or self.app.pargs.wp)):

            if (oldsitetype not in ['html', 'php', 'mysql', 'wp',
                                    'wpsubdomain']
               or (oldsitetype == 'wpsubdomain' and oldcachetype == 'basic')):
                Log.error(self, " Cannot update {0} {1} {2}"
                          " to wpsubdomain basic"
                          .format(ee_domain, oldsitetype, oldcachetype))

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=True, w3tc=False,
                        wpfc=False, wpsc=False, multisite=True,
                        wpsubdir=False, webroot=ee_site_webroot,
                        ee_db_name='', ee_db_user='', ee_db_pass='',
                        ee_db_host='', currsitetype=oldsitetype,
                        currcachetype=oldcachetype)

            stype = 'wpsubdomain'
            cache = 'basic'

            if (self.app.pargs.w3tc and not
               (self.app.pargs.wpfc or self.app.pargs.wpsc)):

                if (oldsitetype not in ['html', 'php', 'mysql', 'wp',
                                        'wpsubdomain'] or
                   (oldsitetype == 'wpsubdomain' and oldcachetype == 'w3tc')):
                    Log.error(self, " Cannot update {0}, {1} {2}"
                              " to wpsubdomain w3tc"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=True,
                            wpfc=False, wpsc=False, multisite=True,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

                stype = 'wpsubdomain'
                cache = 'w3tc'

            if (self.app.pargs.wpfc and not
               (self.app.pargs.wpsc or self.app.pargs.w3tc)):

                if (oldsitetype not in ['html', 'php', 'mysql', 'wp',
                                        'wpsubdomain'] or
                   (oldsitetype == 'wpsubdomain' and oldcachetype == 'wpfc')):
                    Log.error(self, " Cannot update {0}, {1} {2} "
                              "to wpsubdomain wpfc"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False, basic=False, wp=True, w3tc=False,
                            wpfc=True, wpsc=False, multisite=True,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

                stype = 'wpsubdomain'
                cache = 'wpfc'

            if (self.app.pargs.wpsc and not
               (self.app.pargs.w3tc or self.app.pargs.wpfc)):

                if (oldsitetype not in ['html', 'php', 'mysql', 'wp',
                                        'wpsubdomain'] or
                   (oldsitetype == 'wpsubdomain' and oldcachetype == 'wpsc')):
                    Log.error(self, " Cannot update {0}, {1} {2}"
                              " to wpsubdomain wpsc"
                              .format(ee_domain, oldsitetype, oldcachetype))

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False, basic=False, wp=True, w3tc=False,
                            wpfc=False, wpsc=True, multisite=True,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

                stype = 'wpsubdomain'
                cache = 'wpsc'

        if not data:
            Log.error(self, " Cannot update {0}, Invalid Options"
                      .format(ee_domain))

        ee_auth = site_package_check(self, stype)
        sitebackup(self, data)

        # setup NGINX configuration, and webroot
        setupdomain(self, data)

        if 'ee_db_name' in data.keys() and not data['wp']:
            data = setupdatabase(self, data)
            try:
                eedbconfig = open("{0}/ee-config.php".format(ee_site_webroot),
                                  'w')
                eedbconfig.write("<?php \ndefine('DB_NAME', '{0}');"
                                 "\ndefine('DB_USER', '{1}'); "
                                 "\ndefine('DB_PASSWORD', '{2}');"
                                 "\ndefine('DB_HOST', '{3}');\n?>"
                                 .format(data['ee_db_name'],
                                         data['ee_db_user'],
                                         data['ee_db_pass'],
                                         data['ee_db_host']))
                eedbconfig.close()
            except IOError as e:
                Log.debug(self, "{0}".format(e))
                Log.error(self, " Unable to create ee-config.php.")

        if oldsitetype == 'mysql':
            config_file = (ee_site_webroot + '/backup/{0}/ee-config.php'
                           .format(EEVariables.ee_date))
            data['ee_db_name'] = (EEFileUtils.grep(self, config_file,
                                  'DB_NAME')
                                  .split(',')[1]
                                  .split(')')[0].strip())
            data['ee_db_user'] = (EEFileUtils.grep(self, config_file,
                                  'DB_USER')
                                  .split(',')[1]
                                  .split(')')[0].strip())
            data['ee_db_pass'] = (EEFileUtils.grep(self, config_file,
                                  'DB_PASSWORD')
                                  .split(',')[1]
                                  .split(')')[0].strip())

        # Setup WordPress if old sites are html/php/mysql sites
        if data['wp'] and oldsitetype in ['html', 'php', 'mysql']:
            ee_wp_creds = setupwordpress(self, data)

        # Uninstall unnecessary plugins
        if oldsitetype in ['wp', 'wpsubdir', 'wpsubdomain']:
            # Setup WordPress Network if update option is multisite
            # and oldsite is WordPress single site
            if data['multisite'] and oldsitetype == 'wp':
                setupwordpressnetwork(self, data)

            if (oldcachetype == 'w3tc' or oldcachetype == 'wpfc' and
               not (data['w3tc'] or data['wpfc'])):
                uninstallwp_plugin(self, 'w3-total-cache', data)

            if oldcachetype == 'wpsc' and not data['wpsc']:
                uninstallwp_plugin(self, 'wp-super-cache', data)

        if (oldcachetype != 'w3tc' or oldcachetype != 'wpfc') and (data['w3tc']
           or data['wpfc']):
            installwp_plugin(self, 'w3-total-cache', data)

        if oldcachetype != 'wpsc' and data['wpsc']:
            installwp_plugin(self, 'wp-super-cache', data)

        # Service Nginx Reload
        EEService.reload_service(self, 'nginx')

        EEGit.add(self, ["/etc/nginx"],
                  msg="{0} updated with {1} {2}"
                  .format(ee_www_domain, stype, cache))
        # Setup Permissions for webroot
        # setwebrootpermissions(self, data['webroot'])
        if ee_auth and len(ee_auth):
            for msg in ee_auth:
                Log.info(self, Log.ENDC + msg)

        if data['wp'] and oldsitetype in ['html', 'php', 'mysql']:
            Log.info(self, "\n\n" + Log.ENDC + "WordPress admin user :"
                     " {0}".format(ee_wp_creds['wp_user']))
            Log.info(self, Log.ENDC + "WordPress admin password : {0}"
                     .format(ee_wp_creds['wp_pass']) + "\n\n")
        display_cache_settings(self, data)
        updateSiteInfo(self, ee_www_domain, stype=stype, cache=cache)
        Log.info(self, "Successfully updated site"
                 " http://{0}".format(ee_domain))


class EESiteDeleteController(CementBaseController):
    class Meta:
        label = 'delete'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = 'To delete website'
        arguments = [
            (['site_name'],
                dict(help='domain name to be deleted')),
            (['--no-prompt'],
                dict(help="doesnt ask permission for delete",
                     action='store_true')),
            (['--all'],
                dict(help="delete all", action='store_true')),
            (['--db'],
                dict(help="delete db only", action='store_true')),
            (['--files'],
                dict(help="delete webroot only", action='store_true')),
            ]

    @expose(help="Delete website configuration and files")
    def default(self):
        # TODO Write code for ee site update here
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_db_name = ''
        ee_prompt = ''
        ee_nginx_prompt = ''

        if ((not self.app.pargs.db) and (not self.app.pargs.files) and
           (not self.app.pargs.all)):
            self.app.pargs.all = True

        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            ee_site_webroot = EEVariables.ee_webroot + ee_domain

            if self.app.pargs.no_prompt:
                ee_prompt = 'Y'

            if self.app.pargs.db:
                if not ee_prompt:
                    ee_db_prompt = input('Do you want to delete database'
                                         '[Y/N]: ')
                else:
                    ee_db_prompt = 'Y'

                if ee_db_prompt == 'Y' or ee_db_prompt == 'y':
                    self.deleteDB(ee_site_webroot)

            if self.app.pargs.files:
                if not ee_prompt:
                    ee_web_prompt = input('Do you want to delete webroot'
                                          '[Y/N]: ')
                else:
                    ee_web_prompt = 'Y'

                if ee_web_prompt == 'Y' or ee_web_prompt == 'y':
                    self.deleteWebRoot(ee_site_webroot)

            if self.app.pargs.all:
                if not ee_prompt:
                    ee_db_prompt = input('Do you want to delete database'
                                         '[Y/N]: '
                                         )
                    ee_web_prompt = input('Do you want to delete webroot'
                                          '[Y/N]: ')
                    ee_nginx_prompt = input('Do you want to delete NGINX'
                                            ' configuration [Y/N]: ')
                else:
                    ee_db_prompt = 'Y'
                    ee_web_prompt = 'Y'
                    ee_nginx_prompt = 'Y'

                if ee_db_prompt == 'Y' or ee_db_prompt == 'y':
                    self.deleteDB(ee_site_webroot)
                if ee_web_prompt == 'Y' or ee_web_prompt == 'y':
                    self.deleteWebRoot(ee_site_webroot)

                if (ee_nginx_prompt == 'Y' or ee_nginx_prompt == 'y'):
                    Log.debug(self, "Removing Nginx configuration")
                    EEFileUtils.rm(self, '/etc/nginx/sites-available/{0}'
                                   .format(ee_domain))
                deleteSiteInfo(self, ee_domain)
            Log.info(self, "Deleted site {0}".format(ee_domain))
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(hide=True)
    def deleteDB(self, webroot):
        configfiles = glob.glob(webroot + '/*-config.php')
        if configfiles:
            if EEFileUtils.isexist(self, configfiles[0]):
                ee_db_name = (EEFileUtils.grep(self, configfiles[0],
                              'DB_NAME').split(',')[1]
                              .split(')')[0].strip().replace('\'', ''))
                ee_db_user = (EEFileUtils.grep(self, configfiles[0],
                              'DB_USER').split(',')[1]
                              .split(')')[0].strip().replace('\'', ''))
                ee_db_pass = (EEFileUtils.grep(self, configfiles[0],
                              'DB_PASSWORD').split(',')[1]
                              .split(')')[0].strip().replace('\'', ''))
                ee_db_host = (EEFileUtils.grep(self, configfiles[0],
                              'DB_HOST').split(',')[1]
                              .split(')')[0].strip().replace('\'', ''))
            try:
                Log.debug(self, "dropping database {0}".format(ee_db_name))
                EEMysql.execute(self,
                                "drop database {0}".format(ee_db_name),
                                errormsg='Unable to drop database {0}'
                                .format(ee_db_name))
                if ee_db_user != 'root':
                    Log.debug(self, "dropping user {0}".format(ee_db_user))
                    EEMysql.execute(self,
                                    "drop user {0}@{1}"
                                    .format(ee_db_user, ee_db_host))
                    EEMysql.execute(self,
                                    "flush privileges")
            except Exception as e:
                Log.error(self, "Error occured while deleting database")

    @expose(hide=True)
    def deleteWebRoot(self, webroot):
        EEFileUtils.rm(self, webroot)


class EESiteListController(CementBaseController):
    class Meta:
        label = 'list'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = 'List websites'
        arguments = [
            (['--enabled'],
                dict(help='List enabled websites', action='store_true')),
            (['--disabled'],
                dict(help="List disabled websites", action='store_true')),
            ]

    @expose(help="Lists websites")
    def default(self):
            sites = getAllsites(self)
            if not sites:
                pass

            if self.app.pargs.enabled:
                for site in sites:
                    if site.is_enabled:
                        Log.info(self, "{0}".format(site.sitename))
            elif self.app.pargs.disabled:
                for site in sites:
                    if not site.is_enabled:
                        Log.info(self, "{0}".format(site.sitename))
            else:
                for site in sites:
                        Log.info(self, "{0}".format(site.sitename))


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EESiteController)
    handler.register(EESiteCreateController)
    handler.register(EESiteUpdateController)
    handler.register(EESiteDeleteController)
    handler.register(EESiteListController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_site_hook)
