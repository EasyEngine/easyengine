from sqlalchemy import Column, DateTime, String, Integer, Boolean
from sqlalchemy import ForeignKey, func
from sqlalchemy.orm import relationship, backref
from sqlalchemy.ext.declarative import declarative_base
from ee.core.logging import Log
from ee.core.database import db_session
from ee.cli.plugins.models import SiteDB
import sys


def addNewSite(self, site, stype, cache, path,
               enabled=True, ssl=False, fs='ext4', db='mysql'):
    """
    Add New Site record information into ee database.
    """
    try:
        newRec = SiteDB(site, stype, cache, path, enabled, ssl, fs, db)
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


def updateSiteInfo(self, site, stype='', cache='',
                   enabled=True, ssl=False, fs='', db=''):
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
