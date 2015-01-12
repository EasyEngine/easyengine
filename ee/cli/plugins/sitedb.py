from sqlalchemy import Column, DateTime, String, Integer, Boolean
from sqlalchemy import ForeignKey, func
from sqlalchemy.orm import relationship, backref
from sqlalchemy.ext.declarative import declarative_base
from ee.core.logging import Log
import sys

Base = declarative_base()


class SiteDB(Base):
    __tablename__ = 'Site'
    id = Column(Integer, primary_key=True)
    sitename = Column(String, unique=True)

    site_type = Column(String)
    cache_type = Column(String)
    site_path = Column(String)

    # Use default=func.now() to set the default created time
    # of a site to be the current time when a
    # Site record was created

    created_on = Column(DateTime, default=func.now())
    site_enabled = Column(Boolean, unique=False, default=True, nullable=False)
    is_ssl = Column(Boolean, unique=False, default=False)
    storage_fs = Column(String)
    storage_db = Column(String)

    def __init__(self):
    #     from sqlalchemy import create_engine
    #     self.engine = create_engine('sqlite:///orm_in_detail.sqlite')
        self.sitename = sitename
        self.site_type = site_type
        self.cache_type = cache_type
        self.site_path = site_path
        self.created_on = created_on
        self.site_enabled = site_enabled
        self.is_ssl = is_ssl
        self.storage_fs = storage_fs
        self.storage_db = storage_db

# if __name__ == "__main__":
#
#     from sqlalchemy import create_engine
#     engine = create_engine('sqlite:///orm_in_detail.sqlite')
#     from sqlalchemy.orm import sessionmaker
#     session = sessionmaker()
#     session.configure(bind=engine)
#     Base.metadata.create_all(engine)
#     s = session()
#     newRec = SiteDB(sitename='exa.in', site_type='wp', cache_type='basic',
    # site_path='/var/www', site_enabled=True, is_ssl=False, storage_fs='ext4',
    # storage_db='mysql')
#     s.add(newRec)
#     s.commit()
#     s.flush()


def addNewSite(self, site, stype, cache, path,
               enabled=True, ssl=False, fs='ext4', db='mysql'):
    db_path = self.app.config.get('site', 'db_path')
    try:
        from sqlalchemy import create_engine
        engine = create_engine(db_path)
        from sqlalchemy.orm import sessionmaker
        session = sessionmaker()
        session.configure(bind=engine)
        Base.metadata.create_all(engine)
        s = session()
        newRec = SiteDB(sitename=site, site_type=stype, cache_type=cache,
                        site_path=path, site_enabled=enabled, is_ssl=ssl,
                        storage_fs=fs, storage_db=db)
        s.add(newRec)
        s.commit()
        s.flush()
    except Exception as e:
        Log.error(self, "Unable to add site to database : {0}"
                  .format(e))
        sys.exit(1)


def getSiteInfo(self, site):
    db_path = self.app.config.get('site', 'db_path')
    try:
        from sqlalchemy import create_engine
        engine = create_engine(db_path)
        from sqlalchemy.orm import sessionmaker
        session = sessionmaker()
        session.configure(bind=engine)
        Base.metadata.create_all(engine)
        s = session()
        q = s.query(SiteDB).filter_by(sitename=site).first()
        s.flush()
        return q
    except Exception as e:
        Log.error(self, "Unable to add site to database : {0}"
                  .format(e))
        sys.exit(1)
