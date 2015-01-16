from sqlalchemy import Column, DateTime, String, Integer, Boolean
from sqlalchemy import ForeignKey, func
from sqlalchemy.orm import relationship, backref
from sqlalchemy.ext.declarative import declarative_base
from ee.core.logging import Log
import sys
from ee.core.database import db_session
from ee.core.models import SiteDB


def addNewSite(self, site, stype, cache, path,
               enabled=True, ssl=False, fs='ext4', db='mysql'):
    try:
        newRec = SiteDB(site, stype, cache, path, enabled, ssl, fs, db)
        db_session.add(newRec)
        db_session.commit()
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to add site to database")


def getSiteInfo(self, site):
    try:
        q = SiteDB.query.filter(SiteDB.sitename == site).first()
        return q
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to query database for site info")


def updateSiteInfo(self, site, stype='', cache='',
                   enabled=True, ssl=False, fs='', db=''):
    try:
        q = SiteDB.query.filter(SiteDB.sitename == site).first()
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to query database for site info")
    if stype and q.site_type != stype:
        q.site_type = stype

    if cache and q.cache_type != cache:
        q.cache_type = cache

    if enabled and q.is_enabled != enabled:
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
    try:
        q = SiteDB.query.filter(SiteDB.sitename == site).first()
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to query database :")
    try:
        db_session.delete(q)
        db_session.commit()
    except Exception as e:
        Log.debug(self, "{0}".format(e))
        Log.error(self, "Unable to delete site from application database.")
