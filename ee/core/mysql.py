"""EasyEngine MySQL core classes."""
import pymysql
import configparser
from os.path import expanduser


class EEMysql():
    """Method for MySQL connection"""

    def __init__(self):
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
                self.conn = pymysql.connect(host=host, port=int(port),
                                            user=user, passwd=passwd)
                self.cur = self.conn.cursor()
            except Exception as e:
                print("Unable to connect to database")
                return False

    def execute(self, statement):
        try:
            self.cur.execute(statement)
            return True
        except Exception as e:
            print("Error occured while executing "+statement)
            return False
