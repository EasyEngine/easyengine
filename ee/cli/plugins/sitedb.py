from sqlalchemy import Column, DateTime, String, Integer, Boolean
from sqlalchemy import ForeignKey, func
from sqlalchemy.orm import relationship, backref
from sqlalchemy.ext.declarative import declarative_base
from ee.core.logging import Log
from ee.core.database import db_session
from ee.core.fileutils import EEFileUtils
from ee.cli.plugins.models import SiteDB
import sys
import glob


def addNewSite(self, site, stype, cache, path,
               enabled=True, ssl=False, fs='ext4', db='mysql',
               db_name=None, db_user=None, db_password=None,
               db_host='localhost'):
    """
    Add New Site record information into ee database.
    """
    try:
        newRec = SiteDB(site, stype, cache, path, enabled, ssl, fs, db,
                        db_name, db_user, db_password, db_host)
        db_session.add(newRec)
        db_session.commit()
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to add site to database")


def getSiteInfo(self, site):
    """
        Retrieves site record from ee databse
    """
    try:
        q = SiteDB.query.filter(SiteDB.sitename == site).first()
        return q
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to query database for site info")


def updateSiteInfo(self, site, stype='', cache='', webroot='',
                   enabled=True, ssl=False, fs='', db='', db_name=None,
                   db_user=None, db_password=None, db_host=None):
    """updates site record in database"""
    try:
        q = SiteDB.query.filter(SiteDB.sitename == site).first()
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to query database for site info")

    if not q:
        Log.error(self, "{0} does not exist in database".format(site))

    # Check if new record matches old if not then only update database
    if stype and q.site_type != stype:
        q.site_type = stype

    if cache and q.cache_type != cache:
        q.cache_type = cache

    if q.is_enabled != enabled:
        q.is_enabled = enabled

    if ssl and q.is_ssl != ssl:
        q.is_ssl = ssl

    if db_name and q.db_name != db_name:
        q.db_name = db_name

    if db_user and q.db_user != db_user:
        q.db_user = db_user

    if db_user and q.db_password != db_password:
        q.db_password = db_password

    if db_host and q.db_host != db_host:
        q.db_host = db_host

    if webroot and q.site_path != webroot:
        q.site_path = webroot

    try:
        q.created_on = func.now()
        db_session.commit()
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to update site info in application database.")


def deleteSiteInfo(self, site):
    """Delete site record in database"""
    try:
        q = SiteDB.query.filter(SiteDB.sitename == site).first()
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to query database")

    if not q:
        Log.error(self, "{0} does not exist in database".format(site))

    try:
        db_session.delete(q)
        db_session.commit()
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to delete site from application database.")


def getAllsites(self):

    try:
        q = SiteDB.query.all()
        return q
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to query database")


def syncdbinfo(self):
    sites = getAllsites(self)
    if not sites:
        pass
    for site in sites:
        if site.site_type in ['mysql', 'wp', 'wpsubdir', 'wpsubdomain']:
            ee_site_webroot = site.site_path
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
                    ee_db_host = (EEFileUtils.grep(self, configfiles[0],
                                  'DB_HOST').split(',')[1]
                                  .split(')')[0].strip().replace('\'', ''))

                    if site.db_name != ee_db_name:
                        Log.debug(self, "Updating {0}"
                                  .format(site.sitename))
                        updateSiteInfo(self, site.sitename,
                                       db_name=ee_db_name,
                                       db_user=ee_db_user,
                                       db_password=ee_db_pass,
                                       db_host=ee_db_host)
