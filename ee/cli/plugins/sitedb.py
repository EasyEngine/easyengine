from sqlalchemy import Column, DateTime, String, Integer, Boolean
from sqlalchemy import ForeignKey, func
from sqlalchemy.orm import relationship, backref
from sqlalchemy.ext.declarative import declarative_base
from ee.core.logging import Log
from ee.core.database import db_session
from ee.cli.plugins.models import SiteDB
import sys
import glob


def addNewSite(self, site, stype, cache, path,
               enabled=True, ssl=False, fs='ext4', db='mysql',
               db_name=None, db_user=None, db_password=None,
               db_host='localhost', hhvm=0, pagespeed=0, php_version='5.5'):
    """
    Add New Site record information into ee database.
    """
    try:
        newRec = SiteDB(site, stype, cache, path, enabled, ssl, fs, db,
                        db_name, db_user, db_password, db_host, hhvm,
                        pagespeed, php_version)
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
                   db_user=None, db_password=None, db_host=None, hhvm=None,
                   pagespeed=None, php_version=''):
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

    if q.is_ssl != ssl:
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

    if (hhvm is not None) and (q.is_hhvm is not hhvm):
        q.is_hhvm = hhvm

    if (pagespeed is not None) and (q.is_pagespeed is not pagespeed):
        q.is_pagespeed = pagespeed

    if php_version and q.php_version != php_version:
        q.php_version = php_version

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
    """
        1. returns all records from ee database
    """
    try:
        q = SiteDB.query.all()
        return q
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to query database")
