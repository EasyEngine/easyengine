# """EasyEngine site controller."""
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
    import ee.cli.plugins.models
    init_db(app)
    EESync(app).syncdbinfo()


class EESiteController(CementBaseController):
    class Meta:
        label = 'site'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = ('Performs website specific operations')
        arguments = [
            (['site_name'],
                dict(help='Website name', nargs='?')),
            ]
        usage = "ee site (command) <site_name> [options]"

    @expose(hide=True)
    def default(self):
        self.app.args.print_help()

    @expose(help="Enable site example.com")
    def enable(self):
        if not self.app.pargs.site_name:
            try:
                self.app.pargs.site_name = input('Enter site name : ')
            except IOError as e:
                Log.error(self, 'could not input site name')

        # validate domain name
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)

        # check if site exists
        if not check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} does not exist".format(ee_domain))
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            Log.info(self, "Enable domain {0:10} \t".format(ee_domain), end='')
            EEFileUtils.create_symlink(self,
                                       ['/etc/nginx/sites-available/{0}'
                                        .format(ee_domain),
                                        '/etc/nginx/sites-enabled/{0}'
                                        .format(ee_domain)])
            EEGit.add(self, ["/etc/nginx"],
                      msg="Enabled {0} "
                      .format(ee_domain))
            updateSiteInfo(self, ee_domain, enabled=True)
            Log.info(self, "[" + Log.ENDC + "OK" + Log.OKBLUE + "]")
            EEService.reload_service(self, 'nginx')
        else:
            Log.error(self, "nginx configuration file does not exist"
                      .format(ee_domain))

    @expose(help="Disable site example.com")
    def disable(self):
        if not self.app.pargs.site_name:
            try:
                self.app.pargs.site_name = input('Enter site name : ')
            except IOError as e:
                Log.error(self, 'could not input site name')
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        # check if site exists
        if not check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} does not exist".format(ee_domain))

        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            Log.info(self, "Disable domain {0:10} \t"
                     .format(ee_domain), end='')
            if not os.path.isfile('/etc/nginx/sites-enabled/{0}'
                                  .format(ee_domain)):
                Log.debug(self, "Site {0} already disabled".format(ee_domain))
                Log.info(self, "[" + Log.FAIL + "Failed" + Log.OKBLUE+"]")
            else:
                EEFileUtils.remove_symlink(self,
                                           '/etc/nginx/sites-enabled/{0}'
                                           .format(ee_domain))
                EEGit.add(self, ["/etc/nginx"],
                          msg="Disabled {0} "
                          .format(ee_domain))
                updateSiteInfo(self, ee_domain, enabled=False)
                Log.info(self, "[" + Log.ENDC + "OK" + Log.OKBLUE + "]")
                EEService.reload_service(self, 'nginx')
        else:
            Log.error(self, "nginx configuration file does not exist"
                      .format(ee_domain))

    @expose(help="Get example.com information")
    def info(self):
        if not self.app.pargs.site_name:
            try:
                self.app.pargs.site_name = input('Enter site name : ')
            except IOError as e:
                Log.error(self, 'could not input site name')
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_db_name = ''
        ee_db_user = ''
        ee_db_pass = ''

        if not check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} does not exist".format(ee_domain))
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            siteinfo = getSiteInfo(self, ee_domain)

            sitetype = siteinfo.site_type
            cachetype = siteinfo.cache_type
            ee_site_webroot = siteinfo.site_path
            access_log = (ee_site_webroot + '/logs/access.log')
            error_log = (ee_site_webroot + '/logs/error.log')
            ee_db_name = siteinfo.db_name
            ee_db_user = siteinfo.db_user
            ee_db_pass = siteinfo.db_password
            ee_db_host = siteinfo.db_host

            data = dict(domain=ee_domain, webroot=ee_site_webroot,
                        accesslog=access_log, errorlog=error_log,
                        dbname=ee_db_name, dbuser=ee_db_user,
                        dbpass=ee_db_pass, type=sitetype + " " + cachetype +
                        " ({0})".format("enabled"
                                        if siteinfo.is_enabled else
                                        "disabled"))
            self.app.render((data), 'siteinfo.mustache')
        else:
            Log.error(self, "nginx configuration file does not exist"
                      .format(ee_domain))

    @expose(help="Monitor example.com logs")
    def log(self):
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_site_webroot = getSiteInfo(self, ee_domain).site_path

        if not check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} does not exist".format(ee_domain))
        logfiles = glob.glob(ee_site_webroot + '/logs/*.log')
        if logfiles:
            logwatch(self, logfiles)

    @expose(help="Edit Nginx configuration of example.com")
    def edit(self):
        if not self.app.pargs.site_name:
            try:
                self.app.pargs.site_name = input('Enter site name : ')
            except IOError as e:
                Log.error(self, 'Unable to read input, Please try again')
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)

        if not check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} does not exist".format(ee_domain))
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            try:
                EEShellExec.invoke_editor(self, '/etc/nginx/sites-available/'
                                          '{0}'.format(ee_domain))
            except CommandExecutionError as e:
                Log.error(self, "Failed invoke editor")
            if (EEGit.checkfilestatus(self, "/etc/nginx",
               '/etc/nginx/sites-available/{0}'.format(ee_domain))):
                EEGit.add(self, ["/etc/nginx"], msg="Edit website: {0}"
                          .format(ee_domain))
                # Reload NGINX
                EEService.reload_service(self, 'nginx')
        else:
            Log.error(self, "nginx configuration file does not exists"
                      .format(ee_domain))

    @expose(help="Display Nginx configuration of example.com")
    def show(self):
        if not self.app.pargs.site_name:
            try:
                self.app.pargs.site_name = input('Enter site name : ')
            except IOError as e:
                Log.error(self, 'could not input site name')
        # TODO Write code for ee site edit command here
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)

        if not check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} does not exist".format(ee_domain))

        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            Log.info(self, "Display NGINX configuration for {0}"
                     .format(ee_domain))
            f = open('/etc/nginx/sites-available/{0}'.format(ee_domain),
                     encoding='utf-8', mode='r')
            text = f.read()
            Log.info(self, Log.ENDC + text)
            f.close()
        else:
            Log.error(self, "nginx configuration file does not exists"
                      .format(ee_domain))

    @expose(help="Change directory to site webroot")
    def cd(self):
        if not self.app.pargs.site_name:
            try:
                self.app.pargs.site_name = input('Enter site name : ')
            except IOError as e:
                Log.error(self, 'Unable to read input, please try again')

        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)

        if not check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} does not exist".format(ee_domain))

        ee_site_webroot = getSiteInfo(self, ee_domain).site_path
        EEFileUtils.chdir(self, ee_site_webroot)

        try:
            subprocess.call(['bash'])
        except OSError as e:
            Log.debug(self, "{0}{1}".format(e.errno, e.strerror))
            Log.error(self, "unable to change directory")


class EESiteCreateController(CementBaseController):
    class Meta:
        label = 'create'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = ('this commands set up configuration and installs '
                       'required files as options are provided')
        arguments = [
            (['site_name'],
                dict(help='domain name for the site to be created.',
                     nargs='?')),
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
            (['--hhvm'],
                dict(help="create HHVM site", action='store_true')),
            (['--pagespeed'],
                dict(help="create pagespeed site", action='store_true')),
            ]

    @expose(hide=True)
    def default(self):
        # self.app.render((data), 'default.mustache')
        # Check domain name validation
        data = dict()
        try:
            stype, cache = detSitePar(vars(self.app.pargs))
        except RuntimeError as e:
            Log.debug(self, str(e))
            Log.error(self, "Please provide valid options to creating site")

        if not self.app.pargs.site_name:
            try:
                self.app.pargs.site_name = input('Enter site name : ')
            except IOError as e:
                Log.debug(self, str(e))
                Log.error(self, "Unable to input site name, Please try again!")

        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_site_webroot = EEVariables.ee_webroot + ee_domain

        if check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} already exists".format(ee_domain))
        elif os.path.isfile('/etc/nginx/sites-available/{0}'
                            .format(ee_domain)):
            Log.error(self, "Nginx configuration /etc/nginx/sites-available/"
                      "{0} already exists".format(ee_domain))

        if stype in ['html', 'php']:
            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=True,  basic=False, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot)

            if stype == 'php':
                data['static'] = False
                data['basic'] = True
        elif stype in ['mysql', 'wp', 'wpsubdir', 'wpsubdomain']:

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot,
                        ee_db_name='', ee_db_user='', ee_db_pass='',
                        ee_db_host='')

            if stype in ['wp', 'wpsubdir', 'wpsubdomain']:
                data['wp'] = True
                data['basic'] = False
                data[cache] = True
                if stype in ['wpsubdir', 'wpsubdomain']:
                    data['multisite'] = True
                    if stype == 'wpsubdir':
                        data['wpsubdir'] = True

        if data and self.app.pargs.hhvm:
            data['hhvm'] = True
            hhvm = 1
        elif data:
            data['hhvm'] = False
            hhvm = 0

        if data and self.app.pargs.pagespeed:
            data['pagespeed'] = True
            pagespeed = 1
        elif data:
            data['pagespeed'] = False
            pagespeed = 0

        if not data:
            self.app.args.print_help()
            self.app.close(1)

        # Check rerequired packages are installed or not
        ee_auth = site_package_check(self, stype)

        try:
            try:
                # setup NGINX configuration, and webroot
                setupdomain(self, data)
            except SiteError as e:
                # call cleanup actions on failure
                Log.info(self, Log.FAIL + "Oops Something went wrong !!")
                Log.info(self, Log.FAIL + "Calling cleanup actions ...")
                doCleanupAction(self, domain=ee_domain,
                                webroot=data['webroot'])
                Log.debug(self, str(e))
                Log.error(self, "Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")

            addNewSite(self, ee_domain, stype, cache, ee_site_webroot,
                       hhvm=hhvm, pagespeed=pagespeed)
            # Setup database for MySQL site
            if 'ee_db_name' in data.keys() and not data['wp']:
                try:
                    data = setupdatabase(self, data)
                    # Add database information for site into database
                    updateSiteInfo(self, ee_domain, db_name=data['ee_db_name'],
                                   db_user=data['ee_db_user'],
                                   db_password=data['ee_db_pass'],
                                   db_host=data['ee_db_host'])
                except SiteError as e:
                    # call cleanup actions on failure
                    Log.debug(self, str(e))
                    Log.info(self, Log.FAIL + "Oops Something went wrong !!")
                    Log.info(self, Log.FAIL + "Calling cleanup actions ...")
                    doCleanupAction(self, domain=ee_domain,
                                    webroot=data['webroot'],
                                    dbname=data['ee_db_name'],
                                    dbuser=data['ee_db_user'],
                                    dbhost=data['ee_db_host'])
                    deleteSiteInfo(self, ee_domain)
                    Log.error(self, "Check logs for reason "
                              "`tail /var/log/ee/ee.log` & Try Again!!!")

                try:
                    eedbconfig = open("{0}/ee-config.php"
                                      .format(ee_site_webroot),
                                      encoding='utf-8', mode='w')
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
                    Log.debug(self, str(e))
                    Log.debug(self, "Error occured while generating "
                              "ee-config.php")
                    Log.info(self, Log.FAIL + "Oops Something went wrong !!")
                    Log.info(self, Log.FAIL + "Calling cleanup actions ...")
                    doCleanupAction(self, domain=ee_domain,
                                    webroot=data['webroot'],
                                    dbname=data['ee_db_name'],
                                    dbuser=data['ee_db_user'],
                                    dbhost=data['ee_db_host'])
                    deleteSiteInfo(self, ee_domain)
                    Log.error(self, "Check logs for reason "
                              "`tail /var/log/ee/ee.log` & Try Again!!!")

            # Setup WordPress if Wordpress site
            if data['wp']:
                try:
                    ee_wp_creds = setupwordpress(self, data)
                    # Add database information for site into database
                    updateSiteInfo(self, ee_domain, db_name=data['ee_db_name'],
                                   db_user=data['ee_db_user'],
                                   db_password=data['ee_db_pass'],
                                   db_host=data['ee_db_host'])
                except SiteError as e:
                    # call cleanup actions on failure
                    Log.debug(self, str(e))
                    Log.info(self, Log.FAIL + "Oops Something went wrong !!")
                    Log.info(self, Log.FAIL + "Calling cleanup actions ...")
                    doCleanupAction(self, domain=ee_domain,
                                    webroot=data['webroot'],
                                    dbname=data['ee_db_name'],
                                    dbuser=data['ee_db_user'],
                                    dbhost=data['ee_db_host'])
                    deleteSiteInfo(self, ee_domain)
                    Log.error(self, "Check logs for reason "
                              "`tail /var/log/ee/ee.log` & Try Again!!!")

            # Service Nginx Reload
            EEService.reload_service(self, 'nginx')

            EEGit.add(self, ["/etc/nginx"],
                      msg="{0} created with {1} {2}"
                      .format(ee_www_domain, stype, cache))
            # Setup Permissions for webroot
            try:
                setwebrootpermissions(self, data['webroot'])
            except SiteError as e:
                Log.debug(self, str(e))
                Log.info(self, Log.FAIL + "Oops Something went wrong !!")
                Log.info(self, Log.FAIL + "Calling cleanup actions ...")
                doCleanupAction(self, domain=ee_domain,
                                webroot=data['webroot'],
                                dbname=data['ee_db_name'],
                                dbuser=data['ee_db_user'],
                                dbhost=data['ee_db_host'])
                deleteSiteInfo(self, ee_domain)
                Log.error(self, "Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")

            if ee_auth and len(ee_auth):
                for msg in ee_auth:
                    Log.info(self, Log.ENDC + msg, log=False)

            if data['wp']:
                Log.info(self, Log.ENDC + "WordPress admin user :"
                         " {0}".format(ee_wp_creds['wp_user']), log=False)
                Log.info(self, Log.ENDC + "WordPress admin user password : {0}"
                         .format(ee_wp_creds['wp_pass']), log=False)

            display_cache_settings(self, data)

            Log.info(self, "Successfully created site"
                     " http://{0}".format(ee_domain))
        except SiteError as e:
            Log.error(self, "Check logs for reason "
                      "`tail /var/log/ee/ee.log` & Try Again!!!")


class EESiteUpdateController(CementBaseController):
    class Meta:
        label = 'update'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = ('This command updates websites configuration to '
                       'another as per the options are provided')
        arguments = [
            (['site_name'],
                dict(help='domain name for the site to be updated',
                     nargs='?')),
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
            (['--hhvm'],
                dict(help='Use HHVM for site',
                     action='store' or 'store_const',
                     choices=('on', 'off'), const='on', nargs='?')),
            (['--pagespeed'],
                dict(help='Use PageSpeed for site',
                     action='store' or 'store_const',
                     choices=('on', 'off'), const='on', nargs='?'))
            ]

    @expose(help="Update site type or cache")
    def default(self):

        hhvm = None
        pagespeed = None

        data = dict()
        try:
            stype, cache = detSitePar(vars(self.app.pargs))
        except RuntimeError as e:
            Log.debug(self, str(e))
            Log.error(self, "Please provide valid options combination for"
                      " site update")

        if not self.app.pargs.site_name:
            try:
                self.app.pargs.site_name = input('Enter site name : ')
            except IOError as e:
                Log.error(self, 'Unable to input site name, Please try again!')

        (ee_domain,
         ee_www_domain, ) = ValidateDomain(self.app.pargs.site_name)
        ee_site_webroot = EEVariables.ee_webroot + ee_domain

        check_site = getSiteInfo(self, ee_domain)

        if check_site is None:
            Log.error(self, " Site {0} does not exist.".format(ee_domain))
        else:
            oldsitetype = check_site.site_type
            oldcachetype = check_site.cache_type
            old_hhvm = check_site.is_hhvm
            old_pagespeed = check_site.is_pagespeed

        if (self.app.pargs.password and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or self.app.pargs.wp or
            self.app.pargs.w3tc or self.app.pargs.wpfc or self.app.pargs.wpsc
           or self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):
            try:
                updatewpuserpassword(self, ee_domain, ee_site_webroot)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.error(self, "Password Unchanged.")
            self.app.close(0)

        if ((stype == 'php' and oldsitetype != 'html') or
            (stype == 'mysql' and oldsitetype not in ['html', 'php']) or
            (stype == 'wp' and oldsitetype not in ['html', 'php', 'mysql',
                                                   'wp']) or
            (stype == 'wpsubdir' and oldsitetype in ['wpsubdomain']) or
            (stype == 'wpsubdomain' and oldsitetype in ['wpsubdir']) or
           (stype == oldsitetype and cache == oldcachetype)):
            Log.error(self, "can not update {0} {1} to {2} {3}".
                      format(oldsitetype, oldcachetype, stype, cache))

        if stype == 'php':
            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot,
                        currsitetype=oldsitetype, currcachetype=oldcachetype)

        elif stype in ['mysql', 'wp', 'wpsubdir', 'wpsubdomain']:

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot,
                        ee_db_name='', ee_db_user='', ee_db_pass='',
                        ee_db_host='',
                        currsitetype=oldsitetype, currcachetype=oldcachetype)

            if stype in ['wp', 'wpsubdir', 'wpsubdomain']:
                data['wp'] = True
                data['basic'] = False
                data[cache] = True
                if stype in ['wpsubdir', 'wpsubdomain']:
                    data['multisite'] = True
                    if stype == 'wpsubdir':
                        data['wpsubdir'] = True

        if self.app.pargs.pagespeed or self.app.pargs.hhvm:
            if not data:
                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            currsitetype=oldsitetype,
                            currcachetype=oldcachetype,
                            webroot=ee_site_webroot)

                stype = oldsitetype
                cache = oldcachetype

                if oldsitetype == 'html':
                    data['static'] = True
                    data['wp'] = False
                    data['multisite'] = False
                    data['wpsubdir'] = False
                elif oldsitetype == 'php' or oldsitetype == 'mysql':
                    data['static'] = False
                    data['wp'] = False
                    data['multisite'] = False
                    data['wpsubdir'] = False
                elif oldsitetype == 'wp':
                    data['static'] = False
                    data['wp'] = True
                    data['multisite'] = False
                    data['wpsubdir'] = False
                elif oldsitetype == 'wpsubdir':
                    data['static'] = False
                    data['wp'] = True
                    data['multisite'] = True
                    data['wpsubdir'] = True
                elif oldsitetype == 'wpsubdomain':
                    data['static'] = False
                    data['wp'] = True
                    data['multisite'] = True
                    data['wpsubdir'] = False

                if oldcachetype == 'basic':
                    data['basic'] = True
                    data['w3tc'] = False
                    data['wpfc'] = False
                    data['wpsc'] = False
                elif oldcachetype == 'w3tc':
                    data['basic'] = False
                    data['w3tc'] = True
                    data['wpfc'] = False
                    data['wpsc'] = False
                elif oldcachetype == 'wpfc':
                    data['basic'] = False
                    data['w3tc'] = False
                    data['wpfc'] = True
                    data['wpsc'] = False
                elif oldcachetype == 'wpsc':
                    data['basic'] = False
                    data['w3tc'] = False
                    data['wpfc'] = False
                    data['wpsc'] = True

            if self.app.pargs.hhvm == 'on':
                data['hhvm'] = True
                hhvm = 1
            elif self.app.pargs.hhvm == 'off':
                data['hhvm'] = False
                hhvm = 0

            if self.app.pargs.pagespeed == 'on':
                data['pagespeed'] = True
                pagespeed = 1
            elif self.app.pargs.pagespeed == 'off':
                data['pagespeed'] = False
                pagespeed = 0

        if data and (not hhvm):
            if old_hhvm == 1:
                data['hhvm'] = True
                hhvm = 1
            else:
                data['hhvm'] = False
                hhvm = 0

        if data and (not pagespeed):
            if old_pagespeed == 1:
                data['pagespeed'] = True
                pagespeed = 1
            else:
                data['pagespeed'] = False
                pagespeed = 0

        if not data:
            Log.error(self, " Cannot update {0}, Invalid Options"
                      .format(ee_domain))

        ee_auth = site_package_check(self, stype)

        sitebackup(self, data)

        # setup NGINX configuration, and webroot
        try:
            setupdomain(self, data)
        except SiteError as e:
            Log.debug(self, str(e))
            Log.error(self, "Update site failed. Check logs for reason "
                      "`tail /var/log/ee/ee.log` & Try Again!!!")

        if stype == oldsitetype and cache == oldcachetype:

            # Service Nginx Reload
            EEService.reload_service(self, 'nginx')

            updateSiteInfo(self, ee_domain, stype=stype, cache=cache,
                           hhvm=hhvm, pagespeed=pagespeed)

            Log.info(self, "Successfully updated site"
                     " http://{0}".format(ee_domain))
            self.app.close(0)

        if 'ee_db_name' in data.keys() and not data['wp']:
            try:
                data = setupdatabase(self, data)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.error(self, "Update site failed. Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")
            try:
                eedbconfig = open("{0}/ee-config.php".format(ee_site_webroot),
                                  encoding='utf-8', mode='w')
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
                Log.debug(self, str(e))
                Log.debug(self, "creating ee-config.php failed.")
                Log.error(self, "Update site failed. Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")

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
            data['ee_db_host'] = (EEFileUtils.grep(self, config_file,
                                  'DB_HOST')
                                  .split(',')[1]
                                  .split(')')[0].strip())

        # Setup WordPress if old sites are html/php/mysql sites
        if data['wp'] and oldsitetype in ['html', 'php', 'mysql']:
            try:
                ee_wp_creds = setupwordpress(self, data)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.error(self, "Update site failed. Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")

        # Uninstall unnecessary plugins
        if oldsitetype in ['wp', 'wpsubdir', 'wpsubdomain']:
            # Setup WordPress Network if update option is multisite
            # and oldsite is WordPress single site
            if data['multisite'] and oldsitetype == 'wp':
                try:
                    setupwordpressnetwork(self, data)
                except SiteError as e:
                    Log.debug(self, str(e))
                    Log.error(self, "Update site failed. Check logs for reason"
                              " `tail /var/log/ee/ee.log` & Try Again!!!")

            if (oldcachetype == 'w3tc' or oldcachetype == 'wpfc' and
               not (data['w3tc'] or data['wpfc'])):
                try:
                    uninstallwp_plugin(self, 'w3-total-cache', data)
                except SiteError as e:
                    Log.debug(self, str(e))
                    Log.error(self, "Update site failed. Check logs for reason"
                              " `tail /var/log/ee/ee.log` & Try Again!!!")

            if oldcachetype == 'wpsc' and not data['wpsc']:
                try:
                    uninstallwp_plugin(self, 'wp-super-cache', data)
                except SiteError as e:
                    Log.debug(self, str(e))
                    Log.error(self, "Update site failed. Check logs for reason"
                              " `tail /var/log/ee/ee.log` & Try Again!!!")

        if (oldcachetype != 'w3tc' or oldcachetype != 'wpfc') and (data['w3tc']
           or data['wpfc']):
            try:
                installwp_plugin(self, 'w3-total-cache', data)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.error(self, "Update site failed. Check logs for reason"
                          " `tail /var/log/ee/ee.log` & Try Again!!!")

        if oldcachetype != 'wpsc' and data['wpsc']:
            try:
                installwp_plugin(self, 'wp-super-cache', data)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.error(self, "Update site failed. Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")

        # Service Nginx Reload
        EEService.reload_service(self, 'nginx')

        EEGit.add(self, ["/etc/nginx"],
                  msg="{0} updated with {1} {2}"
                  .format(ee_www_domain, stype, cache))
        # Setup Permissions for webroot
        try:
            setwebrootpermissions(self, data['webroot'])
        except SiteError as e:
            Log.debug(self, str(e))
            Log.error(self, "Update site failed. Check logs for reason "
                      "`tail /var/log/ee/ee.log` & Try Again!!!")

        if ee_auth and len(ee_auth):
            for msg in ee_auth:
                Log.info(self, Log.ENDC + msg)

        display_cache_settings(self, data)
        if data['wp'] and oldsitetype in ['html', 'php', 'mysql']:
            Log.info(self, "\n\n" + Log.ENDC + "WordPress admin user :"
                     " {0}".format(ee_wp_creds['wp_user']))
            Log.info(self, Log.ENDC + "WordPress admin password : {0}"
                     .format(ee_wp_creds['wp_pass']) + "\n\n")
        if oldsitetype in ['html', 'php'] and stype != 'php':
            updateSiteInfo(self, ee_domain, stype=stype, cache=cache,
                           db_name=data['ee_db_name'],
                           db_user=data['ee_db_user'],
                           db_password=data['ee_db_pass'],
                           db_host=data['ee_db_host'], hhvm=hhvm,
                           pagespeed=pagespeed)
        else:
            updateSiteInfo(self, ee_domain, stype=stype, cache=cache,
                           hhvm=hhvm, pagespeed=pagespeed)
        Log.info(self, "Successfully updated site"
                 " http://{0}".format(ee_domain))


class EESiteDeleteController(CementBaseController):
    class Meta:
        label = 'delete'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = 'delete an existing website'
        arguments = [
            (['site_name'],
                dict(help='domain name to be deleted', nargs='?')),
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
    @expose(hide=True)
    def default(self):
        if not self.app.pargs.site_name:
            try:
                self.app.pargs.site_name = input('Enter site name : ')
            except IOError as e:
                Log.error(self, 'could not input site name')
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_db_name = ''
        ee_prompt = ''
        ee_nginx_prompt = ''
        mark_db_deleted = False
        mark_webroot_deleted = False
        if not check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} does not exist".format(ee_domain))

        if ((not self.app.pargs.db) and (not self.app.pargs.files) and
           (not self.app.pargs.all)):
            self.app.pargs.all = True

        # Gather information from ee-db for ee_domain
        check_site = getSiteInfo(self, ee_domain)
        ee_site_type = check_site.site_type
        ee_site_webroot = check_site.site_path
        if ee_site_webroot == 'deleted':
            mark_webroot_deleted = True
        if ee_site_type in ['mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
            ee_db_name = check_site.db_name
            ee_db_user = check_site.db_user
            ee_db_host = check_site.db_host
            if ee_db_name == 'deleted':
                mark_db_deleted = True
            if self.app.pargs.all:
                self.app.pargs.db = True
                self.app.pargs.files = True
        else:
            if self.app.pargs.all:
                mark_db_deleted = True
                self.app.pargs.files = True

        # Delete website database
        if self.app.pargs.db:
            if ee_db_name != 'deleted':
                if not self.app.pargs.no_prompt:
                    ee_db_prompt = input('Are you sure, you want to delete'
                                         ' database [y/N]: ')
                else:
                    ee_db_prompt = 'Y'

                if ee_db_prompt == 'Y' or ee_db_prompt == 'y':
                    Log.info(self, "Deleting Database, {0}, user {1}"
                             .format(ee_db_name, ee_db_user))
                    deleteDB(self, ee_db_name, ee_db_user, ee_db_host)
                    updateSiteInfo(self, ee_domain,
                                   db_name='deleted',
                                   db_user='deleted',
                                   db_password='deleted')
                    mark_db_deleted = True
                    Log.info(self, "Deleted Database successfully.")
            else:
                mark_db_deleted = True
                Log.info(self, "Database seems to be deleted.")

        # Delete webroot
        if self.app.pargs.files:
            if ee_site_webroot != 'deleted':
                if not self.app.pargs.no_prompt:
                    ee_web_prompt = input('Are you sure, you want to delete '
                                          'webroot [y/N]: ')
                else:
                    ee_web_prompt = 'Y'

                if ee_web_prompt == 'Y' or ee_web_prompt == 'y':
                    Log.info(self, "Deleting Webroot, {0}"
                             .format(ee_site_webroot))
                    deleteWebRoot(self, ee_site_webroot)
                    updateSiteInfo(self, ee_domain, webroot='deleted')
                    mark_webroot_deleted = True
                    Log.info(self, "Deleted webroot successfully")
            else:
                mark_webroot_deleted = True
                Log.info(self, "Webroot seems to be already deleted")

        if (mark_webroot_deleted and mark_db_deleted):
                # TODO Delete nginx conf
                removeNginxConf(self, ee_domain)
                deleteSiteInfo(self, ee_domain)
                Log.info(self, "Deleted site {0}".format(ee_domain))
        # else:
        #     Log.error(self, " site {0} does not exists".format(ee_domain))


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
