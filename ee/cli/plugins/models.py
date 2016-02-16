from sqlalchemy import Column, DateTime, String, Integer, Boolean, func
from ee.core.database import Base


class SiteDB(Base):
    """
        Databse model for site table
    """
    __tablename__ = 'sites'
    __table_args__ = {'extend_existing': True}
    id = Column(Integer, primary_key=True)
    sitename = Column(String, unique=True)

    site_type = Column(String)
    cache_type = Column(String)
    site_path = Column(String)

    # Use default=func.now() to set the default created time
    # of a site to be the current time when a
    # Site record was created

    created_on = Column(DateTime, default=func.now())
    is_enabled = Column(Boolean, unique=False, default=True, nullable=False)
    is_ssl = Column(Boolean, unique=False, default=False)
    storage_fs = Column(String)
    storage_db = Column(String)
    db_name = Column(String)
    db_user = Column(String)
    db_password = Column(String)
    db_host = Column(String)
    is_hhvm = Column(Boolean, unique=False, default=False)
    is_pagespeed = Column(Boolean, unique=False, default=False)
    php_version = Column(String)

    def __init__(self, sitename=None, site_type=None, cache_type=None,
                 site_path=None, site_enabled=None,
                 is_ssl=None, storage_fs=None, storage_db=None, db_name=None,
                 db_user=None, db_password=None, db_host='localhost',
                 hhvm=None, pagespeed=None, php_version=None):
        self.sitename = sitename
        self.site_type = site_type
        self.cache_type = cache_type
        self.site_path = site_path
        self.is_enabled = site_enabled
        self.is_ssl = is_ssl
        self.storage_fs = storage_fs
        self.storage_db = storage_db
        self.db_name = db_name
        self.db_user = db_user
        self.db_password = db_password
        self.db_host = db_host
        self.is_hhvm = hhvm
        self.is_pagespeed = pagespeed
        self.php_version = php_version
    # def __repr__(self):
    #     return '<Site %r>' % (self.site_type)
    #
    # def getType(self):
    #     return '%r>' % (self.site_type)
