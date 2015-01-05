"""EasyEngine MySQL core classes."""
import pymysql
import configparser
from os.path import expanduser
import sys


class EEMysql():
    """Method for MySQL connection"""

    def execute(self, statement):
        config = configparser.RawConfigParser()
        cnfpath = expanduser("~")+"/.my.cnf"
        if [cnfpath] == config.read(cnfpath):
            user = config.get('client', 'user')
            passwd = config.get('client', 'password')
            try:
                host = config.get('client', 'host')
            except configparser.NoOptionError as e:
                host = 'localhost'

            try:
                port = config.get('client', 'port')
            except configparser.NoOptionError as e:
                port = '3306'

            try:
                conn = pymysql.connect(host=host, port=int(port),
                                       user=user, passwd=passwd)
                cur = conn.cursor()
            except Exception as e:
                self.app.log.error('Unable to connect to database: {0}'
                                   .format(e))
                sys.exit(1)

        try:
            cur.execute(statement)
        except Exception as e:
            self.app.log.error('Error occured while executing: {0}'
                               .format(e))
            cur.close()
            conn.close()
            sys.exit(1)

        cur.close()
        conn.close()

#    def __del__(self):
#        self.cur.close()
#        self.conn.close()
