from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.fileutils import EEFileUtils
from ee.cli.plugins.sitedb import *
from ee.core.mysql import *
from ee.core.logging import Log


def ee_sync_hook(app):
    # do something with the ``app`` object here.
    pass


class EESyncController(CementBaseController):
    class Meta:
        label = 'sync'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'synchronize EasyEngine database'

    @expose(hide=True)
    def default(self):
        self.sync()

    @expose(hide=True)
    def sync(self):
        """
        1. reads database information from wp/ee-config.php
        2. updates records into ee database accordingly.
        """
        Log.info(self, "Synchronizing ee database, please wait...")
        sites = getAllsites(self)
        if not sites:
            pass
        for site in sites:
            if site.site_type in ['mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
                ee_site_webroot = site.site_path
                # Read config files
                configfiles = glob.glob(ee_site_webroot + '/*-config.php')

                #search for wp-config.php inside htdocs/
                if not configfiles:
                    Log.debug(self, "Config files not found in {0}/ "
                                      .format(ee_site_webroot))
                    if site.site_type != 'mysql':
                        Log.debug(self, "Searching wp-config.php in {0}/htdocs/ "
                                      .format(ee_site_webroot))
                        configfiles = glob.glob(ee_site_webroot + '/htdocs/wp-config.php')

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

                        # Check if database really exist
                        try:
                            if not EEMysql.check_db_exists(self, ee_db_name):
                                # Mark it as deleted if not exist
                                ee_db_name = 'deleted'
                                ee_db_user = 'deleted'
                                ee_db_pass = 'deleted'
                        except StatementExcecutionError as e:
                            Log.debug(self, str(e))
                        except Exception as e:
                            Log.debug(self, str(e))

                        if site.db_name != ee_db_name:
                            # update records if any mismatch found
                            Log.debug(self, "Updating ee db record for {0}"
                                      .format(site.sitename))
                            updateSiteInfo(self, site.sitename,
                                           db_name=ee_db_name,
                                           db_user=ee_db_user,
                                           db_password=ee_db_pass,
                                           db_host=ee_db_host)
                else:
                    Log.debug(self, "Config files not found for {0} "
                                      .format(site.sitename))


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EESyncController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_sync_hook)
