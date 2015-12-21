# """EasyEngine site controller."""
from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.cron import EECron
from ee.core.sslutils import SSL
from ee.core.variables import EEVariables
from ee.core.domainvalidate import ValidateDomain
from ee.core.fileutils import EEFileUtils
from ee.cli.plugins.site_functions import *
from ee.core.services import EEService
from ee.cli.plugins.sitedb import *
from ee.core.git import EEGit
from subprocess import Popen
from ee.core.nginxhashbucket import hashbucket
import sys
import os
import glob
import subprocess


def ee_site_hook(app):
    # do something with the ``app`` object here.
    from ee.core.database import init_db
    import ee.cli.plugins.models
    init_db(app)


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
                while not self.app.pargs.site_name:
                    self.app.pargs.site_name = (input('Enter site name : ')
                                                .strip())
            except IOError as e:
                Log.error(self, 'could not input site name')

        self.app.pargs.site_name = self.app.pargs.site_name.strip()
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
            if not EEService.reload_service(self, 'nginx'):
                Log.error(self, "service nginx reload failed. "
                          "check issues with `nginx -t` command")
        else:
            Log.error(self, "nginx configuration file does not exist"
                      .format(ee_domain))

    @expose(help="Disable site example.com")
    def disable(self):
        if not self.app.pargs.site_name:
            try:
                while not self.app.pargs.site_name:
                    self.app.pargs.site_name = (input('Enter site name : ')
                                                .strip())

            except IOError as e:
                Log.error(self, 'could not input site name')
        self.app.pargs.site_name = self.app.pargs.site_name.strip()
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
                if not EEService.reload_service(self, 'nginx'):
                    Log.error(self, "service nginx reload failed. "
                              "check issues with `nginx -t` command")
        else:
            Log.error(self, "nginx configuration file does not exist"
                      .format(ee_domain))

    @expose(help="Get example.com information")
    def info(self):
        if not self.app.pargs.site_name:
            try:
                while not self.app.pargs.site_name:
                    self.app.pargs.site_name = (input('Enter site name : ')
                                                .strip())
            except IOError as e:
                Log.error(self, 'could not input site name')
        self.app.pargs.site_name = self.app.pargs.site_name.strip()
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_db_name = ''
        ee_db_user = ''
        ee_db_pass = ''
        hhvm = ''

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
            if sitetype != "html":
                hhvm = ("enabled" if siteinfo.is_hhvm else "disabled")
            if sitetype == "proxy":
                access_log = "/var/log/nginx/{0}.access.log".format(ee_domain)
                error_log = "/var/log/nginx/{0}.error.log".format(ee_domain)
                ee_site_webroot = ''

            pagespeed = ("enabled" if siteinfo.is_pagespeed else "disabled")
            ssl = ("enabled" if siteinfo.is_ssl else "disabled")
            if (ssl == "enabled"):
                sslprovider = "Lets Encrypt"
                sslexpiry = str(SSL.getExpirationDate(self,ee_domain))
            else:
                sslprovider = ''
                sslexpiry = ''
            data = dict(domain=ee_domain, webroot=ee_site_webroot,
                        accesslog=access_log, errorlog=error_log,
                        dbname=ee_db_name, dbuser=ee_db_user,
                        dbpass=ee_db_pass, hhvm=hhvm, pagespeed=pagespeed,
                        ssl=ssl, sslprovider=sslprovider,  sslexpiry= sslexpiry,
                        type=sitetype + " " + cachetype + " ({0})"
                        .format("enabled" if siteinfo.is_enabled else
                                "disabled"))
            self.app.render((data), 'siteinfo.mustache')
        else:
            Log.error(self, "nginx configuration file does not exist"
                      .format(ee_domain))

    @expose(help="Monitor example.com logs")
    def log(self):
        self.app.pargs.site_name = self.app.pargs.site_name.strip()
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_site_webroot = getSiteInfo(self, ee_domain).site_path

        if not check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} does not exist".format(ee_domain))
        logfiles = glob.glob(ee_site_webroot + '/logs/*.log')
        if logfiles:
            logwatch(self, logfiles)

    @expose(help="Display Nginx configuration of example.com")
    def show(self):
        if not self.app.pargs.site_name:
            try:
                while not self.app.pargs.site_name:
                    self.app.pargs.site_name = (input('Enter site name : ')
                                                .strip())
            except IOError as e:
                Log.error(self, 'could not input site name')
        # TODO Write code for ee site edit command here
        self.app.pargs.site_name = self.app.pargs.site_name.strip()
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
                while not self.app.pargs.site_name:
                    self.app.pargs.site_name = (input('Enter site name : ')
                                                .strip())
            except IOError as e:
                Log.error(self, 'Unable to read input, please try again')

        self.app.pargs.site_name = self.app.pargs.site_name.strip()
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


class EESiteEditController(CementBaseController):
    class Meta:
        label = 'edit'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = ('Edit Nginx configuration of site')
        arguments = [
            (['site_name'],
                dict(help='domain name for the site',
                     nargs='?')),
            (['--pagespeed'],
                dict(help="edit pagespeed configuration for site",
                     action='store_true')),
            ]

    @expose(hide=True)
    def default(self):
        if not self.app.pargs.site_name:
            try:
                while not self.app.pargs.site_name:
                    self.app.pargs.site_name = (input('Enter site name : ')
                                                .strip())
            except IOError as e:
                Log.error(self, 'Unable to read input, Please try again')

        self.app.pargs.site_name = self.app.pargs.site_name.strip()
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)

        if not check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} does not exist".format(ee_domain))

        ee_site_webroot = EEVariables.ee_webroot + ee_domain

        if not self.app.pargs.pagespeed:
            if os.path.isfile('/etc/nginx/sites-available/{0}'
                              .format(ee_domain)):
                try:
                    EEShellExec.invoke_editor(self, '/etc/nginx/sites-availa'
                                              'ble/{0}'.format(ee_domain))
                except CommandExecutionError as e:
                    Log.error(self, "Failed invoke editor")
                if (EEGit.checkfilestatus(self, "/etc/nginx",
                   '/etc/nginx/sites-available/{0}'.format(ee_domain))):
                    EEGit.add(self, ["/etc/nginx"], msg="Edit website: {0}"
                              .format(ee_domain))
                    # Reload NGINX
                    if not EEService.reload_service(self, 'nginx'):
                        Log.error(self, "service nginx reload failed. "
                                  "check issues with `nginx -t` command")
            else:
                Log.error(self, "nginx configuration file does not exists"
                          .format(ee_domain))

        elif self.app.pargs.pagespeed:
            if os.path.isfile('{0}/conf/nginx/pagespeed.conf'
                              .format(ee_site_webroot)):
                try:
                    EEShellExec.invoke_editor(self, '{0}/conf/nginx/'
                                              'pagespeed.conf'
                                              .format(ee_site_webroot))
                except CommandExecutionError as e:
                    Log.error(self, "Failed invoke editor")
                if (EEGit.checkfilestatus(self, "{0}/conf/nginx"
                   .format(ee_site_webroot),
                   '{0}/conf/nginx/pagespeed.conf'.format(ee_site_webroot))):
                    EEGit.add(self, ["{0}/conf/nginx".format(ee_site_webroot)],
                              msg="Edit Pagespped config of site: {0}"
                              .format(ee_domain))
                    # Reload NGINX
                    if not EEService.reload_service(self, 'nginx'):
                        Log.error(self, "service nginx reload failed. "
                                  "check issues with `nginx -t` command")
            else:
                Log.error(self, "Pagespeed configuration file does not exists"
                          .format(ee_domain))


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
            (['--wpredis'],
                dict(help="create wordpress single/multi site with redis cache",
                     action='store_true')),
            (['--hhvm'],
                dict(help="create HHVM site", action='store_true')),
            (['--pagespeed'],
                dict(help="create pagespeed site", action='store_true')),
            (['-le','--letsencrypt'],
                dict(help="configure letsencrypt ssl for the site", action='store_true')),
            (['--user'],
                dict(help="provide user for wordpress site")),
            (['--email'],
                dict(help="provide email address for wordpress site")),
            (['--pass'],
                dict(help="provide password for wordpress user",
                     dest='wppass')),
            (['--proxy'],
                dict(help="create proxy for site", nargs='+')),
            (['--experimental'],
                dict(help="Enable Experimenal packages without prompt",
                     action='store_true')),
            ]

    @expose(hide=True)
    def default(self):
        # self.app.render((data), 'default.mustache')
        # Check domain name validation
        data = dict()
        host, port = None, None
        try:
            stype, cache = detSitePar(vars(self.app.pargs))
        except RuntimeError as e:
            Log.debug(self, str(e))
            Log.error(self, "Please provide valid options to creating site")

        if stype is None and self.app.pargs.proxy:
            stype, cache = 'proxy', ''
            proxyinfo = self.app.pargs.proxy[0].strip()
            if not proxyinfo:
                Log.error(self, "Please provide proxy server host information")
            proxyinfo = proxyinfo.split(':')
            host = proxyinfo[0].strip()
            port = '80' if len(proxyinfo) < 2 else proxyinfo[1].strip()
        elif stype is None and not self.app.pargs.proxy:
            stype, cache = 'html', 'basic'
        elif stype and self.app.pargs.proxy:
            Log.error(self, "proxy should not be used with other site types")
        if (self.app.pargs.proxy and (self.app.pargs.pagespeed
           or self.app.pargs.hhvm)):
            Log.error(self, "Proxy site can not run on pagespeed or hhvm")

        if not self.app.pargs.site_name:
            try:
                while not self.app.pargs.site_name:
                    # preprocessing before finalize site name
                    self.app.pargs.site_name = (input('Enter site name : ')
                                                .strip())
            except IOError as e:
                Log.debug(self, str(e))
                Log.error(self, "Unable to input site name, Please try again!")

        self.app.pargs.site_name = self.app.pargs.site_name.strip()
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)

        if not ee_domain.strip():
            Log.error("Invalid domain name, "
                      "Provide valid domain name")

        ee_site_webroot = EEVariables.ee_webroot + ee_domain

        if check_domain_exists(self, ee_domain):
            Log.error(self, "site {0} already exists".format(ee_domain))
        elif os.path.isfile('/etc/nginx/sites-available/{0}'
                            .format(ee_domain)):
            Log.error(self, "Nginx configuration /etc/nginx/sites-available/"
                      "{0} already exists".format(ee_domain))

        if stype == 'proxy':
            data['site_name'] = ee_domain
            data['www_domain'] = ee_www_domain
            data['proxy'] = True
            data['host'] = host
            data['port'] = port
            ee_site_webroot = ""

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
                        wpfc=False, wpsc=False, wpredis=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot,
                        ee_db_name='', ee_db_user='', ee_db_pass='',
                        ee_db_host='')

            if stype in ['wp', 'wpsubdir', 'wpsubdomain']:
                data['wp'] = True
                data['basic'] = False
                data[cache] = True
                data['wp-user'] = self.app.pargs.user
                data['wp-email'] = self.app.pargs.email
                data['wp-pass'] = self.app.pargs.wppass
                if stype in ['wpsubdir', 'wpsubdomain']:
                    data['multisite'] = True
                    if stype == 'wpsubdir':
                        data['wpsubdir'] = True
        else:
            pass

        if stype == "html" and self.app.pargs.hhvm:
            Log.error(self, "Can not create HTML site with HHVM")

        if data and self.app.pargs.hhvm:
            if (not self.app.pargs.experimental):
                Log.info(self, "HHVM is experimental feature and it may not "
                         "work with all plugins of your site.\nYou can "
                         "disable it by passing --hhvm=off later.\nDo you wish"
                         " to enable HHVM now for {0}?".format(ee_domain))

                # Check prompt
                check_prompt = input("Type \"y\" to continue [n]:")
                if check_prompt != "Y" and check_prompt != "y":
                    Log.info(self, "Not using HHVM for site.")
                    data['hhvm'] = False
                    hhvm = 0
                    self.app.pargs.hhvm = False
                else:
                    data['hhvm'] = True
                    hhvm = 1
            else:
                data['hhvm'] = True
                hhvm = 1

        elif data:
            data['hhvm'] = False
            hhvm = 0

        if data and self.app.pargs.pagespeed:
            if (not self.app.pargs.experimental):
                Log.info(self, "PageSpeed is experimental feature and it may not "
                         "work with all CSS/JS/Cache of your site.\nYou can "
                         "disable it by passing --pagespeed=off later.\nDo you wish"
                         " to enable PageSpeed now for {0}?".format(ee_domain))

                # Check prompt
                check_prompt = input("Type \"y\" to continue [n]:")
                if check_prompt != "Y" and check_prompt != "y":
                    Log.info(self, "Not using PageSpeed for site.")
                    data['pagespeed'] = False
                    pagespeed = 0
                    self.app.pargs.pagespeed = False
                else:
                    data['pagespeed'] = True
                    pagespeed = 1
            else:
                data['pagespeed'] = True
                pagespeed = 1
        elif data:
            data['pagespeed'] = False
            pagespeed = 0

        if (cache == 'wpredis' and (not self.app.pargs.experimental)):
            Log.info(self, "Redis is experimental feature and it may not "
                     "work with all CSS/JS/Cache of your site.\nYou can "
                     "disable it by changing cache later.\nDo you wish"
                     " to enable Redis now for {0}?".format(ee_domain))

                # Check prompt
            check_prompt = input("Type \"y\" to continue [n]:")
            if check_prompt != "Y" and check_prompt != "y":
                Log.error(self, "Not using Redis for site")
                cache = 'basic'
                data['wpredis'] = False
                data['basic'] = True
                self.app.pargs.wpredis = False

        #     self.app.args.print_help()
        # if not data:
        #     self.app.close(1)

        # Check rerequired packages are installed or not
        ee_auth = site_package_check(self, stype)

        try:
            pre_run_checks(self)
        except SiteError as e:
            Log.debug(self, str(e))
            Log.error(self, "NGINX configuration check failed.")

        try:
            try:
                # setup NGINX configuration, and webroot
                setupdomain(self, data)

                # Fix Nginx Hashbucket size error
                hashbucket(self)
            except SiteError as e:
                # call cleanup actions on failure
                Log.info(self, Log.FAIL + "Oops Something went wrong !!")
                Log.info(self, Log.FAIL + "Calling cleanup actions ...")
                doCleanupAction(self, domain=ee_domain,
                                webroot=data['webroot'])
                Log.debug(self, str(e))
                Log.error(self, "Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")

            if 'proxy' in data.keys() and data['proxy']:
                addNewSite(self, ee_domain, stype, cache, ee_site_webroot)
                # Service Nginx Reload
                if not EEService.reload_service(self, 'nginx'):
                    Log.info(self, Log.FAIL + "Oops Something went wrong !!")
                    Log.info(self, Log.FAIL + "Calling cleanup actions ...")
                    doCleanupAction(self, domain=ee_domain)
                    Log.debug(self, str(e))
                    Log.error(self, "service nginx reload failed. "
                              "check issues with `nginx -t` command")
                    Log.error(self, "Check logs for reason "
                              "`tail /var/log/ee/ee.log` & Try Again!!!")
                if ee_auth and len(ee_auth):
                    for msg in ee_auth:
                        Log.info(self, Log.ENDC + msg, log=False)
                Log.info(self, "Successfully created site"
                         " http://{0}".format(ee_domain))
                return
            # Update pagespeed config
            if self.app.pargs.pagespeed:
                operateOnPagespeed(self, data)

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
                                    dbhost=data['ee_mysql_grant_host'])
                    deleteSiteInfo(self, ee_domain)
                    Log.error(self, "Check logs for reason "
                              "`tail /var/log/ee/ee.log` & Try Again!!!")

            # Service Nginx Reload call cleanup if failed to reload nginx
            if not EEService.reload_service(self, 'nginx'):
                Log.info(self, Log.FAIL + "Oops Something went wrong !!")
                Log.info(self, Log.FAIL + "Calling cleanup actions ...")
                doCleanupAction(self, domain=ee_domain,
                                webroot=data['webroot'])
                if 'ee_db_name' in data.keys():
                    doCleanupAction(self, domain=ee_domain,
                                    dbname=data['ee_db_name'],
                                    dbuser=data['ee_db_user'],
                                    dbhost=data['ee_mysql_grant_host'])
                deleteSiteInfo(self, ee_domain)
                Log.info(self, Log.FAIL + "service nginx reload failed."
                         " check issues with `nginx -t` command.")
                Log.error(self, "Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")

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
                                webroot=data['webroot'])
                if 'ee_db_name' in data.keys():
                    print("Inside db cleanup")
                    doCleanupAction(self, domain=ee_domain,
                                    dbname=data['ee_db_name'],
                                    dbuser=data['ee_db_user'],
                                    dbhost=data['ee_mysql_grant_host'])
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

        if self.app.pargs.letsencrypt :
            if (not self.app.pargs.experimental):
                Log.info(self, "Letsencrypt is currently in beta phase."
                             " \nDo you wish"
                             " to enable SSl now for {0}?".format(ee_domain))

                # Check prompt
                check_prompt = input("Type \"y\" to continue [n]:")
                if check_prompt != "Y" and check_prompt != "y":
                    data['letsencrypt'] = False
                    letsencrypt = False
                else:
                    data['letsencrypt'] = True
                    letsencrypt = True
            else:
                 data['letsencrypt'] = True
                 letsencrypt = True

            if data['letsencrypt'] is True:
                 setupLetsEncrypt(self, ee_domain)
                 httpsRedirect(self,ee_domain)
                 Log.info(self,"Creating Cron Job for cert auto-renewal")
                 EECron.setcron_daily(self,'ee site update {0} --le=renew --min_expiry_limit 30 2> /dev/null'.format(ee_domain),'Renew '
                                                                     'letsencrypt SSL cert. Set by EasyEngine')

                 if not EEService.reload_service(self, 'nginx'):
                    Log.error(self, "service nginx reload failed. "
                          "check issues with `nginx -t` command")

                 Log.info(self, "Congratulations! Successfully Configured SSl for Site "
                         " https://{0}".format(ee_domain))

                 if (SSL.getExpirationDays(self,ee_domain)>0):
                    Log.info(self, "Your cert will expire within " + str(SSL.getExpirationDays(self,ee_domain)) + " days.")
                 else:
                    Log.warn(self, "Your cert already EXPIRED ! .PLEASE renew soon . ")

                 # Add nginx conf folder into GIT
                 EEGit.add(self, ["{0}/conf/nginx".format(ee_site_webroot)],
                          msg="Adding letsencrypts config of site: {0}"
                        .format(ee_domain))
                 updateSiteInfo(self, ee_domain, ssl=letsencrypt)

            elif data['letsencrypt'] is False:
                Log.info(self, "Not using Let\'s encrypt for Site "
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
            (['--wpredis'],
                dict(help="update to redis cache", action='store_true')),
            (['--hhvm'],
                dict(help='Use HHVM for site',
                     action='store' or 'store_const',
                     choices=('on', 'off'), const='on', nargs='?')),
            (['--pagespeed'],
                dict(help='Use PageSpeed for site',
                     action='store' or 'store_const',
                     choices=('on', 'off'), const='on', nargs='?')),
            (['-le','--letsencrypt'],
                dict(help="configure letsencrypt ssl for the site",
                     action='store' or 'store_const',
                     choices=('on', 'off', 'renew'), const='on', nargs='?')),
            (['--min_expiry_limit'],
                dict(help="pass minimum expiry days to renew let's encrypt cert")),
            (['--proxy'],
                dict(help="update to proxy site", nargs='+')),
            (['--experimental'],
                dict(help="Enable Experimenal packages without prompt",
                     action='store_true')),
            (['--all'],
                dict(help="update all sites", action='store_true')),
            ]

    @expose(help="Update site type or cache")
    def default(self):
        pargs = self.app.pargs

        if pargs.all:
            if pargs.site_name:
                Log.error(self, "`--all` option cannot be used with site name"
                          " provided")
            if pargs.html:
                Log.error(self, "No site can be updated to html")

            if not (pargs.php or
                    pargs.mysql or pargs.wp or pargs.wpsubdir or
                    pargs.wpsubdomain or pargs.w3tc or pargs.wpfc or
                    pargs.wpsc or pargs.hhvm or pargs.pagespeed or pargs.wpredis or pargs.letsencrypt):
                Log.error(self, "Please provide options to update sites.")

        if pargs.all:
            if pargs.site_name:
                Log.error(self, "`--all` option cannot be used with site name"
                          " provided")

            sites = getAllsites(self)
            if not sites:
                pass
            else:
                for site in sites:
                    pargs.site_name = site.sitename
                    Log.info(self, Log.ENDC + Log.BOLD + "Updating site {0},"
                             " please wait..."
                             .format(pargs.site_name))
                    self.doupdatesite(pargs)
                    print("\n")
        else:
            self.doupdatesite(pargs)

    def doupdatesite(self, pargs):
        hhvm = None
        pagespeed = None
        letsencrypt = False

        data = dict()
        try:
            stype, cache = detSitePar(vars(pargs))
        except RuntimeError as e:
            Log.debug(self, str(e))
            Log.error(self, "Please provide valid options combination for"
                      " site update")

        if stype is None and pargs.proxy:
            stype, cache = 'proxy', ''
            proxyinfo = pargs.proxy[0].strip()
            if not proxyinfo:
                Log.error(self, "Please provide proxy server host information")
            proxyinfo = proxyinfo.split(':')
            host = proxyinfo[0].strip()
            port = '80' if len(proxyinfo) < 2 else proxyinfo[1].strip()
        elif stype is None and not pargs.proxy:
            stype, cache = 'html', 'basic'
        elif stype and pargs.proxy:
            Log.error(self, "--proxy can not be used with other site types")
        if (pargs.proxy and (pargs.pagespeed or pargs.hhvm)):
            Log.error(self, "Proxy site can not run on pagespeed or hhvm")

        if not pargs.site_name:
            try:
                while not pargs.site_name:
                    pargs.site_name = (input('Enter site name : ').strip())
            except IOError as e:
                Log.error(self, 'Unable to input site name, Please try again!')

        pargs.site_name = pargs.site_name.strip()
        (ee_domain,
         ee_www_domain, ) = ValidateDomain(pargs.site_name)
        ee_site_webroot = EEVariables.ee_webroot + ee_domain

        check_site = getSiteInfo(self, ee_domain)

        if check_site is None:
            Log.error(self, " Site {0} does not exist.".format(ee_domain))
        else:
            oldsitetype = check_site.site_type
            oldcachetype = check_site.cache_type
            old_hhvm = check_site.is_hhvm
            old_pagespeed = check_site.is_pagespeed
            check_ssl = check_site.is_ssl

        if (pargs.password and not (pargs.html or
            pargs.php or pargs.mysql or pargs.wp or
            pargs.w3tc or pargs.wpfc or pargs.wpsc
           or pargs.wpsubdir or pargs.wpsubdomain)):
            try:
                updatewpuserpassword(self, ee_domain, ee_site_webroot)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.info(self, "\nPassword Unchanged.")
            return 0

        if ((stype == "proxy" and stype == oldsitetype and self.app.pargs.hhvm)
            or (stype == "proxy" and
                stype == oldsitetype and self.app.pargs.pagespeed)):
                Log.info(self, Log.FAIL +
                         "Can not update proxy site to HHVM or Pagespeed")
                return 1
        if stype == "html" and stype == oldsitetype and self.app.pargs.hhvm:
            Log.info(self, Log.FAIL + "Can not update HTML site to HHVM")
            return 1

        if ((stype == 'php' and oldsitetype not in ['html', 'proxy']) or
            (stype == 'mysql' and oldsitetype not in ['html', 'php',
                                                      'proxy']) or
            (stype == 'wp' and oldsitetype not in ['html', 'php', 'mysql',
                                                   'proxy', 'wp']) or
            (stype == 'wpsubdir' and oldsitetype in ['wpsubdomain']) or
            (stype == 'wpsubdomain' and oldsitetype in ['wpsubdir']) or
           (stype == oldsitetype and cache == oldcachetype) and
           not pargs.pagespeed):
            Log.info(self, Log.FAIL + "can not update {0} {1} to {2} {3}".
                     format(oldsitetype, oldcachetype, stype, cache))
            return 1

        if stype == 'proxy':
            data['site_name'] = ee_domain
            data['www_domain'] = ee_www_domain
            data['proxy'] = True
            data['host'] = host
            data['port'] = port
            pagespeed = False
            hhvm = False
            data['webroot'] = ee_site_webroot
            data['currsitetype'] = oldsitetype
            data['currcachetype'] = oldcachetype

        if stype == 'php':
            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, wpredis=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot,
                        currsitetype=oldsitetype, currcachetype=oldcachetype)

        elif stype in ['mysql', 'wp', 'wpsubdir', 'wpsubdomain']:

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, wpredis=False, multisite=False,
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

        if pargs.pagespeed or pargs.hhvm:
            if not data:
                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            currsitetype=oldsitetype,
                            currcachetype=oldcachetype,
                            webroot=ee_site_webroot)

                stype = oldsitetype
                cache = oldcachetype
                if oldsitetype == 'html' or oldsitetype == 'proxy':
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
                    data['wpredis'] = False
                elif oldcachetype == 'w3tc':
                    data['basic'] = False
                    data['w3tc'] = True
                    data['wpfc'] = False
                    data['wpsc'] = False
                    data['wpredis'] = False
                elif oldcachetype == 'wpfc':
                    data['basic'] = False
                    data['w3tc'] = False
                    data['wpfc'] = True
                    data['wpsc'] = False
                    data['wpredis'] = False
                elif oldcachetype == 'wpsc':
                    data['basic'] = False
                    data['w3tc'] = False
                    data['wpfc'] = False
                    data['wpsc'] = True
                    data['wpredis'] = False
                elif oldcachetype == 'wpredis':
                    data['basic'] = False
                    data['w3tc'] = False
                    data['wpfc'] = False
                    data['wpsc'] = False
                    data['wpredis'] = True

            if pargs.hhvm != 'off':
                data['hhvm'] = True
                hhvm = True
            elif pargs.hhvm == 'off':
                data['hhvm'] = False
                hhvm = False

            if pargs.pagespeed != 'off':
                data['pagespeed'] = True
                pagespeed = True
            elif pargs.pagespeed == 'off':
                data['pagespeed'] = False
                pagespeed = False


        if pargs.pagespeed:
            if pagespeed is old_pagespeed:
                if pagespeed is False:
                    Log.info(self, "Pagespeed is already disabled for given "
                             "site")
                elif pagespeed is True:
                    Log.info(self, "Pagespeed is already enabled for given "
                             "site")
                pargs.pagespeed = False

        #--letsencrypt=renew code goes here
        if pargs.letsencrypt == "renew" and not pargs.min_expiry_limit:
            if check_ssl:
                renewLetsEncrypt(self,ee_domain)
            else:
                Log.error(self,"Cannot RENEW ! SSL is not configured for given site .")

            Log.info(self, "SUCCESS: Certificate was successfully renewed For"
                           " https://{0}".format(ee_domain))
            if (SSL.getExpirationDays(self,ee_domain)>0):
                    Log.info(self, "Your cert will expire within " + str(SSL.getExpirationDays(self,ee_domain)) + " days.")
                    Log.info(self, "Expiration DATE: " + str(SSL.getExpirationDate(self,ee_domain)))

            else:
                    Log.warn(self, "Your cert already EXPIRED ! .PLEASE renew soon . ")

        if pargs.min_expiry_limit:
            if not pargs.min_expiry_limit>0 or not pargs.min_expiry_limit< 90:
                Log.error(self,'INVALID --min_expiry_limit argument provided. Please use range 1-89 .')

            if not pargs.letsencrypt == "renew":
                Log.error(self,'--min_expiry_limit parameter cannot be used as a standalone. Provide --le=renew')

            if not check_ssl:
                Log.error(self,"Cannot RENEW ! SSL is not configured for given site .")

            expiry_days = SSL.getExpirationDays(self,ee_domain)
            min_expiry_days = pargs.min_expiry_limit
            if (expiry_days <= min_expiry_days):
                renewLetsEncrypt(self,ee_domain)
                Log.info(self, "SUCCESS: Certificate was successfully renewed For"
                           " https://{0}".format(ee_domain))
            else:
                Log.info(self, "Not renewing SSL .")

            if (SSL.getExpirationDays(self,ee_domain)>0):
                    Log.info(self, "Your cert will expire within " + str(SSL.getExpirationDays(self,ee_domain)) + " days.")
                    Log.info(self, "Expiration DATE: " + str(SSL.getExpirationDate(self,ee_domain)))

            else:
                    Log.warn(self, "Your cert already EXPIRED ! .PLEASE renew soon . ")

        if pargs.letsencrypt:
            if pargs.letsencrypt == 'on':
                data['letsencrypt'] = True
                letsencrypt = True
            elif pargs.letsencrypt == 'off':
                data['letsencrypt'] = False
                letsencrypt = False

            if letsencrypt is check_ssl:
                if letsencrypt is False:
                    Log.error(self, "SSl is not configured for given "
                             "site")
                elif letsencrypt is True:
                    Log.error(self, "SSl is already configured for given "
                             "site")
                pargs.letsencrypt = False

        if pargs.hhvm:
            if hhvm is old_hhvm:
                if hhvm is False:
                    Log.info(self, "HHVM is allready disabled for given "
                             "site")
                elif hhvm is True:
                    Log.info(self, "HHVM is allready enabled for given "
                             "site")

                pargs.hhvm = False

        if data and (not pargs.hhvm):
            if old_hhvm is True:
                data['hhvm'] = True
                hhvm = True
            else:
                data['hhvm'] = False
                hhvm = False

        if data and (not pargs.pagespeed):
            if old_pagespeed is True:
                data['pagespeed'] = True
                pagespeed = True
            else:
                data['pagespeed'] = False
                pagespeed = False

        if pargs.pagespeed=="on" or pargs.hhvm=="on" or pargs.letsencrypt=="on":
            if pargs.hhvm == "on":
                if (not pargs.experimental):
                    Log.info(self, "HHVM is experimental feature and it may not"
                             " work with all plugins of your site.\nYou can "
                             "disable it by passing --hhvm=off later.\nDo you wish"
                             " to enable HHVM now for {0}?".format(ee_domain))

                    # Check prompt
                    check_prompt = input("Type \"y\" to continue [n]:")
                    if check_prompt != "Y" and check_prompt != "y":
                        Log.info(self, "Not using HHVM for site")
                        data['hhvm'] = False
                        hhvm = False
                    else:
                        data['hhvm'] = True
                        hhvm = True
                else:
                    data['hhvm'] = True
                    hhvm = True

            if pargs.pagespeed=="on":
                if (not pargs.experimental):
                    Log.info(self, "PageSpeed is experimental feature and it may not"
                             " work with all CSS/JS/Cache of your site.\nYou can "
                             "disable it by passing --pagespeed=off later.\nDo you wish"
                             " to enable PageSpeed now for {0}?".format(ee_domain))

                    # Check prompt
                    check_prompt = input("Type \"y\" to continue [n]:")
                    if check_prompt != "Y" and check_prompt != "y":
                        Log.info(self, "Not using Pagespeed for given site")
                        data['pagespeed'] = False
                        pagespeed = False
                    else:
                        data['pagespeed'] = True
                        pagespeed = True
                else:
                    data['pagespeed'] = True
                    pagespeed = True

            if pargs.letsencrypt == "on":

                if (not pargs.experimental):
                    Log.info(self, "Letsencrypt is currently in beta phase."
                             " \nDo you wish"
                             " to enable SSl now for {0}?".format(ee_domain))

                    # Check prompt
                    check_prompt = input("Type \"y\" to continue [n]:")
                    if check_prompt != "Y" and check_prompt != "y":
                        Log.info(self, "Not using letsencrypt for site")
                        data['letsencrypt'] = False
                        letsencrypt = False
                    else:
                        data['letsencrypt'] = True
                        letsencrypt = True
                else:
                    data['letsencrypt'] = True
                    letsencrypt = True



        if pargs.wpredis and data['currcachetype'] != 'wpredis':
            if (not pargs.experimental):
                Log.info(self, "Redis is experimental feature and it may not"
                         " work with all plugins of your site.\nYou can "
                         "disable it by changing cache type later.\nDo you wish"
                         " to enable Redis now for {0}?".format(ee_domain))

                # Check prompt
                check_prompt = input("Type \"y\" to continue [n]: ")
                if check_prompt != "Y" and check_prompt != "y":
                    Log.error(self, "Not using Redis for site")
                    data['wpredis'] = False
                    data['basic'] = True
                    cache = 'basic'

        if ((hhvm is old_hhvm) and (pagespeed is old_pagespeed) and
            (stype == oldsitetype and cache == oldcachetype)):
            return 1

        if not data:
            Log.error(self, "Cannot update {0}, Invalid Options"
                      .format(ee_domain))

        ee_auth = site_package_check(self, stype)
        data['ee_db_name'] = check_site.db_name
        data['ee_db_user'] = check_site.db_user
        data['ee_db_pass'] = check_site.db_password
        data['ee_db_host'] = check_site.db_host
        data['old_pagespeed_status'] = check_site.is_pagespeed

        if not pargs.letsencrypt:
            try:
                pre_run_checks(self)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.error(self, "NGINX configuration check failed.")

            try:
                sitebackup(self, data)
            except Exception as e:
                Log.debug(self, str(e))
                Log.info(self, Log.FAIL + "Check logs for reason "
                     "`tail /var/log/ee/ee.log` & Try Again!!!")
                return 1

            # setup NGINX configuration, and webroot
            try:
                setupdomain(self, data)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.info(self, Log.FAIL + "Update site failed."
                     "Check logs for reason"
                     "`tail /var/log/ee/ee.log` & Try Again!!!")
                return 1

        if 'proxy' in data.keys() and data['proxy']:
            updateSiteInfo(self, ee_domain, stype=stype, cache=cache,
                           hhvm=hhvm, pagespeed=pagespeed)
            Log.info(self, "Successfully updated site"
                     " http://{0}".format(ee_domain))
            return 0

        # Update pagespeed config
        if pargs.pagespeed:
            operateOnPagespeed(self, data)

        if pargs.letsencrypt:
            if data['letsencrypt'] is True:
                if not os.path.isfile("{0}/conf/nginx/ssl.conf.disabled"
                              .format(ee_site_webroot)):
                    setupLetsEncrypt(self, ee_domain)

                else:
                    EEFileUtils.mvfile(self, "{0}/conf/nginx/ssl.conf.disabled"
                               .format(ee_site_webroot),
                               '{0}/conf/nginx/ssl.conf'
                               .format(ee_site_webroot))

                httpsRedirect(self,ee_domain)
                Log.info(self,"Creating Cron Job for cert auto-renewal")
                EECron.setcron_daily(self,'ee site update {0} --le=renew --min_expiry_limit 30 2> /dev/null'.format(ee_domain),'Renew'
                                                                ' letsencrypt SSL cert. Set by EasyEngine')

                if not EEService.reload_service(self, 'nginx'):
                        Log.error(self, "service nginx reload failed. "
                          "check issues with `nginx -t` command")

                Log.info(self, "Congratulations! Successfully Configured SSl for Site "
                         " https://{0}".format(ee_domain))

                if (SSL.getExpirationDays(self,ee_domain)>0):
                    Log.info(self, "Your cert will expire within " + str(SSL.getExpirationDays(self,ee_domain)) + " days.")
                else:
                    Log.warn(self, "Your cert already EXPIRED ! .PLEASE renew soon . ")

            elif data['letsencrypt'] is False:
                Log.info(self,'Setting Nginx configuration')
                if os.path.isfile("{0}/conf/nginx/ssl.conf"
                          .format(ee_site_webroot)):
                        EEFileUtils.mvfile(self, "{0}/conf/nginx/ssl.conf"
                                  .format(ee_site_webroot),
                                  '{0}/conf/nginx/ssl.conf.disabled'
                                  .format(ee_site_webroot))
                        httpsRedirect(self,ee_domain,False)
                        if not EEService.reload_service(self, 'nginx'):
                            Log.error(self, "service nginx reload failed. "
                                 "check issues with `nginx -t` command")
                        Log.info(self,"Removing Cron Job set for cert auto-renewal")
                        EECron.remove_cron(self,'ee site update {0} --le=renew --min_expiry_limit 30 2> \/dev\/null'.format(ee_domain))
                        Log.info(self, "Successfully Disabled SSl for Site "
                         " http://{0}".format(ee_domain))


            # Add nginx conf folder into GIT
            EEGit.add(self, ["{0}/conf/nginx".format(ee_site_webroot)],
                          msg="Adding letsencrypts config of site: {0}"
                        .format(ee_domain))
            updateSiteInfo(self, ee_domain, ssl=letsencrypt)
            return 0

        if stype == oldsitetype and cache == oldcachetype:

            # Service Nginx Reload
            if not EEService.reload_service(self, 'nginx'):
                Log.error(self, "service nginx reload failed. "
                          "check issues with `nginx -t` command")

            updateSiteInfo(self, ee_domain, stype=stype, cache=cache,
                           hhvm=hhvm, pagespeed=pagespeed)

            Log.info(self, "Successfully updated site"
                     " http://{0}".format(ee_domain))
            return 0

        #if data['ee_db_name'] and not data['wp']:
        if 'ee_db_name' in data.keys() and not data['wp']:
            try:
                data = setupdatabase(self, data)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.info(self, Log.FAIL + "Update site failed."
                         "Check logs for reason"
                         "`tail /var/log/ee/ee.log` & Try Again!!!")
                return 1
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
                Log.info(self, Log.FAIL + "Update site failed. "
                         "Check logs for reason "
                         "`tail /var/log/ee/ee.log` & Try Again!!!")
                return 1

        # Setup WordPress if old sites are html/php/mysql sites
        if data['wp'] and oldsitetype in ['html', 'proxy', 'php', 'mysql']:
            try:
                ee_wp_creds = setupwordpress(self, data)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.info(self, Log.FAIL + "Update site failed."
                         "Check logs for reason "
                         "`tail /var/log/ee/ee.log` & Try Again!!!")
                return 1

        # Uninstall unnecessary plugins
        if oldsitetype in ['wp', 'wpsubdir', 'wpsubdomain']:
            # Setup WordPress Network if update option is multisite
            # and oldsite is WordPress single site
            if data['multisite'] and oldsitetype == 'wp':
                try:
                    setupwordpressnetwork(self, data)
                except SiteError as e:
                    Log.debug(self, str(e))
                    Log.info(self, Log.FAIL + "Update site failed. "
                             "Check logs for reason"
                             " `tail /var/log/ee/ee.log` & Try Again!!!")
                    return 1

            if (oldcachetype == 'w3tc' or oldcachetype == 'wpfc' and
               not (data['w3tc'] or data['wpfc'])):
                try:
                    uninstallwp_plugin(self, 'w3-total-cache', data)
                except SiteError as e:
                    Log.debug(self, str(e))
                    Log.info(self, Log.FAIL + "Update site failed. "
                             "Check logs for reason"
                             " `tail /var/log/ee/ee.log` & Try Again!!!")
                    return 1

            if ((oldcachetype in ['w3tc', 'wpsc', 'basic', 'wpredis'] and
                (data['wpfc'])) or (oldsitetype == 'wp' and data['multisite'] and data['wpfc'])):
                try:
                    plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":1,"enable_map":0,"enable_log":0,"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,"purge_homepage_on_del":1,"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,"purge_archive_on_new_comment":0,"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,"purge_page_on_new_comment":1,"purge_page_on_deleted_comment":1,"cache_method":"enable_fastcgi","purge_method":"get_request","redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}'
                    setupwp_plugin(self, 'nginx-helper', 'rt_wp_nginx_helper_options', plugin_data, data)
                except SiteError as e:
                    Log.debug(self, str(e))
                    Log.info(self, Log.FAIL + "Update nginx-helper settings failed. "
                             "Check logs for reason"
                             " `tail /var/log/ee/ee.log` & Try Again!!!")
                    return 1

            elif ((oldcachetype in ['w3tc', 'wpsc', 'basic', 'wpfc'] and
               (data['wpredis'])) or (oldsitetype == 'wp' and data['multisite'] and data['wpredis'])):
                try:
                    plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":1,"enable_map":0,"enable_log":0,"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,"purge_homepage_on_del":1,"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,"purge_archive_on_new_comment":0,"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,"purge_page_on_new_comment":1,"purge_page_on_deleted_comment":1,"cache_method":"enable_redis","purge_method":"get_request","redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}'
                    setupwp_plugin(self, 'nginx-helper', 'rt_wp_nginx_helper_options', plugin_data, data)
                except SiteError as e:
                    Log.debug(self, str(e))
                    Log.info(self, Log.FAIL + "Update nginx-helper settings failed. "
                             "Check logs for reason"
                             " `tail /var/log/ee/ee.log` & Try Again!!!")
                    return 1
            else:
                try:
                    plugin_data = '{"log_level":"INFO","log_filesize":5,"enable_purge":0,"enable_map":0,"enable_log":0,"enable_stamp":0,"purge_homepage_on_new":1,"purge_homepage_on_edit":1,"purge_homepage_on_del":1,"purge_archive_on_new":1,"purge_archive_on_edit":0,"purge_archive_on_del":0,"purge_archive_on_new_comment":0,"purge_archive_on_deleted_comment":0,"purge_page_on_mod":1,"purge_page_on_new_comment":1,"purge_page_on_deleted_comment":1,"cache_method":"enable_redis","purge_method":"get_request","redis_hostname":"127.0.0.1","redis_port":"6379","redis_prefix":"nginx-cache:"}'
                    setupwp_plugin(self, 'nginx-helper', 'rt_wp_nginx_helper_options', plugin_data, data)
                except SiteError as e:
                    Log.debug(self, str(e))
                    Log.info(self, Log.FAIL + "Update nginx-helper settings failed. "
                             "Check logs for reason"
                             " `tail /var/log/ee/ee.log` & Try Again!!!")
                    return 1

            if oldcachetype == 'wpsc' and not data['wpsc']:
                try:
                    uninstallwp_plugin(self, 'wp-super-cache', data)
                except SiteError as e:
                    Log.debug(self, str(e))
                    Log.info(self, Log.FAIL + "Update site failed."
                             "Check logs for reason"
                             " `tail /var/log/ee/ee.log` & Try Again!!!")
                    return 1

            if oldcachetype == 'wpredis' and not data['wpredis']:
                try:
                    uninstallwp_plugin(self, 'redis-cache', data)
                except SiteError as e:
                    Log.debug(self, str(e))
                    Log.info(self, Log.FAIL + "Update site failed."
                             "Check logs for reason"
                             " `tail /var/log/ee/ee.log` & Try Again!!!")
                    return 1

        if (oldcachetype != 'w3tc' or oldcachetype != 'wpfc') and (data['w3tc']
           or data['wpfc']):
            try:
                installwp_plugin(self, 'w3-total-cache', data)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.info(self, Log.FAIL + "Update site failed."
                         "Check logs for reason"
                         " `tail /var/log/ee/ee.log` & Try Again!!!")
                return 1

        if oldcachetype != 'wpsc' and data['wpsc']:
            try:
                installwp_plugin(self, 'wp-super-cache', data)
            except SiteError as e:
                Log.debug(self, str(e))
                Log.info(self, Log.FAIL + "Update site failed."
                         "Check logs for reason "
                         "`tail /var/log/ee/ee.log` & Try Again!!!")
                return 1

        if oldcachetype != 'wpredis' and data['wpredis']:
            try:
                if installwp_plugin(self, 'redis-cache', data):
                    #search for wp-config.php
                    if EEFileUtils.isexist(self,"{0}/wp-config.php".format(ee_site_webroot)):
                        config_path = '{0}/wp-config.php'.format(ee_site_webroot)
                    elif EEFileUtils.isexist(self,"{0}/htdocs/wp-config.php".format(ee_site_webroot)):
                        config_path = '{0}/htdocs/wp-config.php'.format(ee_site_webroot)
                    else:
                        Log.debug(self, "Updating wp-config.php failed. File could not be located.")
                        Log.error(self,"wp-config.php could not be located !!")
                        raise SiteError

                    if EEShellExec.cmd_exec(self, "grep -q \"WP_CACHE_KEY_SALT\" {0}"
                                                  .format(config_path)):
                        pass
                    else:
                        try:
                            wpconfig = open("{0}".format(config_path),
                                               encoding='utf-8', mode='a')
                            wpconfig.write("\n\ndefine( \'WP_CACHE_KEY_SALT\', \'{0}:\' );"
                                           .format(ee_domain))
                            wpconfig.close()
                        except IOError as e:
                            Log.debug(self, str(e))
                            Log.debug(self, "Updating wp-config.php failed.")
                            Log.warn(self, "Updating wp-config.php failed. "
                                           "Could not append:"
                                           "\ndefine( \'WP_CACHE_KEY_SALT\', \'{0}:\' );".format(ee_domain) +
                                           "\nPlease add manually")
            except SiteError as e:
                Log.debug(self, str(e))
                Log.info(self, Log.FAIL + "Update site failed."
                         "Check logs for reason "
                         "`tail /var/log/ee/ee.log` & Try Again!!!")
                return 1

        # Service Nginx Reload
        if not EEService.reload_service(self, 'nginx'):
            Log.error(self, "service nginx reload failed. "
                      "check issues with `nginx -t` command")

        EEGit.add(self, ["/etc/nginx"],
                  msg="{0} updated with {1} {2}"
                  .format(ee_www_domain, stype, cache))
        # Setup Permissions for webroot
        try:
            setwebrootpermissions(self, data['webroot'])
        except SiteError as e:
            Log.debug(self, str(e))
            Log.info(self, Log.FAIL + "Update site failed."
                     "Check logs for reason "
                     "`tail /var/log/ee/ee.log` & Try Again!!!")
            return 1

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
        return 0


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
            (['-f','--force'],
                dict(help="forcefully delete site and configuration",
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
                while not self.app.pargs.site_name:
                    self.app.pargs.site_name = (input('Enter site name : ')
                                                .strip())
            except IOError as e:
                Log.error(self, 'could not input site name')

        self.app.pargs.site_name = self.app.pargs.site_name.strip()
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_db_name = ''
        ee_prompt = ''
        ee_nginx_prompt = ''
        mark_db_delete_prompt = False
        mark_webroot_delete_prompt = False
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
            ee_mysql_grant_host = self.app.config.get('mysql', 'grant-host')
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
            if ee_db_name != 'deleted' and ee_db_name != '':
                if not self.app.pargs.no_prompt:
                    ee_db_prompt = input('Are you sure, you want to delete'
                                         ' database [y/N]: ')
                else:
                    ee_db_prompt = 'Y'
                    mark_db_delete_prompt = True

                if ee_db_prompt == 'Y' or ee_db_prompt == 'y':
                    mark_db_delete_prompt = True
                    Log.info(self, "Deleting Database, {0}, user {1}"
                             .format(ee_db_name, ee_db_user))
                    deleteDB(self, ee_db_name, ee_db_user, ee_mysql_grant_host, False)
                    updateSiteInfo(self, ee_domain,
                                   db_name='deleted',
                                   db_user='deleted',
                                   db_password='deleted')
                    mark_db_deleted = True
                    Log.info(self, "Deleted Database successfully.")
            else:
                mark_db_deleted = True
                Log.info(self, "Does not seems to have database for this site."
                         )

        # Delete webroot
        if self.app.pargs.files:
            if ee_site_webroot != 'deleted':
                if not self.app.pargs.no_prompt:
                    ee_web_prompt = input('Are you sure, you want to delete '
                                          'webroot [y/N]: ')
                else:
                    ee_web_prompt = 'Y'
                    mark_webroot_delete_prompt = True

                if ee_web_prompt == 'Y' or ee_web_prompt == 'y':
                    mark_webroot_delete_prompt = True
                    Log.info(self, "Deleting Webroot, {0}"
                             .format(ee_site_webroot))
                    deleteWebRoot(self, ee_site_webroot)
                    updateSiteInfo(self, ee_domain, webroot='deleted')
                    mark_webroot_deleted = True
                    Log.info(self, "Deleted webroot successfully")
            else:
                mark_webroot_deleted = True
                Log.info(self, "Webroot seems to be already deleted")

        if not self.app.pargs.force:
            if (mark_webroot_deleted and mark_db_deleted):
                # TODO Delete nginx conf
                removeNginxConf(self, ee_domain)
                deleteSiteInfo(self, ee_domain)
                Log.info(self, "Deleted site {0}".format(ee_domain))
             # else:
                # Log.error(self, " site {0} does not exists".format(ee_domain))
        else:
            if (mark_db_delete_prompt or mark_webroot_delete_prompt or (mark_webroot_deleted and mark_db_deleted)):
                # TODO Delete nginx conf
                removeNginxConf(self, ee_domain)
                deleteSiteInfo(self, ee_domain)
                Log.info(self, "Deleted site {0}".format(ee_domain))


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
    handler.register(EESiteEditController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_site_hook)
