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
import sys
import os
import glob
import subprocess
from subprocess import Popen


def ee_site_hook(app):
    # do something with the ``app`` object here.
    from ee.core.database import init_db
    init_db()


class EESiteController(CementBaseController):
    class Meta:
        label = 'site'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = ('site command manages website configuration'
                       ' with the help of the following subcommands')
        arguments = [
            (['site_name'],
                dict(help='website name')),
            ]

    @expose(hide=True)
    def default(self):
        self.app.args.print_help()

    @expose(help="enable site example.com")
    def enable(self):
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            EEFileUtils.create_symlink(self,
                                       ['/etc/nginx/sites-available/{0}'
                                        .format(ee_domain),
                                        '/etc/nginx/sites-enabled/{0}'
                                        .format(ee_domain)])
            updateSiteInfo(self, ee_domain, enabled=True)
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="disable site example.com")
    def disable(self):
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            EEFileUtils.remove_symlink(self,
                                       '/etc/nginx/sites-enabled/{0}'
                                       .format(ee_domain))
            updateSiteInfo(self, ee_domain, enabled=False)
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="get example.com information")
    def info(self):
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_db_name = ''
        ee_db_user = ''
        ee_db_pass = ''
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
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
                        dbpass=ee_db_pass)
            self.app.render((data), 'siteinfo.mustache')
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="Monitor example.com logs")
    def log(self):
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            EEShellExec.cmd_exec(self, 'tail -f /var/log/nginx/{0}.*.log'
                                 .format(ee_domain))
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="Edit example.com's nginx configuration")
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

    @expose(help="Display example.com's nginx configuration")
    def show(self):
        # TODO Write code for ee site edit command here
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            Log.info(self, "Display NGINX configuration for {0}"
                     .format(ee_domain))
            f = open('/etc/nginx/sites-available/{0}'.format(ee_domain), "r")
            text = f.read()
            print(text)
            f.close()
        else:
            Log.error(self, " site {0} does not exists".format(ee_domain))

    @expose(help="change directory to site webroot")
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
        description = 'create command manages website configuration with the \
                        help of the following subcommands'
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
        site_package_check(self, stype)
        # setup NGINX configuration, and webroot
        setupDomain(self, data)
        # Setup database for MySQL site
        if 'ee_db_name' in data.keys() and not data['wp']:
            data = setupDatabase(self, data)
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
            ee_wp_creds = setupWordpress(self, data)
        # Service Nginx Reload
        EEService.reload_service(self, 'nginx')

        EEGit.add(self, ["/etc/nginx"],
                  msg="{0} created with {1} {2}"
                  .format(ee_www_domain, stype, cache))
        # Setup Permissions for webroot
        SetWebrootPermissions(self, data['webroot'])
        if data['wp']:
            Log.info(self, '\033[94m'+"WordPress Admin User :"
                     " {0}".format(ee_wp_creds['wp_user'])+'\033[0m')
            Log.info(self, "WordPress Admin User Password : {0}"
                     .format(ee_wp_creds['wp_pass']))
        addNewSite(self, ee_www_domain, stype, cache, ee_site_webroot)
        Log.info(self, "Successfully created site"
                 " http://{0}".format(ee_domain))


class EESiteUpdateController(CementBaseController):
    class Meta:
        label = 'update'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = 'update command manages website configuration with the \
                        help of the following subcommands'
        arguments = [
            (['site_name'],
                dict(help='website name')),
            (['--html'],
                dict(help="html site", action='store_true')),
            (['--php'],
                dict(help="php site", action='store_true')),
            (['--mysql'],
                dict(help="mysql site", action='store_true')),
            (['--wp'],
                dict(help="wordpress site", action='store_true')),
            (['--wpsubdir'],
                dict(help="wpsubdir site", action='store_true')),
            (['--wpsubdomain'],
                dict(help="wpsubdomain site", action='store_true')),
            (['--w3tc'],
                dict(help="w3tc", action='store_true')),
            (['--wpfc'],
                dict(help="wpfc", action='store_true')),
            (['--wpsc'],
                dict(help="wpsc", action='store_true')),
            ]

    @expose(help="update example.com")
    def default(self):
        # TODO Write code for ee site update here
        (ee_domain,
         ee_www_domain, ) = ValidateDomain(self.app.pargs.site_name)
        ee_site_webroot = EEVariables.ee_webroot + ee_domain

        check_site = getSiteInfo(self, ee_domain)

        if check_site is None:
            Log.error(self, " Site {0} does not exist.".format(ee_domain))
        else:
            oldsitetype = check_site.site_type
            oldcachetype = check_site.cache_type

        print(oldsitetype, oldcachetype)

        if (self.app.pargs.html and not (self.app.pargs.php or
            self.app.pargs.mysql or self.app.pargs.wp or self.app.pargs.w3tc
            or self.app.pargs.wpfc or self.app.pargs.wpsc or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):
            pass

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

                if ((oldsitetype in ['html', 'php', 'mysql', 'wp'])
                   and (oldcachetype not in ['w3tc', 'wpfc', 'wpsc'])):
                    print(oldsitetype, oldcachetype)
                    Log.error(self, " Cannot update {0}, {1} {2} to wp basic"
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

                if (oldsitetype in ['html', 'php', 'mysql', 'wp']
                   and oldcachetype not in ['basic', 'wpfc', 'wpsc']):
                    Log.error(self, " Cannot update {0}, {1} {2}to wp w3tc"
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

                if (oldsitetype in ['html', 'php', 'mysql', 'wp']
                   and oldcachetype not in ['basic', 'w3tc', 'wpsc']):
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

                if (oldsitetype in ['html', 'php', 'mysql', 'wp']
                   and oldcachetype not in ['basic', 'w3tc', 'wpfc']):
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

                if (oldsitetype in ['html', 'php', 'mysql', 'wp', 'wpsubdir']
                   and oldcachetype not in ['w3tc', 'wpfc', 'wpsc']):
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

                if (oldsitetype in ['html', 'php', 'mysql', 'wp', 'wpsubdir']
                   and oldcachetype not in ['basic', 'wpfc', 'wpsc']):
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

                if (oldsitetype in ['html', 'php', 'mysql', 'wp', 'wpsubdir']
                   and oldcachetype not in ['basic', 'w3tc', 'wpsc']):
                    Log.error(self, " Cannot update {0} {1} {2}"
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

                if (oldsitetype in ['html', 'php', 'mysql', 'wp', 'wpsubdir']
                   and oldcachetype not in ['basic', 'w3tc', 'wpfc']):
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

            if (oldsitetype in ['html', 'php', 'mysql', 'wp', 'wpsubdomain']
               and oldcachetype not in ['w3tc', 'wpfc', 'wpsc']):
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

                if (oldsitetype in ['html', 'php', 'mysql', 'wp',
                                    'wpsubdomain']
                   and oldcachetype not in ['basic', 'wpfc', 'wpsc']):
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

                if (oldsitetype in ['html', 'php', 'mysql', 'wp',
                                    'wpsubdomain']
                   and oldcachetype not in ['basic', 'w3tc', 'wpsc']):
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

                if (oldsitetype in ['html', 'php', 'mysql', 'wp',
                                    'wpsubdomain']
                   and oldcachetype not in ['basic', 'w3tc', 'wpfc']):
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
            Log.error(self, " Cannot update"
                      .format(ee_domain))
        site_package_check(self, stype)
        siteBackup(self, data)
        # TODO Check for required packages before update

        # setup NGINX configuration, and webroot
        setupDomain(self, data)

        if 'ee_db_name' in data.keys() and not data['wp']:
            data = setupDatabase(self, data)
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
                Log.error(self, " Unable to create ee-config.php for "
                          "{0}"
                          .format(ee_domain))
                Log.debug(self, "{0} {1}".format(e.errno, e.strerror))

        if oldsitetype == 'mysql':
            config_file = (ee_site_webroot + '/backup/{0}/ee-config.php'
                           .format(EEVariables.ee_date))
            data['ee_db_name'] = EEFileUtils.grep(EEFileUtils
                                                  .grep(self, config_file,
                                                        'DB_NAME')
                                                  .split(',')[1]
                                                  .split(')')[0].strip())
            data['ee_db_user'] = EEFileUtils.grep(EEFileUtils
                                                  .grep(self, config_file,
                                                        'DB_USER')
                                                  .split(',')[1]
                                                  .split(')')[0].strip())
            data['ee_db_pass'] = EEFileUtils.grep(EEFileUtils
                                                  .grep(self, config_file,
                                                        'DB_PASSWORD')
                                                  .split(',')[1]
                                                  .split(')')[0].strip())

        # Setup WordPress if old sites are html/php/mysql sites
        if data['wp'] and oldsitetype in ['html', 'php', 'mysql']:
            ee_wp_creds = setupWordpress(self, data)

        # Uninstall unnecessary plugins
        if oldsitetype in ['wp', 'wpsubdir', 'wpsubdomain']:
            # Setup WordPress Network if update option is multisite
            # and oldsite is WordPress single site
            if data['multisite'] and oldsitetype == 'wp':
                setupWordpressNetwork(self, data)

            if (oldcachetype == 'w3tc' or oldcachetype == 'wpfc' and
               not data['w3tc', 'wpfc']):
                uninstallWP_Plugin(self, 'w3-total-cache', data)

            if oldcachetype == 'wpsc' and not data['wpsc']:
                uninstallWP_Plugin(self, 'wp-super-cache', data)

        if (oldcachetype != 'w3tc' or oldcachetype != 'wpfc') and data['w3tc']:
            installWP_Plugin(self, 'w3-total-cache', data)

        if oldcachetype != 'wpsc' and data['wpsc']:
            installWP_Plugin(self, 'wp-super-cache', data)

        # Service Nginx Reload
        EEService.reload_service(self, 'nginx')

        EEGit.add(self, ["/etc/nginx"],
                  msg="{0} updated with {1} {2}"
                  .format(ee_www_domain, stype, cache))
        # Setup Permissions for webroot
        # SetWebrootPermissions(self, data['webroot'])

        updateSiteInfo(self, ee_www_domain, stype=stype, cache=cache)
        Log.info(self, "Successfully updated site"
                 " http://{0}".format(ee_domain))


class EESiteDeleteController(CementBaseController):
    class Meta:
        label = 'delete'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = 'delete command deletes website'
        arguments = [
            (['site_name'],
                dict(help='domain name to be deleted')),
            (['--no-prompt'],
                dict(help="dont ask for permission for delete",
                     action='store_true')),
            (['--all'],
                dict(help="delete all", action='store_true')),
            (['--db'],
                dict(help="delete db only", action='store_true')),
            (['--files'],
                dict(help="delete webroot only", action='store_true')),
            ]

    @expose(help="delete example.com")
    def default(self):
        # TODO Write code for ee site update here
        (ee_domain, ee_www_domain) = ValidateDomain(self.app.pargs.site_name)
        ee_db_name = ''
        ee_prompt = ''
        if os.path.isfile('/etc/nginx/sites-available/{0}'
                          .format(ee_domain)):
            ee_site_webroot = EEVariables.ee_webroot + ee_domain

            if self.app.pargs.no_prompt:
                ee_prompt = 'Y'

            if self.app.pargs.db:
                if not ee_prompt:
                    ee_db_prompt = input('Do you want to delete database:'
                                         '[Y/N] ')
                else:
                    ee_db_prompt = 'Y'
                if ee_db_prompt == 'Y':
                    self.deleteDB(ee_site_webroot)

            if self.app.pargs.files:
                if not ee_prompt:
                    ee_web_prompt = input('Do you want to delete webroot:'
                                          '[Y/N] ')
                else:
                    ee_web_prompt = 'Y'
                if ee_web_prompt == 'Y':
                    self.deleteWebRoot(ee_site_webroot)

            if self.app.pargs.all:
                if not ee_prompt:
                    ee_db_prompt = input('Do you want to delete database:'
                                         '[Y/N] '
                                         )
                    ee_web_prompt = input('Do you want to delete webroot:'
                                          '[Y/N] ')
                    ee_nginx_prompt = input('Do you want to delete NGINX'
                                            ' configuration:[Y/N] ')
                else:
                    ee_db_prompt = 'Y'
                    ee_web_prompt = 'Y'
                    ee_nginx_prompt = 'Y'

                if ee_db_prompt == 'Y':
                    self.deleteDB(ee_site_webroot)
                if ee_web_prompt == 'Y':
                    self.deleteWebRoot(ee_site_webroot)
                if ee_nginx_prompt == 'Y':
                    EEFileUtils.rm(self, '/etc/nginx/sites-available/{0}'
                                   .format(ee_domain))
                deleteSiteInfo(self, ee_domain)
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
                EEMysql.execute(self,
                                "drop database {0}".format(ee_db_name),
                                errormsg='Unable to drop database {0}'
                                .format(ee_db_name))
                if ee_db_user != 'root':
                    EEMysql.execute(self,
                                    "drop user {0}@{1}"
                                    .format(ee_db_user, ee_db_host))
                    EEMysql.execute(self,
                                    "flush privileges")
            except Exception as e:
                Log.error(self, " Error occured while deleting database")

    @expose(hide=True)
    def deleteWebRoot(self, webroot):
        EEFileUtils.rm(self, webroot)


class EESiteListController(CementBaseController):
    class Meta:
        label = 'list'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = 'list websites'
        arguments = [
            (['--enabled'],
                dict(help='list enabled sites', action='store_true')),
            (['--disabled'],
                dict(help="list disabled sites", action='store_true')),
            ]

    @expose(help="delete example.com")
    def default(self):
            sites = getAllsites(self)
            if not sites:
                self.app.close(1)

            if self.app.pargs.enabled:
                for site in sites:
                    if site.is_enabled:
                        Log.info(self, "{0}".format(site.sitename))
            elif self.app.pargs.disabled:
                for site in sites:
                    if not site.is_enabled:
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
