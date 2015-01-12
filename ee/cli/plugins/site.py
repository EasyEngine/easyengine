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


def ee_site_hook(app):
    # do something with the ``app`` object here.
    pass


class EESiteController(CementBaseController):
    class Meta:
        label = 'site'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = ('site command manages website configuration'
                       'with the help of the following subcommands')
        arguments = [
            (['site_name'],
                dict(help='website name')),
            ]

    @expose(hide=True)
    def default(self):
        # TODO Default action for ee site command
        print("Inside EESiteController.default().")

    @expose(help="delete site example.com")
    def delete(self):
        # TODO Write code for ee site delete command here
        print("Inside EESiteController.delete().")

    @expose(help="enable site example.com")
    def enable(self):
        # TODO Write code for ee site enable command here
        print("Inside EESiteController.enable().")

    @expose(help="disable site example.com")
    def disable(self):
        # TODO Write code for ee site disable command here
        print("Inside EESiteController.disable().")

    @expose(help="get example.com information")
    def info(self):
        # TODO Write code for ee site info command here
        print("Inside EESiteController.info().")

    @expose(help="Monitor example.com logs")
    def log(self):
        # TODO Write code for ee site log command here
        print("Inside EESiteController.log().")

    @expose(help="Edit example.com's nginx configuration")
    def edit(self):
        # TODO Write code for ee site edit command here
        print("Inside EESiteController.edit().")

    @expose(help="Display example.com's nginx configuration")
    def show(self):
        # TODO Write code for ee site edit command here
        print("Inside EESiteController.show().")

    @expose(help="list sites currently available")
    def list(self):
        # TODO Write code for ee site list command here
        print("Inside EESiteController.list().")

    @expose(help="change to example.com's webroot")
    def cd(self):
        # TODO Write code for ee site cd here
        print("Inside EESiteController.cd().")


class EESiteCreateController(CementBaseController):
    class Meta:
        label = 'create'
        stacked_on = 'site'
        stacked_type = 'nested'
        description = 'create command manages website configuration with the \
                        help of the following subcommands'
        arguments = [
            (['site_name'],
                dict(help='the notorious foo option')),
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

    @expose(hide=True)
    def default(self):
        # TODO Default action for ee site command
        # data = dict(foo='EESiteCreateController.default().')
        # self.app.render((data), 'default.mustache')
        # Check domain name validation
        (ee_domain,
         ee_www_domain, ) = ValidateDomain(self.app.pargs.site_name)
        ee_site_webroot = EEVariables.ee_webroot + ee_domain

        # Check if doain previously exists or not
        if os.path.isfile('/etc/nginx/sites-available/{0}.conf'
                          .format(ee_domain)):
            self.app.log.error("site {0} already exists"
                               .format(ee_domain))
            sys.exit(1)

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

        #PHP
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
        #MySQL
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
        #WP
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

        #WPSUBDIR
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

        #WPSUBDOAIN
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

        # setup NGINX configuration, and webroot
        SetupDomain(self, data)
        # Setup database for MySQL site
        if 'ee_db_name' in data.keys() and not data['wp']:
            data = SetupDatabase(self, data)
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
                stype = mysql
            except IOError as e:
                self.app.log.error("Unable to create ee-config.php for "
                                   "{2} ({0}): {1}"
                                   .format(e.errno, e.strerror, ee_domain))
                sys.exit(1)
        # Setup WordPress if Wordpress site
        if data['wp']:
            ee_wp_creds = SetupWordpress(self, data)
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
            Log.error(self, "Site {0} does not exist.".format(ee_domain))
        else:
            oldsitetype = check_site.site_type
            oldcachetype = check_site.cache_type

        if (self.app.pargs.html and not (self.app.pargs.php or
            self.app.pargs.mysql or self.app.pargs.wp or self.app.pargs.w3tc
            or self.app.pargs.wpfc or self.app.pargs.wpsc or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):
            pass

        #PHP
        if (self.app.pargs.php and not (self.app.pargs.html or
            self.app.pargs.mysql or self.app.pargs.wp or self.app.pargs.w3tc
            or self.app.pargs.wpfc or self.app.pargs.wpsc or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):

            if oldsitetype != 'html':
                Log.error("Cannot update {0} to php".format(ee_domain))
                sys.exit(1)

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot,
                        currsitetype=oldsitetype, currcachetype=oldcachetype)

        #MySQL
        if (self.app.pargs.mysql and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.wp or self.app.pargs.w3tc
            or self.app.pargs.wpfc or self.app.pargs.wpsc or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):

            if oldsitetype != 'html' or oldsitetype != 'php':
                Log.error("Cannot update {0} to mysql".format(ee_domain))
                sys.exit(1)

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=False, w3tc=False,
                        wpfc=False, wpsc=False, multisite=False,
                        wpsubdir=False, webroot=ee_site_webroot,
                        ee_db_name='', ee_db_user='', ee_db_pass='',
                        ee_db_host='', currsitetype=oldsitetype,
                        currcachetype=oldcachetype)

        #WP
        if ((self.app.pargs.wp or self.app.pargs.w3tc or self.app.pargs.wpfc or
            self.app.pargs.wpsc) and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or
           self.app.pargs.wpsubdir or self.app.pargs.wpsubdomain)):
            if (self.app.pargs.wp and not (self.app.pargs.w3tc
               or self.app.pargs.wpfc or self.app.pargs.wpsc)):

                if (oldsitetype not in ['html', 'php', 'wp']
                   and oldsitetype not in ['w3tc', 'wpfc', 'wpsc']):
                    Log.error("Cannot update {0} to wp basic"
                              .format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=True, wp=True, w3tc=False,
                            wpfc=False, wpsc=False, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

            if (self.app.pargs.w3tc and not
               (self.app.pargs.wpfc or self.app.pargs.wpsc)):

                if (oldsitetype not in ['html', 'php', 'wp']
                   and oldsitetype not in ['basic', 'wpfc', 'wpsc']):
                    Log.error("Cannot update {0} to wp w3tc".format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=True,
                            wpfc=False, wpsc=False, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

            if (self.app.pargs.wpfc and not
               (self.app.pargs.wpsc or self.app.pargs.w3tc)):

                if (oldsitetype not in ['html', 'php', 'wp']
                   and oldsitetype not in ['basic', 'w3tc', 'wpsc']):
                    Log.error("Cannot update {0} to wp wpfc".format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=True, wpsc=False, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

            if (self.app.pargs.wpsc and not
               (self.app.pargs.w3tc or self.app.pargs.wpfc)):

                if (oldsitetype not in ['html', 'php', 'wp']
                   and oldsitetype not in ['basic', 'w3tc', 'wpfc']):
                    Log.error("Cannot update {0} to wp wpsc".format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=False, wpsc=True, multisite=False,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

        #WPSUBDIR
        if (self.app.pargs.wpsubdir and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or
           self.app.pargs.wpsubdomain or self.app.pargs.wp)):
            if (self.app.pargs.wpsubdir and not (self.app.pargs.w3tc
               or self.app.pargs.wpfc or self.app.pargs.wpsc)):

                if (oldsitetype not in ['html', 'php', 'wp', 'wpsubdir']
                   and oldsitetype not in ['w3tc', 'wpfc', 'wpsc']):
                    Log.error("Cannot update {0} to wpsubdir basic"
                              .format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=True, wp=True, w3tc=False,
                            wpfc=False, wpsc=False, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

            if (self.app.pargs.w3tc and not
               (self.app.pargs.wpfc or self.app.pargs.wpsc)):

                if (oldsitetype not in ['html', 'php', 'wp', 'wpsubdir']
                   and oldsitetype not in ['basic', 'wpfc', 'wpsc']):
                    Log.error("Cannot update {0} to wpsubdir w3tc"
                              .format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=True,
                            wpfc=False, wpsc=False, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

            if (self.app.pargs.wpfc and not
               (self.app.pargs.wpsc or self.app.pargs.w3tc)):

                if (oldsitetype not in ['html', 'php', 'wp', 'wpsubdir']
                   and oldsitetype not in ['basic', 'w3tc', 'wpsc']):
                    Log.error("Cannot update {0} to wpsubdir wpfc"
                              .format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=True, wpsc=False, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

            if (self.app.pargs.wpsc and not
               (self.app.pargs.w3tc or self.app.pargs.wpfc)):

                if (oldsitetype not in ['html', 'php', 'wp', 'wpsubdir']
                   and oldsitetype not in ['basic', 'w3tc', 'wpfc']):
                    Log.error("Cannot update {0} to wpsubdir wpsc"
                              .format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=False,
                            wpfc=False, wpsc=True, multisite=True,
                            wpsubdir=True, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

        if (self.app.pargs.wpsubdomain and not (self.app.pargs.html or
            self.app.pargs.php or self.app.pargs.mysql or
           self.app.pargs.wpsubdir or self.app.pargs.wp)):

            if (oldsitetype not in ['html', 'php', 'wp', 'wpsubdomain']
               and oldsitetype not in ['w3tc', 'wpfc', 'wpsc']):
                Log.error("Cannot update {0} to wpsubdomain basic"
                          .format(ee_domain))
                sys.exit(1)

            data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                        static=False,  basic=True, wp=True, w3tc=False,
                        wpfc=False, wpsc=False, multisite=True,
                        wpsubdir=False, webroot=ee_site_webroot,
                        ee_db_name='', ee_db_user='', ee_db_pass='',
                        ee_db_host='', currsitetype=oldsitetype,
                        currcachetype=oldcachetype)

            if (self.app.pargs.w3tc and not
               (self.app.pargs.wpfc or self.app.pargs.wpsc)):

                if (oldsitetype not in ['html', 'php', 'wp', 'wpsubdomain']
                   and oldsitetype not in ['basic', 'wpfc', 'wpsc']):
                    Log.error("Cannot update {0} to wpsubdomain w3tc"
                              .format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False,  basic=False, wp=True, w3tc=True,
                            wpfc=False, wpsc=False, multisite=True,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

            if (self.app.pargs.wpfc and not
               (self.app.pargs.wpsc or self.app.pargs.w3tc)):

                if (oldsitetype not in ['html', 'php', 'wp', 'wpsubdomain']
                   and oldsitetype not in ['basic', 'w3tc', 'wpsc']):
                    Log.error("Cannot update {0} to wpsubdomain wpfc"
                              .format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False, basic=False, wp=True, w3tc=False,
                            wpfc=True, wpsc=False, multisite=True,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

            if (self.app.pargs.wpsc and not
               (self.app.pargs.w3tc or self.app.pargs.wpfc)):

                if (oldsitetype not in ['html', 'php', 'wp', 'wpsubdomain']
                   and oldsitetype not in ['basic', 'w3tc', 'wpfc']):
                    Log.error("Cannot update {0} to wpsubdomain wpsc"
                              .format(ee_domain))
                    sys.exit(1)

                data = dict(site_name=ee_domain, www_domain=ee_www_domain,
                            static=False, basic=False, wp=True, w3tc=False,
                            wpfc=False, wpsc=True, multisite=True,
                            wpsubdir=False, webroot=ee_site_webroot,
                            ee_db_name='', ee_db_user='', ee_db_pass='',
                            ee_db_host='', currsitetype=oldsitetype,
                            currcachetype=oldcachetype)

        # TODO take site backup before site update
        siteBackup(self, data)
        # TODO Check for required packages before update

        # setup NGINX configuration, and webroot
        SetupDomain(self, data)

        if 'ee_db_name' in data.keys() and not data['wp']:
            data = SetupDatabase(self, data)
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
                stype = mysql
            except IOError as e:
                self.app.log.error("Unable to create ee-config.php for "
                                   "{2} ({0}): {1}"
                                   .format(e.errno, e.strerror, ee_domain))
                sys.exit(1)

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

        # Setup WordPress if Wordpress site
        if data['wp']:
            ee_wp_creds = SetupWordpress(self, data)
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


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EESiteController)
    handler.register(EESiteCreateController)
    handler.register(EESiteUpdateController)

    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_site_hook)
